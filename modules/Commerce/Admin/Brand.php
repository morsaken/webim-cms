<?php
/**
 * @author Orhan POLAT
 */

namespace Commerce\Admin;

use \Admin\Manager;
use \System\Content;
use \System\Media as SystemMedia;
use \Webim\Library\Carbon;
use \Webim\Library\Message;
use \Webim\View\Manager as View;

class Brand {

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
  $manager->addRoute($manager->prefix . '/ecommerce/brands', __CLASS__ . '::getIndex');
  $manager->addRoute($manager->prefix . '/ecommerce/brands/:id+', __CLASS__ . '::getForm');
  $manager->addRoute($manager->prefix . '/ecommerce/brands/:id+', __CLASS__ . '::putForm', 'PUT');
  $manager->addRoute($manager->prefix . '/ecommerce/brands', __CLASS__ . '::postForm', 'POST');
  $manager->addRoute($manager->prefix . '/ecommerce/brands/:id+', __CLASS__ . '::deleteForm', 'DELETE');

  $parent = $manager->addMenu(lang('admin.menu.modules', 'Modüller'), $manager->prefix . '/ecommerce', lang('admin.menu.ecommerce', 'E-Ticaret'), null, 'fa fa-shopping-cart');
  $manager->addMenu(lang('admin.menu.modules', 'Modüller'), $manager->prefix . '/ecommerce/brands', lang('admin.menu.brands', 'Markalar'), $parent, 'fa fa-tags');

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

  $list = array_map(function($row) {
   //Return source
   $row->poster->image = $row->poster->image->src();

   return $row;
  }, Content::init()
   ->where('type', 'brand')
   ->load()->with('poster', array(
    'default' => View::getPath()->folder('layouts.assets.poster')->file('image.png')
   ))->get('rows'));

  if ($manager->app->request->isAjax()) {
   $manager->app->response->setContentType('json');

   return array_to($list);
  }

  $manager->set('caption', lang('admin.menu.brands', 'Markalar'));
  $manager->breadcrumb($manager->prefix . '/ecommerce', lang('admin.menu.ecommerce', 'E-Ticaret'));
  $manager->breadcrumb($manager->prefix . '/ecommerce/brands', lang('admin.menu.brands', 'Markalar'));

  $manager->put('list', $list);

  return View::create('modules.ecommerce.brands')->data($manager::data())->render();
 }

 public function getForm($lang = null, $id) {
  $manager = static::$manager;
  $manager->app->response->setContentType('json');

  $form = Content::init()
   ->where('type', 'brand')
   ->where('id', $id)
   ->load()->with('poster', array(
    'default' => View::getPath()->folder('layouts.assets.poster')->file('image.png')
   ))->get('rows.0');

  $form->poster->image = $form->poster->image->src();

  return array_to($form);
 }

 public function putForm($lang = null, $id) {
  $manager = static::$manager;
  $manager->app->response->setContentType('json');

  $message = Message::result(lang('message.nothing_done', 'Herhangi bir işlem yapılmadı!'));

  $current = Content::init()->where('type', 'brand')->where('id', $id)->load()->get('rows.0');

  if ($current) {
   $status = $current->active == 'true' ? 'false' : 'true';

   $save = Content::init()->set(array(
    'id' => $current->id,
    'active' => $status
   ))->save();

   if ($save->success()) {
    $save->return = $status;
   }

   $message = $save;
  }

  return $message->forData();
 }

 public function postForm($lang = null, $id = null) {
  $manager = static::$manager;
  $manager->app->response->setContentType('json');

  //Poster values
  $poster = array(
   'id' => input('meta-poster_id', 0),
   'image' => null,
   'role' => null
  );

  $save = Content::init()->validation(array(
   'title' => 'required'
  ), array(
   'title.required' => lang('admin.message.content_title_required', 'İçerik başlığını girin!')
  ))->set('id', !is_null($id) ? $id : input('id', 0))
   ->set('type', 'brand')
   ->set('language', '')
   ->set('url', Content::makeUrl('', input('title')))
   ->set('title', input('title'))
   ->set('publish_date', Carbon::now())
   ->set('version', input('version'))
   ->set('active', input('active', array('true', 'false')))
   ->save(function($id) use ($manager, &$poster) {
    if ($file = $manager->app->request->file('poster-file')) {
     //Upload and save
     $upload = SystemMedia::init()->extensions('image', conf('media.image_extensions'))->upload($file, 'image', array(
      'image_max_size' => conf('media.image_max_size', '1920x1080')
     ));

     if ($upload->success()) {
      $poster['id'] = $upload->returns('id');
      $poster['role'] = $upload->returns('role');
      $poster['image'] = $upload->returns('src');
     } else {
      throw new \Exception(lang('message.image_upload_error', [$upload->text()], 'Resim yüklenemedi: %s'));
     }
    } elseif ($poster['id']) {
     $current = SystemMedia::init()->where('id', $poster['id'])->load()->with('files')->get('rows.0');

     if ($current) {
      $poster['role'] = $current->role;
      $poster['image'] = $current->poster->image->src();
     }
    } else {
     $poster['role'] = 'image';
     $poster['image'] = View::getPath()->folder('layouts.assets.poster')->file('image.png')->src();
    }

    $this->saveMeta($id, array(
     'poster_id' => $poster['id']
    ));
   });

  //Set poster
  $save->return = array_merge($save->returns(), array('active' => input('active', array('true', 'false'))), array('poster' => $poster));

  return $save->forData();
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