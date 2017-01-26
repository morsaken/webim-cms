<?php
/**
 * @author Orhan POLAT
 */

namespace System;

use \Webim\App;
use \Webim\Database\Manager as DB;
use \Webim\Http\Request;
use \Webim\Http\Response;
use \Webim\Http\Session;
use \Webim\Library\Auth;
use \Webim\Library\Carbon;
use \Webim\Library\Message;
use \Webim\Library\Str;

class Sign {

  /**
   * Cookie name
   *
   * @var string
   */
  protected static $cookieName = 'WebimCMS';

  /**
   * Cookie life time
   *
   * @var int
   */
  protected static $cookieLifetime = 604800; //1 week

  /**
   * Sign in
   *
   * @param string $name
   * @param string $pass
   * @param bool $hashedPass
   * @param bool $keep
   *
   * @return \Webim\Library\Message
   */
  public static function in($name, $pass, $hashedPass = false, $keep = false) {
    //Return message
    $message = Message::result(lang('message.nothing_done', 'Herhangi bir işlem yapılmadı!'));

    if (!Auth::current()->isLoggedIn()) {
      //Start
      $user = Object::init();

      $key = 'name';

      if (filter_var($name, FILTER_VALIDATE_EMAIL) !== false) {
        $key = 'email';
      }

      $user->only('type', 'user')->where($key, $name)->only('active')->only('meta', array(
        'pass' => ($hashedPass ? $pass : md5($pass))
      ));

      $check = $user->load()->with('meta')->with('groups')->get();

      if ($check->total > 0) {
        $row = array_get($check, 'rows.0');

        $root = ($row->role == 'root');
        $groups = array();

        foreach ($row->groups as $group) {
          $groups[$group->name] = $group;
        }

        if ($root) {
          //Create root group
          $group = new \stdClass();
          $group->id = 0;
          $group->role = 'sys';
          $group->name = 'root';
          $group->first_name = 'Root Group';
          $group->full_name = $group->first_name;

          $groups['root'] = $group;
        }

        //Auth
        $auth = Auth::current();

        $auth->set('id', $row->id);
        $auth->set('role', ($root ? 'admin' : $row->role));
        $auth->set('name', $row->name);
        $auth->set('email', $row->email);
        $auth->set('first_name', $row->first_name);
        $auth->set('last_name', $row->last_name);
        $auth->set('full_name', trim(implode(' ', array($row->first_name, $row->last_name))));
        $auth->set('groups', $groups);

        //Save session
        DB::table('sys_object_session')->insert(array(
          'access_id' => Session::current()->get('access_id', 0),
          'login_time' => Carbon::now(),
          'object_id' => $row->id
        ));

        if ($keep) {
          $info = serialize(array('name' => $row->name, 'pass' => $row->meta->pass));

          Response::current()
            ->setCookie(static::$cookieName, $info, time() + static::$cookieLifetime)
            ->encryptCookies(App::current()->crypt);
        }

        $message->success = true;
        $message->text = lang('message.user_successfully_logged_in', 'Başarılı giriş...');
        $message->return = array(
          'role' => $auth->get('role'),
          'name' => $auth->get('name'),
          'first_name' => $auth->get('first_name'),
          'last_name' => $auth->get('last_name')
        );
      } else {
        $message->text = lang('message.user_pass_match_error', 'Kullanıcı adı ve parolası uyuşmadı!');
      }
    } else {
      $message->text = lang('message.already_logged_in', 'Zaten giriş yapmışsınız!');
    }

    return $message;
  }

  /**
   * Sign out
   */
  public static function out() {
    Auth::current()->logout();
    Response::current()->removeCookie(static::$cookieName);
  }

  /**
   * Check signed in info
   * @return bool
   */
  public static function check() {
    if (Auth::current()->isLoggedIn() || static::fromCookie()) {
      return true;
    }

    return false;
  }

  /**
   * Check cookie signed in info
   *
   * @return bool
   */
  protected static function fromCookie() {
    $cookie = Request::current()->cookie(static::$cookieName);

    if (!is_null($cookie) && Str::text($cookie)->isSerialized()) {
      $info = unserialize($cookie);

      $check = static::in(array_get($info, 'name'), array_get($info, 'pass'), true);

      if ($check->success()) {
        return true;
      }
    }

    return false;
  }

}