<?php
defined('WEBIM') or die('Dosya yok');

/**
 * @author Orhan POLAT
 */

use \System\Content;
use \System\Email;
use \Webim\App;
use \Webim\Library\Carbon;
use \Webim\Library\File;
use \Webim\Library\Message;
use \Webim\View\Manager as View;

App::make()->group((count(langs()) ? '(/:lang#(' . implode('|', array_keys(langs())) . ')#)?' : ''), function () {
  //Default path
  $path = File::path('views.frontend.' . conf('frontend.' . lang() . '.template', 'default'));

  //Set default path
  View::setPath($path);

  //Get menus
  $menus = Content::init()->only('language')->where('type', 'menu')->load()->with('meta')->get('rows');

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
    'copyright' => conf('frontend.' . lang() . '.copyright', 'Powered By Masters'),
    'nav' => $nav,
    'breadcrumb' => array()
  );

  //404
  $this->setNotFoundTemplate(function ($title, $body) use ($data) {
    $page = new \stdClass();
    $page->title = lang('label.error', 'Hata') . ': 404';
    $page->content = '<p class="text-center">' . lang('message.page_requested_not_found', 'Ulaşmaya çalıştığınız sayfa silinmiş ya da taşınmış olabilir. Lütfen bağlantı linklerini takip ediniz!') . '</p>'
      . '<p class="text-center"><a class="theme-btn default-btn" href="' . url('/') . '">' . lang('menu.home', 'Anasayfa') . '</a></p>';

    $data['page'] = $page;

    return View::create('404')->data($data)->render();
  });

  $this->get('/', function ($params = array()) use ($data) {
    $data['sliders'] = Content::published('news', 10, 'slider')->with('poster')->with(function ($rows) {
      return array_map(function (&$row) {
        if (isset($row->meta->options)) {
          $row->meta->options = json_decode($row->meta->options);
        }
      }, $rows);
    })->get('rows');

    $data['about'] = Content::published('page', null, null, function ($content) {
      $content->where('url', 'about');
    })->with('meta')->get('rows.0');

    $data['services'] = Content::published('news', 6, 'services')->with('meta')->with(function ($rows) {
      return array_map(function (&$row) {
        if (isset($row->meta->options)) {
          $row->meta->options = json_decode($row->meta->options);
        }
      }, $rows);
    })->get('rows');

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

  $this->get('/contact', function ($params = array()) use ($data) {
    $page = new \stdClass();
    $page->title = lang('menu.contact', 'İletişim');

    $data['page'] = $page;

    return View::create('contact')->data($data)->render();
  });

  $this->post('/contact', function ($params = array()) {
    $this->response->setContentType('json');

    $message = Message::result(lang('message.nothing_done', 'Herhangi bir işlem yapılmadı!'));

    if (strlen(input('name')) > 3) {
      if (filter_var(input('email'), FILTER_VALIDATE_EMAIL)) {
        if (strlen(input('message')) > 3) {
          //File
          $file = View::getPath()->folder('layouts.email')->file('contact.html');

          $send = Email::create('İletişim Formu Mesajı [' . conf('frontend.' . lang() . '.title', 'Web-IM XI') . ']')
            ->to(conf('email.from'), conf('email.from_name'))
            ->bcc('opolat@hotmail.com', 'Orhan POLAT')
            ->html($file, array(
              'title' => conf('frontend.' . lang() . '.title', 'Web-IM XI') . ' ' . lang('label.contact_form', 'İletişim Formu'),
              'date' => date_show(Carbon::now(), 'long', true),
              'ip' => $this->request->getClientIp(),
              'name' => input('name'),
              'email' => input('email'),
              'subject' => input('subject', 'Konusuz'),
              'message' => nl2br(input('message'))
            ))->send();

          if ($send->success()) {
            $message->success = true;
            $message->text = lang('message.message_sent', 'Mesajınız iletildi...');
          } else {
            $message->text = lang('message.message_not_sent', 'Mesajınız iletilemedi!');
          }
        } else {
          $message->text = lang('message.type_your_message', 'Mesajınızı yazın!');
        }
      } else {
        $message->text = lang('message.invalid_email_address', 'Geçersiz e-posta adresi!');
      }
    } else {
      $message->text = lang('message.type_your_name', 'İsminizi yazın!');
    }

    return $message->forData();
  });

  $this->post('/newsletter', function ($params = array()) {
    $this->response->setContentType('json');

    $message = Message::result(lang('message.nothing_done', 'Herhangi bir işlem yapılmadı!'));

    if (filter_var(input('email'), FILTER_VALIDATE_EMAIL)) {
      $save = Content::init()
        ->set('type', 'newsletter')
        ->set('language', lang())
        ->set('url', input('email'))
        ->set('title', input('email'))
        ->set('publish_date', Carbon::now())
        ->save();

      if ($save->success()) {
        $message->success = true;
        $message->text = lang('message.successfully_subscribed_our_newsletter', 'Bültenimize kaydınız alınmıştır...');
      } else {
        $message->text = lang('message.already_subscribed_our_newsletter', 'Bültenimizde e-posta adresiniz mevcut!');
      }
    } else {
      $message->text = lang('message.invalid_email_address', 'Geçersiz e-posta adresi!');
    }

    return $message->forData();
  });

  $this->get('/sitemap', function ($params = array()) use ($data) {
    $xml = array();

    $xml[] = '<?xml version="1.0" encoding="UTF-8"?>';
    $xml[] = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"'
      . '  xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">';

    foreach ($data['nav'] as $type => $menu) {
      foreach ($menu['items'] as $nav) {
        $xml[] = '<url>';
        $xml[] = '<loc>' . $nav->url . '</loc>';
        $xml[] = '</url>';
      }
    }

    $xml[] = '</urlset>';

    $this->response->setContentType('xml');

    return implode('', $xml);
  });

});