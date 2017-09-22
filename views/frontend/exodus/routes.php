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
use \Webim\Library\Paging;
use \Webim\View\Manager as View;

App::make()->group((count(langs()) ? '(/:lang#(' . implode('|', array_keys(langs())) . ')#)?' : ''), function () {
  //Default path
  $path = File::path('views.frontend.' . conf('frontend.' . lang() . '.template', 'default'));

  //Set default path
  View::setPath($path);

  /**
   * Navigation
   *
   * @param string $title
   * @param string $link
   * @param array $sub
   *
   * @return stdClass
   */
  function nav($title, $link, $sub = array()) {
    $nav = new \stdClass();
    $nav->title = $title;
    $nav->url = !preg_match('/^(http|ftp)./', $link) ? url($link) : $link;
    $nav->link = $link;
    $nav->sub = $sub;
    $nav->active = url_is($link, $link != '/');

    return $nav;
  }

  /**
   * Menu
   *
   * @param $list
   *
   * @return array
   */
  function menu($list) {
    $nav = array();

    foreach ($list as $menu) {
      $children = array();

      if (isset($menu->children) && count($menu->children)) {
        $children = menu($menu->children);
      }

      $nav[] = nav($menu->title, $menu->url, $children);
    }

    return $nav;
  }

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
    'copyright' => conf('frontend.' . lang() . '.copyright', 'Powered By Masters'),
    'keywords' => conf('frontend.' . lang() . '.keywords'),
    'nav' => $nav,
    'breadcrumb' => array()
  );

  //404
  $this->setNotFoundTemplate(function ($title, $body) use ($data) {
    $page = new \stdClass();
    $page->title = lang('label.error', 'Hata') . ': 404';
    $page->content = '<h1 class="text-center"><i class="fa fa-warning"></i> 404 ' . lang('message.page_not_found', 'Sayfa bulunamadı!') . '</h1>'
      . '<p class="text-center">' . lang('message.page_requested_not_found', 'Ulaşmaya çalıştığınız sayfa silinmiş ya da taşınmış olabilir. Lütfen bağlantı linklerini takip ediniz!') . '</p>'
      . '<p class="text-center"><a class="button" href="' . url('/') . '">' . lang('menu.home', 'Anasayfa') . '</a></p>';

    $data['page'] = $page;
    $data['breadcrumb'] = crumb($data['breadcrumb'], array(
      array(lang('menu.home', 'Anasayfa'), '/'),
      array(lang('menu.error', $page->title))
    ));

    return View::create('content')->data($data)->render();
  });

  $this->get('/', function ($params = array()) use ($data) {
    $data['categories'] = Content::published('category', 3, null, function ($content) {
      $content->only('parent', 'product');
    })->get('rows');
    $data['products'] = Content::published('product', 3)->with('poster', array(
      'size' => '800x600'
    ))->with('category')->with(function ($rows) {
      return array_map(function (&$row) {
        $categories = array();

        foreach ($row->category as $category) {
          $categories[$category->url] = $category->title;
        }

        return $row->categories = $categories;
      }, $rows);
    })->get('rows');
    $data['posts'] = Content::published('news', 3)->with('poster', array(
      'size' => '800x600'
    ))->get('rows');

    return View::create('home')->data($data)->render();
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

      return View::create('content')->data($data)->render();
    });
  }

  $this->get('/products/?:slug', function ($params = array()) use ($data) {
    $page = new \stdClass();
    $page->title = lang('menu.products', 'Ürünler');

    $data['page'] = $page;
    $data['breadcrumb'] = crumb($data['breadcrumb'], array(
      array(lang('menu.home', 'Anasayfa'), '/'),
      array($page->title, 'products')
    ));
    $data['popular'] = Content::popular('product', 3)->with('poster', array(
      'size' => '100x100'
    ))->get('rows');

    $slug = array_get($params, 'slug');

    if (!is_null($slug)) {
      $product = Content::published('product', null, null, function ($content) use ($slug) {
        $content->where('url', $slug);
      })->with('poster', array(
        'size' => '1200x600'
      ))->with('media')->with('category')->with(function ($rows) {
        return array_map(function (&$row) {
          $categories = array();

          foreach ($row->category as $category) {
            $categories[$category->url] = $category->title;
          }

          return $row->categories = $categories;
        }, $rows);
      })->get('rows.0');

      if ($product) {
        $page->description = $page->title;
        $page->title = $product->title;

        $data['product'] = $product;
        $data['breadcrumb'] = crumb($data['breadcrumb'], array(
          array($page->title)
        ));
        $data['featured'] = Content::published('product', 5, $product->category[0]->id, function ($content) use ($product) {
          $content->where('id', '<>', $product->id);
        })->with('poster', array(
          'size' => '400x300',
          'default' => View::getPath()->folder('layouts.assets.default')->file('logo.jpg')
        ))->get('rows');

        return View::create('product.view')->data($data)->render();
      }

      return $this->notFound();
    }

    $products = Content::published('product', array(
      'offset' => input('offset', 0),
      'limit' => 5
    ), input('category'), function ($content) {
      if (strlen(input('filter'))) {
        $content->where('title', 'like', '%' . input('filter') . '%');
        $content->orWhereExists(function ($exists) {
          $exists->select('m.content_id')
            ->from('sys_content_meta as m')
            ->where('m.content_id', db()->func(null, 'sys_content.id'))
            ->where(function ($query) {
              $query->where('m.value', 'like', '%' . input('filter') . '%');
            });
        });
      }
    })->with('poster', array(
      'size' => '800x800',
      'default' => View::getPath()->folder('layouts.assets.default')->file('logo.jpg')
    ))->with(function ($rows) {
      return array_map(function (&$row) {
        if (strlen(input('filter'))) {
          $row->meta->summary = preg_replace('/(' . input('filter') . ')/i', '<mark class="color">$1</mark>', $row->meta->summary);
        }

        return $row;
      }, $rows);
    })->with('category')->with(function ($rows) {
      return array_map(function (&$row) {
        $categories = array();

        foreach ($row->category as $category) {
          $categories[$category->url] = $category->title;
        }

        return $row->categories = $categories;
      }, $rows);
    })->get();

    $products->paging = Paging::nav($products->offset, $products->limit, $products->total);

    $data['products'] = $products;

    return View::create('product.list')->data($data)->render();
  });

  $this->get('/blog/?:slug', function ($params = array()) use ($data) {
    $page = new \stdClass();
    $page->title = lang('menu.news', 'Haberler');

    $data['page'] = $page;
    $data['breadcrumb'] = crumb($data['breadcrumb'], array(
      array(lang('menu.home', 'Anasayfa'), '/'),
      array($page->title, 'blog')
    ));
    $data['popular'] = Content::popular('news', 3)->with('poster', array(
      'size' => '100x100'
    ))->get('rows');

    $slug = array_get($params, 'slug');

    if (!is_null($slug)) {
      $post = Content::published('news', null, null, function ($content) use ($slug) {
        $content->where('url', $slug);
      })->with('poster')->with('media')->get('rows.0');

      if ($post) {
        $page->description = $page->title;
        $page->title = $post->title;

        $data['post'] = $post;
        $data['breadcrumb'] = crumb($data['breadcrumb'], array(
          array(str_limit($page->title, 20))
        ));

        return View::create('blog.view')->data($data)->render();
      }

      return $this->notFound();
    }

    $posts = Content::published('news', array(
      'offset' => input('offset', 0),
      'limit' => 5
    ), null, function ($content) {
      if (strlen(input('filter'))) {
        $content->where('title', 'like', '%' . input('filter') . '%');
        $content->orWhereExists(function ($exists) {
          $exists->select('m.content_id')
            ->from('sys_content_meta as m')
            ->where('m.content_id', db()->func(null, 'sys_content.id'))
            ->where(function ($query) {
              $query->where('m.value', 'like', '%' . input('filter') . '%');
            });
        });
      }
    })->with('poster')->with(function ($rows) {
      return array_map(function (&$row) {
        if (strlen(input('filter'))) {
          $row->meta->summary = preg_replace('/(' . input('filter') . ')/i', '<mark class="color">$1</mark>', $row->meta->summary);
        }

        return $row;
      }, $rows);
    })->get();

    $posts->paging = Paging::nav($posts->offset, $posts->limit, $posts->total);

    $data['posts'] = $posts;

    return View::create('blog.list')->data($data)->render();
  });

  $this->get('/contact', function ($params = array()) use ($data) {
    $page = new \stdClass();
    $page->title = lang('menu.contact', 'İletişim');

    $data['page'] = $page;
    $data['breadcrumb'] = crumb($data['breadcrumb'], array(
      array(lang('menu.home', 'Anasayfa'), '/'),
      array($page->title)
    ));

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
});