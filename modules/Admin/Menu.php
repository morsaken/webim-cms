<?php
/**
 * @author Orhan POLAT
 */

namespace Admin;

use \System\Content;
use \Webim\Library\Carbon;
use \Webim\Library\Message;
use \Webim\View\Manager as View;

class Menu {

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
   * @param null|string $lang
   *
   * @return string
   */
  public function getIndex($lang = null) {
    $manager = static::$manager;

    $manager->put('subnavs', array(
      btn(lang('admin.menu.add', 'Ekle'), '#add', 'fa-plus')
    ));

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
   * @param null|string $lang
   * @param null|int $id
   *
   * @return string
   */
  public function postForm($lang = null, $id = null) {
    $manager = static::$manager;

    $manager->app->response->setContentType('json');

    if (is_null($id)) {
      $id = input('id', 0);
    }

    return Content::init()->validation(array(
      'url' => 'required',
      'title' => 'required'
    ), array(
      'url.required' => lang('admin.message.content_url_required', 'İçerik URL\'yi girin!'),
      'title.required' => lang('admin.message.content_title_required', 'İçerik başlığını girin!')
    ))->set('id', $id)
      ->set('type', 'menu')
      ->set('language', input('language', lang()))
      ->set('url', input('url'))
      ->set('title', input('title'))
      ->set('publish_date', Carbon::createFromTimestamp(strtotime(input('publish_date'))))
      ->save(function ($id) use ($manager) {
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
   * @param null|string $lang
   * @param string $ids
   *
   * @return string
   */
  public function deleteForm($lang = null, $ids = '') {
    $manager = static::$manager;

    $manager->app->response->setContentType('json');

    $ids = array_filter(explode(',', $ids), function ($id) {
      return (int) $id > 0;
    });

    if (count($ids)) {
      return Content::init()->delete($ids)->forData();
    }

    return Message::result(lang('message.nothing_done', 'Herhangi bir işlem yapılmadı!'))->forData();
  }

}