<?php
/**
 * @author Orhan POLAT
 */

namespace Commerce\Admin;

use \Admin\Manager;
use \System\Content;
use \System\Settings as SysSettings;
use \Webim\Library\Carbon;
use \Webim\Library\Message;
use \Webim\View\Manager as View;

class Country {

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
    $manager->addRoute($manager->prefix . '/ecommerce/countries', __CLASS__ . '::getIndex');
    $manager->addRoute($manager->prefix . '/ecommerce/countries', __CLASS__ . '::postForm', 'POST');
    $manager->addRoute($manager->prefix . '/ecommerce/countries/:id+', __CLASS__ . '::deleteForm', 'DELETE');

    $parent = $manager->addMenu(lang('admin.menu.modules', 'Modüller'), $manager->prefix . '/ecommerce', lang('admin.menu.ecommerce', 'E-Ticaret'), null, 'fa fa-shopping-cart');
    $manager->addMenu(lang('admin.menu.modules', 'Modüller'), $manager->prefix . '/ecommerce/countries', lang('admin.menu.countries', 'Ülkeler'), $parent, 'fa fa-tags');

    static::$manager = $manager;
  }

  /**
   * Index
   *
   * @param array $params
   *
   * @return string
   */
  public function getIndex($params = array()) {
    $manager = static::$manager;

    $nav = new \stdClass();
    $nav->title = lang('admin.menu.add', 'Ekle');
    $nav->url = '#add';
    $nav->icon = 'fa-plus';

    $manager->put('subnavs', array(
      $nav
    ));

    $manager->set('caption', lang('admin.menu.countries', 'Ülkeler'));
    $manager->breadcrumb($manager->prefix . '/ecommerce', lang('admin.menu.ecommerce', 'E-Ticaret'));
    $manager->breadcrumb($manager->prefix . '/ecommerce/countries', lang('admin.menu.countries', 'Ülkeler'));

    $manager->set('list', Content::init()->where('type', 'lookup-country')->orderBy('id')->load()->get('rows'));

    return View::create('modules.ecommerce.countries')->data($manager::data())->render();
  }

  /**
   * Post form
   *
   * @param array $params
   *
   * @return string
   */
  public function postForm($params = array()) {
    $manager = static::$manager;

    $manager->app->response->setContentType('json');

    $version = 0;

    if (input('id', 0)) {
      $version = Content::init()->where('id', input('id', 0))->load()->get('rows.0.version');
    }

    return Content::init()->validation(array(
      'url' => 'required|size:3',
      'title' => 'required|min:3'
    ), array(
      'url.required' => lang('message.url_required', 'URL gerekli!'),
      'url.size' => lang('message.url_length_size', 'URL :size karakter olmalı!'),
      'title.required' => lang('message.title_required', 'Başlık gerekli!'),
      'title.size' => lang('message.title_length_min', 'Başlık en az :min karakter olmalı!')
    ))->set(array(
      'id' => input('id', 0),
      'type' => 'lookup-country',
      'language' => '',
      'url' => input('url'),
      'title' => input('title'),
      'publish_date' => Carbon::now(),
      'version' => $version
    ))->save()->forData();
  }

  /**
   * Delete form
   *
   * @param array $params
   *
   * @return string
   */
  public function deleteForm($params = array()) {
    $manager = static::$manager;

    $manager->app->response->setContentType('json');

    $currency = array_get($params, 'currency');

    if (strlen($currency) && isset(static::$list[$currency])) {
      return SysSettings::init()->remove('system', 'currency.' . $currency)->forData();
    } else {
      return Message::result(lang('message.nothing_done'))->forData();
    }
  }

}