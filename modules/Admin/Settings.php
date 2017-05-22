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
    $manager->addRoute(array(
      $manager->prefix . '/system',
      $manager->prefix . '/system/settings'
    ), __CLASS__ . '::postIndex', 'POST');

    $parent = $manager->addMenu(lang('admin.menu.system', 'Sistem'), $manager->prefix . '/system', lang('admin.menu.system', 'Sistem'), null, 'fa fa-cogs');
    $manager->addMenu(lang('admin.menu.system', 'Sistem'), $manager->prefix . '/system/settings', lang('admin.menu.settings', 'Ayarlar'), $parent, 'fa fa-cog');

    static::$manager = $manager;
  }

  /**
   * Get
   *
   * @param array $params
   *
   * @return string
   */
  public function getIndex($params = array()) {
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
   * @param array $params
   *
   * @return string
   */
  public function postIndex($params = array()) {
    $manager = static::$manager;

    $manager->app->response->setContentType('json');

    $settings = array(
      'system.name' => input('system_name'),
      'system.admin' => input('system_admin'),
      'backend.session.timeout_active' => input('backend_session_timeout_active', 'no'),
      'backend.session.timeout_after' => intval(input('backend_session_timeout_after')),
      'email.from' => input('email_from'),
      'email.from_name' => input('email_from_name'),
      'email.smtp.active' => input('email_smtp_active', array('no', 'yes'))
    );

    foreach (langs() as $code => $lang) {
      $settings['system.' . $code . '.publish_date'] = input('system_' . $code . '_publish_date');
      $settings['system.' . $code . '.offline_message'] = input('system_' . $code . '_offline_message');
      $settings['frontend.' . $code . '.template'] = input('frontend_' . $code . '_template', 'default');
      $settings['frontend.' . $code . '.title'] = input('frontend_' . $code . '_title', 'Web-IM XI');
      $settings['frontend.' . $code . '.description'] = input('frontend_' . $code . '_description');
      $settings['frontend.' . $code . '.keywords'] = input('frontend_' . $code . '_keywords');
      $settings['frontend.' . $code . '.copyright'] = input('frontend_' . $code . '_copyright');
      $settings['backend.' . $code . '.template'] = input('backend_' . $code . '_template', 'default');
      $settings['backend.' . $code . '.title'] = input('backend_' . $code . '_title', 'Web-IM XI');
      $settings['backend.' . $code . '.description'] = input('backend_' . $code . '_description');
      $settings['backend.' . $code . '.keywords'] = input('backend_' . $code . '_keywords');
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