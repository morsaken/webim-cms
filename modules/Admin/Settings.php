<?php
/**
 * @author Orhan POLAT
 */

namespace Admin;

use \System\Object;
use \System\Settings as SysSettings;
use \Webim\Library\File;
use \Webim\View\Manager as View;

class Settings {

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
  $manager->addRoute(array(
   $manager->prefix . '/system',
   $manager->prefix . '/system/settings'
  ), __CLASS__ . '::getIndex');
  $manager->addRoute($manager->prefix . '/system/settings', __CLASS__ . '::postIndex', 'POST');

  $parent = $manager->addMenu(lang('admin.menu.system', 'Sistem'), $manager->prefix . '/system', lang('admin.menu.system', 'Sistem'), null, 'fa fa-cogs');
  $manager->addMenu(lang('admin.menu.system', 'Sistem'), $manager->prefix . '/system/settings', lang('admin.menu.settings', 'Ayarlar'), $parent, 'fa fa-cog');

  static::$manager = $manager;
 }

 /**
  * Get
  *
  * @param null|string $lang
  *
  * @return string
  */
 public function getIndex($lang = null) {
  $manager = static::$manager;

  $manager::put('admins', Object::init()->whereIn('role', ['root', 'admin'])->load()->get('rows'));
  $manager::put('backends', File::in('views.backend')->folders());
  $manager::put('frontends', File::in('views.frontend')->folders());

  $manager->set('caption', lang('admin.menu.settings', 'Ayarlar'));
  $manager->breadcrumb($manager->prefix . '/system', lang('admin.menu.system', 'Sistem'));
  $manager->breadcrumb($manager->prefix . '/system/settings', lang('admin.menu.settings', 'Ayarlar'));

  return View::create('system.settings')->data($manager::data())->render();
 }

 /**
  * Post
  *
  * @param null|string $lang
  *
  * @return string
  */
 public function postIndex($lang = null) {
  $manager = static::$manager;

  $manager->app->response->setContentType('json');

  $settings = array(
   'system.name' => input('system_name'),
   'system.admin' => input('system_admin'),
   'system.publish_date' => input('system_publish_date'),
   'backend.session.timeout_active' => input('backend_session_timeout_active', 'no'),
   'backend.session.timeout_after' => intval(input('backend_session_timeout_after')),
   'email.from' => input('email_from'),
   'email.from_name' => input('email_from_name'),
   'email.smtp.active' => input('email_smtp_active', array('no', 'yes'))
  );

  foreach (langs() as $alias => $lang) {
   $settings['system.' . $alias . '.offline_message'] = input('system_' . $alias . '_offline_message');
   $settings['frontend.' . $alias . '.template'] = input('frontend_' . $alias . '_template', 'default');
   $settings['frontend.' . $alias . '.title'] = input('frontend_' . $alias . '_title', 'Web-IM XI');
   $settings['frontend.' . $alias . '.description'] = input('frontend_' . $alias . '_description');
   $settings['frontend.' . $alias . '.keywords'] = input('frontend_' . $alias . '_keywords');
   $settings['frontend.' . $alias . '.copyright'] = input('frontend_' . $alias . '_copyright');
   $settings['backend.' . $alias . '.template'] = input('backend_' . $alias . '_template', 'default');
   $settings['backend.' . $alias . '.title'] = input('backend_' . $alias . '_title', 'Web-IM XI');
   $settings['backend.' . $alias . '.description'] = input('backend_' . $alias . '_description');
   $settings['backend.' . $alias . '.keywords'] = input('backend_' . $alias . '_keywords');
  }

  if (input('email_smtp_active', array('no', 'yes')) == 'yes') {
   $settings['email.smtp.host'] = input('email_smtp_host');
   $settings['email.smtp.port'] = input('email_smtp_port');
   $settings['email.smtp.user'] = input('email_smtp_user');
   $settings['email.smtp.pass'] = input('email_smtp_pass');
   $settings['email.smtp.secure'] = input('email_smtp_secure', array('', 'ssl', 'tls'));
   $settings['email.smtp.auth'] = input('email_smtp_auth', array('no', 'yes'));
  }

  return SysSettings::init()->saveAll('system', $settings)->forData();
 }

}