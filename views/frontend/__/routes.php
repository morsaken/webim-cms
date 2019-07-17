<?php
defined('WEBIM') or die('Dosya yok');

/**
 * @author Orhan POLAT
 */

use ReCaptcha\ReCaptcha;
use System\Content;
use System\Email;
use System\Search;
use Webim\App;
use Webim\Library\Carbon;
use Webim\Library\File;
use Webim\Library\Message;
use Webim\View\Manager as View;

App::make()->group((count(langs()) ? '(/:lang#(' . implode('|', array_keys(langs())) . ')#)?' : ''), function () {
  // default path
  $path = File::path('views.frontend.' . conf('frontend.' . lang() . '.template', 'default'));

  // set default path
  View::setPath($path);

  // file checksums
  $md5 = array();

  // minify js
  $js_path = View::getPath()->folder('layouts.js');

  foreach ($js_path->fileIn('*.js')->fileNotIn('*.min.js')->files() as $js) {
    $min_js = $js_path->file($js->name . '.min.js');

    if (!$min_js->exists()) {
      $min_js->create()->write(minifyJS($js->content()));
    }

    $md5[$min_js->baseName] = md5_file($js->getPath());
  }

  // minify css
  $css_path = View::getPath()->folder('layouts.css');

  foreach ($css_path->fileIn('*.css')->fileNotIn('*.min.css')->files() as $css) {
    $min_css = $css_path->file($css->name . '.min.css');

    if (!$min_css->exists()) {
      $min_css->create()->write(minifyCSS($css->content()));
    }

    $md5[$min_css->baseName] = md5_file($css->getPath());
  }

  // menu list
  $menus = Content::init()->only('language')->where('type', 'menu')->load()->with('meta')->get('rows');

  // page nav
  $nav = array();

  foreach ($menus as $menu) {
    $nav[$menu->url] = array(
      'title' => $menu->title,
      'items' => menu($menu->meta->items)
    );
  }

  // context
  $data = array(
    'root' => View::getPath()->folder('layouts')->src(),
    'title' => conf('frontend.' . lang() . '.title', 'Web-IM XI'),
    'description' => conf('frontend.' . lang() . '.description', 'Web Internet Manager'),
    'keywords' => conf('frontend.' . lang() . '.keywords'),
    'copyright' => conf('frontend.' . lang() . '.copyright', 'Powered By Masters'),
    'template' => ($this->request->isAjax() ? 'empty' : 'default'),
    'separator' => '::',
    'nav' => $nav,
    'md5' => $md5,
    'breadcrumb' => array()
  );

  // 500
  $this->setErrorTemplate(function ($title, $body) use ($data) {
    $page = new \stdClass();
    $page->status = 500;
    $page->title = lang('label.system_error', 'Sistem Hatası') . ': 500';
    $page->description = lang('label.page_error_occurred', 'Hata oluştu!');
    $page->content = $body;

    $data['page'] = $page;
    $data['breadcrumb'] = crumb($data['breadcrumb'], array(
      array(lang('menu.home', 'Anasayfa'), '/'),
      array($page->title)
    ));

    return View::create('500')->data($data)->render();
  });

  // 404
  $this->setNotFoundTemplate(function ($title, $body) use ($data) {
    $page = new \stdClass();
    $page->status = 404;
    $page->title = lang('label.error', 'Hata') . ': 404';
    $page->description = lang('label.page_not_found', 'Sayfa bulunamadı!');
    $page->content = '<p>' . lang('message.page_not_found', 'Ulaşmaya çalıştığınız sayfa silinmiş ya da taşınmış olabilir. Lütfen bağlantı linklerini takip ediniz!') . '</p>'
      . '<p><a class="btn btn-danger" href="' . url('/') . '">' . lang('menu.home', 'Anasayfa') . '</a></p>';

    $data['page'] = $page;
    $data['breadcrumb'] = crumb($data['breadcrumb'], array(
      array(lang('menu.home', 'Anasayfa'), '/'),
      array($page->title)
    ));

    return View::create('404')->data($data)->render();
  });

  $this->get('/', function ($params = array()) use ($data) {
    return View::create('home')->data($data)->render();
  });

  $this->get('/' . lang('url.search', 'arama'), function($params = array()) use ($data) {
    Search::setLanguage(lang());
    Search::setTypes(array(
      'page',
      'news',
      'products'
    ));

    $urls = array(
      'news' => lang('url.news', 'haberler'),
      'products' => lang('url.products', 'urunler')
    );

    $found = new \stdClass();
    $found->total = 0;
    $found->rows = array();

    $search = Search::init(input('q'))->get();

    if ($search->total) {
      foreach (Content::init()->whereIn('id', $search->ids)->orderBy('publish_date', 'desc')->load()->with('poster', array(
        'size' => '400x300'
      ))->with('fullUrl')->get('rows') as $row) {
        $content = new \stdClass();
        $content->url = isset($urls[$row->type]) ? url($urls[$row->type] . '/' . $row->url) : url($row->full_url);
        $content->title = $row->title;
        $content->summary = isset($row->meta->summary) ? $row->meta->summary : (isset($row->meta->description) ? $row->meta->description : '');
        $content->poster = $row->poster;

        $found->rows[] = $content;
      }

      $found->total = $search->total;
    }

    $page = new \stdClass();
    $page->title = lang('menu.search', 'Arama');
    $page->description = lang('label.search_for', [input('q')], '"%s" arama sonucu');
    $page->content = $found;

    $data['page'] = $page;

    return View::create('search')->data($data)->render();
  });

  $this->get('/' . lang('url.contact', 'iletisim'), function ($params = array()) use ($data) {
    $page = new \stdClass();
    $page->title = lang('menu.contact', 'İletişim');

    $data['page'] = $page;

    return View::create('contact')->data($data)->render();
  });

  $this->post('/' . lang('url.contact', 'iletisim'), function ($params = array()) use ($data) {
    $this->response->setContentType('json');

    $message = Message::result(lang('message.nothing_done', 'Herhangi bir işlem yapılmadı'));

    $recaptcha = new ReCaptcha($data['captcha']['secret']);
    $captcha = $recaptcha->verify(input('g-recaptcha-response'), $this->request->getClientIp());

    try {
      if (!$captcha->isSuccess()) {
        throw new \Exception(lang('message.invalid_captcha', 'İnsan olduğunuzu doğrulayın'));
      }

      if (strlen(input('full_name')) < 3) {
        throw new \Exception(lang('message.type_your_name_and_surname', 'Adınızı ve soyadınızı yazınız'));
      }

      if (filter_var(input('email'), FILTER_VALIDATE_EMAIL) === false) {
        throw new \Exception(lang('message.type_your_valid_email', 'Geçerli e-posta adresinizi yazınız'));
      }

      if (strlen(input('message')) < 5) {
        throw new \Exception(lang('message.type_your_message', 'Mesajınızı yazınız'));
      }

      // file
      $file = View::getPath()->folder('layouts.email')->file('contact.html');

      $send = Email::create('İletişim Formu Mesajı [' . conf('frontend.' . lang() . '.title', 'Web-IM XI') . ']')
        ->to(conf('email.from'), conf('email.from_name'))
        ->bcc('opolat@hotmail.com', 'Orhan POLAT')
        ->html($file, array(
          'title' => conf('frontend.' . lang() . '.title', 'Web-IM XI') . ' '. lang('label.contact_form', 'İletişim Formu'),
          'date' => date_show(Carbon::now(), 'long', true),
          'ip' => $this->request->getClientIp(),
          'name' => input('full_name'),
          'email' => input('email'),
          'subject' => input('subject', 'Konusuz'),
          'message' => nl2br(input('message'))
        ))->send();

      if (!$send->success()) {
        throw new \Exception(lang('message.form_not_sent', 'Mesajınız iletilemedi'));
      }

      $message->success = true;
      $message->text = lang('message.form_sent', 'Mesajınız iletildi');
    } catch (\Exception $e) {
      $message->text = $e->getMessage();
    }

    return $message->forData();
  });

  $this->post('/' . lang('url.subscribe', 'kayit'), function ($params = array()) {
    $this->response->setContentType('json');

    $message = Message::result(lang('message.nothing_done', 'Herhangi bir işlem yapılmadı'));

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
        $message->text = lang('message.successfully_subscribed_our_newsletter', 'Abonelik kaydınız alınmıştır');
      } else {
        $message->text = lang('message.already_subscribed_our_newsletter', 'Abonelik için e-posta adresiniz mevcut');
      }
    } else {
      $message->text = lang('message.invalid_email_address', 'Geçersiz e-posta adresi');
    }

    return $message->forData();
  });

  $this->get('/sitemap', function ($params = array()) use ($data) {
    $xml = array();

    $xml[] = '<?xml version="1.0" encoding="UTF-8"?>';
    $xml[] = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"'
      . '  xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">';

    foreach ($data['nav'] as $menu) {
      foreach ($menu['items'] as $nav) {
        $xml[] = '<url>';
        $xml[] = '<loc>' . $nav->url . '</loc>';
        $xml[] = '</url>';

        if (isset($nav->children) && count($nav->children)) {
          foreach ($nav->children as $child) {
            $xml[] = '<url>';
            $xml[] = '<loc>' . $child->url . '</loc>';
            $xml[] = '</url>';
          }
        }
      }
    }

    $pages = array_filter(Content::published('page')->get('rows'), function($item) use ($data) {
      $in = false;

      $url = url($item->url);

      foreach ($data['nav'] as $menu) {
        foreach ($menu['items'] as $nav) {
          if ($nav->url === $url) {
            $in = true;
          }

          if (isset($nav->children) && count($nav->children)) {
            foreach ($nav->children as $child) {
              if ($child->url === $url) {
                $in = true;
              }
            }
          }
        }
      }

      return !$in;
    });

    foreach ($pages as $page) {
      $xml[] = '<url>';
      $xml[] = '<loc>' . url($page->url) . '</loc>';
      $xml[] = '</url>';
    }

    foreach (Content::published('news')->with('poster', array(
      'source' => true
    ))->get('rows') as $content) {
      $xml[] = '<url>';
      $xml[] = '<loc>' . url(lang('url.news', 'haberler') . '/' . $content->url) . '</loc>';

      if ($content->poster->image && ($content->poster->source->role == 'image')) {
        $xml[] = '<' . $content->poster->source->role . ':' . $content->poster->source->role . '>';
        $xml[] = '<' . $content->poster->source->role . ':title><![CDATA[' . $content->title . ']]></' . $content->poster->source->role . ':title>';
        $xml[] = '<' . $content->poster->source->role . ':loc>' . $content->poster->image->src() . '</' . $content->poster->source->role . ':loc>';
        $xml[] = '</' . $content->poster->source->role . ':' . $content->poster->source->role . '>';
      }

      $xml[] = '</url>';
    }

    foreach (Content::published('product')->with('poster', array(
      'source' => true
    ))->with('category')->get('rows') as $content) {
      $xml[] = '<url>';
      $xml[] = '<loc>' . url(lang('url.products', 'urunler') . '/' . (isset($content->category[0]) ? $content->category[0]->url . '/' : '') . $content->url) . '</loc>';

      if ($content->poster->image && ($content->poster->source->role == 'image')) {
        $xml[] = '<' . $content->poster->source->role . ':' . $content->poster->source->role . '>';
        $xml[] = '<' . $content->poster->source->role . ':title><![CDATA[' . $content->title . ']]></' . $content->poster->source->role . ':title>';
        $xml[] = '<' . $content->poster->source->role . ':loc>' . $content->poster->image->src() . '</' . $content->poster->source->role . ':loc>';
        $xml[] = '</' . $content->poster->source->role . ':' . $content->poster->source->role . '>';
      }

      $xml[] = '</url>';
    }

    $xml[] = '</urlset>';

    $this->response->setContentType('xml');

    return implode('', $xml);
  });

  // pages
  $pages = Content::published('page')->with('poster')->with('media')->with('tags')->with('fullUrl')->get('rows');

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

});