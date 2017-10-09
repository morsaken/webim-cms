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

class Publish {

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
    $manager->addRoute($manager->prefix . '/publishes', __CLASS__ . '::getIndex');
    $manager->addRoute($manager->prefix . '/publishes/form/?:id', __CLASS__ . '::getForm');
    $manager->addRoute($manager->prefix . '/publishes/form/?:id', __CLASS__ . '::postForm', 'POST');
    $manager->addRoute($manager->prefix . '/publishes/form/:id+', __CLASS__ . '::deleteForm', 'DELETE');

    $manager->addMenu(lang('admin.menu.modules', 'Modüller'), $manager->prefix . '/publishes', lang('admin.menu.publishes', 'Yayınlar'), null, 'fa fa-book');

    static::$manager = $manager;
  }

  public function getIndex($params = array()) {
    $manager = static::$manager;

    $nav = new \stdClass();
    $nav->title = lang('admin.menu.create', 'Yeni Oluştur');
    $nav->url = url($manager->prefix . '/publishes/form');
    $nav->icon = 'fa-plus';

    $manager->put('subnavs', array(
      $nav
    ));

    $manager->set('caption', lang('admin.menu.publishes', 'Yayınlar'));
    $manager->breadcrumb($manager->prefix . '/publishes', lang('admin.menu.publishes', 'Yayınlar'));

    $manager->put('categories',
      Content::init()
        ->where('type', 'category')
        ->load()
        ->getListIndented('&nbsp;&nbsp;')
    );

    $manager->put('list',
      Content::init()
        ->where('type', 'publish')
        ->only('category', input('categories'))
        ->only(function ($content) {
          if (input('id', 0) > 0) {
            $content->where('id', input('id', 0));
          }

          if (strlen(input('title'))) {
            $content->where('title', 'like', '%' . input('title') . '%');
          }
        })->orderBy(input('orderby', 'id'), input('order', ['desc', 'asc']))
        ->load(input('offset', 0), input('limit', 20))
        ->with('category')
        ->with(function ($rows) {
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

    return View::create('modules.others.publish.list')->data($manager::data())->render();
  }

  public function getForm($params = array()) {
    $manager = static::$manager;

    $manager->put('categories',
      Content::init()
        ->where('type', 'category')
        ->load()
        ->getListIndented('&nbsp;&nbsp;')
    );

    $id = array_get($params, 'id', 0);
    $action = 'new';
    $actionTitle = lang('admin.label.create_new', 'Yeni Oluştur');

    $content = Content::init()
      ->where('type', 'publish')
      ->where('id', $id)
      ->load()->with('meta')->with('category')->with('media', array(
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
      ))->with(function ($rows) {
        return array_map(function (&$row) use ($rows) {
          $categories = array();

          foreach ($row->category as $category) {
            $categories[$category->id] = $category->id;
          }

          $row->categories = $categories;
        }, $rows);
      })->get('rows.0');

    if ($content) {
      $action = 'edit';
      $actionTitle = lang('admin.label.edit', 'Düzenle');

      $manager->put('content', $content);
    }

    $manager->set('caption', lang('admin.menu.publishes', 'Yayınlar'));
    $manager->breadcrumb($manager->prefix . '/publishes', lang('admin.menu.publishes', 'Yayınlar'));
    $manager->breadcrumb($manager->prefix . '/publishes/form', lang('admin.menu.' . $action, $actionTitle));

    return View::create('modules.others.publish.form')->data($manager::data())->render();
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
    $url = Content::makeUrl(input('url'), input('title'), $publish_date->format('Y/m/d'), (conf('news.url_with_date', 'no') == 'yes'));

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
    ))->set(array(
      'id' => (!is_null($id) ? $id : input('id', 0)),
      'type' => 'publish',
      'language' => input('language', lang()),
      'url' => $url,
      'name' => (strlen(input('name')) ? slug(input('name')) : null),
      'title' => input('title'),
      'publish_date' => $publish_date,
      'expire_date' => $expire_date,
      'version' => input('version', 0),
      'active' => input('active', array('false', 'true'))
    ))->save(function ($id) use ($manager, &$poster) {
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

      $this->saveCategory($id, input('category'));

      $this->saveMeta($id, array(
        'poster_id' => $poster['id'],
        'summary' => input('meta-summary'),
        'show_summary_inside' => input('meta-show_summary_inside', array('no', 'yes')),
        'content' => raw_input('meta-content'),
        'author' => input('meta-author'),
        'publisher' => input('meta-publisher'),
        'publish_year' => input('meta-publish_year'),
        'publish_language' => input('meta-publish_language')
      ));

      $this->saveMedia($id, input('media_id'));

      $this->saveTags($id, explode(',', input('tags')));
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

}