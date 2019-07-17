<?php
/**
 * @author Orhan POLAT
 */

namespace Admin;

use System\Media;
use Webim\View\Manager as View;

class ContentBuilder {

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
    $manager->addRoute($manager->prefix . '/content/builder', __CLASS__ . '::editor');
    $manager->addRoute($manager->prefix . '/content/builder/snippets', __CLASS__ . '::snippets');
    $manager->addRoute($manager->prefix . '/content/builder/media/?:role', __CLASS__ . '::media');

    static::$manager = $manager;
  }

  /**
   * Editor
   *
   * @param null|string $lang
   *
   * @return string
   */
  public function editor($params = array()) {
    $manager = static::$manager;

    $manager->set('title', lang('admin.label.editor', 'Editör'));

    return View::create('content.builder.editor')->data($manager::data())->render();
  }

  public function snippets($params = array()) {
    $manager = static::$manager;

    return View::create('content.builder.snippets')->data($manager::data())->render();
  }

  public function media($params = array()) {
    $manager = static::$manager;

    $role = array_get($params, 'role');

    if (is_null($role)) {
      $role = 'image';
    } else {
      $role = 'file';
    }

    $files = array();

    foreach (Media::init()->only('meta', array('role' => $role))->orderBy('id', 'desc')->load()->with('files', array(
      'poster' => array(
        'size' => '100x100',
        'default' => array(
          'file' => View::getPath()->folder('layouts.assets.poster')->file('file.png')
        )
      )
    ))->get('rows') as $row) {
      if ($row->file) {
        $file = new \stdClass();
        $file->title = $row->title;
        $file->src = $row->file->src();
        $file->poster = $row->poster->image->src();

        $files[] = $file;
      }
    }

    $manager->set('files', $files);

    return View::create('content.builder.media')->data($manager::data())->render();
  }

}