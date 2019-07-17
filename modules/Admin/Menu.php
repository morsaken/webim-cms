<?php
/**
 * @author Orhan POLAT
 */

namespace Admin;

use System\Content;
use Webim\Library\Carbon;
use Webim\Library\Message;
use Webim\View\Manager as View;

class Menu {

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
    $manager->addRoute($manager->prefix . '/content/menu', __CLASS__ . '::getIndex');
    $manager->addRoute($manager->prefix . '/content/menu', __CLASS__ . '::postForm', 'POST');
    $manager->addRoute($manager->prefix . '/content/menu/orders', __CLASS__ . '::postOrders', 'POST');
    $manager->addRoute($manager->prefix . '/content/menu/:id+', __CLASS__ . '::deleteForm', 'DELETE');

    $parent = $manager->addMenu(lang('admin.menu.system', 'Sistem'), $manager->prefix . '/content', lang('admin.menu.content', 'İçerik'), null, 'fa fa-edit');
    $manager->addMenu(lang('admin.menu.system', 'Sistem'), $manager->prefix . '/content/menu', lang('admin.menu.menu', 'Menü'), $parent, 'fa fa-list');

    static::$manager = $manager;
  }

  /**
   * List
   *
   * @param array $params
   *
   * @return string
   */
  public function getIndex($params = array()) {
    $manager = static::$manager;

    $manager->put('subnavs', array(
      btn(lang('admin.menu.add', 'Ekle'), '#add', 'fa-plus')
    ));

    $lang = array_get($params, 'lang');

    //List
    $list = array();

    foreach (Content::init()->where('type', 'menu')->where('language', input('language', $lang))->load()->with('meta')->get('rows') as $row) {
      $list[] = array(
        'id' => $row->id,
        'url' => $row->url,
        'title' => $row->title,
        'active' => $row->active,
        'items' => array_get($row, 'meta.items', array())
      );
    }

    if ($manager->app->request->isAjax()) {
      $manager->app->response->setContentType('json');

      return array_to($list);
    }

    $manager->set('caption', lang('admin.menu.menu', 'Menü'));
    $manager->breadcrumb($manager->prefix . '/content', lang('admin.menu.content', 'İçerik'));
    $manager->breadcrumb($manager->prefix . '/content/menu', lang('admin.menu.menu', 'Menü'));

    $manager->put('list', $list);

    return View::create('content.menu')->data($manager::data())->render();
  }

  /**
   * Save
   *
   * @param array $params
   *
   * @return string
   */
  public function postForm($params = array()) {
    $manager = static::$manager;

    $manager->app->response->setContentType('json');

    $id = array_get($params, 'id', input('id', 0));

    //Create url
    $url = Content::makeUrl(input('url'), input('title'));

    return Content::init()->validation(array(
      'title' => 'required'
    ), array(
      'title.required' => lang('admin.message.content_title_required', 'İçerik başlığını girin!')
    ))->set(array(
      'id' => $id,
      'type' => 'menu',
      'language' => input('language', lang()),
      'url' => $url,
      'title' => input('title'),
      'publish_date' => Carbon::createFromTimestamp(strtotime(input('publish_date')))
    ))->save(function ($id) use ($manager) {
      //Menu items
      $items = @json_decode(input('items'));

      $this->saveMeta($id, array(
        'items' => $items
      ));
    })->forData();
  }

  /**
   * Delete
   *
   * @param array $params
   *
   * @return string
   */
  public function deleteForm($params = array()) {
    $manager = static::$manager;

    $manager->app->response->setContentType('json');

    $ids = array_filter(explode(',', array_get($params, 'id', '')), function ($id) {
      return (int) $id > 0;
    });

    if (count($ids)) {
      return Content::init()->delete($ids)->forData();
    }

    return Message::result(lang('message.nothing_done', 'Herhangi bir işlem yapılmadı!'))->forData();
  }

}