<?php
/**
 * @author Orhan POLAT
 */

namespace Other\Admin;

use \Admin\Manager;
use \System\Settings as SysSettings;
use Webim\Library\File;
use \Webim\View\Manager as View;

class Slider {

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
    $manager->addRoute($manager->prefix . '/slider', __CLASS__ . '::getForm');
    $manager->addRoute($manager->prefix . '/slider', __CLASS__ . '::postForm', 'POST');
    $manager->addRoute($manager->prefix . '/slider/folders', __CLASS__ . '::getFolders');

    $manager->addMenu(lang('admin.menu.modules', 'Modüller'), $manager->prefix . '/slider', lang('label.slider', 'Slider'), null, 'fa fa-tree');

    static::$manager = $manager;
  }

  public function getForm($params = array()) {
    $manager = static::$manager;

    $manager->set('caption', lang('label.slider', 'Slider'));
    $manager->breadcrumb($manager->prefix . '/slider', lang('label.slider', 'Slider'));

    $manager::put('templates', File::in('views.frontend')->folders());

    return View::create('modules.others.slider')->data($manager::data())->render();
  }

  public function postForm($params = array()) {
    $manager = static::$manager;

    $manager->app->response->setContentType('json');

    //Settings container
    $slider = array(
      'slider.' . input('language', lang()) => array(
        'html' => raw_input('slider-html'),
        'css' => raw_input('slider-css'),
        'js' => raw_input('slider-js')
      )
    );

    //Remove first
    SysSettings::init()->remove('system', 'slider.' . input('language', lang()));

    return SysSettings::init()->saveAll('system', $slider)->forData();
  }

  public function getFolders($params = array()) {
    $manager = static::$manager;

    $manager->app->response->setContentType('json');

    $template = input('template', 'default');
    $root = File::in('views.frontend.' . $template . '.layouts');

    $list = array(
      array(
        'path' => '.',
        'type' => 'folder',
        'name' => '.',
        'size' => null,
        'permissions' => null
      )
    );

    foreach ($root->filesAndFolders() as $path => $item) {
      $list[] = array(
        'path' => str_replace($root->info('rawPath') . '.', '', $item->info('rawPath')),
        'type' => $item->info('type'),
        'name' => $item->info('name'),
        'size' => $item->size(),
        'perms' => $item->info('perms')
      );
    }

    return array_to($list);
  }
}