<?php
defined('WEBIM') or die('Dosya yok');

/**
 * @author Orhan POLAT
 */

use \System\Content;
use \Webim\App;
use \Webim\Library\File;
use \Webim\View\Manager as View;

App::make()->group((count(langs()) ? '(/:lang#(' . implode('|', array_keys(langs())) . ')#)?' : ''), function () {
  //Default path
  $path = File::path('views.frontend.' . conf('frontend.' . lang() . '.template', 'default'));

  //Set default path
  View::setPath($path);

  //Get menus
  $menus  = Content::init()->only('language')->where('type', 'menu')->load()->with('meta')->get('rows');

  //Page all nav
  $nav = array();

  foreach ($menus as $menu) {
    $nav[$menu->url] = array(
      'title' => $menu->title,
      'items' => menu($menu->meta->items)
    );
  }

  //Common conf
  $data = array(
    'root' => View::getPath()->folder('layouts')->src(),
    'title' => conf('frontend.' . lang() . '.title', 'Web-IM XI'),
    'description' => conf('frontend.' . lang() . '.description', 'Web Internet Manager'),
    'keywords' => conf('frontend.' . lang() . '.keywords'),
    'separator' => '::',
    'nav' => $nav,
    'breadcrumb' => array()
  );

  $this->get('/', function ($params = array()) use ($data) {
    return View::create('home')->data($data)->render();
  });

  //Pages
  $pages = Content::published('page')->with('meta')->with('media')->with('tags')->with('fullUrl')->get('rows');

  foreach ($pages as $page) {
    $this->get('/' . $page->full_url, function ($params = array()) use ($data, $pages, $page) {
      $data['breadcrumb'] = crumb($data['breadcrumb'], array(
        array(lang('menu.home', 'Anasayfa'), '/'),
        array(lang('menu.' . $page->url, $page->title), $page->url)
      ));

      $page->description = $page->meta->description;
      $page->content = $page->meta->content;

      if (count($page->tags)) {
        $data['keywords'] = implode(', ', $page->tags);
      }

      if (strlen($page->description)) {
        $data['description'] = $page->description;
      }

      $data['page'] = $page;
      $data['pages'] = makePageMenu($page, $pages);

      //View file
      $file = 'content';

      if ($this->request->isAjax()) {
        $file = 'ajax.' . $file;
      }

      return View::create($file)->data($data)->render();
    });
  }

  $this->get('/download', function($params = array()) {
    $file = View::getPath()->folder('layouts.assets')->file('12_dev_adam.mp3');

    if ($file->exists()) {
      $download = $file->download('Ceza - 12 Dev Adam');

      $this->response->setHeaders($download->headers);

      return $download->content;
    } else {
      return $this->notFound();
    }
  });

});