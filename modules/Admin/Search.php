<?php
/**
 * @author Orhan POLAT
 */

namespace Admin;

use \Webim\View\Manager as View;

class Search {

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
    $manager->addRoute($manager->prefix . '/search', __CLASS__ . '::getIndex');

    static::$manager = $manager;
  }

  public function getIndex($params = array()) {
    $manager = static::$manager;

    return View::create('search')->data($manager::data())->render();
  }

}