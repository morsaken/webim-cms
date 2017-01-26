<?php
/**
 * @author Orhan POLAT
 */

namespace Admin;

use System\Mail;
use \Webim\View\Manager as View;

class Inbox {

  /**
   * Manager
   *
   * @var \Admin\Manager
   */
  protected static $manager;

  /**
   * Register current class and routes
   *
   * @param \Admin\Manager $manager
   */
  public static function register(Manager $manager) {
    $manager->addRoute(array(
      $manager->prefix . '/my/inbox',
      $manager->prefix . '/my/inbox/list'
    ), __CLASS__ . '::getInbox');
    $manager->addRoute($manager->prefix . '/my/inbox/view/:id+', __CLASS__ . '::getView');

    if ($manager->app->request->isAjax()) {
      $manager->addRoute($manager->prefix . '/my/inbox/list', __CLASS__ . '::getList');
      $manager->addRoute($manager->prefix . '/my/inbox/reply', __CLASS__ . '::getReply');
      $manager->addRoute($manager->prefix . '/my/inbox/compose', __CLASS__ . '::getCompose');
    }

    static::$manager = $manager;
  }

  public function getInbox() {
    $manager = static::$manager;

    $manager->set('caption', lang('admin.menu.inbox', 'Mesajlar'));
    $manager->breadcrumb($manager->prefix . '/my/inbox', lang('admin.menu.inbox', 'Mesajlar'));

    $manager->set('list', Mail::inbox()->orderBy('m.date', 'desc')->load()->with('total', 'attachment')->get());

    return View::create('my.inbox.list')->data($manager::data())->render();
  }

  public function getView($lang = null, $key = null) {
    $manager = static::$manager;

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
      $manager->app->redirect(url($manager->prefix . '/inbox'));
    }

    return View::create('my.inbox.view')->data($manager::data())->render();
  }

  public function getReply($lang = null, $key = null) {
    $manager = static::$manager;

    return View::create('my.inbox.reply')->data($manager::data())->render();
  }

  public function getCompose($lang = null, $key = null) {
    $manager = static::$manager;

    return View::create('my.inbox.compose')->data($manager::data())->render();
  }

}