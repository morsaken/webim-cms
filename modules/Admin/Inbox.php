<?php
/**
 * @author Orhan POLAT
 */

namespace Admin;

use System\Mail;
use Webim\Library\Message;
use Webim\Library\Paging;
use Webim\View\Manager as View;

class Inbox {

  /**
   * Manager
   *
   * @var Admin\Manager
   */
  protected static $manager;

  /**
   * Register current class and routes
   *
   * @param Admin\Manager $manager
   */
  public static function register(Manager $manager) {
    $manager->addRoute(array(
      $manager->prefix . '/my/inbox',
      $manager->prefix . '/my/inbox/list'
    ), __CLASS__ . '::getInbox');
    $manager->addRoute($manager->prefix . '/my/inbox/view/:key+', __CLASS__ . '::getView');

    if ($manager->app->request->isAjax()) {
      $manager->addRoute($manager->prefix . '/my/inbox/list', __CLASS__ . '::getList');
      $manager->addRoute($manager->prefix . '/my/inbox/reply', __CLASS__ . '::getReply');
      $manager->addRoute($manager->prefix . '/my/inbox/compose', __CLASS__ . '::getCompose');
      $manager->addRoute($manager->prefix . '/my/inbox/trash/:id+', __CLASS__ . '::trashMail', 'DELETE');
    }

    static::$manager = $manager;
  }

  public function getInbox() {
    $manager = static::$manager;

    $manager->set('caption', lang('admin.menu.inbox', 'Mesajlar'));
    $manager->breadcrumb($manager->prefix . '/my/inbox', lang('admin.menu.inbox', 'Mesajlar'));

    $list = Mail::inbox()->orderBy('m.date', 'desc')
      ->load(input('offset', 0), input('limit', 20))
      ->with('total', 'attachment')->get();

    $list->nav = Paging::nav($list->offset, $list->limit, $list->total);

    $manager->set('list', $list);

    return View::create('my.inbox.list')->data($manager::data())->render();
  }

  public function getView($params = array()) {
    $manager = static::$manager;

    $key = array_get($params, 'key');

    $mail = Mail::inbox(function ($mail) use ($key) {
      $mail->where('m.key', $key);
    })->load()->with(function ($rows) {
      return array_map(function (&$row) {
        if (($row->content_type == 'html') && preg_match('#(<body[^>]*>)(.*?)<\/body>#s', $row->content, $match)) {
          $row->content = $match[2];
        }
      }, $rows);
    })->with('attachments')->get('rows.0');

    if ($mail) {
      $manager->set('caption', lang('admin.menu.inbox', $mail->subject));
      $manager->breadcrumb($manager->prefix . '/my/inbox', lang('admin.menu.inbox', 'Mesajlar'));
      $manager->breadcrumb($manager->prefix . '/my/inbox/view/' . $key, $mail->subject);

      Mail::mark('read', $mail->key);

      $manager->set('mail', $mail);
    } else {
      $manager->app->redirect(url($manager->prefix . '/my/inbox'));
    }

    return View::create('my.inbox.view')->data($manager::data())->render();
  }

  public function getReply($params = array()) {
    $manager = static::$manager;

    return View::create('my.inbox.reply')->data($manager::data())->render();
  }

  public function getCompose($params = array()) {
    $manager = static::$manager;

    return View::create('my.inbox.compose')->data($manager::data())->render();
  }

  public function trashMail($params = array()) {
    $manager = static::$manager;

    $manager->app->response->setContentType('json');

    $ids = array_filter(explode(',', array_get($params, 'id', '')), function ($id) {
      return (int) $id > 0;
    });

    if (count($ids)) {
      $total = 0;

      foreach ($ids as $id) {
        $total += Mail::mark('trashed', $id) ? 1 : 0;
      }

      if ($total) {
        return Message::result(lang('message.mail_trashed', [$total], '%s mesaj silindi'), true)->forData();
      }
    }

    return Message::result(lang('message.nothing_done', 'Herhangi bir işlem yapılmadı!'))->forData();
  }

}