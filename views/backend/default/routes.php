<?php
defined('WEBIM') or die('Dosya yok!');

/**
 * @author Orhan POLAT
 */

use \Admin\Manager as Admin;
use \Webim\App;
use \Webim\View\Manager as View;

App::make()->group((count(langs()) ? '(/:lang#(' . implode('|', array_keys(langs())) . ')#)?' : ''), function () {
 //Init
 Admin::init()->yieldRoutes();

 //404
 $this->setNotFoundTemplate(function ($title, $body) {
  return View::create('404')->data(Admin::data())->render();
 });

 //500
 $this->setErrorTemplate(function ($title, $body) {
  return View::create('500')->data(Admin::data())->with('content', $body)->render();
 });
});