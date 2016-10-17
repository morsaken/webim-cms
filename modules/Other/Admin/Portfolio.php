<?php
/**
 * @author Orhan POLAT
 */

namespace Other\Admin;

use \Admin\Manager;
use \System\Content;
use \System\Media;
use \Webim\Library\Carbon;
use \Webim\Library\Message;
use \Webim\View\Manager as View;

class Portfolio {

 /**
  * Manager
  *
  * @var \Admin\Manager
  */
 protected static $manager;

 /**
  * Register current class and routes
  *
  * @param \Admin\Manager $manager
  */
 public static function register(Manager $manager) {
  //Routes
  $manager->addRoute($manager->prefix . '/portfolio', __CLASS__ . '::getIndex');
  $manager->addRoute($manager->prefix . '/portfolio/form/?:id', __CLASS__ . '::getForm');
  $manager->addRoute($manager->prefix . '/portfolio/form/?:id', __CLASS__ . '::postForm', 'POST');
  $manager->addRoute($manager->prefix . '/portfolio/form/:id+', __CLASS__ . '::deleteForm', 'DELETE');

  $manager->addMenu(lang('admin.menu.modules', 'Modüller'), $manager->prefix . '/portfolio', lang('admin.menu.portfolio', 'Portfolyo'), null, 'fa fa-support');

  static::$manager = $manager;
 }

 public function getIndex($lang = null) {
  $manager = static::$manager;

  $nav = new \stdClass();
  $nav->title = lang('admin.menu.create', 'Yeni Oluştur');
  $nav->url = url($manager->prefix . '/portfolio/form');
  $nav->icon = 'fa-plus';

  $manager->put('subnavs', array(
   $nav
  ));

  $manager->set('caption', lang('admin.menu.portfolio', 'Portfolyo'));
  $manager->breadcrumb($manager->prefix . '/portfolio', 'Portfolyo');

  $manager->put('list',
   Content::init()->where('type', 'portfolio-group')
    ->only(function($content) {
     if (input('id', 0) > 0) {
      $content->where('id', input('id', 0));
     }

     if (strlen(input('title'))) {
      $content->where('title', 'like', '%' . input('title') . '%');
     }
    })->orderBy(input('orderby', 'id'), input('order', ['desc', 'asc']))
    ->load(input('offset', 0), input('limit', 20))
    ->with('category')
    ->with(function($rows) {
     return array_map(function (&$row) use ($rows) {
      $categories = array();

      foreach ($row->category as $category) {
       $categories[] = $category->title;
      }

      $row->categories = count($categories) ? implode(', ', $categories) : '-';
      $row->active = $row->active == 'true';
      $row->status = $row->active ? lang('admin.label.active', 'Aktif') : lang('admin.label.passive', 'Pasif');
     }, $rows);
    })->get()
  );

  return View::create('modules.others.portfolio.list')->data($manager::data())->render();
 }

 public function getForm($lang = null, $id = 0) {
  $manager = static::$manager;

  $manager->put('categories',
   Content::init()
    ->where('type', 'category')
    ->load()
    ->getListIndented('&nbsp;&nbsp;')
  );

  $action = 'new';
  $actionTitle = lang('admin.label.create_new', 'Yeni Oluştur');

  $orders = array(
   1 => lang('admin.label.content.at_the_beginning', 'En Başta')
  );

  foreach (Content::init()->where('type', 'portfolio-group')->where('id', '<>', $id)->orderBy('order')->load()->get('rows') as $row) {
   $orders[] = $row->title;
  }

  $content = Content::init()->where('type', 'portfolio-group')
   ->where('id', $id)
   ->load()
   ->with('children', array(
    'with' => array('meta')
   ))->with('meta')->with('category')->with('media', array(
    'poster' => array(
     'default' => array(
      'image' => View::getPath()->folder('layouts.assets.poster')->file('image.png'),
      'file' => View::getPath()->folder('layouts.assets.poster')->file('file.png'),
      'video' => View::getPath()->folder('layouts.assets.poster')->file('video.png'),
      'audio' => View::getPath()->folder('layouts.assets.poster')->file('audio.png'),
      'link' => View::getPath()->folder('layouts.assets.poster')->file('link.png')
     ),
     'size' => '150x150'
     )
    ))->with('poster', array(
    'source' => true
   ))->with(function($rows) {
    return array_map(function (&$row) use ($rows) {
     $categories = array();

     foreach ($row->category as $category) {
      $categories[$category->id] = $category->id;
     }

     $row->categories = $categories;

     $children = array();

     if (isset($row->children)) {
      foreach ($row->children as $child) {
       $children[$child->language] = $child;
      }
     }

     $row->children = $children;
    }, $rows);
   })->get('rows.0');

  if ($content) {
   $action = 'edit';
   $actionTitle = lang('admin.label.edit', 'Düzenle');

   $manager->put('content', $content);
  }

  $manager->set('caption', lang('admin.menu.portfolio', 'Portfolyo'));
  $manager->set('orders', $orders);
  $manager->breadcrumb($manager->prefix . '/portfolio', lang('admin.menu.portfolio', 'Portfolyo'));
  $manager->breadcrumb($manager->prefix . '/portfolio/form', lang('admin.menu.' . $action, $actionTitle));

  return View::create('modules.others.portfolio.form')->data($manager::data())->render();
 }

 public function postForm($lang = null, $id = 0) {
  $manager = static::$manager;

  $manager->app->response->setContentType('json');

  $posts = array();
  $langs = array();

  foreach (langs() as $alias => $lang) {
   if (strlen(input($alias . '-title'))) {
    $posts[$alias] = array(
     'id' => input($alias . '-id', 0),
     'version' => input($alias . '-version', 0),
     'url' => slug(input('url')),
     'title' => input($alias . '-title'),
     'content' => raw_input($alias . '-meta-content')
    );

    $langs[$alias] = array(
     'id' => input($alias . '-id', 0),
     'version' => input($alias . '-version', 0)
    );
   }
  }

  //Default return
  $message = Message::result(lang('message.nothing_done', 'Herhangi bir işlem yapılmadı!'));

  if (strlen(input('url'))) {
   if (count($posts)) {
    $parent = Content::init()
     ->where('type', 'portfolio-group')
     ->where('id', ($id ? $id : input('id', 0)))
     ->load()->get('rows.0');

    //Poster values
    $poster = array(
     'id' => input('meta-poster_id', 0),
     'image' => null,
     'role' => null
    );

    $save = Content::init()
     ->set('type', 'portfolio-group')
     ->set('language', 'xx')
     ->set('url', slug(input('url')));

    if ($parent) {
     $save->set('id', $parent->id);
    }

    $save = $save->set('title', array_get(array_first($posts), 'title'))
     ->set('publish_date', Carbon::now())
     ->set('version', input('version'))
     ->set('order', input('order', 1))
     ->set('active', input('active', array('false', 'true')))
     ->save(function ($id) use ($manager, &$poster, $posts, &$langs) {
      $this->saveCategory($id, explode(',', input('category')));

      if ($file = $manager->app->request->file('poster-file')) {
       //Upload and save
       $upload = Media::init()->extensions('image', conf('media.image_extensions'))->upload($file, 'image', array(
        'image_max_size' => conf('media.image_max_size', '1920x1080')
       ));

       if ($upload->success()) {
        $poster['id'] = $upload->returns('id');
        $poster['role'] = $upload->returns('role');
        $poster['image'] = $upload->returns('src');
       } else {
        throw new \Exception(lang('message.image_upload_error', [$upload->text()], 'Resim yüklenemedi: %s'));
       }
      }

      $this->saveMeta($id, array(
       'poster_id' => $poster['id'],
       'link' => input('meta-link'),
       'client' => input('meta-client')
      ));

      $this->saveMedia($id, explode(',', input('media_id')));

      $this->saveOrders($id);

      //Set parent_id
      $parent_id = $id;

      foreach ($posts as $lang => $post) {
       $postSave = Content::init()
        ->set('id', $post['id'])
        ->set('type', 'portfolio')
        ->set('language', $lang)
        ->set('parent_id', $parent_id)
        ->set('url', $post['url'])
        ->set('title', $post['title'])
        ->set('publish_date', Carbon::now())
        ->set('order', input('order', 1))
        ->set('version', $post['version'])
        ->set('active', input('active', array('false', 'true')))
        ->save(function ($id) use ($manager, $lang, $poster, $post, $parent_id) {
         $this->saveCategory($id, explode(',', input('category')));

         $this->saveMeta($id, array(
          'poster_id' => $poster['id'],
          'link' => input('meta-link'),
          'client' => input('meta-client'),
          'content' => $post['content']
         ));

         $this->saveMedia($id, explode(',', input('media_id')));
         $this->setOrders('portfolio', $lang, null, $id, input('order', 1), false);
        });

       if (!$postSave->success()) {
        throw new \ErrorException($postSave->text());
       }

       $langs[$lang]['id'] = $postSave->returns('id');
       $langs[$lang]['version'] = $postSave->returns('version');
      }
     });

    $message->success = $save->success();
    $message->text = $save->text();
    $message->return = $save->returns() + array('poster' => $poster) + array('langs' => $langs);
   }
  } else {
   $message->text = lang('admin.message.content.url_required', 'İçerik URL girilmelidir!');
  }

  return $message->forData();
 }

 public function deleteForm($lang = null, $ids = '') {
  $manager = static::$manager;

  $manager->app->response->setContentType('json');

  $ids = array_filter(explode(',', $ids), function($id) {
   return (int) $id > 0;
  });

  if (count($ids)) {
   //First delete children
   $children = array();

   foreach (Content::init()->whereIn('parent_id', $ids)->load()->get('rows') as $row) {
    $children[$row->id] = $row->id;
   }

   if (count($children)) {
    Content::init()->delete($children);
   }

   return Content::init()->delete($ids)->forData();
  } else {
   return Message::result(lang('message.nothing_done'));
  }
 }

}