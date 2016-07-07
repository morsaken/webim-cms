<?php
/**
 * @author Orhan POLAT
 */

namespace Admin;

use \System\Settings as SysSettings;
use \Webim\View\Manager as View;

class ContentSettings {

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
   $manager->prefix . '/content',
   $manager->prefix . '/content/settings'
  ), __CLASS__ . '::getIndex');
  $manager->addRoute($manager->prefix . '/content/settings', __CLASS__ . '::postIndex', 'POST');

  $parent = $manager->addMenu(lang('admin.menu.system', 'Sistem'), $manager->prefix . '/content', lang('admin.menu.content', 'İçerik'), null, 'fa fa-edit');
  $manager->addMenu(lang('admin.menu.system', 'Sistem'), $manager->prefix . '/content/settings', lang('admin.menu.settings', 'Ayarlar'), $parent, 'fa fa-cog');

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

  $manager->set('caption', lang('admin.menu.settings', 'Ayarlar'));
  $manager->breadcrumb($manager->prefix . '/content', lang('admin.menu.content', 'İçerik'));
  $manager->breadcrumb($manager->prefix . '/content/settings', lang('admin.menu.settings', 'Ayarlar'));

  return View::create('content.settings')->data($manager::data())->render();
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
   'contact.address_title1' => input('contact_address_title1'),
   'contact.address_title2' => input('contact_address_title2'),
   'contact.address1' => input('contact_address1'),
   'contact.address2' => input('contact_address2'),
   'contact.phone1' => input('contact_phone1'),
   'contact.phone2' => input('contact_phone2'),
   'contact.fax1' => input('contact_fax1'),
   'contact.fax2' => input('contact_fax2'),
   'contact.email1' => input('contact_email1'),
   'contact.email2' => input('contact_email2'),
   'contact.web1' => input('contact_web1'),
   'contact.web2' => input('contact_web2'),
   'map.key' => input('map_key', ''),
   'map.geo_lat' => input('map_geo_lat', 0.0),
   'map.geo_lon' => input('map_geo_lon', 0.0),
   'map.marker_geo_lat' => input('map_marker_geo_lat', input('map_geo_lat', 0.0)),
   'map.marker_geo_lon' => input('map_marker_geo_lon', input('map_geo_lon', 0.0)),
   'map.marker_content' => raw_input('map_marker_content'),
   'social.facebook' => input('social_facebook'),
   'social.twitter' => input('social_twitter'),
   'social.google_plus' => input('social_google_plus'),
   'social.linkedin' => input('social_linkedin'),
   'social.instagram' => input('social_instagram'),
   'social.youtube' => input('social_youtube'),
   'social.flickr' => input('social_flickr'),
   'social.pinterest' => input('social_pinterest'),
   'social.skype' => input('social_skype'),
   'social.vimeo' => input('social_vimeo'),
   'social.github' => input('social_github'),
   'google.search_console' => raw_input('google_search_console'),
   'google.analytics' => raw_input('google_analytics'),
   'google.adsense' => raw_input('google_adsense')
  );

  return SysSettings::init()->saveAll('system', $settings)->forData();
 }

}