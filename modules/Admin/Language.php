<?php
/**
 * @author Orhan POLAT
 */

namespace Admin;

use Webim\Library\File;
use Webim\Library\Input;
use Webim\Library\Language as Lang;
use Webim\Library\Message;
use Webim\View\Manager as View;

class Language {

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
    $manager->addRoute($manager->prefix . '/content/languages(/:code#[a-zA-Z\-]{2,5}#)?', __CLASS__ . '::getIndex');
    $manager->addRoute($manager->prefix . '/content/languages(/:code#[a-zA-Z\-]{2,5}#)?', __CLASS__ . '::postIndex', 'POST');
    $manager->addRoute($manager->prefix . '/content/languages(/:code#[a-zA-Z\-]{2,5}#)?', __CLASS__ . '::deleteIndex', 'DELETE');
    $manager->addRoute($manager->prefix . '/content/languages/create', __CLASS__ . '::create', 'POST');
    $manager->addRoute($manager->prefix . '/content/languages/crawl/:code+', __CLASS__ . '::crawl', 'POST');

    $parent = $manager->addMenu(lang('admin.menu.system', 'Sistem'), $manager->prefix . '/content', lang('admin.menu.content', 'İçerik'), null, 'fa fa-edit');
    $manager->addMenu(lang('admin.menu.system', 'Sistem'), $manager->prefix . '/content/languages', lang('admin.menu.languages', 'Diller'), $parent, 'fa fa-language');

    static::$manager = $manager;
  }

  /**
   * Index
   *
   * @param array $params
   *
   * @return string
   */
  public function getIndex($params = array()) {
    $manager = static::$manager;

    $code = array_get($params, 'code');

    if (is_null($code) || !Lang::has($code)) {
      $code = lang();
    }

    $manager->set('current', $code);

    $main = File::path('language.' . $code, $code . EXT);
    $list = array_get(Lang::getVars(), $code, array());
    $info = array();

    if ($main->exists()) {
      foreach (array_dot($main->load()) as $key => $value) {
        array_set($info, $code . '.' . $key, $value);
        array_forget($list, $key);
      }

      function removeEmptyItems(&$item) {
        if (is_array($item) && $item) {
          $item = array_filter($item, __FUNCTION__);
        }

        return !!$item;
      }

      removeEmptyItems($list);
    }

    $manager->set('caption', lang('admin.menu.languages', 'Diller'));
    $manager->breadcrumb($manager->prefix . '/content', lang('admin.menu.content', 'İçerik'));
    $manager->breadcrumb($manager->prefix . '/content/languages', lang('admin.menu.languages', 'Diller'));
    $manager->set('list', $info + $list);

    return View::create('content.languages')->data($manager->data())->render();
  }

  /**
   * Index savings
   *
   * @param array $params
   *
   * @return string
   */
  public function postIndex($params = array()) {
    $manager = static::$manager;

    $code = array_get($params, 'code');

    if (is_null($code) || !Lang::has($code)) {
      $code = lang();
    }

    $manager->app->response->setContentType('json');

    //Return
    $message = Message::result(lang('message.nothing_done', 'Herhangi bir işlem yapılmadı!'));

    //Get strings into an array
    $strs = array();

    foreach (Input::all() as $key => $value) {
      array_set($strs, str_replace('>', '.', $key), $value);
    }

    //Total written as byte
    $written = 0;

    foreach ($strs as $key => $values) {
      $text = '<?php' . "\n" . 'return ' . var_export($values, true) . ';';

      $written += File::path('language.' . $code, $key . EXT)->create()->write($text);
    }

    if ($written > 0) {
      $message->success = true;
      $message->text = lang('message.saved', 'Kaydedildi...');
    }

    return $message->forData();
  }

  /**
   * Delete language content
   *
   * @param array $params
   *
   * @return string
   */
  public function deleteIndex($params = array()) {
    $manager = static::$manager;

    $manager->app->response->setContentType('json');

    $code = array_get($params, 'code');

    if (is_null($code) || !Lang::has($code)) {
      $code = lang();
    }

    $message = Message::result(lang('message.nothing_done', 'Herhangi bir işlem yapılmadı!'));

    if (conf('default.language') !== $code) {
      if (File::path('language.' . $code)->remove()) {
        $message->success = true;
        $message->text = lang('admin.message.language_deleted', 'Dil silindi...');
      }
    } else {
      $message->text = lang('admin.message.default_language_cannot_delete', 'Varsayılan dil silinemez!');
    }

    return $message->forData();
  }

  /**
   * Creates new language
   *
   * @param array $params
   *
   * @return string
   */
  public function create($params = array()) {
    $manager = static::$manager;

    $manager->app->response->setContentType('json');

    $message = Message::result(lang('message.nothing_done', 'Herhangi bir işlem yapılmadı!'));

    $code = slug(input('language-code'));
    $abbr = input('language-abbr');
    $name = input('language-name');
    $native = input('language-native', $name);

    if (strlen($code) >= 2) {
      if (!Lang::has($code)) {
        $values = array(
          'abbr' => $abbr,
          'name' => $name,
          'native' => $native,
          'charset' => 'utf-8',
          'dir' => 'ltr',
          'order' => (count(langs()) + 1),
          'locale' => $code . '_' . $code,
          'time_zone' => 'Europe/Istanbul'
        );

        $text = '<?php' . "\n" . 'return ' . var_export($values, true) . ';';

        $written = File::path('language.' . $code, $code . EXT)->create()->write($text);

        if ($written > 0) {
          $message->success = true;
          $message->text = lang('admin.message.new_language_created', 'Yeni dil oluşturuldu...');
          $message->return = array(
            'code' => $code
          );
        } else {
          $message->text = lang('admin.message.new_language_cannot_created', 'Yeni dil kaydı oluşturulamadı!');
        }
      } else {
        $message->text = lang('admin.message.language_already_exists', 'Bu şekilde bir dil kaydı var!');
      }
    } else {
      $message->text = lang('admin.message.language_code_must_be_at_least_two_characters', 'Dil kodu en az iki karakter olmalı!');
    }

    return $message->forData();
  }

  /**
   * Crawling language usages
   *
   * @param array $params
   *
   * @return string
   */
  public function crawl($params = array()) {
    $manager = static::$manager;

    $manager->app->response->setContentType('json');

    $code = array_get($params, 'code');
    $list = array();
    $values = array();

    foreach (explode(',', input('paths')) as $path) {
      $fullPath = (($path == 'modules') ? 'modules' : 'views.' . $path . '.' . conf($path . '.' . $code . '.template', 'default'));

      $values = array_merge_distinct($values, Lang::crawl(File::path($fullPath)));
    }

    foreach ($values as $key => $value) {
      $list[$key] = is_array($value) ? array_dot($value) : $value;
    }

    return array_to($list);
  }

}