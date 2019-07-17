<?php
/**
 * @author Orhan POLAT
 */

namespace System;

use Webim\Database\Manager as DB;
use Webim\Http\Request;
use Webim\Http\Session;
use Webim\Library\Controller;

class Access extends Controller {

  /**
   * Constructor
   */
  public function __construct() {
    parent::__construct(DB::table('sys_access'));
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
   * Write access info into database
   *
   * @return \Webim\Http\Session
   *
   * @throws \Exception
   */
  public static function write() {
    $session = Session::current();

    if (!$session->get('access_id')) {
      $ip = Request::current()->getClientIp();

      $access = static::init()->set(array(
        'ip_address' => ip2long($ip),
        'referer' => substr(Request::current()->header('Referer'), 0, 255),
        'user_agent' => substr(Request::current()->header('User-Agent'), 0, 255)
      ))->save();

      $session->set('access_id', $access['id']);
      $session->set('ip_address', $ip);
      $session->set('secret', md5(uniqid(rand(), true)));
    }

    return $session;
  }

}