<?php
/**
 * @author Orhan POLAT
 */

namespace Other\Admin;

use \Admin\Manager;
use \System\Content;
use \System\Media as SystemMedia;
use \Webim\Library\Carbon;
use \Webim\Library\Language as Lang;
use \Webim\Library\Message;
use \Webim\View\Manager as View;

class Timeline {

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
    $manager->addRoute($manager->prefix . '/timeline', __CLASS__ . '::getIndex');
    $manager->addRoute($manager->prefix . '/timeline/form/?:id', __CLASS__ . '::getForm');
    $manager->addRoute($manager->prefix . '/timeline/form/?:id', __CLASS__ . '::postForm', 'POST');
    $manager->addRoute($manager->prefix . '/timeline/form/:id+', __CLASS__ . '::deleteForm', 'DELETE');
    $manager->addRoute($manager->prefix . '/timeline/rename/:id+', __CLASS__ . '::renameURL', 'POST');
    $manager->addRoute($manager->prefix . '/timeline/duplicate', __CLASS__ . '::duplicate', 'POST');

    $manager->addMenu(lang('admin.menu.modules', 'Modüller'), $manager->prefix . '/timeline', lang('admin.menu.timeline', 'Zaman Tüneli'), null, 'fa fa-clock-o');

    static::$manager = $manager;
  }

  public function getIndex($params = array()) {
    $manager = static::$manager;

    $nav = new \stdClass();
    $nav->title = lang('admin.menu.create', 'Yeni Oluştur');
    $nav->url = url($manager->prefix . '/timeline/form');
    $nav->icon = 'fa-plus';

    $manager->put('subnavs', array(
      $nav
    ));

    $manager->set('caption', lang('admin.menu.timeline', 'Zaman Tüneli'));
    $manager->breadcrumb($manager->prefix . '/timeline', lang('admin.menu.timeline', 'Zaman Tüneli'));

    $manager->put('categories',
      Content::init()
        ->where('type', 'category')
        ->orderBy('order')
        ->load()
        ->getListIndented('&nbsp;&nbsp;')
    );

    $manager->put('list',
      Content::init()
        ->only('type', 'timeline')
        ->only('category', input('categories'))
        ->only(function ($content) {
          if (input('id', 0) > 0) {
            $content->where('id', input('id', 0));
          }

          if (Lang::has(input('language'))) {
            $content->where('language', input('language'));
          }

          if (strlen(input('period_title'))) {
            $content->only('meta', array(
              'period_title' => array(
                'like',
                '%' . input('period_title') . '%'
              )
            ));
          }

          if (strlen(input('period'))) {
            $content->only('meta', array(
              'period' => array(
                'like',
                '%' . input('period') . '%'
              )
            ));
          }

          if (strlen(input('title'))) {
            $content->where('title', 'like', '%' . input('title') . '%');
          }
        })->orderBy(input('orderby', 'id'), input('order', ['desc', 'asc']))
        ->load(input('offset', 0), input('limit', 20))
        ->with('meta')
        ->with(function ($rows) {
          return array_map(function (&$row) use ($rows) {
            $row->language = lang('name', $row->language, $row->language);
            $row->active = $row->active == 'true';
            $row->status = $row->active ? lang('admin.label.active', 'Aktif') : lang('admin.label.passive', 'Pasif');
          }, $rows);
        })->get()
    );

    return View::create('modules.others.timeline.list')->data($manager::data())->render();
  }

  public function getForm($params = array()) {
    $manager = static::$manager;

    $defaultPosters = array(
      'image' => View::getPath()->folder('layouts.assets.poster')->file('image.png'),
      'file' => View::getPath()->folder('layouts.assets.poster')->file('file.png'),
      'video' => View::getPath()->folder('layouts.assets.poster')->file('video.png'),
      'audio' => View::getPath()->folder('layouts.assets.poster')->file('audio.png'),
      'link' => View::getPath()->folder('layouts.assets.poster')->file('link.png')
    );

    $id = array_get($params, 'id', 0);
    $action = 'new';
    $actionTitle = lang('admin.label.create_new', 'Yeni Oluştur');

    $content = Content::init()
      ->where('type', 'timeline')
      ->where('id', $id)
      ->load()
      ->with('meta')
      ->with('media', array(
        'poster' => array(
          'default' => $defaultPosters,
          'size' => '150x150'
        )
      ))->with('poster', array(
        'default' => $defaultPosters,
        'source' => true
      ))->get('rows.0');

    if ($content) {
      $action = 'edit';
      $actionTitle = lang('admin.label.edit', 'Düzenle');

      $manager->put('content', $content);
    }

    $manager->set('caption', lang('admin.menu.timeline', 'Zaman Tüneli'));
    $manager->breadcrumb($manager->prefix . '/timeline', lang('admin.menu.timeline', 'Zaman Tüneli'));
    $manager->breadcrumb($manager->prefix . '/timeline/form', lang('admin.menu.' . $action, $actionTitle));

    return View::create('modules.others.timeline.form')->data($manager::data())->render();
  }

  public function postForm($params = array()) {
    $manager = static::$manager;

    $manager->app->response->setContentType('json');

    $id = array_get($params, 'id');

    //Publish & expire date
    $publish_date = Carbon::createFromTimestamp(strtotime(input('publish_date')));
    $expire_date = null;

    if (strlen(input('expire_date'))) {
      $expire_date = Carbon::createFromTimestamp(strtotime(input('expire_date')));
    }

    //New url
    $url = Content::makeUrl(input('url'), input('title'));

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
      ->set('type', 'timeline')
      ->set('language', input('language', lang()))
      ->set('url', $url)
      ->set('title', input('title'))
      ->set('publish_date', $publish_date)
      ->set('expire_date', $expire_date)
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

        $this->saveMeta($id, array(
          'poster_id' => $poster['id'],
          'summary' => input('meta-summary'),
          'show_summary_inside' => input('meta-show_summary_inside', array('no', 'yes')),
          'content' => raw_input('meta-content'),
          'period_title' => input('meta-period_title'),
          'period' => input('meta-period'),
        ));

        $this->saveMedia($id, input('media_id'));
      });

    //Set poster
    $save->return = array_merge($save->returns(), array('poster' => $poster));

    return $save->forData();
  }

  public function deleteForm($params = array()) {
    $manager = static::$manager;

    $manager->app->response->setContentType('json');

    $ids = array_filter(explode(',', array_get($params, 'id', '')), function ($id) {
      return (int) $id > 0;
    });

    if (count($ids)) {
      return Content::init()->delete($ids)->forData();
    } else {
      return Message::result(lang('message.nothing_done'))->forData();
    }
  }

  public function renameURL($params = array()) {
    $manager = static::$manager;

    $manager->app->response->setContentType('json');

    $id = array_get($params, 'id');

    if (strlen(input('url'))) {
      //New url
      $url = Content::makeUrl(input('url'), input('url'), substr(input('url'), 0, 10));

      $save = Content::init()
        ->set('id', $id)
        ->set('url', $url)
        ->set('version', input('version', 0))->save();

      if ($save->success()) {
        $save->return = $save->returns() + array('url' => $url);
      }

      return $save->forData();
    } else {
      return Message::result(lang('message.nothing_done'))->forData();
    }
  }

  public function duplicate($params = array()) {
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