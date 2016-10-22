<?php
/**
 * @author Orhan POLAT
 */

namespace System;

use \System\Property\Manager as Property;
use \Webim\Database\Manager as DB;
use \Webim\Http\Session;
use \Webim\Image\Picture;
use \Webim\Library\Carbon;
use \Webim\Library\Controller;
use \Webim\Library\File;
use \Webim\Library\Language;
use \Webim\Library\Message;
use \Webim\Library\Str;

class Content extends Controller {

 /**
  * Block size
  */
 const SIZE = 65535;

 /**
  * Constructor
  */
 public function __construct() {
  parent::__construct(DB::table('sys_content'));
 }

 /**
  * Static creator
  *
  * @return self
  */
 public static function init() {
  return new self();
 }

 /**
  * Save
  *
  * @return Message
  */
 public function save() {
  $message = Message::result(lang('message.nothing_done', 'Herhangi bir işlem yapılmadı!'));

  try {
   if (!parent::unique('parent_id', 'type', 'language', 'url')) {
    throw new \Exception(lang('message.duplicate_entry', [array_get($this->data, 'url')], 'Bu isimde bir kayıt var: %s'));
   }

   $save = call_user_func_array(array(
    'parent',
    'save'
   ), func_get_args());

   $message->success = true;
   $message->text = lang('message.saved', 'Kaydedildi...');
   $message->return = $save;
  } catch (\Exception $e) {
   if (stripos($e->getMessage(), 'duplicate entry') !== false) {
    $message->text = lang('message.duplicate_entry', [$e->getMessage()], 'Bu isimde bir kayıt var: %s');
   } elseif (stripos($e->getMessage(), 'version mismatch') !== false) {
    $message->text = lang('message.version_mismatch', 'Versiyon hatası! Sayfayı yenileyin!');
   } else {
    $message->text = $e->getMessage();
   }
  }

  return $message;
 }

 /**
  * Filters with only
  *
  * @param string $only
  * @param array $params
  *
  * @return $this
  */
 public function only($only, $params = array()) {
  if ($only instanceof \Closure) {
   call_user_func($only, $this, $params);
  } else {
   switch ($only) {
    case 'type':

     $this->where('type', (string) $params);

     break;
    case 'root':

     $this->where(function($query) {
      $query->whereNull('parent_id');
      $query->orWhere('parent_id', 0);
     });

     break;
    case 'parent':

     $this->where('parent_id', function($query) use ($params) {
      $query->select('parent.id')
       ->from('sys_content as parent')
       ->where('parent.type', '=', DB::func(null, 'sys_content.type'))
       ->where('parent.language', '=', DB::func(null, 'sys_content.language'))
       ->where('parent.url', (string) $params);
     });

     break;
    case 'language':

     $lang = Language::current()->alias();

     if (is_string($params) && Language::has($params)) {
      $lang = $params;
     }

     $this->where('language', $lang);

     break;
    case 'active':

     $this->where('active', 'true');

     break;
    case 'published':

     //Set active
     $this->only('active');

     $this->where('publish_date', '<=', DB::val(Carbon::now()))->where(DB::func('IFNULL', array(
      'expire_date',
      DB::val(Carbon::now())
     )), '>=', DB::val(Carbon::now()));

     break;
    case 'category':

     //Split params into id and url params
     $ids = array();
     $urls = array();

     foreach ((array) $params as $param) {
      if (is_numeric($param) && ($param > 0)) {
       $ids[$param] = $param;
      } elseif (is_scalar($param) && strlen($param)) {
       $urls[$param] = $param;
      }
     }

     if (count($ids) || count($urls)) {
      $this->whereExists(function ($exists) use ($ids, $urls) {
       $exists->select('cat_c.content_id')
        ->from('sys_content_category as cat_c')
        ->join('sys_content as cat', function ($join) {
         $join->on('cat.id', '=', 'cat_c.category_id');
         $join->on('cat.type', '=', DB::raw('category'));
         $join->on('cat.active', '=', DB::raw('true'));
        });

       if (count($ids)) {
        $exists->whereIn('cat.id', $ids);
       }

       if (count($urls)) {
        $exists->whereIn('cat.url', $urls);
       }

       $exists->where('cat_c.content_id', DB::func(null, 'sys_content.id'));
      });
     }

     break;
    case 'tag':

     //Params may be come as string
     $tags = !is_array($params) ? array((string) $params) : (array) $params;

     if (count($tags)) {
      $this->whereExists(function ($exists) use ($tags) {
       $exists->select('tag.content_id')
        ->from('sys_content_tag as tag')
        ->whereIn('tag.tag', $tags)
        ->where('tag.content_id', DB::func(null, 'sys_content.id'));
      });
     }

     break;
    case 'meta':

     if (count((array) $params)) {
      $this->where(function ($query) use ($params) {
       //Number of sql
       $num = 0;

       foreach ((array) $params as $key => $value) {
        $query->whereExists(function ($exists) use ($num, $key, $value) {
         $exists->select('meta' . $num . '.content_id')
          ->from('sys_content_meta as meta' . $num)
          ->where('meta' . $num . '.content_id', DB::func(null, 'sys_content.id'))
          ->where(function ($query) use ($num, $key, $value) {
           $query->where('meta' . $num . '.key', $key);

           if (is_scalar($value)) {
            $query->where('meta' . $num . '.value', $value);
           } else {
            if (is_array(array_get($value, 0))) {
             $query->whereIn('meta' . $num . '.value', array_get($value, 0, array()));
            } else {
             $query->where('meta' . $num . '.value', array_get($value, 0, '='), array_get($value, 1));
            }
           }
          });
        });

        $num++;
       }
      });
     }

     break;
   }
  }

  return $this;
 }

 /**
  * Order by category order
  *
  * @param mixed $category
  *
  * @return $this
  */
 public function orderByCategory($category) {
  //Split params into id and url params
  $category_id = 0;
  $category_url = null;

  if (is_numeric($category) && ($category > 0)) {
   $category_id = $category;
  } elseif (is_scalar($category) && strlen($category)) {
   $category_url = $category;
  }

  if ($category_id || $category_url) {
   $order = DB::table('sys_content_category as cat_c')
    ->join('sys_content as cat', function ($join) {
     $join->on('cat.id', '=', 'cat_c.category_id');
     $join->on('cat.type', '=', DB::raw('category'));
     $join->on('cat.active', '=', DB::raw('true'));
    });

   if ($category_id) {
    $order->where('cat.id', $category_id);
   } elseif (strlen($category_url)) {
    $order->where('cat.url', $category_url);
   }

   $order = $order->where('cat_c.content_id', DB::func(null, 'sys_content.id'))
    ->addSelect('cat_c.order')
    ->toSql(true);

   $this->orderBy(DB::raw('(' . $order . ')'), 'asc');
  }

  return $this;
 }

 /**
  * Shortcut for published content
  *
  * @param string $type
  * @param null|int $limit
  * @param array $categories
  * @param \Closure $filters
  *
  * @return Content
  */
 public static function published($type = null, $limit = null, $categories = array(), \Closure $filters = null) {
  $content = new self();
  $content->only('type', $type);
  $content->only('language');

  if (!is_array($categories)) {
   $categories = array($categories);
  }

  if (count($categories)) {
   $content->only('category', $categories);
   $content->orderByCategory(array_first($categories));
  }

  if ($filters instanceof \Closure) {
   $content->only($filters);
  }

  $content->only('published');
  $content->orderBy('order')->orderBy('publish_date', 'desc');

  //Limit and offset
  $limits = array(
   'offset' => 0,
   'limit' => null
  );

  if (!is_null($limit) && !is_array($limit)) {
   array_set($limits, 'limit', (int) $limit);
  } else {
   array_set($limits, 'offset', array_get($limit, 'offset', 0));
   array_set($limits, 'limit', array_get($limit, 'limit'));
  }

  return $content->load($limits['offset'], $limits['limit']);
 }

 /**
  * Popular content
  *
  * @param string $type
  * @param null|int $limit
  * @param array $categories
  * @param \Closure $filters
  *
  * @return Content
  */
 public static function popular($type = null, $limit = null, $categories = array(), \Closure $filters = null) {
  return static::published($type, $limit, $categories, function($content) use ($filters) {
   $content->addSelect(
    '*',
    DB::func(' ', DB::raw(DB::table('sys_content_hit')
     ->where('content_id', DB::func(null, 'sys_content.id'))
     ->select(DB::func('COUNT', '*'))
     ->toSql()), 'hit')
   )->orderBy('hit', 'desc');

   if ($filters instanceof \Closure) {
    $content->only($filters);
   }
  });
 }

 /**
  * Duplicate content
  *
  * @param int $id
  * @param string $language
  * @param array $category
  *
  * @return Message
  */
 public static function duplicate($id, $language, $category = array()) {
  //Default return
  $message = Message::result(lang('message.nothing_done', 'Herhangi bir işlem yapılmadı!'));

  //Get content
  $content = DB::table('sys_content')->where('id', $id)->first();

  if ($content) {
   if ($language != array_get($content, 'language')) {
    //Meta
    $meta = array();

    foreach (DB::table('sys_content_meta')->where('content_id', $id)->get() as $row) {
     $meta[array_get($row, 'key')] = array_get($row, 'value');
    }

    //Media
    $media = array();

    foreach (DB::table('sys_content_media')->where('content_id', $id)->orderBy('order')->get() as $row) {
     $media[] = array_get($row, 'media_id');
    }

    try {
     $save = static::init()->set(array(
      'type' => array_get($content, 'type'),
      'language' => $language,
      'url' => array_get($content, 'url'),
      'title' => array_get($content, 'title'),
      'publish_date' => array_get($content, 'publish_date'),
      'expire_date' => array_get($content, 'expire_date'),
      'active' => array_get($content, 'active')
     ))->save();

     if ($save->success()) {
      $id = $save->returns('id');

      static::init()->saveCategory($id, $category);
      static::init()->saveMeta($id, $meta);
      static::init()->saveMedia($id, $media);
     } else {
      throw new \ErrorException($save->text());
     }

     $message->success = true;
     $message->text = $save->text();
    } catch (\ErrorException $e) {
     $message->text = $e->getMessage();
    }
   } else {
    $message->text = lang('admin.message.given_language_same_as_content_language', 'Çoklanacak dil ile seçilen dil aynı!');
   }
  } else {
   $message->text = lang('message.content_not_found', 'İçerik bulunamadı!');
  }

  return $message;
 }

 /**
  * Parents
  *
  * @param array $rows
  * @param array $params
  *
  * @return array
  */
 protected function parents($rows, $params = array()) {
  if (in_array(__FUNCTION__, $this->called)) {
   return $rows;
  }

  //Add called class
  $this->called[] = __FUNCTION__;

  //Get parent ids
  $parent_ids = array();

  foreach ($rows as $row) {
   if ($row->parent_id) {
    $parent_ids[$row->parent_id] = $row->parent_id;
   }
  }

  if (count($parent_ids)) {
   foreach (self::init()->only(function ($query) use ($parent_ids, $params) {
    $query->whereIn('id', $parent_ids);

    $this->childProcess($query, $params);
   })->load()->with(function() use ($params) {
    $this->childProcess($this, $params);
   })->get('rows') as $parent) {
    foreach ($rows as $row) {
     if ($row->parent_id == $parent->id) {
      $row->parent = $parent;
     }
    }
   }
  }

  return $rows;
 }

 /**
  * Children
  *
  * @param array $rows
  * @param array $params
  *
  * @return array
  */
 protected function children($rows, $params = array()) {
  if (in_array(__FUNCTION__, $this->called)) {
   return $rows;
  }

  //Add called class
  $this->called[] = __FUNCTION__;

  foreach (self::init()->only(function ($query) use ($params) {
   $query->whereIn('parent_id', $this->ids());

   $this->childProcess($query, $params);
  })->load()->with('children', $params)->with(function() use ($params) {
   $this->childProcess($this, $params);
  })->get('rows') as $child) {
   foreach ($rows as $row) {
    if (!isset($row->children)) {
     $row->children = array();
    }

    if ($row->id == $child->parent_id) {
     $row->children[] = $child;
    }
   }
  }

  return $rows;
 }

 /**
  * Child process for children action
  *
  * @param Content|DB $class
  * @param array $actions
  */
 private function childProcess($class, $actions = array()) {
  foreach ((array) $actions as $key => $params) {
   if (is_numeric($key)) {
    foreach ($params as $method => $options) {
     if (is_callable(array($class, $method))) {
      call_user_func_array(array(
       $class, $method
      ), (array) $options);
     }
    }
   } elseif (is_callable(array($class, $key))) {
    $method = $key;

    if (is_array($params)) {
     foreach ($params as $action => $options) {
      if (is_numeric($action)) {
       if (!is_array($options)) {
        $options = array($options);
       }
       call_user_func_array(array(
        $class, $method
       ), $options);
      } else {
       call_user_func(array(
        $class, $method
       ), $action, $options);
      }
     }
    } else {
     call_user_func(array(
      $class, $method
     ), $params);
    }
   }
  }
 }

 /**
  * Meta data
  *
  * @param array $rows
  * @param array $params
  *
  * @return array
  */
 protected function meta($rows, $params = array()) {
  if (in_array(__FUNCTION__, $this->called)) {
   return $rows;
  }

  //Add called class
  $this->called[] = __FUNCTION__;

  //Prepare values
  $values = array();

  foreach (DB::table('sys_content_meta')
            ->whereIn('content_id', $this->ids())
            ->get() as $row) {
   //Change key into array
   $key = implode('.', array_pad(explode('.', array_get($row, 'key')), 2, 0));

   //Set
   array_set($values, array_get($row, 'content_id') . '.' . $key, array_get($row, 'value'));
  }

  foreach ($values as $id => $list) {
   foreach ($list as $key => &$value) {
    if (is_array($value)) {
     //First sort by key
     ksort($value);

     //Convert value
     $value = implode('', $value);
    }

    if (Str::text($value)->isSerialized()) {
     $value = unserialize($value);
    }

    //Convert to text
    $values[$id][$key] = $value;
   }
  }

  return array_map(function (&$row) use ($values) {
   $row->meta = new \stdClass();

   foreach (array_get($values, $row->id, array()) as $key => $value) {
    $row->meta->$key = $value;
   }
  }, $rows);
 }

 /**
  * Save meta data
  *
  * @param int $content_id
  * @param array $meta
  * @param bool $reset
  */
 public function saveMeta($content_id, $meta = array(), $reset = true) {
  if ($content_id > 0) {
   if ($reset) {
    //Remove all
    DB::table('sys_content_meta')->where('content_id', $content_id)->delete();
   } else {
    foreach (array_keys($meta) as $key) {
     DB::table('sys_content_meta')
      ->where('content_id', $content_id)
      ->where(function ($query) use ($key) {
       $query->where('key', $key);
       $query->orWhere('key', 'like', $key . '.%');
      })->delete();
    }
   }

   //Add
   $inserts = array();

   foreach ((array) $meta as $key => $value) {
    if (strlen($key)) {
     if (!is_scalar($value)) {
      $value = serialize($value);
     }

     //Split into size
     $values = str_split($value, static::SIZE);

     foreach ($values as $num => $text) {
      $newKey = $key;

      if ($num > 0) {
       $newKey = $key . '.' . $num;
      }

      $inserts[] = array(
       'content_id' => $content_id,
       'key' => $newKey,
       'value' => $text
      );
     }
    }
   }

   foreach ($inserts as $insert) {
    DB::table('sys_content_meta')->insert($insert);
   }
  }
 }

 /**
  * Categories
  *
  * @param array $rows
  * @param array $params
  *
  * @return array
  */
 protected function category($rows, $params = array()) {
  if (in_array(__FUNCTION__, $this->called)) {
   return $rows;
  }

  //Add called class
  $this->called[] = __FUNCTION__;

  //Get category ids
  $ids = array();

  foreach (DB::table('sys_content_category')
            ->whereIn('content_id', $this->ids())
            ->orderBy('order')
            ->get('content_id', 'category_id') as $row) {
   $ids[array_get($row, 'category_id')][array_get($row, 'content_id')] = array_get($row, 'content_id');
  }

  //Category list
  $list = array();

  if (count($ids)) {
   //Set content ids of the category list
   $content_ids = array_keys($ids);

   foreach (self::init()->only(function ($query) use ($content_ids, $params) {
    $query->where('type', 'category');
    $query->whereIn('id', $content_ids);
   })->only('published')->load()->with('meta')->get('rows') as $row) {
    foreach (array_get($ids, $row->id) as $content_id) {
     $list[$content_id][] = $row;
    }
   }
  }

  return array_map(function (&$row) use ($list) {
   $row->category = array();

   foreach ($list as $id => $category) {
    if ($id == $row->id) {
     $row->category = $category;
    }
   }
  }, $rows);
 }

 /**
  * Save category
  *
  * @param int $content_id
  * @param array $category
  * @param bool $reset
  */
 public function saveCategory($content_id, $category = array(), $reset = true) {
  if ($content_id > 0) {
   if ($reset) {
    //Remove all
    DB::table('sys_content_category')->where('content_id', $content_id)->delete();
   }

   //Id list for save
   $category_ids = array_filter((array) $category, function ($id) {
    return is_numeric($id) && ($id > 0);
   });

   //Maybe category comes with name
   $category_urls = array_filter((array) $category, function ($id) {
    return is_string($id) && strlen($id);
   });

   if (count($category_urls)) {
    $query = DB::table('sys_content')->whereType('category')->whereIn('url', $category_urls)->lists('id');

    foreach ($query as $id) {
     if (!in_array($id, $category_ids)) {
      $category_ids[] = $id;
     }
    }
   }

   //Remove repeats
   array_unique($category_ids);

   //Add
   $inserts = array();

   foreach ($category_ids as $category_id) {
    $inserts[] = array(
     'content_id' => $content_id,
     'category_id' => $category_id
    );
   }

   if (count($inserts)) {
    DB::table('sys_content_category')->insert($inserts);
   }
  }
 }

 /**
  * Images
  *
  * @param array $rows
  * @param array $params
  *
  * @return array
  */
 protected function media($rows, $params = array()) {
  if (in_array(__FUNCTION__, $this->called)) {
   return $rows;
  }

  //Add called class
  $this->called[] = __FUNCTION__;

  //Media names
  $names = array();

  //Media ids
  $ids = array();

  foreach (DB::table('sys_content_media')
            ->whereIn('content_id', $this->ids())
            ->orderBy('order')
            ->get('content_id', 'media_id', 'name') as $row) {
   //Media name
   $name = strlen(array_get($row, 'name')) ? array_get($row, 'name') : 'media';
   $names[array_get($row, 'media_id')] = $name;
   $ids[array_get($row, 'media_id')][array_get($row, 'content_id')] = array_get($row, 'content_id');
  }

  //Image list
  $list = array();

  if (count($ids)) {
   //Set content ids of the image list
   $content_ids = array_keys($ids);

   //Get media
   $media = Media::init()->only(function ($query) use ($content_ids, $params) {
    $query->whereIn('id', $content_ids);

    /**
     * image, link, movie, ...
     */
    if (array_get($params, 'only')) {
     $query->only('meta', array(
      'role' => array_get($params, 'only')
     ));
    }

    /**
     * jpg, zip, pdf
     */
    if (array_get($params, 'extension')) {
     $query->only('meta', array(
      'extension' => array_get($params, 'extension')
     ));
    }
   })->only('published')->load()->with('files', array(
    'poster' => array(
     'default' => array(
      'image' => array_get($params, 'poster.default.image'),
      'file' => array_get($params, 'poster.default.file'),
      'video' => array_get($params, 'poster.default.video'),
      'audio' => array_get($params, 'poster.default.audio'),
      'link' => array_get($params, 'poster.default.link')
     ),
     'size' => array_get($params, 'poster.size')
    )
   ))->get('rows');

   foreach ($media as $row) {
    if ($row->file || ($row->role == 'link')) {
     foreach (array_get($ids, $row->id) as $content_id) {
      $order = 0;

      foreach (array_keys($ids) as $id) {
       if ($id == $row->id) {
        break;
       }

       $order++;
      }

      //Add media type
      $row->type = $names[$row->id];

      //Set
      $list[$content_id][$order] = $row;

      //Sort
      ksort($list[$content_id]);
     }
    }
   }
  }

  return array_map(function (&$row) use ($list, $names) {
   //Set media
   $row->media = array();

   //Set media with roles
   $row->media_images = array();
   $row->media_files = array();
   $row->media_videos = array();
   $row->media_audios = array();
   $row->media_links = array();

   foreach ($list as $id => $media) {
    if ($id == $row->id) {
     $row->media = array_values($media);

     foreach ($media as $item) {
      $row->{'media_' . $item->role . 's'}[] = $item;
     }
    }
   }
  }, $rows);
 }

 /**
  * Save media
  *
  * @param int $content_id
  * @param array $media
  * @param bool $reset
  * @param null|string $name
  */
 public function saveMedia($content_id, $media = array(), $reset = true, $name = null) {
  if ($content_id > 0) {
   if ($reset) {
    //Remove all
    DB::table('sys_content_media')->where('content_id', $content_id)->delete();
   }

   //Id list for save
   $media_ids = array_filter((array) $media, function ($id) {
    return is_numeric($id) && ($id > 0);
   });

   //Remove repeats
   $media_ids = array_unique($media_ids, SORT_REGULAR);

   //Add
   $inserts = array();

   //Start order
   $order = 0;

   foreach ($media_ids as $media_id) {
    $inserts[] = array(
     'content_id' => $content_id,
     'media_id' => $media_id,
     'order' => ++$order,
     'name' => $name
    );
   }

   if (count($inserts)) {
    DB::table('sys_content_media')->insert($inserts);
   }
  }
 }

 /**
  * Poster image
  *
  * @param array $rows
  * @param array $params
  *
  * @return array
  */
 protected function poster($rows, $params = array()) {
  if (in_array(__FUNCTION__, $this->called)) {
   return $rows;
  }

  //Add called class
  $this->called[] = __FUNCTION__;

  $this->with('meta');

  if (array_get($params, 'ifset', false)) {
   //Call media
   $this->with('media', array(
    'only' => 'image'
   ));
  }

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
   $media = Media::init()
    ->whereIn('id', array_keys($ids))
    ->only('published')
    ->load()->with('files', array(
     'poster' => array(
      'default' => array_get($params, 'default'),
      'size' => array_get($params, 'size')
     )
    ))->get('rows');

   foreach ($media as $row) {
    if ($row->file || ($row->role == 'link')) {
     foreach ($ids[$row->id] as $media_id) {
      $posters[$media_id] = $row;
     }
    }
   }
  }

  return array_map(function (&$row) use ($posters, $params) {
   //Default poster
   $poster = new \stdClass();
   $poster->id = null;
   $poster->image = null;
   $poster->source = null;

   //Poster size
   list($width, $height) = array_pad(explode('x', array_get($params, 'size', '0x0')), 2, '0');

   if (isset($posters[$row->id]->poster)) {
    $poster = $posters[$row->id]->poster;

    if (array_get($params, 'source', false)) {
     unset($posters[$row->id]->poster);
     $poster->source = $posters[$row->id];
    }
   } else {
    //Default poster
    $default = array_get($params, 'default');

    if (isset($row->media[0]) && array_get($params, 'ifset', false)) {
     $poster = $row->media[0]->poster;

     if (array_get($params, 'source', false)) {
      unset($row->media[0]->poster);
      $poster->source = $row->media[0];
     }
    } elseif (($default instanceof File) && $default->isFile()) {
     $poster->image = Picture::file($default)->size($width, $height);
    } elseif (is_scalar($default) && strlen($default)) {
     $poster->image = $default;
    }
   }

   $row->poster = $poster;
  }, $rows);
 }

 /**
  * Properties
  *
  * @param array $rows
  * @param array $params
  *
  * @return array
  */
 protected function properties($rows, $params = array()) {
  if (in_array(__FUNCTION__, $this->called)) {
   return $rows;
  }

  //Add called class
  $this->called[] = __FUNCTION__;

  //Properties
  $properties = array();

  $query = DB::table('sys_content_form_value as cfv')
   ->join('sys_form_propery as fp', 'fp.id', 'cfv.property_id')
   ->join('sys_form_group as fg', 'fg.id', 'fp.group_id')
   ->join('sys_form_field as ff', 'ff.id', 'fp.field_id')
   ->whereIn('cfv.id', $this->ids())
   ->get(array(
    'cfv.content_id',
    'cfv.property_id',
    'fg.id as group_id',
    'fg.label as group_label',
    'ff.id as field_id',
    'ff.label as field_label',
    'ff.type as field_type',
    'cfv.value',
    'cfv.text'
   ));

  //Set properties
  foreach ($query as $row) {
   $group = new \stdClass();
   $group->id = array_get($row, 'group_id');
   $group->label = array_get($row, 'group_label');

   $field = new \stdClass();
   $field->id = array_get($row, 'field_id');
   $field->label = array_get($row, 'field_label');
   $field->type = array_get($row, 'field_type');
   $field->value = array_get($row, 'value');
   $field->text = array_get($row, 'text');

   $property = new \stdClass();
   $property->group = $group;
   $property->field = $field;

   $properties[array_get($row, 'property_id')][array_get($row, 'content_id')][] = $property;
  }

  return array_map(function (&$row) use ($properties) {
   $row->properties = array();

   foreach ($properties as $property) {
    $row->properties = array_get($property, $row->id, array());
   }
  }, $rows);
 }

 /**
  * Form
  *
  * @param array $rows
  * @param array $params
  *
  * @return array
  */
 protected function form($rows, $params = array()) {
  if (in_array(__FUNCTION__, $this->called)) {
   return $rows;
  }

  $this->with('meta');

  //Add called class
  $this->called[] = __FUNCTION__;

  //Set forms
  $forms = array();

  //Get form id
  $form_ids = array();

  foreach ($rows as $row) {
   if ($form_id = array_get($row->meta, 'form_id')) {
    $form_ids[$form_id] = $form_id;
   }
  }

  if (count($form_ids)) {
   //Get form properties
   $properties = array();

   foreach (Property::init()->whereIn('form_id', $form_ids)->orderBy('order')->load()->with('elements')->get('rows') as $property) {
    $properties[$property->form_id][$property->group_id][$property->field_id] = $property;
   }

   $forms = Property::cascade($properties);
  }

  return array_map(function(&$row) use ($forms) {
   $row->form = null;

   foreach ($forms as $form) {
    if (array_get($row->meta, 'form_id') == $form->id) {
     $row->form = $form;
    }
   }
  }, $rows);
 }

 /**
  * Form fields with values
  *
  * @param array $rows
  * @param array $params
  *
  * @return array
  */
 protected function formFields($rows, $params = array()) {
  if (in_array(__FUNCTION__, $this->called)) {
   return $rows;
  }

  //Add called class
  $this->called[] = __FUNCTION__;

  //Form fields
  $fields = array();

  $query = DB::table('sys_content_form_value as fv')
   ->join('sys_form_property as fp', 'fp.id', 'fv.property_id')
   ->join('sys_form_group as fg', 'fg.id', 'fp.group_id')
   ->join('sys_form_field as ff', 'ff.id', 'fp.field_id')
   ->whereIn('fv.content_id', $this->ids())->get(array(
    'fv.content_id',
    'fg.id as group_id',
    'fg.label as group_label',
    'ff.id',
    'ff.name',
    'ff.label',
    'fv.value',
    'fv.text'
   ));

  foreach ($query as $row) {
   $group = new \stdClass();
   $group->id = array_get($row, 'group_id');
   $group->label = array_get($row, 'group_label');

   $field = new \stdClass();
   $field->group = $group;
   $field->id = array_get($row, 'id');
   $field->name = array_get($row, 'name');

   if (!strlen($field->name)) {
    $field->name = 'field-' . $field->id;
   }

   $field->label = array_get($row, 'label');
   $field->value = array_get($row, 'value');
   $field->text = array_get($row, 'text');

   $fields[array_get($row, 'content_id')][$group->label][$field->name] = $field;
  }

  return array_map(function (&$row) use ($fields) {
   $row->form_fields = (object) array_get($fields, $row->id, array());
  }, $rows);
 }

 /**
  * Form values
  *
  * @param array $rows
  * @param array $params
  *
  * @return array
  */
 protected function formValues($rows, $params = array()) {
  if (in_array(__FUNCTION__, $this->called)) {
   return $rows;
  }

  //Add called class
  $this->called[] = __FUNCTION__;

  //Form values
  $values = array();

  foreach (DB::table('sys_content_form_value')->whereIn('content_id', $this->ids())->get() as $row) {
   $value = new \stdClass();
   $value->value = array_get($row, 'value');
   $value->text = array_get($row, 'text');

   $values[array_get($row, 'content_id')][array_get($row, 'property_id')] = $value;
  }

  return array_map(function (&$row) use ($values) {
   $row->form_values = array_get($values, $row->id, array());
  }, $rows);
 }

 /**
  * Save form values
  *
  * @param int $content_id
  * @param int $property_id
  * @param mixed $value
  * @param string $text
  */
 public function saveFormValue($content_id, $property_id, $value, $text) {
  if (($content_id > 0) && ($property_id > 0)) {
   //Delete if exists
   DB::table('sys_content_form_value')
    ->where('content_id', $content_id)
    ->where('property_id', $property_id)
    ->delete();

   //Insert
   DB::table('sys_content_form_value')->insert(array(
    'content_id' => $content_id,
    'property_id' => $property_id,
    'value' => $value,
    'text' => $text
   ));
  }
 }

 /**
  * Reset form values
  *
  * @param int $content_id
  */
 public function resetFormValues($content_id) {
  if ($content_id > 0) {
   //Delete all
   DB::table('sys_content_form_value')
    ->where('content_id', $content_id)
    ->delete();
  }
 }

 /**
  * Hits
  *
  * @param array $rows
  * @param array $params
  *
  * @return array
  */
 protected function hit($rows, $params = array()) {
  if (in_array(__FUNCTION__, $this->called)) {
   return $rows;
  }

  //Add called class
  $this->called[] = __FUNCTION__;

  //Get hits
  $hits = array();

  foreach (DB::table('sys_content_hit')
            ->whereIn('content_id', $this->ids())
            ->groupBy('content_id')
            ->get(array(
             'content_id',
             DB::func('COUNT', '*', 'total')
            )) as $row) {
   $hits[array_get($row, 'content_id')] = array_get($row, 'total');
  }

  return array_map(function (&$row) use ($hits) {
   $row->hit = array_get($hits, $row->id, 0);
  }, $rows);
 }

 /**
  * Save hit
  *
  * @param int $content_id
  */
 public function saveHit($content_id) {
  if ($content_id > 0) {
   $hitted = DB::table('sys_content_hit')
    ->where('content_id', $content_id)
    ->where('access_id', Session::current()->get('access_id'))
    ->count();

   if (!$hitted) {
    DB::table('sys_content_hit')->insert(array(
     'content_id' => $content_id,
     'access_id' => Session::current()->get('access_id'),
     'created_at' => Carbon::now()
    ));
   }
  }
 }

 /**
  * Rates
  *
  * @param array $rows
  * @param array $params
  *
  * @return array
  */
 protected function rate($rows, $params = array()) {
  if (in_array(__FUNCTION__, $this->called)) {
   return $rows;
  }

  //Add called class
  $this->called[] = __FUNCTION__;

  //Get rates
  $rates = array();

  foreach (DB::table('sys_content_rate')
            ->whereIn('content_id', $this->ids())
            ->groupBy('content_id')
            ->get(array(
             'content_id',
             DB::func('AVG', 'score', 'rate')
            )) as $row) {
   $rates[array_get($row, 'content_id')] = array_get($row, 'rate');
  }

  return array_map(function (&$row) use ($rates) {
   $row->rate = array_get($rates, $row->id, 0.0);
  }, $rows);
 }

 /**
  * Save rate
  *
  * @param int $content_id
  * @param float $score
  */
 public function saveRate($content_id, $score = 0.0) {
  if ($content_id > 0) {
   $rated = DB::table('sys_content_rate')
    ->where('content_id', $content_id)
    ->where('access_id', Access::current()->get('access_id'))
    ->count();

   if (!$rated) {
    DB::table('sys_content_rate')->insert(array(
     'content_id' => $content_id,
     'access_id' => Access::current()->get('access_id'),
     'created_at' => Carbon::now(),
     'score' => floatval($score)
    ));
   }
  }
 }

 /**
  * Tags
  *
  * @param array $rows
  * @param array $params
  *
  * @return array
  */
 protected function tags($rows, $params = array()) {
  if (in_array(__FUNCTION__, $this->called)) {
   return $rows;
  }

  //Add called class
  $this->called[] = __FUNCTION__;

  //Get tags
  $tags = array();

  foreach (DB::table('sys_content_tag')
            ->whereIn('content_id', $this->ids())
            ->get() as $row) {
   $tags[array_get($row, 'content_id')][] = array_get($row, 'tag');
  }

  return array_map(function (&$row) use ($tags) {
   $row->tags = array_get($tags, $row->id, array());
  }, $rows);
 }

 /**
  * Save tags
  *
  * @param int $content_id
  * @param array $tags
  * @param bool $reset
  */
 public function saveTags($content_id, $tags = array(), $reset = true) {
  if ($content_id > 0) {
   if ($reset) {
    //Remove all
    DB::table('sys_content_tag')->where('content_id', $content_id)->delete();
   }

   //Add
   $inserts = array();

   foreach (array_filter(array_map('trim', $tags), 'strlen') as $tag) {
    $inserts[] = array(
     'content_id' => $content_id,
     'tag' => $tag
    );
   }

   if (count($inserts)) {
    DB::table('sys_content_tag')->insert($inserts);
   }
  }
 }

 /**
  * Comments
  *
  * @param array $rows
  * @param array $params
  *
  * @return array
  */
 protected function comments($rows, $params = array()) {
  if (in_array(__FUNCTION__, $this->called)) {
   return $rows;
  }

  //Add called class
  $this->called[] = __FUNCTION__;

  //Get comment ids
  $ids = array();

  foreach (DB::table('sys_content_comment')
            ->whereIn('content_id', $this->ids())
            ->orderBy('order')
            ->get('content_id', 'comment_id') as $row) {
   $ids[array_get($row, 'comment_id')][array_get($row, 'content_id')] = array_get($row, 'content_id');
  }

  //Comment list
  $list = array();

  if (count($ids)) {
   //Set content ids of the category list
   $content_ids = array_keys($ids);

   foreach (self::init()->only(function ($query) use ($content_ids, $params) {
    $query->where('type', 'comment');
    $query->whereIn('id', $content_ids);
   })->only('published')->load()->with('meta')->get('rows') as $row) {
    foreach (array_get($ids, $row->id) as $content_id) {
     $list[$content_id][] = $row;
    }
   }
  }

  return array_map(function (&$row) use ($list) {
   $row->comments = array();

   foreach ($list as $id => $comments) {
    if ($id == $row->id) {
     $row->comments = $comments;
    }
   }
  }, $rows);
 }

 /**
  * Set comment
  *
  * @param int|string $comment_id
  * @param int $content_id
  */
 public function setComment($comment_id, $content_id) {
  if ($content_id > 0) {
   if (is_string($comment_id)) {
    $comment_id = DB::table('sys_content')
     ->whereType('comment')
     ->where('url', $comment_id)
     ->pluck('id');
   }

   if ($comment_id > 0) {
    DB::table('sys_content_comment')->insert(array(
     'content_id' => $content_id,
     'comment_id' => $comment_id
    ));
   }
  }
 }

 /**
  * Set orders
  *
  * @param string $type
  * @param string $language
  * @param null|int $parent_id
  * @param null|int $id
  * @param null|int $order
  * @param bool $use_parent_id
  *
  * @return array
  */
 public function setOrders($type, $language, $parent_id = null, $id = null, $order = null, $use_parent_id = true) {
  //Return
  $orders = array();

  //List
  $list = DB::table('sys_content');

  if ($use_parent_id) {
   $list = $list->where('parent_id', $parent_id);
  }

  $list = $list->where('type', $type)
   ->where('language', $language)
   ->where('id', '<>', intval($id))
   ->orderBy('order')->lists('id');

  //Start from beginning
  $new_order = 1;

  foreach ($list as $target_id) {
   if ($new_order === intval($order)) {
    $orders[$order] = $id;
    $new_order++;
   }

   $update = DB::table('sys_content');

   if ($use_parent_id) {
    $update = $update->where('parent_id', $parent_id);
   }

   $update->where('type', $type)
    ->where('language', $language)
    ->where('id', $target_id)
    ->update(array(
     'order' => $new_order
    ));

   $orders[$new_order] = $target_id;

   $new_order++;
  }

  return $orders;
 }

 /**
  * Save orders
  *
  * @param int $id
  *
  * @return null|array
  */
 public function saveOrders($id) {
  $content = DB::table('sys_content')->where('id', $id)->first(array(
   'id', 'type', 'language', 'parent_id', 'order'
  ));

  if ($content) {
   return $this->setOrders(
    array_get($content, 'type'),
    array_get($content, 'language'),
    array_get($content, 'parent_id'),
    array_get($content, 'id'),
    array_get($content, 'order')
   );
  }

  return null;
 }

 /**
  * Order list
  *
  * @param string $type
  * @param string $language
  * @param int $parent_id
  * @param int $id
  *
  * @return mixed
  */
 public static function orderList($type, $language, $parent_id = 0, $id = 0) {
  if ($id > 0) {
   $content = DB::table('sys_content')
    ->where('id', $id)
    ->where('type', $type)
    ->first(array(
     'parent_id',
     'language'
    ));

   if ($content) {
    $parent_id = array_get($content, 'parent_id');
    $language = array_get($content, 'language');
   }
  }

  //Get list
  $query = DB::table('sys_content');

  if ($parent_id > 0) {
   $query->where('parent_id', $parent_id);
  } else {
   $query->where(function($q) {
    $q->whereNull('parent_id');
    $q->orWhere('parent_id', 0);
   });
  }

   $query = $query->where('type', $type)
    ->where('language', $language)
    ->where('id', '<>', $id)
    ->orderBy('order')
    ->lists('title');

  return $query;
 }

 /**
  * Save category content orders
  *
  * @param int $category_id
  * @param array $content_ids
  *
  * @return array
  */
 public static function saveCategoryOrders($category_id, $content_ids = array()) {
  //Order list
  $orders = array();

  if ($category_id > 0) {
   //Get current category contents
   $list = DB::table('sys_content_category')
    ->where('category_id', $category_id)
    ->whereIn('content_id', (array) $content_ids)
    ->orderBy('order')->lists('content_id');

   foreach ($content_ids as $content_id) {
    if (in_array($content_id, $list)) {
     $orders[] = $content_id;
    }
   }

   foreach ($list as $content_id) {
    if (!in_array($content_id, $orders)) {
     $orders[] = $content_id;
    }
   }

   //Start order
   $order = 0;

   foreach ($orders as $content_id) {
    DB::table('sys_content_category')
     ->where('content_id', $content_id)
     ->where('category_id', $category_id)
     ->update(array(
      'order' => ++$order
     ));
   }
  }

  return $orders;
 }

 /**
  * Save content orders
  *
  * @param string $content_type
  * @param int $content_id
  * @param null|int $id
  * @param null|int $order
  *
  * @return array
  */
 public static function saveContentOrders($content_type, $content_id, $id = null, $order = null) {
  //Return
  $orders = array();

  //Get list
  $list = array();

  switch ($content_type) {
   case 'comment':

    $list = DB::table('sys_content_comment')
             ->where('content_id', $content_id)
             ->where('comment_id', '<>', $id)
             ->orderBy('order')->lists('comment_id');

    break;
   case 'media':

    $list = DB::table('sys_content_media')
             ->where('content_id', $content_id)
             ->where('media_id', '<>', $id)
             ->orderBy('order')->lists('media_id');

    break;
  }

  //Start from beginning
  $new_order = 1;

  foreach ($list as $target_id) {
   if ($new_order === $order) {
    $orders[$order] = $id;
    $new_order++;
   }

   switch ($content_type) {
    case 'comment':

     DB::table('sys_content_comment')
      ->where('content_id', $content_id)
      ->where('comment_id', $target_id)
      ->update(array(
       'order' => $new_order
      ));

     break;
    case 'media':

     DB::table('sys_content_media')
      ->where('content_id', $content_id)
      ->where('media_id', $target_id)
      ->update(array(
       'order' => $new_order
      ));

     break;
   }

   $orders[$new_order] = $target_id;

   $new_order++;
  }

  return $orders;
 }

 /**
  * Indented list
  *
  * @param string $indent
  * @param mixed $column
  *
  * @param string $key
  *
  * @return array
  */
 public function getListIndented($indent = ' ', $column = 'title', $key = 'id') {
  $list = array();

  foreach (static::deep($this->list->rows) as $row) {
   //Set value as default all row
   $id = array_get($row, $key);
   $value = $row;

   if ($column instanceof \Closure) {
    $value = str_repeat($indent, $row->level) . call_user_func($column, $row);
   } elseif (is_scalar($column)) {
    $value = str_repeat($indent, $row->level) . array_get($row, $column);
   }

   if (!is_null($id)) {
    $list[$id] = $value;
   } else {
    $list[] = $value;
   }
  }

  return $list;
 }

 /**
  * Reorganize content using relations
  *
  * @param array $rows
  * @param int $id
  * @param int $level
  *
  * @return array
  */
 private static function deep($rows, $id = 0, $level = 0) {
  //Return
  $deep = array();

  if ($id == 0) {
   //Cycle all rows has parent_id equals zero
   $check = array_filter($rows, function($row) {
    return $row->parent_id == 0;
   });

   if (count($rows) && !count($check)) {
    //Take the first rows parent_id as id
    $id = array_first($rows)->parent_id;
   }
  }

  foreach ($rows as $row) {
   if ($row->parent_id == $id) {
    //Add level
    $row->level = $level;

    //Add
    $deep[$row->id] = $row;

    //Continue
    $deep += forward_static_call(array('static', __FUNCTION__), $rows, $row->id, ($level + 1));
   }
  }

  return $deep;
 }

 /**
  * Delete
  *
  * @param null|int $id
  *
  * @return Message
  */
 public function delete($id = null) {
  //Default return
  $message = Message::result(lang('message.nothing_done', 'Herhangi bir işlem yapılmadı!'));

  if (!is_array($id)) {
   $id = array($id);
  }

  if (DB::table('sys_content')->whereIn('parent_id', $id)->count()) {
   $message->text = lang('message.record_is_parent_of_another', 'Mevcut kayıt başka bir kaydın üst sınıfı!');
  } else {
   try {
    $deleted = parent::delete(function() use ($id) {
     DB::table('sys_content_category')->whereIn('content_id', $id)->delete();
     DB::table('sys_content_meta')->whereIn('content_id', $id)->delete();
     DB::table('sys_content_comment')->whereIn('content_id', $id)->delete();
     DB::table('sys_content_form_value')->whereIn('content_id', $id)->delete();
     DB::table('sys_content_hit')->whereIn('content_id', $id)->delete();
     DB::table('sys_content_media')->whereIn('content_id', $id)->delete();
     DB::table('sys_content_meta')->whereIn('content_id', $id)->delete();
     DB::table('sys_content_permission')->whereIn('content_id', $id)->delete();
     DB::table('sys_content_rate')->whereIn('content_id', $id)->delete();
     DB::table('sys_content_tag')->whereIn('content_id', $id)->delete();

     //Get type and parent id for ordering
     $contents = DB::table('sys_content')->whereIn('id', $id)->get(array('type', 'parent_id'));

     //Delete main
     $delete = DB::table('sys_content')->whereIn('id', $id)->delete();

     foreach ($contents as $content) {
      $this->setOrders(
       array_get($content, 'type'),
       array_get($content, 'language'),
       array_get($content, 'parent_id')
      );
     }

     return $delete->return;
    });

    if ($deleted) {
     $message->success = true;
     $message->text = choice('message.deleted', $deleted, [$deleted], array(
      'Kayıt silindi...',
      'Kayıtlar silindi...'
     ));
    }
   } catch (\Exception $e) {
    if (stripos($e->getMessage(), 'Cannot delete or update a parent row') !== false) {
     $message->text = lang('message.entry_using_another_content', 'İçerik başka bir kayıtta kullanıldığından silinemez!');
    } else {
     $message->text = $e->getMessage();
    }
   }
  }

  return $message;
 }

 /**
  * Make URL
  *
  * @param string $url
  * @param string $title
  * @param string $date
  * @param bool $prefix
  *
  * @return string
  */
 public static function makeUrl($url, $title, $date = '', $prefix = false) {
  //Create url
  $newUrl = '';

  if ($prefix) {
   if (!preg_match('/^[0-9]{4}[\/\-]{1}[0-9]{2}[\/\-]{1}[0-9]{2}$/', $date)) {
    $date = 'now';
   }

   $newUrl .= date('Y/m/d', strtotime($date)) . '/';
  }

  if (preg_match('/^[0-9]{4}[\/\-]{1}[0-9]{2}[\/\-]{1}[0-9]{2}[\/\-]{1}/', $url)) {
   $url = substr($url, 11);
  }

  $url = implode('/', array_map(function($part) {
   return slug($part);
  }, explode('/', $url)));

  if (strlen($url)) {
   $newUrl .= $url;
  } elseif (strlen(slug($title))) {
   $newUrl .= slug($title);
  } else {
   $newUrl .= uniqid();
  }

  return (string) $newUrl;
 }

 /**
  * Give total records of specific target
  *
  * @param array $rows
  * @param string $target
  *
  * @return array
  */
 protected function total($rows, $target) {
  $valid = array(
   'category', 'comment', 'form_value', 'hit', 'media', 'meta', 'permission', 'rate', 'tag'
  );

  if (!in_array($target, $valid, true) || in_array($target . ucfirst(__FUNCTION__), $this->called)) {
   return $rows;
  }

  //Add called class
  $this->called[] = __FUNCTION__ . ucfirst($target);

  //Get totals
  $totals = array();

  foreach (DB::table('sys_content_' . $target)
            ->whereIn('content_id', $this->ids())
            ->groupBy('content_id')
            ->get(array(
             'content_id',
             DB::func('COUNT', '*', 'total')
            )) as $row) {
   $totals[array_get($row, 'content_id')] = array_get($row, 'total');
  }

  return array_map(function (&$row) use ($target, $totals) {
   //Default 0
   $row->{ 'total' . ucfirst($target) } = 0;

   foreach ($totals as $list) {
    $row->{ 'total' . ucfirst($target) } = array_get($list, $row->id, 0);
   }
  }, $rows);
 }

}