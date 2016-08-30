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

 public function getIndex($lang = null) {
  $manager = static::$manager;

  $nav = new \stdClass();
  $nav->title = lang('admin.menu.add', 'Ekle');
  $nav->url = '#add';
  $nav->icon = 'fa-plus';

  $manager->put('subnavs', array(
   $nav
  ));

  $list = Content::init()
   ->where('type', 'menu')
   ->where('language', input('language', $lang))
   ->only('root')
   ->orderBy('order')
   ->load()->with('meta')->with('children', array(
    'with' => array(
     'meta'
    )
   ))->get('rows');

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

 public function postForm($lang = null, $id = null) {
  $manager = static::$manager;

  $manager->app->response->setContentType('json');

  if (is_null($id)) {
   $id = input('id', 0);
  }

  //Default order
  $order = null;

  if ($id) {
   //Current order
   $order = Content::init()->where('id', $id)->load()->get('rows.0.order');
  }

  if (!$order) {
   //Calculate new order
   $order = Content::init()->where('type', 'menu')->where('language', input('language', lang()))->count() + 1;
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
   ->set('order', $order)
   ->save(function($id) use ($manager) {
    $this->saveMeta($id, array(
     'target' => input('target')
    ));
    $this->saveOrders($id);
   })->forData();
 }

 private function changeParents($menu, $parent_id = null) {
  $order = 1;

  if (is_array($menu)) {
   foreach ($menu as $item) {
    if (isset($item->id)) {
     $menu = Content::init()->where('id', $item->id)->where('type', 'menu')->load()->get('rows.0');

     if ($menu) {
      Content::init()->set(array(
       'id' => $menu->id,
       'parent_id' => $parent_id,
       'type' => 'menu',
       'language' => $menu->language,
       'url' => $menu->url,
       'order' => $order++,
       'version' => $menu->version
      ))->save();
     }

     if (isset($item->children)) {
      $this->changeParents($item->children, $item->id);
     }
    }
   }
  }
 }

 public function postOrders($lang = null) {
  $manager = static::$manager;
  $manager->app->response->setContentType('json');

  $list = @json_decode(input('list'));

  $this->changeParents($list);

  return Message::result(lang('message.saved', var_export($list)))->forData();
 }

 public function deleteForm($lang = null, $ids = '') {
  $manager = static::$manager;

  $manager->app->response->setContentType('json');

  $ids = array_filter(explode(',', $ids), function($id) {
   return (int) $id > 0;
  });

  if (count($ids)) {
   return Content::init()->delete($ids)->forData();
  }

  return Message::result(lang('message.nothing_done', 'Herhangi bir işlem yapılmadı!'))->forData();
 }

}