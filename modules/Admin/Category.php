<?php
/**
 * @author Orhan POLAT
 */

namespace Admin;

use \System\Content;
use \System\Media as SystemMedia;
use \Webim\Library\Carbon;
use \Webim\Library\Language as Lang;
use \Webim\Library\Message;
use \Webim\View\Manager as View;

class Category {

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
    $manager->addRoute($manager->prefix . '/content', __CLASS__ . '::toIndex');
    $manager->addRoute($manager->prefix . '/content/categories', __CLASS__ . '::getIndex');
    $manager->addRoute($manager->prefix . '/content/categories/form/?:id', __CLASS__ . '::getForm');
    $manager->addRoute($manager->prefix . '/content/categories/form/?:id', __CLASS__ . '::postForm', 'POST');
    $manager->addRoute($manager->prefix . '/content/categories/form/:id+', __CLASS__ . '::deleteForm', 'DELETE');
    $manager->addRoute($manager->prefix . '/content/categories/parents/?:id', __CLASS__ . '::parents', 'POST');
    $manager->addRoute($manager->prefix . '/content/categories/orders/?:id', __CLASS__ . '::orders', 'POST');
    $manager->addRoute($manager->prefix . '/content/categories/rename/:id+', __CLASS__ . '::renameURL', 'POST');
    $manager->addRoute($manager->prefix . '/content/categories/duplicate', __CLASS__ . '::duplicate', 'POST');

    $parent = $manager->addMenu(lang('admin.menu.system', 'Sistem'), $manager->prefix . '/content', lang('admin.menu.content', 'İçerik'), null, 'fa fa-edit');
    $manager->addMenu(lang('admin.menu.system', 'Sistem'), $manager->prefix . '/content/categories', lang('admin.menu.categories', 'Kategoriler'), $parent, 'fa fa-tree');

    static::$manager = $manager;
  }

  public function toIndex() {
    $manager = static::$manager;
    $manager->app->redirect(url($manager->prefix . '/content/categories'));
  }

  public function getIndex($lang = null) {
    $manager = static::$manager;

    $manager->put('subnavs', array(
      btn(lang('admin.menu.create', 'Yeni Oluştur'), url($manager->prefix . '/content/categories/form'), 'fa-plus'),
    ));

    $manager->set('caption', lang('admin.menu.categories', 'Kategoriler'));
    $manager->breadcrumb($manager->prefix . '/content', lang('admin.menu.content', 'İçerik'));
    $manager->breadcrumb($manager->prefix . '/content/categories', lang('admin.menu.categories', 'Kategoriler'));

    $manager->put('list',
      Content::init()
        ->where('type', 'category')
        ->only(function ($content) {
          if (input('id', 0) > 0) {
            $content->where('id', input('id', 0));
          }

          if (strlen(input('publish_date-start')) && strlen(input('publish_date-end'))) {
            $content->whereBetween('publish_date', array(
              Carbon::createFromTimestamp(strtotime(input('publish_date-start'))),
              Carbon::createFromTimestamp(strtotime(input('publish_date-end')))
            ));
          } elseif (strlen(input('publish_date-start'))) {
            $content->where('publish_date', Carbon::createFromTimestamp(strtotime(input('publish_date-start'))));
          }

          if (Lang::has(input('language'))) {
            $content->where('language', input('language'));
          }

          if (strlen(input('title'))) {
            $content->where('title', 'like', '%' . input('title') . '%');
          }
        })->orderBy(input('orderby', 'id'), input('order', ['desc', 'asc']))
        ->load(input('offset', 0), input('limit', 20))
        ->with('parents')->with('fullUrl')
        ->with(function ($rows) {
          return array_map(function (&$row) use ($rows) {
            $row->url = $row->full_url;
            $row->language = lang('name', $row->language, $row->language);
            $row->active = $row->active == 'true';
            $row->status = $row->active ? lang('admin.label.active', 'Aktif') : lang('admin.label.passive', 'Pasif');
          }, $rows);
        })->get()
    );

    return View::create('content.categories.list')->data($manager::data())->render();
  }

  public function getForm($lang = null, $id = 0) {
    $manager = static::$manager;

    $action = 'new';
    $actionTitle = lang('admin.label.create_new', 'Yeni Oluştur');

    $content = Content::init()
      ->where('type', 'category')
      ->where('id', $id)
      ->load()->with('meta')->with('poster', array(
        'source' => true
      ))->with('fullUrl')->with('tags')->with(function ($rows) {
        return array_map(function (&$row) use ($rows) {
          $row->tags = $row->tags ? implode(', ', $row->tags) : '';
        }, $rows);
      })->get('rows.0');

    if ($content) {
      $action = 'edit';
      $actionTitle = lang('admin.label.edit', 'Düzenle');

      $manager->put('content', $content);
    }

    $manager->set('caption', lang('admin.menu.categories', 'Kategoriler'));
    $manager->breadcrumb($manager->prefix . '/content', lang('admin.menu.content', 'İçerik'));
    $manager->breadcrumb($manager->prefix . '/content/categories', lang('admin.menu.categories', 'Kategoriler'));
    $manager->breadcrumb($manager->prefix . '/content/categories/form', lang('admin.menu.' . $action, $actionTitle));

    return View::create('content.categories.form')->data($manager::data())->render();
  }

  public function postForm($lang = null, $id = null) {
    $manager = static::$manager;

    $manager->app->response->setContentType('json');

    //Poster values
    $poster = array(
      'id' => input('meta-poster_id', 0),
      'image' => null,
      'role' => null
    );

    $save = Content::init()->validation(array(
      'title' => 'required'
    ), array(
      'title.required' => lang('admin.message.content_title_required', 'İçerik başlığını girin!')
    ))->set('id', !is_null($id) ? $id : input('id', 0))
      ->set('parent_id', (input('parent_id', 0) > 0 ? input('parent_id', 0) : null))
      ->set('type', 'category')
      ->set('language', input('language', lang()))
      ->set('url', Content::makeUrl(input('url'), input('title')))
      ->set('title', input('title'))
      ->set('publish_date', Carbon::now())
      ->set('order', (input('order', 0) > 0 ? input('order', 0) : null))
      ->set('version', input('version', 0))
      ->set('active', input('active', array('false', 'true')))
      ->save(function ($id) use ($manager, &$poster) {
        if ($file = $manager->app->request->file('poster-file')) {
          //Upload and save
          $upload = SystemMedia::init()->extensions('image', conf('media.image_extensions'))->upload($file, 'image', array(
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

        $this->saveOrders($id);

        $this->saveMeta($id, array(
          'description' => input('meta-description'),
          'poster_id' => $poster['id']
        ));
      });

    //Set poster
    $save->return = array_merge($save->returns(), array('poster' => $poster));

    return $save->forData();
  }

  public function parents($lang = null) {
    $manager = static::$manager;

    $manager->app->response->setContentType('json');

    $parents = array();

    $parents[] = array(
      'id' => 0,
      'title' => '.'
    );

    foreach (Content::init()
               ->where('id', '<>', input('id', 0))
               ->only('type', 'category')
               ->only('language', input('language', $lang))
               ->orderBy('order')
               ->load()->getListIndented('', null) as $id => $row) {
      $parents[] = array(
        'id' => $id,
        'parent_id' => $row->parent_id,
        'url' => $row->url,
        'title' => str_repeat('&nbsp;&nbsp;', $row->level) . $row->title
      );
    }

    return array_to($parents);
  }

  public function orders($lang = null) {
    $manager = static::$manager;

    $manager->app->response->setContentType('json');

    $order = 1;
    $orders = array();

    $orders[] = array(
      'id' => $order,
      'title' => lang('admin.label.at_the_beginning', 'En Başta')
    );

    foreach (Content::orderList('category', input('language', $lang), input('parent_id', 0), input('id', 0)) as $title) {
      $orders[] = array(
        'id' => ++$order,
        'title' => lang('admin.label.after', [$title], '%s sonuna')
      );
    }

    return array_to($orders);
  }

  public function deleteForm($lang = null, $ids = '') {
    $manager = static::$manager;

    $manager->app->response->setContentType('json');

    $ids = array_filter(explode(',', $ids), function ($id) {
      return (int) $id > 0;
    });

    if (count($ids)) {
      return Content::init()->delete($ids)->forData();
    }

    return Message::result(lang('message.nothing_done', 'Herhangi bir işlem yapılmadı!'))->forData();
  }

  public function renameURL($lang = null, $id) {
    $manager = static::$manager;

    $manager->app->response->setContentType('json');

    if (strlen(input('url'))) {
      //New url
      $url = Content::makeUrl(input('url'), input('url'));

      $save = Content::init()
        ->set('id', $id)
        ->set('url', $url)
        ->set('version', input('version', 0))->save();

      if ($save->success()) {
        $save->return = $save->returns() + array('url' => $url);
      }

      return $save->forData();
    } else {
      return Message::result(lang('message.nothing_done', 'Herhangi bir işlem yapılmadı!'))->forData();
    }
  }

  public function duplicate($lang = null) {
    $manager = static::$manager;
    $manager->app->response->setContentType('json');

    //Return
    $message = Message::result(lang('message.nothing_done', 'Herhangi bir işlem yapılmadı!'));

    $ids = array_filter(explode(',', input('id')), function ($id) {
      return (int) $id > 0;
    });

    if (count($ids)) {
      $duplicated = 0;
      $error = 0;

      foreach ($ids as $id) {
        $duplicate = Content::duplicate($id, input('lang'), input('category'));

        if ($duplicate->success()) {
          $duplicated++;
        } else {
          $error++;
        }
      }

      if ($duplicated) {
        $message->success = true;
        $message->text = lang('admin.message.duplicated_total', [$error, $duplicated], '%s hata ile %s kayıt çoklandı...');
      }
    }

    return $message->forData();
  }

}