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

class Event {

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
    $manager->addRoute($manager->prefix . '/content/events', __CLASS__ . '::getIndex');
    $manager->addRoute($manager->prefix . '/content/events/form/?:id', __CLASS__ . '::getForm');
    $manager->addRoute($manager->prefix . '/content/events/form/?:id', __CLASS__ . '::postForm', 'POST');
    $manager->addRoute($manager->prefix . '/content/events/form/:id+', __CLASS__ . '::deleteForm', 'DELETE');
    $manager->addRoute($manager->prefix . '/content/events/rename/:id+', __CLASS__ . '::renameURL', 'POST');
    $manager->addRoute($manager->prefix . '/content/events/duplicate', __CLASS__ . '::duplicate', 'POST');

    $parent = $manager->addMenu(lang('admin.menu.system', 'Sistem'), $manager->prefix . '/content', lang('admin.menu.content', 'İçerik'), null, 'fa fa-edit');
    $manager->addMenu(lang('admin.menu.system', 'Sistem'), $manager->prefix . '/content/events', lang('admin.menu.events', 'Etkinlikler'), $parent, 'fa fa-calendar');

    static::$manager = $manager;
  }

  public function getIndex($lang = null) {
    $manager = static::$manager;

    $nav = new \stdClass();
    $nav->title = lang('admin.menu.create', 'Yeni Oluştur');
    $nav->url = url($manager->prefix . '/content/events/form');
    $nav->icon = 'fa-plus';

    $manager->put('subnavs', array(
      $nav
    ));

    $manager->set('caption', lang('admin.menu.events', 'Etkinlikler'));
    $manager->breadcrumb($manager->prefix . '/content', lang('admin.menu.content', 'İçerik'));
    $manager->breadcrumb($manager->prefix . '/content/events', lang('admin.menu.events', 'Etkinlikler'));

    $manager->put('list',
      Content::init()
        ->only('type', 'event')
        ->only(function ($content) {
          if (input('id', 0) > 0) {
            $content->where('id', input('id', 0));
          }

          if (Lang::has(input('language'))) {
            $content->where('language', input('language'));
          }

          if (strlen(input('publish_date-start')) && strlen(input('publish_date-end'))) {
            $content->whereBetween('publish_date', array(
              Carbon::createFromTimestamp(strtotime(input('publish_date-start'))),
              Carbon::createFromTimestamp(strtotime(input('publish_date-end')))
            ));
          } elseif (strlen(input('publish_date-start'))) {
            $content->where('publish_date', Carbon::createFromTimestamp(strtotime(input('publish_date-start'))));
          }

          if (strlen(input('title'))) {
            $content->where('title', 'like', '%' . input('title') . '%');
          }

          if (strlen(input('event_date-start')) && strlen(input('event_date-end'))) {
            $content->whereBetween('event_date', array(
              Carbon::createFromTimestamp(strtotime(input('event_date-start'))),
              Carbon::createFromTimestamp(strtotime(input('event_date-end')))
            ));
          } elseif (strlen(input('event_date-start'))) {
            $content->where('event_date', Carbon::createFromTimestamp(strtotime(input('event_date-start'))));
          }

          if (strlen(input('location'))) {
            $content->only('meta', array('location' => array('like', '%' . input('location') . '%')));
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

    return View::create('modules.others.events.list')->data($manager::data())->render();
  }

  public function getForm($lang = null, $id = 0) {
    $manager = static::$manager;

    $defaultPosters = array(
      'image' => View::getPath()->folder('layouts.assets.poster')->file('image.png'),
      'file' => View::getPath()->folder('layouts.assets.poster')->file('file.png'),
      'video' => View::getPath()->folder('layouts.assets.poster')->file('video.png'),
      'audio' => View::getPath()->folder('layouts.assets.poster')->file('audio.png'),
      'link' => View::getPath()->folder('layouts.assets.poster')->file('link.png')
    );

    $action = 'new';
    $actionTitle = lang('admin.label.create_new', 'Yeni Oluştur');

    $content = Content::init()
      ->where('type', 'event')
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

    $manager->set('caption', lang('admin.menu.events', 'Etkinlikler'));
    $manager->breadcrumb($manager->prefix . '/content', lang('admin.menu.content', 'İçerik'));
    $manager->breadcrumb($manager->prefix . '/content/events', lang('admin.menu.events', 'Etkinlikler'));
    $manager->breadcrumb($manager->prefix . '/content/events/form', lang('admin.menu.' . $action, $actionTitle));

    return View::create('modules.others.events.form')->data($manager::data())->render();
  }

  public function postForm($lang = null, $id = null) {
    $manager = static::$manager;

    $manager->app->response->setContentType('json');

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
      ->set('type', 'event')
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
          'date' => Carbon::createFromTimestamp(strtotime(input('meta-date'))),
          'location' => input('meta-location'),
          'use_map' => input('meta-use_map', array('no', 'yes')),
          'geo_lat' => input('meta-geo_lat', 0.0),
          'geo_lon' => input('meta-geo_lon', 0.0)
        ));

        $this->saveMedia($id, input('media_id'));
      });

    //Set poster
    $save->return = array_merge($save->returns(), array('poster' => $poster));

    return $save->forData();
  }

  public function deleteForm($lang = null, $ids = '') {
    $manager = static::$manager;

    $manager->app->response->setContentType('json');

    $ids = array_filter(explode(',', $ids), function ($id) {
      return (int) $id > 0;
    });

    if (count($ids)) {
      return Content::init()->delete($ids)->forData();
    } else {
      return Message::result(lang('message.nothing_done'))->forData();
    }
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
      return Message::result(lang('message.nothing_done'))->forData();
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