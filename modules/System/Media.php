<?php
/**
 * @author Orhan POLAT
 */

namespace System;

use \Webim\Image\Picture;
use \Webim\Library\Carbon;
use \Webim\Library\File;
use \Webim\Library\Message;

class Media extends Content {

  /**
   * Valid roles
   *
   * @var array
   */
  protected $roles = array(
    'image', 'file', 'video', 'audio', 'link'
  );

  /**
   * Valid extensions
   *
   * @var array
   */
  protected $extensions = array(
    'image' => array(),
    'file' => array(),
    'video' => array(),
    'audio' => array()
  );

  /**
   * Constructor
   */
  public function __construct() {
    parent::__construct();

    $this->where('type', 'media');
  }

  /**
   * Constructor
   *
   * @return Media
   */
  public static function init() {
    return new self();
  }

  /**
   * Set extensions
   *
   * @param string $role
   * @param array $extensions
   *
   * @return $this
   */
  public function extensions($role, $extensions = array()) {
    if (isset($this->extensions[$role])) {
      if (is_string($extensions)) {
        $extensions = array_filter(array_map(function ($ext) {
          return trim($ext);
        }, explode(',', $extensions)), function ($ext) {
          return strlen($ext);
        });
      }

      if (!count($extensions)) {
        $this->extensions[$role][''] = '';
      }

      foreach ((array) $extensions as $extension) {
        $this->extensions[$role][$extension] = '*.' . $extension;
      }
    }

    return $this;
  }

  /**
   * Upload
   *
   * @param array $file
   * @param string $role
   * @param array $params
   * @param array $extraMeta
   *
   * @return \Webim\Library\Message
   */
  public function upload(array $file, $role = 'auto', $params = array(), $extraMeta = array()) {
    //Set timeout limit
    set_time_limit(0);

    //Return message
    $message = Message::result(lang('message.nothing_done', 'Herhangi bir işlem yapılmadı!'));

    if (isset($file['tmp_name'])) {
      //Role
      if ($role == 'auto') {
        $role = $this->discoverRole($file);
      }

      if (!isset($this->roles[$role])) {
        $url = uniqid();

        try {
          $upload = File::path('assets.' . $role . '.' . Carbon::now()->format('Y.m') . '.' . $url)
            ->create()
            ->fileIn(array_get($this->extensions, $role, array()))
            ->upload($file);

          $media = parent::init()
            ->set('type', 'media')
            ->set('language', lang())
            ->set('url', $url)
            ->set('title', $upload->basename)
            ->set('publish_date', Carbon::now())
            ->save(function ($id) use ($upload, $role, $params, $extraMeta) {
              $file = $upload->file;

              if (array_get($params, 'file_max_size') && (array_get($params, 'file_max_size') < $file->size())) {
                throw new \Exception(lang('message.file.size_exceeded', 'Dosya boyutu çok büyük!'));
              }

              if (($role == 'image') && ($size = array_get($params, 'image_max_size'))) {
                //Current resolution
                $picture = Picture::file($file);

                //New sizes
                list($width, $height) = explode('x', $size);

                if ($picture->orientation() == 'portrait') {
                  //Change sizes
                  list($height, $width) = explode('x', $size);
                }

                $picture->fit($width, $height);
              }

              $meta = array(
                'role' => $role,
                'path' => $file->rawPath,
                'name' => $upload->name,
                'extension' => $upload->extension,
                'file_size' => $file->size()
              );

              foreach ((array) $extraMeta as $metaKey => $metaValue) {
                if (!isset($meta[$metaKey])) {
                  if (!is_scalar($metaValue)) {
                    $metaValue = serialize($metaValue);
                  }

                  $meta[$metaKey] = $metaValue;
                }
              }

              if ($role == 'image') {
                $meta['image_size'] = Picture::file($file)->resolution();
              }

              $this->saveMeta($id, $meta);
            });

          if ($media->success()) {
            $message->success = true;
            $message->text = $media->text();
            $message->return = array(
              'id' => $media->returns('id'),
              'role' => $role,
              'src' => $upload->file->src()
            );
          } else {
            $message->text = $media->text();
          }
        } catch (\Exception $e) {
          $message->text = $e->getMessage();
        }
      } else {
        $message->text = lang('message.invalid_media_role', [$role], 'Geçersiz medya türü: %s');
      }
    } else {
      $message->text = lang('message.pick_file', 'Dosya seçiniz!');
    }

    return $message;
  }

  /**
   * Discover file role
   *
   * @param array $file
   *
   * @return string
   */
  protected function discoverRole(array $file) {
    //Return
    $discovered = 'file';

    if (isset($file['name'])) {
      $fileExtension = strtolower(trim(strrchr($file['name'], '.'), '.'));

      foreach ($this->extensions as $role => $extensions) {
        foreach ($extensions as $extension) {
          if (str_replace('*.', '', $extension) == $fileExtension) {
            $discovered = $role;
            break;
          }
        }
      }
    }

    return $discovered;
  }

  /**
   * Import external content
   *
   * @param string $role
   * @param array $path
   * @param array $params
   *
   * @return \Webim\Library\Message
   */
  public function import($role, array $path, $params = array()) {
    //Return message
    $message = Message::result(lang('message.nothing_done', 'Herhangi bir işlem yapılmadı!'));

    try {
      if (!isset($this->roles[$role])) {
        throw new \Exception(lang('message.invalid_media_role', array($role), 'Geçersiz medya türü: %s'));
      }

      if (ini_get('allow_url_fopen') !== 'On') {
        throw new \Exception(lang('admin.message.php_ini_fopen_error', '"php.ini" dosyasından "allow_url_fopen" özelliğini "On" yapmanız gerekmektedir!'));
      }

      if (!isset($path['url']) || !isset($path['title'])) {
        throw new \Exception(lang('message.type_target', 'Hedefi yazınız!'));
      }

      if (!function_exists('curl_init')) {
        throw new \Exception(lang('message.need_curl_library', 'CURL kütüphanesi gerekli!'));
      }

      //Poster url
      $url = uniqid();

      //Create curl
      $ch = @curl_init($path['url']);
      @curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      @curl_exec($ch);

      //Get target url's mime type
      $mime = @curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

      if (!$mime || (strpos($mime, 'image/') !== 0)) {
        throw new \Exception(lang('message.target_is_not_an_image', 'Hedef resim değil!'));
      }

      //Extension
      $extension = '.' . strtolower(str_replace('image/', '', $mime));

      $destination = File::path(
        'assets.' . $role . '.' . Carbon::now()->format('Y.m') . '.' . $url,
        slug($path['title']) . $extension
      )->create();

      //Target content
      $content = @file_get_contents($path['url']);

      if ($content === false) {
        //Remove created destination
        $destination->folder()->remove();

        throw new \Exception(lang('message.target_not_copied_into_system', 'Hedef sisteme kopyalanamadı!'));
      }

      //Save to file
      $destination->write($content);

      $media = parent::init()
        ->set('type', 'media')
        ->set('language', lang())
        ->set('url', $url)
        ->set('title', $path['title'])
        ->set('publish_date', Carbon::now())
        ->save(function ($id) use ($destination, $role, $params) {
          if (array_get($params, 'max_size') && (array_get($params, 'max_size') < $destination->size())) {
            throw new \Exception(lang('message.file.size_exceeded', 'Dosya boyutu çok büyük!'));
          }

          if (($role == 'image') && ($size = array_get($params, 'image_max_size'))) {
            list($width, $height) = explode('x', $size);

            //Current resolution
            Picture::file($destination)->fit($width, $height);
          }

          $meta = array(
            'role' => $role,
            'path' => $destination->rawPath,
            'name' => $destination->baseName,
            'extension' => $destination->extension(),
            'file_size' => $destination->size()
          );

          if ($role == 'image') {
            $meta['image_size'] = Picture::file($destination)->resolution();
          }

          $this->saveMeta($id, $meta);
        });

      if (!$media->success()) {
        throw new \Exception($media->text());
      }

      $message->success = true;
      $message->text = $media->text();
      $message->return = array(
        'id' => $media->returns('id'),
        'src' => $destination->src()
      );
    } catch (\Exception $e) {
      $message->text = $e->getMessage();
    }

    return $message;
  }

  /**
   * Files
   *
   * @param array $rows
   * @param array $params
   *
   * @return array
   */
  protected function files($rows, $params = array()) {
    if (in_array(__FUNCTION__, $this->called)) {
      return $rows;
    }

    //Add called class
    $this->called[] = __FUNCTION__;

    //Call meta
    $this->with('meta');

    //Posters
    $posters = array();

    //Poster ids
    $ids = array();

    foreach ($rows as $row) {
      if (isset($row->meta->poster_id) && ($row->meta->poster_id > 0)) {
        $ids[$row->meta->poster_id][$row->id] = $row->id;
      }
    }

    if (count($ids)) {
      foreach (self::init()->whereIn('id', array_keys($ids))->only('meta', array(
        'role' => 'image'
      ))->load()->get('rows') as $row) {
        if (isset($row->file) && $row->file) {
          foreach ($ids[$row->id] as $media_id) {
            $posters[$media_id] = $row->file;
          }
        }
      }
    }

    return array_map(function (&$row) use ($rows, $posters, $params) {
      //Default file
      $file = null;

      //Video and audio extra sources
      $sources = array();

      //Default poster
      $poster = new \stdClass();
      $poster->id = null;
      $poster->image = null;

      //Poster size
      list($width, $height) = array_pad(explode('x', array_get($params, 'poster.size', '0x0')), 2, '0');

      if (isset($row->meta->path) && isset($row->meta->name) && isset($row->meta->extension)) {
        //Set role
        $row->role = isset($row->meta->role) ? $row->meta->role : 'file';

        $file = File::path($row->meta->path, $row->meta->name);

        if ($file->exists()) {
          $row->name = $row->meta->name;
          $row->extension = $row->meta->extension;

          if ($row->role == 'image') {
            $poster->id = $row->id;
            $poster->image = Picture::file($file);

            if ($width || $height) {
              $poster->image = $poster->image->size($width, $height);
            }
          }

          if (in_array($row->role, array('video', 'audio'))) {
            $sources[$row->extension] = $file;

            foreach ($file->folder()->folders() as $folder) {
              foreach ($folder->files() as $source) {
                $sources[$folder->name] = $source;
              }
            }
          }
        }
      } elseif (isset($row->meta->url)) {
        $row->role = isset($row->meta->role) ? $row->meta->role : 'link';
        $row->link = isset($row->meta->embed_url) ? $row->meta->embed_url : $row->meta->url;
      }

      if (isset($posters[$row->id])) {
        $poster->id = $row->meta->poster_id;
        $poster->image = Picture::file($posters[$row->id]);

        if ($width || $height) {
          $poster->image = $poster->image->size($width, $height);
        }
      } elseif (!strlen($poster->image)) {
        //Default image
        $default = array_get($params, 'poster.default');

        if (is_array($default)) {
          $default = array_get($params, 'poster.default.' . $row->role);

          if (is_array($default)) {
            $default = null;
          }
        }

        if ($default instanceof File) {
          $poster->image = Picture::file($default);

          if ($width || $height) {
            $poster->image = $poster->image->size($width, $height);
          }
        } else {
          $poster->image = $default;
        }
      }

      $row->file = $file;
      $row->sources = $sources;
      $row->poster = $poster;
    }, $rows);
  }

  /**
   * Overwrite get
   *
   * @param null|string $key
   * @param null|mixed $default
   *
   * @return \stdClass
   */
  public function get($key = null, $default = null) {
    $this->with('files');

    return parent::get($key, $default);
  }

}