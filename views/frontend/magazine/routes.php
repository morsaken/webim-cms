<?php
defined('WEBIM') or die('Dosya yok');

/**
 * @author Orhan POLAT
 */

use \System\Content;
use \Webim\App;
use \Webim\Library\File;
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
    'separator' => '::',
    'copyright' => conf('frontend.' . lang() . '.copyright'),
    'nav' => $nav,
    'breadcrumb' => array(),
    'categories' => cascade(Content::published('category', null, null, function($content) {
      $content->only('parent', 'news');
    })->with('children')->get('rows'), false)
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
      . '<p><a class="btn btn-danger" href="' . url('/') . '">' . lang('menu.home', 'Anasayfa') . '</a></p>';

    $data['page'] = $page;
    $data['breadcrumb'] = crumb($data['breadcrumb'], array(
      array(lang('menu.home', 'Anasayfa'), '/'),
      array($page->title)
    ));

    return View::create('error')->data($data)->render();
  });

  $this->get('/', function ($params = array()) use ($data) {
    // IDs
    $ids = array();

    // News categories
    $categories = $data['categories'];

    // Slider
    $data['slides'] = Content::published('news', 10, array_keys($data['categories']), function($content) {
      $content->only('category', 'slide');
    })->with('poster')->with('category')->with(function($rows) use (&$ids, $categories) {
      return array_map(function(&$row) use (&$ids, $categories) {
        $ids[$row->id] = $row->id;

        $row->category = array_values(array_filter($row->category, function($category) use ($categories) {
          return isset($categories[$category->url]);
        }));
      }, $rows);
    })->get('rows');

    $data['home'] = Content::published('news', 4, array_keys($data['categories']), function($content) use ($ids) {
      if (count($ids)) {
        $content->whereNotIn('id', $ids);
      }

      $content->only('category', 'home');
    })->with('poster')->with('category')->with(function($rows) use (&$ids, $categories) {
      return array_map(function(&$row) use (&$ids, $categories) {
        $ids[$row->id] = $row->id;

        $row->category = array_values(array_filter($row->category, function($category) use ($categories) {
          return isset($categories[$category->url]);
        }));
      }, $rows);
    })->get('rows');

    $data['latest'] = Content::published('news', 10, array_keys($data['categories']), function($content) use ($ids) {
      if (count($ids)) {
        $content->whereNotIn('id', $ids);
      }

      $content->only('category', 'home');
    })->with('poster')->with('category')->with(function($rows) use ($categories) {
      return array_map(function(&$row) use ($categories) {
        $row->category = array_values(array_filter($row->category, function($category) use ($categories) {
          return isset($categories[$category->url]);
        }));
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

  $this->get('/news/category/?:category', function($params = array()) use ($data) {
    $categories = $data['categories'];

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

    $data['list'] = Content::published('news', array(
      'offset' => array_get($params, 'page', 1) - 1 * 10,
      'limit' => 10
    ), array_map(function($category) {
      return $category->id;
    }, $categories))->with('poster')->get();

    $data['list']->nav = Paging::nav(
      $data['list']->offset,
      $data['list']->limit,
      $data['list']->total
    );

    return View::create('news/list')->data($data)->render();
  });

  $this->get('/news(/page/:page#[0-9]#)?', function($params = array()) use ($data) {
    $categories = $data['categories'];

    $page = new \stdClass();
    $page->title = lang('menu.news', 'Haberler');

    $data['page'] = $page;
    $data['breadcrumb'] = crumb($data['breadcrumb'], array(
      array(lang('menu.home', 'Anasayfa'), '/'),
      array($page->title)
    ));

    $data['list'] = Content::published('news', array(
      'offset' => array_get($params, 'page', 1) - 1 * 10,
      'limit' => 10
    ), array_map(function($category) {
      return $category->id;
    }, $categories))->with('poster')->get();

    $data['list']->nav = Paging::nav(
      $data['list']->offset,
      $data['list']->limit,
      $data['list']->total
    );

    return View::create('news/list')->data($data)->render();
  });

  $this->get('/news/:url+', function($params = array()) use ($data) {
    $article = Content::published('news', null, array_map(function($category) {
      return $category->id;
    }, $data['categories']), function($content) use ($params) {
      $content->where('url', array_get($params, 'url'));
    })->with('poster')->with('media')->with('category')->with('tags')->get('rows.0');

    if (!$article) {
      return $this->notFound();
    }

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

    // More in current categories
    $data['more'] = Content::published('news', 6, array_map(function($category) {
      return $category->id;
    }, $article->category))->with('poster')->get('rows');

    // Popular news
    $data['most'] = Content::popular('news', 10, array_map(function($category) {
      return $category->id;
    }, $data['categories']))->with('poster')->with('category')->get('rows');

    return View::create('news/details')->data($data)->render();
  });

});