<?php
/**
 * @author Orhan POLAT
 */

namespace System;

use \Webim\Database\Manager as DB;
use \Webim\Library\Auth;
use \Webim\Library\Carbon;
use \Webim\Library\Controller;
use \Webim\Library\File;
use \Webim\Library\Message;

class Mail extends Controller {

  /**
   * Current user
   *
   * @var \Webim\Library\Auth
   */
  protected $me;

  /**
   * Recipient list for send
   *
   * @var array
   */
  protected $recipients = array();

  /**
   * Attachments for send
   *
   * @var array
   */
  protected $attachments = array();

  /**
   * Construct
   *
   * @param bool $withAlias
   */
  public function __construct($withAlias = false) {
    parent::__construct(DB::table('sys_mail' . ($withAlias ? ' as m' : '')));

    $this->me = Auth::current();
  }

  /**
   * Inbox
   *
   * @param \Closure|null $filters
   *
   * @return Mail
   */
  public static function inbox(\Closure $filters = null) {
    $mail = new self(true);

    $mail = $mail->join('sys_mail_recipient as r', function ($join) use ($mail) {
      $join->on('r.mail_id', 'm.id');
      $join->where('r.recipient_id', '=', DB::raw($mail->me->id));
      $join->where('r.trashed', '=', DB::raw('false'));
    })->join('sys_object as o', 'o.id', '=', 'm.sender_id')
      ->where('m.trashed', 'false')
      ->where('m.active', 'true')
      ->addSelect(array(
        'm.id', 'm.key', 'm.date', 'm.subject', 'm.content', 'm.content_type', 'm.priority',
        'm.sender_id', 'o.role as sender_role', 'o.name as sender_name',
        'o.email as sender_email', 'o.first_name as sender_first_name',
        'o.last_name as sender_last_name', 'r.read', 'r.read_at', 'r.starred',
        'r.starred_at', 'r.archived', 'r.archived_at'
      ));

    if ($filters instanceof \Closure) {
      call_user_func($filters, $mail);
    }

    return $mail;
  }

  /**
   * Outbox
   *
   * @param \Closure|null $filters
   *
   * @return Mail
   */
  public static function outbox(\Closure $filters = null) {
    $mail = new self();

    $mail = $mail->where('active', 'true');

    if ($filters instanceof \Closure) {
      call_user_func($filters, $mail);
    }

    return $mail;
  }

  /**
   * Mark incoming mail
   *
   * @param $as
   * @param $key
   *
   * @return bool
   */
  public static function mark($as, $key) {
    $valid = array(
      'read', 'starred', 'archived', 'trashed'
    );

    if (in_array($as, $valid, true)) {
      //Keys
      $keys = array();

      if (!is_array($key)) {
        $keys[] = $key;
      } else {
        $keys = array_merge($keys, $key);
      }

      $mails = DB::table('sys_mail as m')
        ->join('sys_mail_recipient as r', function ($join) {
          $join->on('r.mail_id', 'm.id');
          $join->where('r.recipient_id', '=', DB::raw(Auth::current()->id));
          $join->where('r.trashed', '=', DB::raw('false'));
        })->whereIn('m.key', $keys)->get(array(
          'm.id',
          'r.read',
          'r.starred',
          'r.archived',
          'r.trashed'
        ));

      $saved = 0;

      foreach ($mails as $mail) {
        $action = ($mail[$as] == 'false' ? 'true' : 'false');

        $update = DB::table('sys_mail_recipient')->where('mail_id', $mail['id'])->update(array(
          $as => $action,
          $as . '_at' => DB::raw('NOW()')
        ));

        if ($update->success) {
          $saved++;
        }
      }

      return $saved;
    }

    return false;
  }

  /**
   * Load list
   *
   * @param null|int $offset
   * @param null|int $limit
   *
   * @return $this
   */
  public function load($offset = null, $limit = null) {
    //Limit and offset
    $limits = array(
      'offset' => 0,
      'limit' => null
    );

    if ($limit > 0) {
      $limits['limit'] = $limit;

      if (!is_null($offset)) {
        $limits['offset'] = $offset;
      }
    }

    parent::load($limits['offset'], $limits['limit']);

    return $this;
  }

  /**
   * Create message
   *
   * @param string $subject
   * @param string $content
   * @param string $contentType
   * @param string $priority
   *
   * @return Mail
   */
  public static function create($subject, $content, $contentType = 'html', $priority = 'normal') {
    //Create
    $mail = new self();

    //Set mail attributes
    $mail->set(array(
      'key' => uniqid(),
      'sender_id' => my('id'),
      'date' => Carbon::now(),
      'subject' => $subject,
      'content' => $content,
      'content_type' => (($contentType == 'html') ? 'html' : 'plain'),
      'priority' => (in_array($priority, array('low', 'normal', 'high', 'urgent')) ? $priority : 'normal')
    ));

    return $mail;
  }

  /**
   * Attach file
   *
   * @param \Webim\Library\File $file
   *
   * @return $this
   */
  public function attach(File $file) {
    $this->attachments[] = $file;

    return $this;
  }

  /**
   * Attachments
   *
   * @param array $rows
   * @param array $params
   *
   * @return array
   */
  protected function attachments($rows, $params = array()) {
    if (in_array(__FUNCTION__, $this->called)) {
      return $rows;
    }

    //Add called class
    $this->called[] = __FUNCTION__;

    //Prepare files
    $list = array();

    if (count($this->ids())) {
      //Get
      $query = DB::table('sys_mail_attachment')
        ->whereIn('mail_id', $this->ids())
        ->orderBy('order')
        ->get();

      foreach ($query as $row) {
        $list[$row['mail_id']][] = $row['file'];
      }
    }

    return array_map(function (&$row) use ($list) {
      $row->attachments = array();

      foreach (array_get($list, $row->id, array()) as $files) {
        $row->attachments = $files;
      }
    }, $rows);
  }

  /**
   * Save attachments
   *
   * @param int $mail_id
   */
  protected function saveAttachments($mail_id) {
    if (($mail_id > 0) && count($this->attachments)) {
      //Start order
      $order = 0;

      foreach ($this->attachments as $file) {
        DB::table('sys_mail_attachment')->insert(array(
          'mail_id' => $mail_id,
          'order' => ++$order,
          'file' => DB::func('LOAD_FILE', $file->getPath())
        ));
      }
    }
  }

  /**
   * Set to
   *
   * @param mixed $object
   * @param bool $blind
   *
   * @return $this
   */
  public function to($object, $blind = false) {
    //Find user or user members of a group
    $object = Object::findUsers($object, !is_numeric($object) ? 'name' : 'id');

    foreach ($object as $user) {
      $user->blind = $blind;

      $this->recipients[] = $user;
    }

    return $this;
  }

  /**
   * To as a blind copy
   *
   * @param mixed $object
   *
   * @return $this
   */
  public function bcc($object) {
    $this->to($object, true);

    return $this;
  }

  /**
   * With recipient list
   *
   * @param array $rows
   * @param array $params
   *
   * @return array
   */
  protected function recipients($rows, $params = array()) {
    if (in_array(__FUNCTION__, $this->called)) {
      return $rows;
    }

    //Add called class
    $this->called[] = __FUNCTION__;

    //Prepare recipients
    $list = array();

    if (count($this->ids())) {
      //Get
      $query = DB::table('sys_mail_recipient as r')
        ->join('sys_object as o', 'o.id', 'r.recipient_id')
        ->whereIn('r.mail_id', $this->ids())
        ->get(array(
          'r.mail_id', 'o.id', 'o.role', 'o.name', 'o.email', 'o.first_name', 'o.last_name'
        ));

      foreach ($query as $row) {
        $list[$row['mail_id']][] = array(
          'id' => $row['id'],
          'role' => $row['role'],
          'name' => $row['name'],
          'email' => $row['email'],
          'first_name' => $row['first_name'],
          'last_name' => $row['last_name']
        );
      }
    }

    return array_map(function (&$row) use ($list) {
      $row->recipients = array();

      foreach (array_get($list, $row->id, array()) as $recipients) {
        $row->recipients = $recipients;
      }
    }, $rows);
  }

  /**
   * Save recipients
   *
   * @param int $mail_id
   */
  protected function saveRecipients($mail_id) {
    if (($mail_id > 0) && count($this->recipients)) {
      foreach ($this->recipients as $user) {
        DB::table('sys_mail_recipient')->insert(array(
          'mail_id' => $mail_id,
          'recipient_id' => $user->id,
          'blind' => isset($user->blind) ? $user->blind : 'false'
        ));
      }
    }
  }

  /**
   * Send crated message
   *
   * @return Message
   */
  public function send() {
    $message = Message::result(lang('message.nothing_done', 'Herhangi bir işlem yapılmadı!'));

    try {
      $this->save(function ($id) {
        $this->saveAttachments($id);
        $this->saveRecipients($id);
      });

      $message->success = true;
      $message->text = lang('message.sent_successfully', 'Mesaj iletildi...');
    } catch (\Exception $e) {
      $message->text = $e->getMessage();
    }

    return $message;
  }

  /**
   * Totals
   *
   * @param array $rows
   * @param string $target
   *
   * @return array
   */
  protected function total($rows, $target) {
    $valid = array(
      'attachment', 'recipient'
    );

    if (!in_array($target, $valid, true) || in_array($target . ucfirst(__FUNCTION__), $this->called)) {
      return $rows;
    }

    //Add called class
    $this->called[] = $target . ucfirst(__FUNCTION__);

    //Get totals
    $totals = array();

    foreach (DB::table('sys_mail_' . $target)
               ->whereIn('mail_id', $this->ids())
               ->groupBy('mail_id')
               ->get(array(
                 'mail_id',
                 DB::func('COUNT', '*', 'total')
               )) as $row) {
      $totals[array_get($row, 'mail_id')] = array_get($row, 'total');
    }

    return array_map(function (&$row) use ($target, $totals) {
      //Default 0
      $row->{$target . 'Total'} = 0;

      foreach ($totals as $list) {
        $row->{$target . 'Total'} = array_get($list, $row->id, 0);
      }
    }, $rows);
  }

}