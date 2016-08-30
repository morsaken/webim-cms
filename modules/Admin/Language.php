<?php
/**
 * @author Orhan POLAT
 */

namespace Admin;

use \Webim\Library\File;
use \Webim\Library\Input;
use \Webim\Library\Language as Lang;
use \Webim\Library\Message;
use \Webim\View\Manager as View;

class Language {

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
  $manager->addRoute($manager->prefix . '/content/languages(/:alias#[a-z]{2}#)?', __CLASS__ . '::getIndex');
  $manager->addRoute($manager->prefix . '/content/languages(/:alias#[a-z]{2}#)?', __CLASS__ . '::postIndex', 'POST');
  $manager->addRoute($manager->prefix . '/content/languages(/:alias#[a-z]{2}#)?', __CLASS__ . '::deleteIndex', 'DELETE');
  $manager->addRoute($manager->prefix . '/content/languages/create', __CLASS__ . '::create', 'POST');
  $manager->addRoute($manager->prefix . '/content/languages/crawl/:alias+', __CLASS__ . '::crawl', 'POST');

  $parent = $manager->addMenu(lang('admin.menu.system', 'Sistem'), $manager->prefix . '/content', lang('admin.menu.content', 'İçerik'), null, 'fa fa-edit');
  $manager->addMenu(lang('admin.menu.system', 'Sistem'), $manager->prefix . '/content/languages', lang('admin.menu.languages', 'Diller'), $parent, 'fa fa-language');

  static::$manager = $manager;
 }

 /**
  * Index
  *
  * @param null|string $lang
  * @param null|string $alias
  *
  * @return string
  */
 public function getIndex($lang = null, $alias = null) {
  $manager = static::$manager;

  if (is_null($alias) || !Lang::has($alias)) {
   $alias = lang();
  }

  $manager->set('current', $alias);

  $main = File::path('language.' . $alias, $alias . EXT);
  $list = array_get(Lang::getVars(), $alias, array());
  $info = array();

  if ($main->exists()) {
   foreach (array_dot($main->load()) as $key => $value) {
    array_set($info, $alias . '.' . $key, $value);
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
  * @param null|string $lang
  * @param null|string $alias
  *
  * @return string
  */
 public function postIndex($lang = null, $alias = null) {
  $manager = static::$manager;

  if (is_null($alias) || !Lang::has($alias)) {
   $alias = lang();
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

   $written += File::path('language.' . $alias, $key . EXT)->create()->write($text);
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
  * @param null|string $lang
  * @param null|string $alias
  *
  * @return string
  */
 public function deleteIndex($lang = null, $alias = null) {
  $manager = static::$manager;

  $manager->app->response->setContentType('json');

  if (is_null($alias) || !Lang::has($alias)) {
   $alias = lang();
  }

  $message = Message::result(lang('message.nothing_done', 'Herhangi bir işlem yapılmadı!'));

  if (conf('default.language') !== $alias) {
   if (File::path('language.' . $alias)->remove()) {
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
  * @param null|string $lang
  *
  * @return string
  */
 public function create($lang = null) {
  $manager = static::$manager;

  $manager->app->response->setContentType('json');

  $message = Message::result(lang('message.nothing_done', 'Herhangi bir işlem yapılmadı!'));

  $alias = str_case(input('language-alias'), 'lower');
  $abbr = input('language-abbr');
  $name = input('language-name');

  if (strlen($alias) == 2) {
   if (!Lang::has($alias)) {
    $values = array(
     'abbr' => $abbr,
     'name' => $name,
     'charset' => 'utf-8',
     'dir' => 'ltr',
     'order' => (count(langs()) + 1),
     'locale' => $alias . '_' . str_case($alias),
     'time_zone' => 'Europe/Istanbul'
    );

    $text = '<?php' . "\n" . 'return ' . var_export($values, true) . ';';

    $written = File::path('language.' . $alias, $alias . EXT)->create()->write($text);

    if ($written > 0) {
     $message->success = true;
     $message->text = lang('admin.message.new_language_created', 'Yeni dil oluşturuldu...');
     $message->return = array(
      'alias' => $alias
     );
    } else {
     $message->text = lang('admin.message.new_language_cannot_created', 'Yeni dil kaydı oluşturulamadı!');
    }
   } else {
    $message->text = lang('admin.message.language_already_exists', 'Bu şekilde bir dil kaydı var!');
   }
  } else {
   $message->text = lang('admin.message.language_alias_must_be_two_characters_long', 'Dil adı iki karakter olmalı!');
  }

  return $message->forData();
 }

 /**
  * Crawling language usages
  *
  * @param null|string $lang
  * @param string $alias
  *
  * @return string
  */
 public function crawl($lang = null, $alias) {
  $manager = static::$manager;

  $manager->app->response->setContentType('json');

  $list = array();
  $values = array();

  foreach (explode(',', input('paths')) as $path) {
   $fullPath = (($path == 'modules') ? 'modules' : 'views.' . $path . '.' . conf($path . '.' . $alias . '.template', 'default'));

   $values = array_merge_distinct($values, Lang::crawl(File::path($fullPath)));
  }

  foreach ($values as $key => $values) {
   $list[$key] = array_dot($values);
  }

  return array_to($list);
 }

}