<?php
/**
 * @author Orhan POLAT
 */

namespace System;

use \Webim\Database\Manager as DB;
use \Webim\Library\Auth;
use \Webim\Library\Config;
use \Webim\Library\Controller;
use \Webim\Library\Message;

class Settings extends Controller {

  /**
   * Constructor
   */
  public function __construct() {
    parent::__construct(DB::table('sys_settings'));
  }

  /**
   * Static creator
   *
   * @return self
   */
  public static function init() {
    return new self();
  }

  /**
   * Save
   *
   * @return Message
   */
  public function save() {
    $message = Message::result(lang('message.nothing_done', 'Herhangi bir işlem yapılmadı!'));

    try {
      if (!parent::unique('type', 'owner_id', 'key')) {
        throw new \Exception(lang('message.duplicate_entry', [array_get($this->data, 'key')], 'Bu isimde bir kayıt var: %s'));
      }

      $save = call_user_func_array(array(
        'parent',
        'save'
      ), func_get_args());

      $message->success = true;
      $message->text = lang('message.saved', 'Kaydedildi...');
      $message->return = $save;
    } catch (\Exception $e) {
      if (stripos($e->getMessage(), 'duplicate entry') !== false) {
        $message->text = lang('message.duplicate_entry', 'Bu isimde bir kayıt var!');
        $message->return = array(
          'error' => $e->getMessage()
        );
      } elseif (stripos($e->getMessage(), 'version mismatch') !== false) {
        $message->text = lang('message.version_mismatch', 'Versiyon hatası! Sayfayı yenileyin!');
      } else {
        $message->text = $e->getMessage();
      }
    }

    return $message;
  }

  /**
   * Save all settings
   *
   * @param string $type
   * @param array $settings
   * @param null|int $ownerId
   * @param bool $deleteAbsents
   *
   * @return \Webim\Library\Message
   */
  public function saveAll($type, $settings = array(), $ownerId = null, $deleteAbsents = false) {
    //Default return
    $message = Message::result(lang('message.nothing_done', 'Herhangi bir işlem yapılmadı!'));

    //Actions
    $toInsert = array();
    $toUpdate = array();
    $toDelete = array();

    //Current list
    $list = array();

    foreach (static::init()->where('type', $type)->where('owner_id', $ownerId)->load()->get('rows') as $row) {
      $list[$row->key] = array(
        'id' => $row->id,
        'value' => $row->value,
        'version' => $row->version
      );
    }

    foreach ($settings as $key => $value) {
      //Value may come as array
      $values = array();

      if (is_array($value)) {
        $values = array_dot(array($key => $value));
      } else {
        $values[$key] = $value;
      }

      foreach ($values as $k => $v) {
        if (isset($list[$k])) {
          $toUpdate[$k] = array(
            'id' => $list[$k]['id'],
            'value' => $v,
            'version' => $list[$k]['version']
          );
        } else {
          $toInsert[$k] = array(
            'id' => 0,
            'value' => $v,
            'version' => 1
          );
        }

        unset($list[$k]);
      }
    }

    foreach ($list as $key => $values) {
      $toDelete[$key] = $values['id'];
    }

    DB::beginTransaction();

    try {
      if (count($toDelete) && $deleteAbsents) {
        static::init()->delete($toDelete);
      }

      if (count($toUpdate)) {
        foreach ($toUpdate as $key => $values) {
          static::init()
            ->set('id', $values['id'])
            ->set('key', $key)
            ->set('value', $values['value'])
            ->set('version', $values['version'])
            ->save();
        }
      }

      if (count($toInsert)) {
        foreach ($toInsert as $key => $values) {
          static::init()
            ->set('type', $type)
            ->set('owner_id', $ownerId)
            ->set('key', $key)
            ->set('value', $values['value'])
            ->save();
        }
      }

      //Commit
      DB::commit();

      $message->success = true;
      $message->text = lang('message.saved', 'Kaydedildi...');
    } catch (\Exception $e) {
      //Rollback
      DB::rollBack();

      $message->text = $e->getMessage();
    }

    return $message;
  }

  /**
   * Delete settings
   *
   * @param string $type
   * @param string $key
   * @param null|int $ownerId
   *
   * @return \Webim\Library\Message
   */
  public function remove($type, $key, $ownerId = null) {
    //Default return
    $message = Message::result(lang('message.nothing_done', 'Herhangi bir işlem yapılmadı!'));

    $delete = static::init()
      ->where('type', $type)
      ->where('owner_id', $ownerId)
      ->where('key', 'like', $key . '%')
      ->delete();

    if ($delete) {
      $message->success = true;
      $message->text = lang('message.deleted', 'Silindi...');
    }

    return $message;
  }

  /**
   * Get global configuration
   *
   * @return mixed
   */
  public function getGlobalConf() {
    //Get settings from database
    $this->where(function ($q) {
      $q->where('type', 'system');
      $q->whereNull('owner_id');
    });

    if (Auth::current()->isLoggedIn()) {
      $this->orWhere(function ($q) {
        $q->where('type', 'user');
        $q->where('owner_id', Auth::current()->get('id'));
      });
    }

    $memberOf = array_filter(array_values(Auth::current()->get('member_of')), function ($value) {
      return ((int) $value > 0);
    });

    if (count($memberOf) > 0) {
      $this->orWhere(function ($q) use ($memberOf) {
        $q->where('type', 'group');
        $q->whereIn('owner_id', $memberOf);
      });
    }

    return $this->load()->get('rows');
  }

  /**
   * Set global configuration
   */
  public function setGlobalConf() {
    foreach ($this->getGlobalConf() as $row) {
      if (!Config::init()->has(array_get($row, 'key'))) {
        Config::set(array_get($row, 'key'), array_get($row, 'value'));
      }
    }
  }

}