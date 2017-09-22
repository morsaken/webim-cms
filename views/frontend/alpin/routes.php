<?php
defined('WEBIM') or die('Dosya yok');

/**
 * @author Orhan POLAT
 */

use \System\Content;
use \System\Email;
use \System\Media;
use \System\Search;
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

  $partners = Content::published('page', null, null, function($content) {
    $content->where('url', 'partners');
  })->with('meta')->get('rows.0');

  if ($partners) {
    $partners->companies = array();

    foreach (preg_split('[\r\n]', $partners->meta->description) as $description) {
      if (strlen(trim($description))) {
        $company = new \stdClass();
        $company->category = substr($description, 0, strpos($description, ':'));

        if (preg_match('/\[(.*?)\]/', $description, $match)) {
          $company->link = $match[1];
        }

        $company->title = trim(str_replace(array(
          $company->category,
          (isset($company->link) ? $company->link : null)
        ), '', $description), ' :[]');

        $partners->companies[] = $company;
      }
    }
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
      $content->where('url', 'about');
    })->with('meta')->get('rows.0'),
    'partners' => $partners,
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
      'default' => View::getPath()->folder('layouts.assets.default')->file('poster.jpg'),
      'source' => true
    ))->get('rows');

    $categories = Content::published('category', null, null, function($content) {
      $content->only('parent', 'products');
    })->with('children')->get('rows');

    $data['categories'] = $categories;

    foreach ($categories as $category) {
      $data['products'][$category->url] = Content::published('product', 10, array_keys(cascade($category->children)))->with('poster', array(
        'default' => View::getPath()->folder('layouts.assets.default')->file('poster.jpg')
      ))->with('category')->get('rows');
    }

    //View file
    $file = 'home';

    if ($this->request->isAjax()) {
      $file = 'ajax.' . $file;
    }

    return View::create($file)->data($data)->render();
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

  $this->get('/products/?:slug', function ($params = array()) use ($data) {
    $page = new \stdClass();
    $page->title = lang('menu.products', 'Ürünler');

    $data['breadcrumb'] = crumb($data['breadcrumb'], array(
      array(lang('menu.home', 'Anasayfa'), '/'),
      array(lang('menu.products', 'Ürünler'), 'products')
    ));

    $slug = array_get($params, 'slug');

    if (!is_null($slug)) {
      $item = Content::published('product', null, null, function ($content) use ($slug) {
        $content->where('url', $slug);
      })->with('poster', array(
        'default' => View::getPath()->folder('layouts.assets.default')->file('poster.jpg')
      ))->with('media')->with('category')->with('formGroups')->with('tags')->get('rows.0');

      if ($item) {
        $page->title = $item->title;

        if (strlen($item->meta->summary)) {
          $data['description'] = $item->meta->summary;
        }

        if (count($item->tags)) {
          $data['keywords'] = implode(', ', $item->tags);
        }

        $data['page'] = $page;
        $data['product'] = $item;
        $data['breadcrumb'] = crumb($data['breadcrumb'], $item->title, $item->url);
        $data['offers'] = Content::published('page', null, null, function($content) {
          $content->where('url', 'offers');
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
      $filters = array();

      $categories = Content::published('category', null, null, function($content) {
        $content->only('parent', 'products');
      })->with('children', array(
        'only' => function($content) {
          $content->orderBy('order');
        },
        'with' => function($rows) {
          $this->with('fullUrl');

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
        $filter = new \stdClass();
        $filter->url = $category->url;
        $filter->title = $category->title;
        $filter->children = cascade($category->children, false);

        $filters[] = $filter;
      }

      $data['filters'] = $filters;

      //Products
      $items = Content::published('product')->with('poster', array(
        'default' => View::getPath()->folder('layouts.assets.default')->file('poster.jpg')
      ))->with('media')->with('category')->get('rows');

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

  $this->get('/catalogs/?:slug', function($params = array()) use ($data) {
    $slug = array_get($params, 'slug');

    if (!is_null($slug)) {
      $keys = explode(',', $slug);

      $catalogs = Media::init()->whereIn('url', $keys)->load()->with('files')->get('rows');

      if (count($catalogs)) {
        //Media files
        $files = array();

        foreach ($catalogs as $catalog) {
          $files[] = $catalog->file;
        }

        //Remove first file from zip
        array_shift($files);

        $download = $catalogs[0]->file->download($catalogs[0]->title, (count($catalogs) > 1), $files);

        if ($download) {
          $this->response->setHeaders($download->headers);

          return $download->content;
        }
      }

      return $this->notFound();
    }

    $page = new \stdClass();
    $page->title = lang('menu.brochures', 'Kataloglar');

    $data['breadcrumb'] = crumb($data['breadcrumb'], array(
      array(lang('menu.home', 'Anasayfa'), '/'),
      array(lang('menu.brochures', 'Kataloglar'), 'catalogs')
    ));

    $brochures = array();

    //Filters
    $filters = array();

    $categories = Content::published('category', null, null, function($content) {
      $content->only('parent', 'products');
    })->with('children', array(
      'only' => function($content) {
        $content->orderBy('order');
      },
      'with' => function($rows) {
        $this->with('fullUrl');
      }
    ))->get('rows');

    foreach ($categories as $category) {
      $filter = new \stdClass();
      $filter->url = $category->url;
      $filter->title = $category->title;
      $filter->children = cascade($category->children, false);

      $filters[] = $filter;
    }

    $data['filters'] = $filters;

    //Products
    $items = Content::published('product')->with('category')->with('media', array(
      'only' => 'file'
    ))->get('rows');

    foreach ($items as $item) {
      foreach ($item->media as $media) {
        $brochure = new \stdClass();
        $brochure->title = $item->title;
        $brochure->url = $media->url;
        $brochure->category = $item->category;

        $brochures[] = $brochure;
      }
    }

    $data['brochures'] = $brochures;
    $data['page'] = $page;

    //View file
    $file = 'catalog.brochures';

    if ($this->request->isAjax()) {
      $file = 'ajax.' . $file;
    }

    return View::create($file)->data($data)->render();
  });

  $this->get('/gallery', function ($params = array()) use ($data) {
    $page = new \stdClass();
    $page->title = lang('menu.gallery', 'Galeri');

    $data['page'] = $page;
    $data['breadcrumb'] = crumb($data['breadcrumb'], array(
      array(lang('menu.home', 'Anasayfa'), '/'),
      array($page->title)
    ));

    //View file
    $file = 'gallery';

    if ($this->request->isAjax()) {
      $file = 'ajax.' . $file;
    }

    //Articles
    $articles = Content::published('news', null, 'gallery')->with('meta')->with('media')->get('rows');

    $data['articles'] = $articles;

    return View::create($file)->data($data)->render();
  });

  $this->get('/contact', function ($params = array()) use ($data) {
    $page = new \stdClass();
    $page->title = lang('menu.contact', 'Bize Ulaşın');

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

  $this->get('/search', function($params = array()) use ($data) {
    Search::setLanguage(lang());
    Search::setTypes(array(
      'product'
    ));

    $urls = array(
      'product' => 'products'
    );

    $found = new \stdClass();
    $found->total = 0;
    $found->rows = array();

    $search = Search::init(input('q'))->get();

    if ($search->total) {
      foreach (Content::init()->whereIn('id', $search->ids)->orderBy('publish_date', 'desc')->load()->with('poster', array(
        'default' => View::getPath()->folder('layouts.assets.default')->file('poster.jpg')
      ))->get('rows') as $row) {
        $content = new \stdClass();
        $content->url = url($urls[$row->type] . '/' . $row->url);
        $content->title = $row->title;
        $content->summary = isset($row->meta->summary) ? $row->meta->summary : '';
        $content->poster = $row->poster;

        $found->rows[] = $content;
      }

      $found->total = $search->total;
    }

    $page = new \stdClass();
    $page->title = lang('menu.search', 'Arama');
    $page->content = $found;

    $data['page'] = $page;
    $data['breadcrumb'] = crumb($data['breadcrumb'], array(
      array(lang('menu.home', 'Anasayfa'), '/'),
      array($page->title)
    ));

    //View file
    $file = 'search';

    if ($this->request->isAjax()) {
      $file = 'ajax.' . $file;
    }

    return View::create($file)->data($data)->render();
  });

  $this->get('/sitemap', function($params = array()) use ($data) {
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

    foreach (Content::published('product')->with('poster', array(
      'source' => true
    ))->get('rows') as $content) {
      $xml[] = '<url>';
      $xml[] = '<loc>' . url('products/' . $content->url) . '</loc>';

      if ($content->poster->image && ($content->poster->source->role == 'image')) {
        $xml[] = '<' . $content->poster->source->role . ':' . $content->poster->source->role . '>';
        $xml[] = '<' . $content->poster->source->role . ':title>' . $content->title . '</' . $content->poster->source->role . ':title>';
        $xml[] = '<' . $content->poster->source->role . ':loc>' . $content->poster->image->src() . '</' . $content->poster->source->role . ':loc>';
        $xml[] = '</' . $content->poster->source->role . ':' . $content->poster->source->role . '>';
      }

      $xml[] = '</url>';
    }

    $xml[] = '</urlset>';

    $this->response->setContentType('xml');

    return implode('', $xml);
  });

});