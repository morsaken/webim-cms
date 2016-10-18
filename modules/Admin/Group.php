<?php
/**
 * @author Orhan POLAT
 */

namespace Admin;

use \System\Object;
use \Webim\Library\Message;
use \Webim\View\Manager as View;

class Group {

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
  $manager->addRoute($manager->prefix . '/system/groups', __CLASS__ . '::getIndex');
  $manager->addRoute($manager->prefix . '/system/groups/form/?:id', __CLASS__ . '::getForm');
  $manager->addRoute($manager->prefix . '/system/groups/form/?:id', __CLASS__ . '::postForm', 'POST');
  $manager->addRoute($manager->prefix . '/system/groups/form/?:id+', __CLASS__ . '::deleteForm', 'DELETE');

  $parent = $manager->addMenu(lang('admin.menu.system', 'Sistem'), $manager->prefix . '/system', lang('admin.menu.system', 'Sistem'), null, 'fa fa-cogs');
  $manager->addMenu(lang('admin.menu.system', 'Sistem'), $manager->prefix . '/system/groups', lang('admin.menu.groups', 'Gruplar'), $parent, 'fa fa-users');

  static::$manager = $manager;
 }

 public function getIndex($lang = null) {
  $manager = static::$manager;

  $manager->put('subnavs', array(
   btn(lang('admin.menu.create', 'Yeni Oluştur'), url($manager->prefix . '/system/groups/form'), 'fa-plus')
  ));

  $manager->set('roles', array(
   'sys' => lang('admin.label.role.system', 'Sistem'),
   'app' => lang('admin.label.role.application', 'Uygulama')
  ));

  $manager->set('caption', lang('admin.menu.groups', 'Gruplar'));
  $manager->breadcrumb($manager->prefix . '/system', lang('admin.menu.system', 'Sistem'));
  $manager->breadcrumb($manager->prefix . '/system/groups', lang('admin.menu.groups', 'Gruplar'));

  $manager->put('list',
   Object::init()->where('type', 'group')
    ->orderBy(input('orderby', 'id'), input('order', ['desc', 'asc']))
    ->load(input('offset', 0), input('limit', 20))
    ->with('totalMembers')
    ->with(function($rows) use ($manager) {
     return array_map(function (&$row) use ($rows, $manager) {
      $row->role = $manager->get('roles.' . $row->role, $row->role);
      $row->active = $row->active == 'true';
      $row->status = $row->active ? lang('admin.label.active', 'Aktif') : lang('admin.label.passive', 'Pasif');
     }, $rows);
    })->get()
  );

  return View::create('system.groups.list')->data($manager::data())->render();
 }

 public function getForm($lang = null, $id = 0) {
  $manager = static::$manager;

  $manager->set('roles', array(
   'sys' => lang('admin.label.role.system', 'Sistem'),
   'app' => lang('admin.label.role.application', 'Uygulama')
  ));

  $manager->set('groups', Object::init()->where('type', 'group')->load()->getList('first_name'));

  $action = 'new';
  $actionTitle = lang('admin.label.create_new', 'Yeni Oluştur');

  $group = Object::init()
   ->where('type', 'group')
   ->where('id', $id)
   ->load()
   ->with('meta')
   ->with('members')->get('rows.0');

  if ($group) {
   $action = 'edit';
   $actionTitle = lang('admin.label.edit', 'Düzenle');

   $manager->put('group', $group);
  }

  $manager->set('caption', lang('admin.menu.groups', 'Gruplar'));
  $manager->breadcrumb($manager->prefix . '/system', lang('admin.menu.system', 'Sistem'));
  $manager->breadcrumb($manager->prefix . '/system/groups', lang('admin.menu.groups', 'Gruplar'));
  $manager->breadcrumb($manager->prefix . '/system/groups/form', lang('admin.menu.' . $action, $actionTitle));

  return View::create('system.groups.form')->data($manager::data())->render();
 }

 public function postForm($lang = null, $id = null) {
  $manager = static::$manager;

  $manager->app->response->setContentType('json');

  return Object::init()->validation(array(
   'name' => 'required',
   'first_name' => 'required'
  ), array(
   'name.required' => lang('admin.message.group_name_required', 'Grup adını girin!'),
   'first_name.required' => lang('admin.message.group_name_required', 'Grup adını girin!')
  ))->set('id', !is_null($id) ? $id : input('id', 0))
   ->set('type', 'group')
   ->set('role', input('role', array('sys', 'app')))
   ->set('name', input('name'))
   ->set('first_name', input('first_name'))
   ->set('version', input('version'))
   ->set('active', input('active', array('false', 'true')))
   ->save(function($id) use ($manager) {
    $this->saveMembers($id, input('members'));
   })->forData();
 }

 public function deleteForm($lang = null, $ids = '') {
  $manager = static::$manager;

  $manager->app->response->setContentType('json');

  $ids = array_filter(explode(',', $ids), function($id) {
   return (int) $id > 0;
  });

  if (count($ids)) {
   return Object::init()->delete($ids)->forData();
  }

  return Message::result(lang('message.nothing_done', 'Herhangi bir işlem yapılmadı!'))->forData();
 }

}