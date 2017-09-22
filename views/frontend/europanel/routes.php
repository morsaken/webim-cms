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
    'nav' => $nav,
    'copyright' => conf('frontend.' . lang() . '.copyright', 'Powered By Masters'),
    'breadcrumb' => array(),
    'about' => Content::published('page', null, null, function($content) {
      $content->where('url', 'hakkimizda');
    })->with('meta')->get('rows.0'),
    'slogan' => Content::published('page', null, null, function($content) {
      $content->where('url', 'slogan');
    })->with('meta')->get('rows.0')
  );

  //404
  $this->setNotFoundTemplate(function ($title, $body) use ($data) {
    $page = new \stdClass();
    $page->title = lang('label.error', 'Hata') . ': 404';
    $page->content = '<h1 class="text-center"><i class="fa fa-warning"></i> 404 ' . lang('message.page_not_found', 'Sayfa bulunamadı!') . '</h1>'
      . '<p class="text-center">' . lang('message.page_requested_not_found', 'Ulaşmaya çalıştığınız sayfa silinmiş ya da taşınmış olabilir. Lütfen bağlantı linklerini takip ediniz!') . '</p>'
      . '<p class="text-center"><a class="pop btn btn-danger" href="' . url('/') . '">' . lang('menu.home', 'Anasayfa') . '</a></p>';

    $data['page'] = $page;
    $data['breadcrumb'] = crumb($data['breadcrumb'], array(
      array(lang('menu.home', 'Anasayfa'), '/'),
      array(lang('menu.error', $page->title))
    ));

    //View file
    $file = '404';

    if ($this->request->isAjax()) {
      $file = 'ajax.' . $file;
    }

    return View::create($file)->data($data)->render();
  });

  $this->get('/', function ($params = array()) use ($data) {
    $data['slides'] = Content::published('news', 10, 'slider')->with('poster', array(
      'default' => View::getPath()->folder('layouts.assets.default')->file('poster.jpg')
    ))->get('rows');

    $data['products']['popular'] = Content::published('product', 10, 'popular')->with('poster', array(
      'default' => View::getPath()->folder('layouts.assets.default')->file('poster.jpg')
    ))->with('category')->with(function ($rows) {
      return array_map(function (&$row) {
        $categories = array();

        foreach ($row->category as $category) {
          if (!in_array($category->url, array('popular', 'showcase', 'picks'))) {
            $categories[] = $category;
          }
        }

        $row->category = $categories;
      }, $rows);
    })->get('rows');

    $data['products']['showcase'] = Content::published('product', 10, 'showcase')->with('poster', array(
      'default' => View::getPath()->folder('layouts.assets.default')->file('poster.jpg')
    ))->with('category')->with(function ($rows) {
      return array_map(function (&$row) {
        $categories = array();

        foreach ($row->category as $category) {
          if (!in_array($category->url, array('popular', 'showcase', 'picks'))) {
            $categories[] = $category;
          }
        }

        $row->category = $categories;
      }, $rows);
    })->get('rows');

    $data['products']['picks'] = Content::published('product', 10, 'picks')->with('poster', array(
      'default' => View::getPath()->folder('layouts.assets.default')->file('poster.jpg')
    ))->with('category')->with(function ($rows) {
      return array_map(function (&$row) {
        $categories = array();

        foreach ($row->category as $category) {
          if (!in_array($category->url, array('popular', 'showcase', 'picks'))) {
            $categories[] = $category;
          }
        }

        $row->category = $categories;
      }, $rows);
    })->get('rows');

    //View file
    $file = 'home';

    if ($this->request->isAjax()) {
      $file = 'ajax.' . $file;
    }

    return View::create($file)->data($data)->render();
  });

  //Pages
  $pages = Content::published('page')->with('meta')->with('media')->with('fullUrl')->get('rows');

  foreach ($pages as $page) {
    $this->get('/' . $page->full_url, function ($params = array()) use ($data, $pages, $page) {
      $data['breadcrumb'] = crumb($data['breadcrumb'], array(
        array(lang('menu.home', 'Anasayfa'), '/'),
        array(lang('menu.' . $page->url, $page->title), $page->url)
      ));

      $page->description = $page->meta->description;
      $page->content = $page->meta->content;

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

  $this->get('/duvar-panelleri/?:slug', function ($params = array()) use ($data) {
    $page = new \stdClass();
    $page->title = lang('menu.catalog', 'Duvar Panelleri Koleksiyonu');

    $data['breadcrumb'] = crumb($data['breadcrumb'], array(
      array(lang('menu.home', 'Anasayfa'), '/'),
      array(lang('menu.catalog', 'Duvar Panelleri Koleksiyonu'), 'duvar-panelleri')
    ));

    $slug = array_get($params, 'slug');

    if (!is_null($slug)) {
      $item = Content::published('product', null, null, function ($content) use ($slug) {
        $content->where('url', $slug);
      })->with('poster', array(
        'default' => View::getPath()->folder('layouts.assets.default')->file('poster.jpg')
      ))->with('media')->with('category')->with(function ($rows) {
        return array_map(function (&$row) {
          $categories = array();

          foreach ($row->category as $category) {
            if (!in_array($category->url, array('popular', 'showcase', 'picks'))) {
              $categories[] = $category;
            }
          }

          $row->category = $categories;
        }, $rows);
      })->with('formFields')->get('rows.0');

      if ($item) {
        $page->title = $item->title;

        $data['page'] = $page;
        $data['product'] = $item;
        $data['breadcrumb'] = crumb($data['breadcrumb'], $item->title, $item->url);
        $data['offers'] = Content::published('page', null, null, function($content) {
          $content->where('url', 'offers');
        })->with('meta')->get('rows.0.meta.content');
        $data['faq'] = Content::published('page', null, null, function($content) {
          $content->where('url', 'faq');
        })->with('meta')->get('rows.0.meta.content');

        //View file
        $file = 'catalog.view';

        if ($this->request->isAjax()) {
          $file = 'ajax.' . $file;
        }

        return View::create($file)->data($data)->render();
      } else {
        return $this->notFound();
      }
    } else {
      //Filters
      $filters = array(
        'texture' => array(),
        'color' => array(),
        'model' => array()
      );

      $categories = Content::published('category', null, null, function($content) {
        $content->only('parent', 'product');
      })->with('children', array(
        'with' => function($rows) {
          $counts = array();

          $query = db()->table('sys_content as c')
            ->join('sys_content_category as cc', 'cc.content_id', 'c.id')
            ->where('c.type', 'product')
            ->whereIn('cc.category_id', $this->ids())
            ->groupBy('cc.category_id')
            ->get(array(
              'cc.category_id',
              db()->func('COUNT', '*', 'total')
            ));

          foreach ($query as $count) {
            $counts[array_get($count, 'category_id')] = array_get($count, 'total', 0);
          }

          return array_map(function (&$row) use ($counts) {
            $row->total_products = array_get($counts, $row->id, 0);
          }, $rows);
        }
      ))->get('rows');

      foreach ($categories as $category) {
        if (isset($filters[$category->url])) {
          $filters[$category->url] = $category->children;
        }
      }

      $data['filters'] = $filters;

      //Products
      $items = Content::published('product')->with('poster', array(
        'default' => View::getPath()->folder('layouts.assets.default')->file('poster.jpg')
      ))->with('media')->with('category')->with(function ($rows) {
        return array_map(function (&$row) {
          $categories = array();

          foreach ($row->category as $category) {
            if (!in_array($category->url, array('popular', 'showcase', 'picks'))) {
              $categories[] = $category;
            }
          }

          $row->category = $categories;
        }, $rows);
      })->get('rows');

      $data['products'] = $items;
    }

    $data['page'] = $page;

    //View file
    $file = 'catalog.list';

    if ($this->request->isAjax()) {
      $file = 'ajax.' . $file;
    }

    return View::create($file)->data($data)->render();
  });

  $this->get('/bize-ulasin', function ($params = array()) use ($data) {
    $page = new \stdClass();
    $page->title = lang('menu.contact', 'İletişim');

    $data['page'] = $page;
    $data['breadcrumb'] = crumb($data['breadcrumb'], array(
      array(lang('menu.home', 'Anasayfa'), '/'),
      array($page->title)
    ));

    //View file
    $file = 'contact';

    if ($this->request->isAjax()) {
      $file = 'ajax.' . $file;
    }

    return View::create($file)->data($data)->render();
  });

  $this->post('/bize-ulasin', function($params = array()) {
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

  $this->post('/numune-isteyin', function($params = array()) {
    $this->response->setContentType('json');

    $message = Message::result(lang('message.nothing_done', 'Herhangi bir işlem yapılmadı!'));

    if (strlen(input('social_number')) > 10) {
      if (strlen(input('name')) > 3) {
        if (filter_var(input('email'), FILTER_VALIDATE_EMAIL)) {
          if (strlen(input('product')) > 3) {
            if (strlen(input('phone')) > 9) {
              if (strlen(input('address')) > 3) {
                //File
                $file = View::getPath()->folder('layouts.email')->file('example.html');

                $send = Email::create('Numune Formu [' . conf('frontend.' . lang() . '.title', 'Web-IM XI') . ']')
                  ->to(conf('email.from'), conf('email.from_name'))
                  ->bcc('opolat@hotmail.com', 'Orhan POLAT')
                  ->html($file, array(
                    'title' => conf('frontend.' . lang() . '.title', 'Web-IM XI') . ' '. lang('label.example_form', 'Numune Formu'),
                    'date' => date_show(Carbon::now(), 'long', true),
                    'ip' => $this->request->getClientIp(),
                    'social_number' => input('social_number'),
                    'tax_division' => input('tax_division'),
                    'name' => input('name'),
                    'email' => input('email'),
                    'product' => input('product'),
                    'phone' => input('phone'),
                    'address' => nl2br(input('address'))
                  ))->send();

                if ($send->success()) {
                  $message->success = true;
                  $message->text = lang('message.message_sent', 'Mesajınız iletildi...');
                } else {
                  $message->text = lang('message.message_not_sent', 'Mesajınız iletilemedi!');
                }
              } else {
                $message->text = lang('message.type_your_address', 'Adresinizi yazın!');
              }
            } else {
              $message->text = lang('message.type_product', 'Telefon numaranızı yazın!');
            }
          } else {
            $message->text = lang('message.type_product', 'Ürünü yazın!');
          }
        } else {
          $message->text = lang('message.invalid_email_address', 'Geçersiz e-posta adresi!');
        }
      } else {
        $message->text = lang('message.type_your_name', 'İsminizi yazın!');
      }
    } else {
      $message->text = lang('message.type_social_number', 'T.C. / Vergi numaranızı yazın!');
    }

    return $message->forData();
  });

  $this->get('/download/:file+', function($params = array()) {
    $file = array_get($params, 'file');

    if ($file == 'catalog') {
      $file_name = 'europanel-katalog';

      $download = View::getPath()->folder('layouts.assets')->file($file_name . '.zip')->download($file_name);

      if ($download) {
        $this->response->setHeaders($download->headers);

        return $download->content;
      }
    }

    return $this->notFound();
  });

  $this->post('/newsletter', function($params = array()) {
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

});