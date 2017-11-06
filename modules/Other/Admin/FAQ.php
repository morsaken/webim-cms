<?php
/**
 * @author Orhan POLAT
 */

namespace Other\Admin;

use \Admin\Manager;
use \System\Content;
use \Webim\Library\Carbon;
use \Webim\Library\Message;
use \Webim\View\Manager as View;

class FAQ {

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
    $manager->addRoute($manager->prefix . '/faq', __CLASS__ . '::getList');
    $manager->addRoute($manager->prefix . '/faq/orders', __CLASS__ . '::orders');
    $manager->addRoute($manager->prefix . '/faq/:id#[0-9]+#', __CLASS__ . '::getForm');
    $manager->addRoute($manager->prefix . '/faq', __CLASS__ . '::postForm', 'POST');
    $manager->addRoute($manager->prefix . '/faq/:id#[0-9]+#', __CLASS__ . '::deleteForm', 'DELETE');

    $manager->addMenu(lang('admin.menu.modules', 'Modüller'), $manager->prefix . '/faq', lang('label.faq', 'Sık Sorulan Sorular'), null, 'fa fa-question');

    static::$manager = $manager;
  }

  public function getList($params = array()) {
    $manager = static::$manager;

    if ($manager->app->request->isAjax()) {
      $manager->app->response->setContentType('json');

      $list = array();

      foreach (Content::init()
                 ->only('type', 'faq')
                 ->only('language', input('language', lang()))
                 ->orderBy('order')
                 ->load()->with('meta')->get('rows') as $row) {
        $list[] = array(
          'id' => $row->id,
          'title' => $row->title,
          'answer' => nl2br($row->meta->answer),
          'active' => $row->active
        );
      }

      return array_to($list);
    }

    $manager->set('caption', lang('label.faq', 'Sık Sorulan Sorular'));
    $manager->breadcrumb($manager->prefix . '/faq', lang('label.faq', 'Sık Sorulan Sorular'));

    return View::create('modules.others.faq')->data($manager::data())->render();
  }

  public function getForm($params = array()) {
    $manager = static::$manager;

    $manager->app->response->setContentType('json');

    $list = array();

    $row = Content::init()->only('type', 'faq')
      ->where('id', array_get($params, 'id', 0))
      ->load()->with('meta')->get('rows.0');

    if ($row) {
      $list['id'] = $row->id;
      $list['language'] = $row->language;
      $list['title'] = $row->title;
      $list['answer'] = $row->meta->answer;
      $list['order'] = $row->order;
      $list['version'] = $row->version;
      $list['active'] = $row->active;
    }

    return array_to($list);
  }

  public function postForm($params = array()) {
    $manager = static::$manager;

    $manager->app->response->setContentType('json');

    return Content::init()->validation(array(
      'title' => 'required'
    ), array(
      'title.required' => lang('admin.message.content_title_required', 'İçerik başlığını girin!')
    ))->set(array(
      'id' => input('id', 0),
      'type' => 'faq',
      'language' => input('language', lang()),
      'url' => slug(input('title')),
      'title' => input('title'),
      'publish_date' => Carbon::now(),
      'order' => (input('order', 0) ? input('order', 0) : null),
      'version' => input('version', 0),
      'active' => input('active', array('false', 'true'))
    ))->save(function ($id) use ($manager) {
      $this->saveOrders($id);

      $this->saveMeta($id, array(
        'answer' => input('answer')
      ));
    })->forData();
  }

  public function deleteForm($params = array()) {
    $manager = static::$manager;

    $manager->app->response->setContentType('json');

    $ids = array(array_get($params, 'id'));

    if (count($ids)) {
      return Content::init()->delete($ids)->forData();
    } else {
      return Message::result(lang('message.nothing_done'))->forData();
    }
  }

  public function orders($params = array()) {
    $manager = static::$manager;

    $manager->app->response->setContentType('json');

    $lang = array_get($params, 'lang');

    $orders = array(
      1 => lang('admin.label.at_the_beginning', 'En Başta')
    );

    foreach (Content::orderList('faq', input('language', $lang), input('parent_id', 0), input('id', 0)) as $title) {
      $orders[] = lang('admin.label.after', [$title], '%s sonuna');
    }

    return array_to($orders);
  }

}