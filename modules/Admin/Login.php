<?php
/**
 * @author Orhan POLAT
 */

namespace Admin;

use System\Sign;
use Webim\Http\Session;
use Webim\Image\Captcha;
use Webim\Library\Auth;
use Webim\View\Manager as View;

class Login {

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
    $check = __CLASS__ . '::check';

    $manager->addRoute($manager->prefix . '/captcha', __CLASS__ . '::captcha');
    $manager->addRoute($manager->prefix . '/login', __CLASS__ . '::getIndex', 'GET', $check, 'login');
    $manager->addRoute($manager->prefix . '/login', __CLASS__ . '::postIndex', 'POST', $check);
    $manager->addRoute($manager->prefix . '/logout', __CLASS__ . '::getLogout', 'GET', $check);

    static::$manager = $manager;
  }

  /**
   * Get
   *
   * @param array $params
   *
   * @return string
   */
  public function getIndex($params = array()) {
    $manager = static::$manager;
    $app = $manager->app;

    //Login Page
    if (Auth::current()->isLoggedIn()) {
      if (Auth::current()->isAdmin()) {
        $app->redirect(url($manager->prefix));
      } else {
        $app->redirect(url($manager->prefix . '/logout'));
      }
    }

    return View::create('login')->data($manager::data())->with('error', $app->flash->getMessage('login_error'))->render();
  }

  /**
   * Post
   *
   * @param array $params
   */
  public function postIndex($params = array()) {
    $manager = static::$manager;
    $app = $manager->app;

    $code = strtolower(Session::current()->get('captcha'));

    Session::current()->delete('captcha');

    if ($code !== strtolower(input('captcha'))) {
      $app->flash('login_error', lang('message.invalid_captcha_code', 'Geçersiz kod!'));
      $app->redirect(url());
    }

    if (!Sign::check()) {
      $check = Sign::in(input('name'), raw_input('pass'), false, input('stay-signed-in', false));

      if (!$check->success()) {
        $app->flash('login_error', $check->text());
        $app->redirect(url());
      }
    }

    $app->redirect(strlen(input('redirect')) ? input('redirect') : url());
  }

  /**
   * Logout
   */
  public function getLogout() {
    $manager = static::$manager;
    $app = $manager->app;

    Sign::out();

    $app->redirect(url($manager->prefix . '/login'));
  }

  /**
   * CAPTCHA
   */
  public function captcha() {
    //Fonts
    $fonts = View::getPath()->folder('layouts.fonts.open-sans')->fileIn('*.ttf')->files();

    //Create
    $captcha = Captcha::create($fonts);

    //Set to check
    Session::current()->set('captcha', $captcha->getStr());

    //Display
    $captcha->simple()->fontSize(16)->size(100, 40)->bgColor(255, 255, 255)->strColor(46, 50, 143)->display();
  }

  /**
   * Checks login status and redirects to login page
   *
   * @param \Webim\Http\Route $route
   */
  public static function check($route) {
    $manager = static::$manager;
    $app = $manager->app;

    if (!Sign::check() && !url_is($manager->prefix . '/login')) {
      $app->redirect(url(trim($manager->prefix, '/') . '/login?redirect=' . url()));
    } elseif (Auth::current()->isLoggedIn() && !Auth::current()->isAdmin()) {
      $app->redirect(url(trim($manager->prefix, '/') . '/logout'));
    }
  }

}