<?php
defined('WEBIM') or die('Dosya yok');

/**
 * @author Orhan POLAT
 */

use \System\Content;
use \System\Email;
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
  $menus  = Content::init()->only('language')->where('type', 'menu')->load()->with('meta')->get('rows');

  //Page all nav
  $nav = array();

  foreach ($menus as $menu) {
    $nav[$menu->url] = array(
      'title' => $menu->title,
      'items' => menu($menu->meta->items)
    );
  }

  function getArticles($offset, $category = null) {
    $categories = cascade(Content::published('category', null, null, function($content) {
      $content->only('parent', 'news');
    })->with('children')->get('rows'), false);

    if ($category === 'all') {
      $category = null;
    }

    $articles = Content::published('news', array(
      'offset' => $offset,
      'limit' => 10
    ), array_map(function ($category) {
      return $category->id;
    }, array_filter($categories, function($item) use ($category) {
      return is_null($category) || $item->url === $category;
    })))->with('poster', array(
      //'default' => View::getPath()->folder('layouts.assets.default')->file('poster.jpg'),
      'default' => array(
        'link' => View::getPath()->folder('layouts.assets.default')->file('poster.jpg')
      ),
      'size' => '320x0',
      'source' => true
    ))->with('category')->get();

    return array(
      'next' => (($articles->offset + $articles->limit) < $articles->total),
      'list' => array_map(function($article) {
        $type = 'standard';
        $media = null;

        if (isset($article->poster->source)) {
          if ($article->poster->source->role === 'link') {
            $type = 'video';
            $media = $article->poster->source->link;
          } elseif ($article->poster->source->role === 'audio') {
            $type = 'audio';
            $media = $article->poster->source->file->src();
          }
        }

        return (object) array(
          'type' => $type,
          'url' => url('article/' . $article->url),
          'date' => $article->publish_date,
          'poster' => $article->poster->image ? $article->poster->image->src() : null,
          'media' => $media,
          'title' => $article->title,
          'description' => $article->meta->summary,
          'categories' => array_map(function($category) {
            return (object) array(
              'url' => url('article/category/' . $category->url),
              'title' => $category->title
            );
          }, $article->category)
        );
      }, $articles->rows),
      'category' => (isset($categories[$category]) ? $categories[$category] : null)
    );
  }

  //Common conf
  $data = array(
    'root' => View::getPath()->folder('layouts')->src(),
    'title' => conf('frontend.' . lang() . '.title', 'Web-IM XI'),
    'description' => conf('frontend.' . lang() . '.description', 'Web Internet Manager'),
    'keywords' => conf('frontend.' . lang() . '.keywords'),
    'copyright' => conf('frontend.' . lang() . '.copyright', 'Powered By Masters'),
    'separator' => '::',
    'nav' => $nav,
    'breadcrumb' => array(),
    'about' => Content::published('page', null, null, function($content) {
      $content->where('url', 'about');
    })->with('meta')->get('rows.0')
  );

  //500
  $this->setErrorTemplate(function ($title, $body) use ($data) {
    $page = new \stdClass();
    $page->title = lang('label.system_error', 'Sistem Hatası') . ': 500';
    $page->description = lang('label.page_error_occured', 'Hata oluştu!');
    $page->content = $body;

    $data['page'] = $page;
    $data['breadcrumb'] = crumb($data['breadcrumb'], array(
      array(lang('menu.home', 'Anasayfa'), '/'),
      array($page->title)
    ));

    return View::create('error')->data($data)->render();
  });

  //404
  $this->setNotFoundTemplate(function ($title, $body) use ($data) {
    $page = new \stdClass();
    $page->title = lang('label.error', 'Hata') . ': 404';
    $page->description = lang('label.page_not_found', 'Sayfa bulunamadı!');
    $page->content = '<p>' . lang('message.page_not_found', 'Ulaşmaya çalıştığınız sayfa silinmiş ya da taşınmış olabilir. Lütfen bağlantı linklerini takip ediniz!') . '</p>'
      . '<p><a class="button button-primary" href="' . url('/') . '">' . lang('menu.home', 'Anasayfa') . '</a></p>';

    $data['page'] = $page;
    $data['breadcrumb'] = crumb($data['breadcrumb'], array(
      array(lang('menu.home', 'Anasayfa'), '/'),
      array($page->title)
    ));

    return View::create('error')->data($data)->render();
  });

  $this->get('/', function ($params = array()) use ($data) {
    $data['slides'] = Content::published('news', 10, 'slide')->with('poster', array(
      'default' => View::getPath()->folder('layouts.assets.default')->file('poster.jpg'),
      'size' => '320x0'
    ))->get('rows');

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

      return View::create('content')->data($data)->render();
    });
  }

  $this->get('/articles/:category+/:page+', function($params = array()) use ($data) {
    $this->response->setContentType('json');

    return array_to(getBlog((array_get($params, 'page') - 1) * 10, array_get($params, 'category')));
  });

  $this->get('/article/category/?:category', function($params = array()) use ($data) {
    $categories = cascade(Content::published('category', null, null, function($content) {
      $content->only('parent', 'news');
    })->with('children')->get('rows'), false);

    if (!isset($categories[array_get($params, 'category')])) {
      return $this->notFound();
    }

    $page = new \stdClass();
    $page->title = $categories[array_get($params, 'category')]->title;

    $data['page'] = $page;
    $data['breadcrumb'] = crumb($data['breadcrumb'], array(
      array(lang('menu.home', 'Anasayfa'), '/'),
      array($page->title)
    ));
    $data['description'] = lang('label.articles_with_category', [$page->title], '%s Kategorisinde Haberler');
    $data['category'] = $categories[array_get($params, 'category')];

    return View::create('article/list')->data($data)->render();
  });

  $this->get('/article/:url+', function($params = array()) use ($data) {
    $article = Content::published('news', null, null, function($content) use ($params) {
      $content->where('url', array_get($params, 'url'));
    })->with('poster', array(
      'source' => true
    ))->with('media')->with('category')->with('tags')->get('rows.0');

    if (!$article) {
      return $this->notFound();
    }

    $type = 'standard';

    if (isset($article->poster->source)) {
      if ($article->poster->source->role === 'link') {
        $type = 'video';
      } elseif ($article->poster->source->role === 'audio') {
        $type = 'audio';
      }
    }

    $article->type = $type;
    $data['article'] = $article;

    $page = new \stdClass();
    $page->title = $article->title;

    $data['page'] = $page;
    $data['breadcrumb'] = crumb($data['breadcrumb'], array(
      array(lang('menu.home', 'Anasayfa'), '/'),
      array($page->title)
    ));
    $data['description'] = $article->meta->summary;
    $data['keywords'] = implode(', ', $article->tags);

    return View::create('article/view')->data($data)->render();
  });

  $this->get('/contact', function ($params = array()) use ($data) {
    $page = new \stdClass();
    $page->title = lang('menu.contact', 'Bize Ulaşın');

    $data['page'] = $page;
    $data['breadcrumb'] = crumb($data['breadcrumb'], array(
      array(lang('menu.home', 'Anasayfa'), '/'),
      array($page->title)
    ));

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

  $this->get('/search/?:page', function($params = array()) use ($data) {
    $urls = array(
      'news' => 'article'
    );

    $found = new \stdClass();
    $found->total = 0;
    $found->rows = array();

    $search = Search::init(input('q'))->language(lang())->types(array(
      'news'
    ))->get((array_get($params, 'page', 1) - 1) * 10, 10);

    if ($search->total) {
      foreach (Content::init()->whereIn('id', $search->ids)->orderBy('publish_date', 'desc')->load()->with('poster', array(
        'size' => '320x0'
      ))->with('category')->get('rows') as $row) {
        $content = new \stdClass();
        $content->type = 'standard';
        $content->url = url($urls[$row->type] . '/' . $row->url);
        $content->title = $row->title;
        $content->description = isset($row->meta->summary) ? $row->meta->summary : '';
        $content->poster = $row->poster->image ? $row->poster->image->src() : null;
        $content->categories = array_map(function($category) {
          return (object) array(
            'url' => $category->url,
            'title' => $category->title
          );
        }, $row->category);

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
    $data['keyword'] = input('q');

    return View::create('search')->data($data)->render();
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

    foreach (Content::published('news')->with('poster', array(
      'source' => true
    ))->get('rows') as $content) {
      $xml[] = '<url>';
      $xml[] = '<loc>' . url('article/' . $content->url) . '</loc>';

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