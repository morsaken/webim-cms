<?php
defined('WEBIM') or die('Dosya yok');

/**
 * @author Orhan POLAT
 */

use \Commerce\Product;
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
    'copyright' => conf('frontend.' . lang() . '.copyright', 'Powered By Masters'),
    'nav' => $nav,
    'breadcrumb' => array(),
    'months' => array(
      lang('date.months.1', 'Ocak'),
      lang('date.months.2', 'Şubat'),
      lang('date.months.3', 'Mart'),
      lang('date.months.4', 'Nisan'),
      lang('date.months.5', 'Mayıs'),
      lang('date.months.6', 'Haziran'),
      lang('date.months.7', 'Temmuz'),
      lang('date.months.8', 'Ağustos'),
      lang('date.months.9', 'Eylül'),
      lang('date.months.10', 'Ekim'),
      lang('date.months.11', 'Kasım'),
      lang('date.months.12', 'Aralık')
    ),
    'days' => array(
      lang('date.short_days.0', 'Paz'),
      lang('date.short_days.1', 'Pzt'),
      lang('date.short_days.2', 'Sal'),
      lang('date.short_days.3', 'Çar'),
      lang('date.short_days.4', 'Per'),
      lang('date.short_days.5', 'Cum'),
      lang('date.short_days.6', 'Cts')
    )
  );

  //404
  $this->setNotFoundTemplate(function ($title, $body) use ($data) {
    $page = new \stdClass();
    $page->title = lang('label.error', 'Hata') . ': 404';
    $page->content = '<h1 class="text-center"><i class="fa fa-warning"></i> 404 ' . lang('message.page_not_found', 'Sayfa bulunamadı!') . '</h1>'
      . '<p class="text-center">' . lang('message.page_requested_not_found', 'Ulaşmaya çalıştığınız sayfa silinmiş ya da taşınmış olabilir. Lütfen bağlantı linklerini takip ediniz!') . '</p>'
      . '<p class="text-center"><a class="btn btn-danger" href="' . url('/') . '">' . lang('menu.home', 'Anasayfa') . '</a></p>';

    $data['page'] = $page;
    $data['breadcrumb'] = crumb($data['breadcrumb'], array(
      array(lang('menu.home', 'Anasayfa'), '/'),
      array(lang('menu.error', $page->title))
    ));

    return View::create('404')->data($data)->render();
  });

  //Home
  $this->get('/', function ($params = array()) use ($data) {
    $data['sliders'] = Content::published('news', 10, 'slider')->with('poster', array(
      'default' => View::getPath()->folder('layouts.assets.default')->file('poster.jpg')
    ))->get('rows');
    $data['carousel'] = Content::published('news', 10, 'carousel')->with('poster', array(
      'default' => View::getPath()->folder('layouts.assets.default')->file('poster.jpg')
    ))->get('rows');
    $data['front'] = Content::published('news', 1, 'front')->with('poster', array(
      'default' => View::getPath()->folder('layouts.assets.default')->file('poster.jpg')
    ))->get('rows.0');

    $data['events'] = array();

    $galleryCategories = cascade(Content::published('category', null, null, function($content) {
      $content->where('url', 'gallery');
    })->with('children')->get('rows'));

    $data['gallery'] = array();

    foreach (Content::published('news', 12, array_keys($galleryCategories))->with('media', array(
      'only' => 'image'
    ))->get('rows') as $gallery) {
      $data['gallery'] = array_merge($data['gallery'], $gallery->media);
    }

    $data['timeline'] = Content::published('timeline')->with('poster')->get('rows');

    return View::create('home')->data($data)->render();
  });

  //Pages
  $pages = Content::published('page')->with('meta')->with('media')->with('fullUrl')->get('rows');

  foreach ($pages as $page) {
    $this->get('/' . $page->full_url, function($params = array()) use ($data, $pages, $page) {
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

  $this->get('/explore/?:slug', function($params = array()) use ($data) {
    $page = new \stdClass();
    $page->url = 'explore';
    $page->title = lang('menu.explore', 'Keşfet');

    $data['page'] =& $page;
    $data['breadcrumb'] = crumb($data['breadcrumb'], array(
      array(lang('menu.home', 'Anasayfa'), '/'),
      array($page->title, $page->url)
    ));

    $categories = cascade(Content::published('category', null, null, function($content) use ($page) {
      $content->where('url', $page->url);
    })->with('meta')->with('children', array(
      'with' => function() {
        $this->with('meta');
      }
    ))->get('rows'), false);

    $data['categories'] = $categories;

    $slug = array_get($params, 'slug');

    if (strlen($slug)) {
      $item = Content::published('news', null, array_keys($categories), function($content) use ($slug) {
        $content->where('url', $slug);
      })->with('poster')->get('rows.0');

      if ($item) {
        $page->title = $item->title;
        $page->content = $item;

        $data['breadcrumb'] = crumb($data['breadcrumb'], array(
          array($page->title)
        ));

        return View::create('content')->data($data)->render();
      }

      return $this->notFound();
    }

    $page->content = array();

    if (count($categories)) {
      foreach (Content::published('news', null, array_keys($categories))->with('poster')->with('category')->get('rows') as $item) {
        $page->content[array_first($item->category)->url][] = $item;
      }
    }

    return View::create('list')->data($data)->render();
  });

  $this->get('/learn/?:slug', function($params = array()) use ($data) {
    $page = new \stdClass();
    $page->url = 'learn';
    $page->title = lang('menu.learn', 'Öğren');

    $data['page'] =& $page;
    $data['breadcrumb'] = crumb($data['breadcrumb'], array(
      array(lang('menu.home', 'Anasayfa'), '/'),
      array($page->title, $page->url)
    ));

    $categories = cascade(Content::published('category', null, null, function($content) use ($page) {
      $content->where('url', $page->url);
    })->with('meta')->with('children', array(
      'with' => function() {
        $this->with('meta');
      }
    ))->get('rows'), false);

    $data['categories'] = $categories;

    $slug = array_get($params, 'slug');

    if (strlen($slug)) {
      $item = Content::published('news', null, array_keys($categories), function($content) use ($slug) {
        $content->where('url', $slug);
      })->with('poster')->get('rows.0');

      if ($item) {
        $page->title = $item->title;
        $page->content = $item;

        $data['breadcrumb'] = crumb($data['breadcrumb'], array(
          array($page->title)
        ));

        return View::create('content')->data($data)->render();
      }

      return $this->notFound();
    }

    $page->content = array();

    if (count($categories)) {
      foreach (Content::published('news', null, array_keys($categories))->with('poster')->with('category')->get('rows') as $item) {
        $page->content[array_first($item->category)->url][] = $item;
      }
    }

    return View::create('list')->data($data)->render();
  });

  $this->get('/events/?:slug', function($params = array()) use ($data) {
    if ($this->request->isAjax()) {
      //Set response type as json
      $this->response->setContentType('json');

      //Get events
      $events = Content::published('event', null, null, function($content) {
        $year = input('year', Carbon::now()->format('Y'));
        $month = input('month', Carbon::now()->format('m'));

        if (strlen($month) == 1) {
          $month = '0' . $month;
        }

        $content->only('meta', array(
          'month' => $month,
          'year' => $year
        ));
      })->with('poster')->get('rows');

      //Return list
      $list = array();

      foreach ($events as $event) {
        $item = new \stdClass();
        $item->url = url('events/' . $event->url);
        $item->title = $event->title;
        $item->description = str_limit($event->meta->summary, 50);
        $item->poster = $event->poster->image ? $event->poster->image->src() : null;
        $item->day = intval(Carbon::createFromTimestamp(strtotime($event->meta->date))->format('d'));

        $list[] = $item;
      }

      return array_to($list);
    }

    $page = new \stdClass();
    $page->title = lang('menu.events', 'Etkinlikler');

    $data['breadcrumb'] = crumb($data['breadcrumb'], array(
      array(lang('menu.home', 'Anasayfa'), '/'),
      array($page->title, 'events')
    ));

    $slug = array_get($params, 'slug');

    if (strlen($slug)) {
      $item = Content::published('event', null, null, function($content) use ($slug) {
        $content->where('url', $slug);
      })->with('poster', array(
        'default' => View::getPath()->folder('layouts.assets.default')->file('poster.jpg')
      ))->get('rows.0');

      if ($item) {
        $page->title = $item->title;
        $page->content = $item;

        $data['breadcrumb'] = crumb($data['breadcrumb'], $page->title);
        $data['page'] = $page;

        return View::create('content')->data($data)->render();
      }

      return $this->notFound();
    }

    $page->content = Content::published('event', 20, null, function($content) {
      $year = Carbon::now()->format('Y');
      $month = Carbon::now()->format('m');
      $day = Carbon::now()->format('d');

      $content->only('meta', array(
        'year' => array(
          '<=', $year
        ),
        'month' => array(
          '<=', $month
        ),
        'day' => array(
          '<', $day
        )
      ));
    })->with('poster', array(
      'default' => View::getPath()->folder('layouts.assets.default')->file('poster.jpg')
    ))->get('rows');

    //Get current month events
    $events = Content::published('event', null, null, function($content) {
      $year = Carbon::now()->format('Y');
      $month = Carbon::now()->format('m');
      $day = Carbon::now()->format('d');

      $content->only('meta', array(
        'year' => array(
          '>=', $year
        ),
        'month' => array(
          '>=', $month
        )
      ));
    })->with('poster', array(
      'default' => View::getPath()->folder('layouts.assets.default')->file('poster.jpg')
    ))->get('rows');

    //Today
    $today = Carbon::today();

    //Monthly
    $monthly = array();

    //Return list
    $list = array();

    foreach ($events as $event) {
      $item = new \stdClass();
      $item->url = $event->url;
      $item->title = $event->title;
      $item->description = $event->meta->summary;
      $item->poster = $event->poster;
      $item->date = Carbon::createFromTimestamp(strtotime($event->meta->date));
      $item->day = intval($item->date->format('d'));
      $item->month = intval($item->date->format('n'));
      $item->month_name = array_get($data, 'months.' . ($item->month - 1), $item->month);

      if ($item->day >= $today->day) {
        $list[] = $item;
      }

      $monthly[] = $item->month . '-' . $item->day;
    }

    //Create calendar
    $tempDate = Carbon::createFromDate($today->year, $today->month, 1);

    //Calendar array
    $calendar = array();

    $calendar[] = '<table class="table">';
    $calendar[] = '<caption>' . str_case(array_get($data, 'months.' . ($today->month - 1))) . ' ' . $today->year . '</caption>';
    $calendar[] = '<thead>';
    $calendar[] = '<tr>';

    $firstDayOfWeek = lang('date.first_day', 1);

    for ($i = $firstDayOfWeek; $i <= 7; $i++) {
      $calendar[] = '<th>' . str_case(array_get($data, 'days.' . $i % 7)) . '</th>';
    }

    $calendar[] = '</thead>';

    $skip = $tempDate->dayOfWeek;

    for ($i = $firstDayOfWeek; $i < $skip; $i++) {
      $tempDate->subDay();
    }

    $calendar[] = '<tbody>';

    //loops through month
    do {
      $calendar[] = '<tr>';

      //loops through each week
      for ($i = 0; $i < 7; $i++) {
        //Classes to add to day column
        $classes = array();

        if ($tempDate->isToday()) {
          $classes[] = 'today';
        }

        if (in_array($tempDate->month . '-' . $tempDate->day, $monthly, true)) {
          $classes[] = 'active';
        }

        $class = count($classes) ? ' class="' . implode(' ', $classes) . '"' : '';

        $calendar[] = '<td' . $class . '>' . ($tempDate->month == $today->month ? $tempDate->day : '&nbsp;') . '</td>';

        $tempDate->addDay();
      }

      $calendar[] = '</tr>';

    } while ($tempDate->month == $today->month);

    $calendar[] = '</tbody>';
    $calendar[] = '</table>';

    $data['page'] = $page;

    usort($list, function ($a, $b) {
      return $a->date->getTimestamp() - $b->date->getTimestamp();
    });

    $data['events'] = $list;
    $data['calendar'] = implode("\n", $calendar);

    return View::create('events')->data($data)->render();
  });

  $this->get('/visit', function($params = array()) use ($data) {
    $page = new \stdClass();
    $page->title = lang('menu.contact', 'İletişim');

    $data['page'] = $page;
    $data['breadcrumb'] = crumb($data['breadcrumb'], array(
      array(lang('menu.home', 'Anasayfa'), '/'),
      array($page->title)
    ));

    return View::create('visit')->data($data)->render();
  });

  $this->post('/visit', function($params = array()) {
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

  $this->get('/shop/?:shop_url/?:product_url', function($params = array()) use ($data) {
    $page = new \stdClass();
    $page->title = lang('menu.shop', 'Mağaza');

    $data['page'] = $page;
    $data['breadcrumb'] = crumb($data['breadcrumb'], array(
      array(lang('menu.home', 'Anasayfa'), '/'),
      array($page->title, 'shop')
    ));

    $categories = cascade(Content::published('category', null, null, function($content) {
      $content->only('parent', 'products');
    })->with('poster')->get('rows'), false);

    $data['categories'] = $categories;

    $filter = null;

    $shop_url = array_get($params, 'shop_url');
    $product_url = array_get($params, 'product_url');

    if ($shop_url == 'category' && isset($categories[$product_url])) {
      $filter = $product_url;
    } else {
      $slug = $shop_url;

      if (strlen($product_url)) {
        $slug .= '/' . $product_url;
      }

      if (strlen($slug)) {
        $product = Product::load(null, null, function($content) use ($slug) {
          $content->where('url', $slug);
        })->with('poster', array(
          'default' => View::getPath()->folder('layouts.assets.default')->file('poster.jpg')
        ))->with('category')->with('media')->get('rows.0');

        if ($product) {
          $page->title = $product->title;

          $data['product'] = $product;
          $data['breadcrumb'] = crumb($data['breadcrumb'], $product->title);

          return View::create('shop.details')->data($data)->render();
        }

        return $this->notFound();
      }
    }

    $products = Product::load(array(
      'offset' => input('offset'),
      'limit' => 15
    ), $filter)->with('poster', array(
      'default' => View::getPath()->folder('layouts.assets.default')->file('poster.jpg')
    ))->with('category')->get();

    $products->paging = Paging::nav($products->offset, $products->limit, $products->total);

    $data['products'] = $products;
    $data['selected_category'] = $filter;

    return View::create('shop.list')->data($data)->render();
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