<?php
/**
 * @author Orhan POLAT
 */

namespace Admin;

use System\Mail;
use Webim\Library\Auth;
use Webim\Library\Carbon;
use Webim\Library\File;
use Webim\View\Manager as View;

class Manager extends \System\Manager {

  /**
   * @var string
   */
  public $prefix = '';

  /**
   * Defaults
   */
  protected function defaults() {
    //Default path
    $path = File::path('views.backend.' . conf('backend.' . lang() . '.template', 'default'));

    //Root
    $root = $path->folder('layouts')->src();

    //Set default path
    View::setPath($path);

    //Admin prefix
    $this->prefix = '/' . conf('default.admin.prefix', 'admin');

    //Common conf
    $this->conf = array(
      'root' => $root,
      'title' => conf('backend.' . lang() . '.title', 'Web-IM XI'),
      'description' => conf('backend.' . lang() . '.description', 'Web Internet Manager'),
      'keywords' => conf('backend.' . lang() . '.keywords'),
      'breadcrumb' => array(),
      'prefix' => trim($this->prefix, '/') . '/',
      'newInboxTotal' => Mail::inbox(function ($mail) {
        $mail->where('r.read', 'false');
      })->count()
    );

    //Add default home to breadcrumb
    $this->breadcrumb($this->prefix, lang('admin.menu.home', 'Anasayfa'));

    //Add refresher route
    $this->addRoute($this->prefix . '/refresh', function () {
      return Carbon::now()->toATOMString();
    }, 'POST');

    //Instance
    $manager = $this;

    //Login and logout
    Login::register($manager);

    if (Auth::current()->isAdmin()) {
      //Home
      Dashboard::register($manager);

      //Search
      Search::register($manager);

      //Inbox
      Inbox::register($manager);

      //Settings
      Settings::register($manager);

      //Users
      User::register($manager);

      //Groups
      Group::register($manager);

      //Forms
      Form::register($manager);

      //Settings
      ContentSettings::register($manager);

      //Content Builder
      ContentBuilder::register($manager);

      //Settings
      Language::register($manager);

      //Categories
      Category::register($manager);

      //Media
      Media::register($manager);

      //News
      News::register($manager);

      //Menu
      Menu::register($manager);

      //Pages
      Page::register($manager);

      //Discover existing modules
      $manager->discover();
    } elseif (!url_is($manager->prefix . '/login') && !url_is($manager->prefix . '/captcha')) {
      $manager->app->redirect(url($manager->prefix . '/login?redirect=' . url()));
    }
  }

}