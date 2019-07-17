<?php
/**
 * @author Orhan POLAT
 */

namespace System;

use Webim\App;
use Webim\Library\File;

abstract class Manager {

  /**
   * App instance
   *
   * @var \Webim\App
   */
  public $app;

  /**
   * Configuration array
   *
   * @var array
   */
  protected $conf = array();

  /**
   * Route list
   *
   * @var array
   */
  protected $routes = array();

  /**
   * Current instance
   *
   * @var object
   */
  protected static $instance;

  /**
   * Constructor
   */
  public function __construct() {
    if (!static::$instance) {
      $this->app = App::current();
      $this->defaults();

      static::$instance = $this;
    }
  }

  /**
   * Init class
   *
   * @return static
   */
  public static function init() {
    return static::$instance ? static::$instance : new static();
  }

  abstract protected function defaults();

  /**
   * Get all data config
   *
   * @param null|string $key
   * @param null|mixed $default
   *
   * @return mixed
   */
  public static function data($key = null, $default = null) {
    return static::init()->get($key, $default);
  }

  /**
   * Put data into config
   *
   * @param string|array $key
   * @param null|mixed $value
   *
   * @return $this
   */
  public static function put($key, $value = null) {
    $class = static::init();

    if (is_array($key)) {
      foreach ($key as $k => $v) {
        $class->set($k, $v);
      }
    } else {
      $class->set($key, $value);
    }

    return $class;
  }

  /**
   * Breadcrumb
   *
   * @param string $link
   * @param mixed $title
   *
   * @return $this
   */
  public function breadcrumb($link, $title) {
    //All
    $crumbs = $this->get('breadcrumb', array());

    foreach ($crumbs as $crumb) {
      $crumb->active = false;
    }

    $crumb = new \stdClass();
    $crumb->link = $link;
    $crumb->url = url($link);
    $crumb->title = $title;
    $crumb->active = true;

    $crumbs[] = $crumb;

    //Set breadcrumb
    $this->set('breadcrumb', $crumbs);

    return $this;
  }

  /**
   * Add to menu
   *
   * @param string $type
   * @param string $link
   * @param mixed $title
   * @param null|string $parent
   * @param null|string $icon
   * @param null|string $badge
   * @param null|int $order
   *
   * @return \stdClass
   */
  public function addMenu($type = 'Web-IM', $link, $title, $parent = null, $icon = null, $badge = null, $order = null) {
    //All
    $menus = $this->get('menu', array());

    //Create nav
    $nav = new \stdClass();
    $nav->type = $type;
    $nav->link = trim($link, '/');
    $nav->url = url($nav->link);
    $nav->title = $title;
    $nav->icon = $icon;
    $nav->order = $order;
    $nav->badge = $badge;
    $nav->sub = array();

    if (!is_null($parent)) {
      foreach (array_get($menus, $type, array()) as $menu) {
        switch (true) {
          case is_object($parent) && ($menu->link === $parent->link):
          case is_string($parent) && ($menu->link === trim($parent, '/')):

            $menu->sub[] = $nav;

            if (!is_null($nav->order)) {
              usort($menu->sub, function ($a, $b) {
                return $a->order - $b->order;
              });
            }

            break;
        }
      }
    } else {
      $exists = false;

      foreach (array_get($menus, $type, array()) as $menu) {
        if ($menu->link === $nav->link) {
          $exists = true;
          break;
        }
      }

      if (!$exists) {
        $menus[$type][] = $nav;

        if (!is_null($nav->order)) {
          usort($menus[$type], function ($a, $b) {
            return $a->order - $b->order;
          });
        }
      }
    }

    //Set menu
    $this->set('menu', $menus);

    return $nav;
  }

  /**
   * Add route
   *
   * @param string|array $path
   * @param string|callable $class
   * @param null|mixed $method
   * @param null|string $before
   * @param null|string $name
   *
   * @return $this
   */
  public function addRoute($path, $class, $method = null, $before = null, $name = null) {
    if (is_array($path)) {
      $paths = $path;

      foreach ($paths as $path) {
        $this->addToRoute($path, $class, $method, $before, $name);
      }
    } else {
      $this->addToRoute($path, $class, $method, $before, $name);
    }

    return $this;
  }

  /**
   * Add to route
   *
   * @param string $path
   * @param string|callable $class
   * @param null|mixed $method
   * @param null|string $before
   * @param null|string $name
   */
  private function addToRoute($path, $class, $method = null, $before = null, $name = null) {
    if (strlen($path)) {
      //Add routes
      $this->routes[] = array(
        'path' => $path,
        'class' => $class,
        'method' => is_null($method) ? 'GET' : array_map(function ($val) {
          return strtoupper($val);
        }, (array) $method),
        'before' => $before,
        'name' => $name
      );
    }
  }

  /**
   * Yield routes
   */
  public function yieldRoutes() {
    foreach ($this->routes as $route) {
      $app = $this->app->map($route['path'], array(
        'name' => $route['name']
      ), function ($r) use ($route) {
        if (is_callable($route['before'])) {
          forward_static_call($route['before'], $r);
        }
      }, $route['class']);

      call_user_func_array(array($app, 'via'), (array) $route['method']);
    }
  }

  /**
   * Discover extra modules
   *
   * @return $this
   */
  public function discover() {
    foreach (File::path(PUB_ROOT)->folder('modules')->folders() as $folder) {
      $admin = $folder->folder('Admin');

      if ($admin->exists()) {
        foreach ($admin->fileNotIn('index' . EXT)->files() as $file) {
          $class = '\\' . str_case($folder->name, 'upperFirst')
            . '\Admin\\' . str_case($file->name, 'upperFirst');

          if (method_exists($class, 'register')) {
            forward_static_call($class . '::register', $this);
          }
        }
      }
    }

    return $this;
  }

  /**
   * Get config
   *
   * @param null|string $key
   * @param mixed $default
   *
   * @return mixed
   */
  public function get($key = null, $default = null) {
    return array_get($this->conf, $key, $default);
  }

  /**
   * Set config
   *
   * @param string $key
   * @param mixed $value
   *
   * @return $this
   */
  public function set($key, $value) {
    array_set($this->conf, $key, $value);

    return $this;
  }

  /**
   * Magic get
   *
   * @param null|string $key
   *
   * @return mixed
   */
  public function __get($key = null) {
    return $this->get($key);
  }

  /**
   * Magic set
   *
   * @param string $key
   * @param mixed $value
   */
  public function __set($key, $value) {
    $this->set($key, $value);
  }

}