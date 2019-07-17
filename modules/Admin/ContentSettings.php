<?php
/**
 * @author Orhan POLAT
 */

namespace Admin;

use System\Settings as SysSettings;
use Webim\View\Manager as View;

class ContentSettings {

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
    $manager->addRoute(array(
      $manager->prefix . '/content',
      $manager->prefix . '/content/settings'
    ), __CLASS__ . '::getIndex');
    $manager->addRoute(array(
      $manager->prefix . '/content',
      $manager->prefix . '/content/settings'
    ), __CLASS__ . '::postIndex', 'POST');

    $parent = $manager->addMenu(lang('admin.menu.system', 'Sistem'), $manager->prefix . '/content', lang('admin.menu.content', 'İçerik'), null, 'fa fa-edit');
    $manager->addMenu(lang('admin.menu.system', 'Sistem'), $manager->prefix . '/content/settings', lang('admin.menu.settings', 'Ayarlar'), $parent, 'fa fa-cog');

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

    $manager->set('caption', lang('admin.menu.settings', 'Ayarlar'));
    $manager->breadcrumb($manager->prefix . '/content', lang('admin.menu.content', 'İçerik'));
    $manager->breadcrumb($manager->prefix . '/content/settings', lang('admin.menu.settings', 'Ayarlar'));

    return View::create('content.settings')->data($manager::data())->render();
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

    //Make settings part by part
    $contacts = array();
    $maps = array();
    $socials = array(
      'facebook' => input('social_facebook'),
      'twitter' => input('social_twitter'),
      'google_plus' => input('social_google_plus'),
      'linkedin' => input('social_linkedin'),
      'instagram' => input('social_instagram'),
      'youtube' => input('social_youtube'),
      'flickr' => input('social_flickr'),
      'pinterest' => input('social_pinterest'),
      'skype' => input('social_skype'),
      'vimeo' => input('social_vimeo'),
      'github' => input('social_github'),
      'whatsapp' => input('social_whatsapp')
    );
    $google = array(
      'map_key' => raw_input('google_map_key'),
      'search_console' => raw_input('google_search_console'),
      'analytics' => raw_input('google_analytics'),
      'recaptcha_site_key' => input('google_recaptcha_site_key'),
      'recaptcha_site_secret' => input('google_recaptcha_site_secret'),
      'adsense' => raw_input('google_adsense')
    );
    $oauth = array(
      'facebook' => array(
        'app_id' => input('facebook_app_id'),
        'app_secret' => input('facebook_app_secret')
      ),
      'twitter' => array(
        'api_key' => input('twitter_api_key'),
        'api_secret' => input('twitter_api_secret'),
        'access_token' => input('twitter_access_token'),
        'access_token_secret' => input('twitter_access_token_secret')
      )
    );

    foreach (input('contact_title') as $key => $contact) {
      $contacts[] = array(
        'title' => $contact,
        'name' => array_get(input('contact_name'), $key),
        'address' => array_get(input('contact_address'), $key),
        'phone' => array_get(input('contact_phone'), $key),
        'gsm' => array_get(input('contact_gsm'), $key),
        'fax' => array_get(input('contact_fax'), $key),
        'email' => array_get(input('contact_email'), $key),
        'web' => array_get(input('contact_web'), $key)
      );
    }

    foreach (input('map_title') as $key => $map) {
      $maps[] = array(
        'title' => $map,
        'name' => array_get(input('map_name'), $key),
        'lat' => array_get(input('map_lat'), $key),
        'lon' => array_get(input('map_lon'), $key),
        'zoom' => array_get(input('map_zoom'), $key),
        'marker_lat' => array_get(input('map_marker_lat'), $key),
        'marker_lon' => array_get(input('map_marker_lon'), $key),
        'marker_content' => array_get(raw_input('map_marker_content'), $key)
      );
    }

    //Settings container
    $settings = array(
      'contact' => $contacts,
      'map' => $maps,
      'social' => $socials,
      'google' => $google,
      'oauth' => $oauth,
      'html_editor' => input('html_editor', 'default')
    );

    //Remove first
    SysSettings::init()->remove('system', 'contact');
    SysSettings::init()->remove('system', 'map');

    return SysSettings::init()->saveAll('system', $settings)->forData();
  }

}