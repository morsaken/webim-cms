<?php
defined('WEBIM') or die('Dosya yok!');

/**
 * @author Orhan POLAT
 */

use \System\Access;
use \System\Settings;
use \Webim\App;
use \Webim\Library\Auth;
use \Webim\Library\Carbon;
use \Webim\Library\File;
use \Webim\Library\Language;

App::make(array(
 'mode' => 'development',
 'root' => PUB_ROOT,
 'ext' => EXT
))->with('lang')->with('cache')->with('db')->with(function() {
 Settings::init()->setGlobalConf();
 Access::write();
})->with(function() {
 //Get language
 $lang = Language::current()->alias();
 $segment = $this->request->segment(1);

 if ($this->request->segment(1) === $lang) {
  $segment = $this->request->segment(2);
 }

 if (conf('default.admin.ui', false) && ($segment === conf('default.admin.extension', 'admin'))) {
  File::path('views.backend.' . conf('backend.' . $lang . '.template', 'default'), 'routes' . EXT)->load();
 } else {
  //Default
  $template = 'default';

  //Check published
  $published = Carbon::createFromTimestamp(
   strtotime(conf('system.publish_date') . ' ' . conf('system.publish_hour'))
  )->isPast();

  if ($published || Auth::current()->isAdmin()) {
   $template = conf('frontend.' . $lang . '.template', 'default');
  }

  File::path('views.frontend.' . $template, 'routes' . EXT)->load();
 }
})->run();