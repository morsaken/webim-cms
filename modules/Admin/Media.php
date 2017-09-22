<?php
/**
 * @author Orhan POLAT
 */

namespace Admin;

use \HTMLParser\Dom;
use \System\Content;
use \System\Media as SystemMedia;
use \System\Settings as SysSettings;
use \Webim\Image\Picture;
use \Webim\Library\Carbon;
use \Webim\Library\File;
use \Webim\Library\Message;
use \Webim\View\Manager as View;

class Media {

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
    $manager->addRoute($manager->prefix . '/content/media', __CLASS__ . '::getIndex');
    $manager->addRoute($manager->prefix . '/content/media/upload', __CLASS__ . '::postUpload', 'POST');
    $manager->addRoute($manager->prefix . '/content/media/link', __CLASS__ . '::postLink', 'POST');
    $manager->addRoute($manager->prefix . '/content/media/save/:id+', __CLASS__ . '::save', 'POST');
    $manager->addRoute($manager->prefix . '/content/media/delete/:id+', __CLASS__ . '::delete', 'DELETE');
    $manager->addRoute($manager->prefix . '/content/media/settings', __CLASS__ . '::getSettings');
    $manager->addRoute($manager->prefix . '/content/media/settings', __CLASS__ . '::postSettings', 'POST');

    $parent = $manager->addMenu(lang('admin.menu.system', 'Sistem'), $manager->prefix . '/content', lang('admin.menu.content', 'İçerik'), null, 'fa fa-edit');
    $manager->addMenu(lang('admin.menu.system', 'Sistem'), $manager->prefix . '/content/media', lang('admin.menu.media', 'Medya'), $parent, 'fa fa-picture-o');

    static::$manager = $manager;
  }

  /**
   * Index
   *
   * @param array $params
   *
   * @return string
   */
  public function getIndex($params = array()) {
    $manager = static::$manager;

    if ($manager->app->request->isAjax()) {
      $manager->app->response->setContentType('json');

      $list = array();

      $media = SystemMedia::init()->only(function ($content) {
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

        if (strlen(input('title'))) {
          $content->where('title', 'like', '%' . input('title') . '%');
        }

        if (strlen(input('role'))) {
          $content->only('meta', array(
            'role' => input('role')
          ));
        }
      })->orderBy('id', 'desc')->load(input('offset', 0), input('limit', 20))->with('files', array(
        'poster' => array(
          'default' => array(
            'image' => View::getPath()->folder('layouts.assets.poster')->file('image.png'),
            'file' => View::getPath()->folder('layouts.assets.poster')->file('file.png'),
            'video' => View::getPath()->folder('layouts.assets.poster')->file('video.png'),
            'audio' => View::getPath()->folder('layouts.assets.poster')->file('audio.png'),
            'link' => View::getPath()->folder('layouts.assets.poster')->file('link.png')
          )
        )
      ))->get();

      //List values
      $list['offset'] = $media->offset;
      $list['limit'] = $media->limit;
      $list['total'] = $media->total;
      $list['rows'] = array();

      foreach ($media->rows as $row) {
        //Default size
        $width = $height = 250;

        $item = array(
          'id' => $row->id,
          'role' => $row->role,
          'title' => $row->title,
          'extension' => ($row->file instanceof File ? $row->file->info('extension') : null),
          'description' => array_get($row, 'meta.description'),
          'poster' => null,
          'thumb' => null,
          'width' => $width,
          'height' => $height,
          'file' => ($row->file instanceof File ? $row->file->src() : null),
          'path' => ($row->file instanceof File ? '/' . $row->file->info('source') : null)
        );

        if ($row->poster->image instanceof Picture) {
          $item['poster'] = $row->poster->image->src();

          $orientation = $row->poster->image->orientation();

          if ($orientation == 'portrait') {
            $height *= 1.5;
          } elseif ($orientation == 'landscape') {
            $width *= 1.5;
          }

          $item['thumb'] = $row->poster->image->size($width, $height)->src();
          $item['width'] = $width;
          $item['height'] = $height;
        }

        if ($row->role == 'link') {
          $item['link'] = $row->meta->url;
        } elseif (in_array($row->role, ['audio', 'video'])) {
          $item['sources'] = array();

          foreach ($row->sources as $ext => $source) {
            $item['sources'][$ext] = $source->src();
          }
        }

        $list['rows'][] = $item;
      }

      return array_to($list);
    }

    $manager->put('subnavs', array(
      btn(lang('admin.menu.upload_file', 'Dosya Yükle'), '#upload', 'fa-upload'),
      btn(lang('admin.menu.create_link', 'Bağlantı Oluştur'), '#link', 'fa-link'),
      btn(lang('admin.menu.settings', 'Ayarlar'), url($manager->prefix . '/content/media/settings'), 'fa-cog')
    ));

    $manager->set('caption', lang('admin.menu.media', 'Medya'));
    $manager->breadcrumb($manager->prefix . '/content', lang('admin.menu.system', 'İçerik'));
    $manager->breadcrumb($manager->prefix . '/content/media', lang('admin.menu.media', 'Medya'));

    return View::create('content.media.list')->data($manager::data())->render();
  }

  /**
   * Upload
   *
   * @param array $params
   *
   * @return mixed
   */
  public function postUpload($params = array()) {
    $manager = static::$manager;

    //Set execution time to unlimited
    set_time_limit(0);

    $manager->app->response->setContentType('json');

    if ($manager->app->request->hasFile('media-file')) {
      //Media ids after upload
      $media_ids = array();

      foreach ($manager->app->request->files('media-file') as $file) {
        //Upload and save
        $upload = SystemMedia::init()
          ->extensions('image', conf('media.image_extensions'))
          ->extensions('file', conf('media.file_extensions'))
          ->extensions('video', conf('media.video_extensions'))
          ->extensions('audio', conf('media.audio_extensions'))
          ->upload($file, 'auto', array(
            'image_max_size' => conf('media.image_max_size', '1920x1080')
          ));

        if ($upload->success()) {
          $media_ids[] = $upload->returns('id');
        } else {
          return json_encode(array(
            'error' => lang('message.file_upload_error', [$upload->text()], 'Dosya yüklenemedi: %s')
          ));
        }
      }

      if (count($media_ids)) {
        $list = array();

        foreach (SystemMedia::init()->only(function ($query) use ($media_ids) {
          $query->whereIn('id', $media_ids);
        })->only('published')->load()->with('files', array(
          'poster' => array(
            'default' => array(
              'image' => View::getPath()->folder('layouts.assets.poster')->file('image.png'),
              'file' => View::getPath()->folder('layouts.assets.poster')->file('file.png'),
              'video' => View::getPath()->folder('layouts.assets.poster')->file('video.png'),
              'audio' => View::getPath()->folder('layouts.assets.poster')->file('audio.png'),
              'link' => View::getPath()->folder('layouts.assets.poster')->file('link.png')
            )
          )
        ))->get('rows') as $row) {
          $orientation = $row->poster->image->orientation();

          //Default size
          $width = $height = 250;

          if ($orientation == 'portrait') {
            $height *= 1.5;
          } elseif ($orientation == 'landscape') {
            $width *= 1.5;
          }

          $list[] = array(
            'id' => $row->id,
            'role' => $row->role,
            'title' => $row->title,
            'extension' => ($row->file instanceof File ? $row->file->info('extension') : null),
            'poster' => $row->poster->image->src(),
            'thumb' => $row->poster->image->size($width, $height)->src(),
            'width' => $width,
            'height' => $height,
            'file' => ($row->file instanceof File ? $row->file->src() : null),
            'path' => ($row->file instanceof File ? '/' . $row->file->info('source') : null)
          );
        }

        return array_to($list);
      }
    } else {
      return json_encode(array(
        'error' => lang('message.no_file_selected', 'Dosya seçilmemiş!')
      ));
    }

    return json_encode(array(
      'error' => lang('message.nothing_done', 'Herhangi bir işlem yapılmadı!')
    ));
  }

  /**
   * Link
   *
   * @param array $params
   *
   * @return array|\Webim\Library\Message
   */
  public function postLink($params = array()) {
    $manager = static::$manager;

    $manager->app->response->setContentType('json');

    if (strlen(input('link-embed-url'))) {
      if (strlen(input('link-title'))) {
        //Poster
        $poster_id = 0;

        if (strlen(input('link-poster'))) {
          //Get poster headers
          $headers = get_headers(input('link-poster'), 1);

          if (in_array(array_get($headers, 'Content-Type'), array('image/jpg', 'image/jpeg', 'image/png'))) {
            $import = SystemMedia::init()->extensions('image', conf('media.image_extensions'))->import('image', array(
              'url' => input('link-poster'),
              'title' => input('link-title')
            ), array(
              'image_max_size' => conf('media.image_max_size', '1920x1080')
            ));

            if ($import->success()) {
              $poster_id = $import->returns('id');
            }
          }
        }

        return Content::init()
          ->set('type', 'media')
          ->set('language', lang())
          ->set('url', uniqid())
          ->set('title', input('link-title'))
          ->set('publish_date', Carbon::now())
          ->save(function ($id) use ($poster_id) {
            $this->saveMeta($id, array(
              'role' => 'link',
              'url' => input('link-url'),
              'embed_url' => input('link-embed-url'),
              'description' => input('link-description'),
              'poster_id' => $poster_id
            ));
          })->forData();
      } else {
        return Message::result(lang('message.type_title', 'Bağlantı için başlık yazın!'))->forData();
      }
    } elseif (strlen(input('link-raw-url'))) {
      //Variables
      $url = input('link-raw-url');
      $title = '';
      $description = '';
      $images = array();

      if (!preg_match('/^http/', $url)) {
        $url = 'http://' . $url;
      }

      try {
        $dom = new Dom;
        $dom->loadFromUrl($url);

        if ($dom->find('title', 0)) {
          $title = $dom->find('title', 0)->text;
        }

        if ($dom->find('meta[name="description"]', 0)) {
          $description = $dom->find('meta[name="description"]', 0)->getAttribute('content');
        }

        foreach ($dom->find('img') as $img) {
          if (preg_match('/^.*\.(jpe?g|png)$/', $img->getAttribute('src'))) {
            $image = $img->getAttribute('src');

            if (!preg_match('/^[' . preg_quote($url, '/') . ']/', $image)) {
              $image = $url . $image;
            }

            $images[] = $image;
          }
        }

        foreach ($dom->find('meta[property^=og:]') as $og) {
          switch ($og->getAttribute('property')) {
            case 'og:title':

              //Title
              $title = $og->getAttribute('content');

              break;
            case 'og:description':

              //Description
              $description = $og->getAttribute('content');

              break;
            case 'og:image':

              $image = $og->getAttribute('content');

              if (!preg_match('/^[' . preg_quote($url, '/') . ']/', $image)) {
                $image = $url . $image;
              }

              //Image
              array_unshift($images, $image);

              break;
            case 'og:video:url':

              //Change path (embed)
              $url = $og->getAttribute('content');

              break;
          }
        }

        if ($embed = $dom->find('link[itemprop="embedURL"]', 0)) {
          $url = $embed->getAttribute('href');
        }

        return array_to(array(
          'link-url' => input('link-raw-url'),
          'link-embed-url' => html_entity_decode($url),
          'link-title' => html_entity_decode($title, ENT_QUOTES),
          'link-description' => html_entity_decode($description, ENT_QUOTES),
          'link-images' => $images
        ));
      } catch (\Exception $e) {
        return Message::result($e->getMessage())->forData();
      }
    }

    return Message::result(lang('message.paste_link_first', 'Önce bağlantıyı yapıştırın!'))->forData();
  }

  public function save($params = array()) {
    $manager = static::$manager;

    $manager->app->response->setContentType('json');

    $id = array_get($params, 'id');

    $save = Content::init()->set(array(
      'id' => $id,
      'title' => input('title')
    ))->save(function ($id) {
      $this->saveMeta($id, array(
        'description' => input('description')
      ), false);
    });

    return $save->forData();
  }

  public function delete($params = array()) {
    $manager = static::$manager;

    $manager->app->response->setContentType('json');

    $ids = array_filter(explode(',', array_get($params, 'id')), function ($id) {
      return (int) $id > 0;
    });

    //Total deleted
    $deleted = 0;

    //Return message
    $message = Message::result(lang('message.nothing_done', 'Herhangi bir işlem yapılmadı!'));

    if (count($ids)) {
      $media = SystemMedia::init()->whereIn('id', $ids)->load()->with('meta')->get('rows');

      foreach ($media as $item) {
        $delete = SystemMedia::init()->delete($item->id);

        if ($delete->success()) {
          $deleted++;

          if ($item->role != 'link') {
            File::path($item->meta->path)->remove();
          }
        }
      }

      if ($deleted) {
        $message->success = true;
        $message->text = choice('message.deleted', $deleted, [$deleted], array(
          'Kayıt silindi...',
          'Kayıtlar silindi...'
        ));
      }
    }

    return $message->forData();
  }

  public function getSettings($params = array()) {
    $manager = static::$manager;

    $manager->set('caption', lang('admin.menu.settings', 'Ayarlar'));
    $manager->breadcrumb($manager->prefix . '/content', lang('admin.menu.system', 'İçerik'));
    $manager->breadcrumb($manager->prefix . '/content/media', lang('admin.menu.media', 'Medya'));
    $manager->breadcrumb($manager->prefix . '/content/media/settings', lang('admin.menu.settings', 'Ayarlar'));

    return View::create('content.media.settings')->data($manager::data())->render();
  }

  public function postSettings($params = array()) {
    $manager = static::$manager;

    $manager->app->response->setContentType('json');

    $settings = array(
      'media.image_thumbnail_size' => input('media_image_thumbnail_size', '150x150'),
      'media.image_max_size' => input('media_image_max_size', '1920x1080'),
      'media.image_extensions' => input('media_image_extensions'),
      'media.file_extensions' => input('media_file_extensions'),
      'media.video_extensions' => input('media_video_extensions'),
      'media.audio_extensions' => input('media_audio_extensions')
    );

    return SysSettings::init()->saveAll('system', $settings)->forData();
  }

}