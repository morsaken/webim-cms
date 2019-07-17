<?php
defined('WEBIM') or die('Dosya yok!');

/**
 * @author Orhan POLAT
 */

use System\Access;
use System\Browser;
use System\Settings;
use Webim\App;
use Webim\Library\Auth;
use Webim\Library\Carbon;
use Webim\Library\File;

App::make(array(
  'mode' => 'development',
  'encryption_key' => 'O9s_lWeIn7cOL0M]S6Xg4aR^GwovA&UN',
  'root' => PUB_ROOT,
  'ext' => EXT
))->with(array('lang', 'cache', 'db'))->with(function () {
  Settings::init()->setGlobalConf();
  Access::write();

  $this->browser = new Browser();
})->with(function () {
  //Get language
  $lang = lang();
  $admin = $this->request->segment(1);

  if ($this->request->segment(1) === $lang) {
    $admin = $this->request->segment(2);
  }

  if (conf('default.admin.ui', false) && ($admin === conf('default.admin.extension', 'admin'))) {
    File::path('views.backend.' . conf('backend.' . $lang . '.template', 'default'), 'routes' . EXT)->load();
  } else {
    //Default
    $template = 'default';

    //Check published
    $published = Carbon::createFromTimestamp(
      strtotime(conf('system.' . $lang . '.publish_date'))
    )->isPast();

    if ($published || Auth::current()->isAdmin()) {
      $template = conf('frontend.' . $lang . '.template', 'default');
    }

    File::path('views.frontend.' . $template, 'routes' . EXT)->load();
  }
})->run();