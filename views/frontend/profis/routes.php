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
    'template' => 'default',
    'separator' => '::',
    'nav' => $nav,
    'breadcrumb' => array(),
    'about' => Content::published('page', null, null, function($content) {
      $content->where('name', 'about');
    })->with('meta')->get('rows.0'),
    'slogan' => Content::published('page', null, null, function($content) {
      $content->where('name', 'slogan');
    })->with('meta')->get('rows.0'),
    'product_categories' => Content::published('category', null, null, function($content) {
      $content->only('parent.name', 'products');
    })->with('poster', array(
      'default' => View::getPath()->folder('layouts.assets.default')->file('poster.jpg')
    ))->with(function ($rows) {
      return array_map(function (&$row) {
        if (isset($row->meta->options)) {
          $row->meta->options = json_decode($row->meta->options);
        }
      }, $rows);
    })->get('rows')
  );

  //500
  $this->setErrorTemplate(function ($title, $body) use ($data) {
    $page = new \stdClass();
    $page->status = 500;
    $page->title = lang('label.system_error', 'Sistem Hatası');
    $page->content = $body;

    $data['page'] = $page;

    if ($this->request->isAjax()) {
      $data['template'] = 'empty';
    }

    return View::create('error')->data($data)->render();
  });

  //404
  $this->setNotFoundTemplate(function ($title, $body) use ($data) {
    $page = new \stdClass();
    $page->status = 404;
    $page->title = lang('label.page_not_found', 'Sayfa bulunamadı');
    $page->content = '<p>' . lang('message.page_not_found', 'Ulaşmaya çalıştığınız sayfa silinmiş ya da taşınmış olabilir. Lütfen bağlantı linklerini takip ediniz!') . '</p>'
      . '<p><a class="btn" href="' . url('/') . '">' . lang('menu.home', 'Anasayfa') . '</a></p>';

    $data['page'] = $page;

    if ($this->request->isAjax()) {
      $data['template'] = 'empty';
    }

    return View::create('error')->data($data)->render();
  });

  $this->get('/', function ($params = array()) use ($data) {
    //Banner
    $data['slides'] = Content::published('news', 10, 'slide')->with('poster', array(
      'default' => View::getPath()->folder('layouts.assets.default')->file('poster.jpg'),
      'source' => true
    ))->get('rows');

    if ($this->request->isAjax()) {
      $data['template'] = 'empty';
    }

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

      if ($this->request->isAjax()) {
        $data['template'] = 'empty';
      }

      return View::create('content')->data($data)->render();
    });
  }

  $this->get('/products/category/:category+/?:sub_category', function($params = array()) use ($data) {
    $page = new \stdClass();
    $page->title = lang('menu.products', 'Ürünler');

    $data['page'] = $page;

    if ($this->request->isAjax()) {
      $data['template'] = 'empty';
    }

    $category = null;
    $sub_category = null;

    foreach ($data['product_categories'] as $product_category) {
      if ($product_category->url === array_get($params, 'category')) {
        $category = $product_category;
      }
    }

    if ($category) {
      if (array_get($params, 'sub_category')) {
        $sub_category = Content::published('category', null, null, function($content) use ($params) {
          $content->where('url', array_get($params, 'sub_category'));
        })->get('rows.0');

        if ($sub_category) {
          $data['page']->title = $sub_category->title;
          $data['products'] = Content::published('product', null, $sub_category->id)->with('poster', array(
            'default' => View::getPath()->folder('layouts.assets.default')->file('poster.jpg')
          ))->get('rows');

          return View::create('product.list')->data($data)->render();
        }

        return $this->notFound();
      }

      $data['page']->title = $category->title;
      $data['parent_category'] = $category;
      $data['categories'] = Content::published('category', null, null, function($content) use ($category) {
        $content->only('parent', $category->url);
      })->with('poster', array(
        'default' => View::getPath()->folder('layouts.assets.default')->file('poster.jpg')
      ))->get('rows');

      return View::create('product.categories')->data($data)->render();
    }

    return $this->notFound();
  });

  $this->get('/products/:slug+', function ($params = array()) use ($data) {
    if ($this->request->isAjax()) {
      $data['template'] = 'empty';
    }

    $product = Content::published('product', null, null, function($content) use ($params) {
      $content->where('url', array_get($params, 'slug'));
    })->with('meta')->with('category')->with('media')->get('rows.0');

    if ($product) {
      $page = new \stdClass();
      $page->title = $product->title;

      $data['page'] = $page;
      $data['product'] = $product;

      return View::create('product.view')->data($data)->render();
    }

    return $this->notFound();
  });

  $this->get('/contact', function ($params = array()) use ($data) {
    $page = new \stdClass();
    $page->title = lang('menu.contact', 'İletişim');

    $data['page'] = $page;
    $data['breadcrumb'] = crumb($data['breadcrumb'], array(
      array(lang('menu.home', 'Anasayfa'), '/'),
      array($page->title)
    ));

    if ($this->request->isAjax()) {
      $data['template'] = 'empty';
    }

    return View::create('contact')->data($data)->render();
  });

  $this->post('/contact', function($params = array()) {
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
              'title' => conf('frontend.' . lang() . '.title', 'Web-IM XI') . ' '. lang('label.contact_form', 'İletişim Formu'),
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

});