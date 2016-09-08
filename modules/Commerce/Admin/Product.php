<?php
/**
 * @author Orhan POLAT
 */

namespace Commerce\Admin;

use \Admin\Manager;
use \System\Content;
use \System\Media as SystemMedia;
use \System\Property\Field;
use \System\Property\Form;
use \System\Property\Manager as Property;
use \Webim\Library\Carbon;
use \Webim\Library\Message;
use \Webim\View\Manager as View;

class Product {

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
  $manager->addRoute($manager->prefix . '/ecommerce/products', __CLASS__ . '::getIndex');
  $manager->addRoute($manager->prefix . '/ecommerce/products/form/?:id', __CLASS__ . '::getForm');
  $manager->addRoute($manager->prefix . '/ecommerce/products/form/?:id', __CLASS__ . '::postForm', 'POST');
  $manager->addRoute($manager->prefix . '/ecommerce/products/form/?:id+', __CLASS__ . '::deleteForm', 'DELETE');
  $manager->addRoute($manager->prefix . '/ecommerce/products/categories/?:id', __CLASS__ . '::categories', 'POST');
  $manager->addRoute($manager->prefix . '/ecommerce/products/rename/?:id+', __CLASS__ . '::renameURL', 'POST');
  $manager->addRoute($manager->prefix . '/ecommerce/products/duplicate', __CLASS__ . '::duplicate', 'POST');

  $parent = $manager->addMenu(lang('admin.menu.modules', 'Modüller'), $manager->prefix . '/ecommerce', lang('admin.menu.ecommerce', 'E-Ticaret'), null, 'fa fa-shopping-cart');
  $manager->addMenu(lang('admin.menu.modules', 'Modüller'), $manager->prefix . '/ecommerce/products', lang('admin.menu.products', 'Ürünler'), $parent, 'fa fa-tags');

  static::$manager = $manager;
 }

 /**
  * Index
  *
  * @param null|string $lang
  *
  * @return string
  */
 public function getIndex($lang = null) {
  $manager = static::$manager;

  $nav = new \stdClass();
  $nav->title = lang('admin.menu.create', 'Yeni Oluştur');
  $nav->url = url($manager->prefix . '/ecommerce/products/form');
  $nav->icon = 'fa-plus';

  $manager->put('subnavs', array(
   $nav
  ));

  $manager->set('caption', lang('admin.menu.products', 'Ürünler'));
  $manager->breadcrumb($manager->prefix . '/ecommerce', lang('admin.menu.ecommerce', 'E-Ticaret'));
  $manager->breadcrumb($manager->prefix . '/ecommerce/products', lang('admin.menu.products', 'Ürünler'));

  $manager->put('shops',
   Content::init()
    ->where('type', 'shop')
    ->orderBy('title')
    ->load()
    ->getListIndented('&nbsp;&nbsp;')
  );

  $manager->put('categories',
   Content::init()
    ->where('type', 'category')
    ->orderBy('order')
    ->load()
    ->getListIndented('&nbsp;&nbsp;')
  );

  $manager->put('list',
   Content::init()
    ->only('type', 'product')
    ->only('category', explode(',', input('categories')))
    ->only(function($content) {
     if (input('id', 0) > 0) {
      $content->where('id', input('id', 0));
     }

     if (input('parent_id', 0) > 0) {
      $content->where('parent_id', input('parent_id', 0));
     }

     if (strlen(input('title'))) {
      $content->where('title', 'like', '%' . input('title') . '%');
     }
    })->orderBy(input('orderby', 'id'), input('order', ['desc', 'asc']))
    ->load(input('offset', 0), input('limit', 20))
    ->with('parents')
    ->with('category')
    ->with(function($rows) {
     return array_map(function (&$row) use ($rows) {
      $categories = array();

      foreach ($row->category as $category) {
       $categories[] = $category->title;
      }

      $row->language = lang('name', $row->language, $row->language);
      $row->categories = count($categories) ? implode(', ', $categories) : '-';
      $row->active = $row->active == 'true';
      $row->status = $row->active ? lang('admin.label.active', 'Aktif') : lang('admin.label.passive', 'Pasif');
     }, $rows);
    })->get()
  );

  return View::create('modules.ecommerce.products.list')->data($manager::data())->render();
 }

 /**
  * Get form
  *
  * @param null|string $lang
  * @param int $id
  *
  * @return string
  */
 public function getForm($lang = null, $id = 0) {
  $manager = static::$manager;

  $manager->put('shops', Content::init()
   ->where('type', 'shop')
   ->orderBy('title')
   ->load()
   ->getListIndented('&nbsp;&nbsp;', null)
  );

  $manager->put('brands',
   Content::init()
    ->where('type', 'brand')
    ->orderBy('title')
    ->load()
    ->getListIndented('&nbsp;&nbsp;')
  );

  $manager->put('forms',
   Form::init()
    ->where('language', $lang ? $lang : lang())
    ->where('active', 'true')
    ->load()
    ->getList('label')
  );

  $action = 'new';
  $actionTitle = lang('admin.label.create_new', 'Yeni Oluştur');

  $content = Content::init()
   ->where('type', 'product')
   ->where('id', $id)
   ->load()
   ->with('meta')
   ->with('category')
   ->with('media', array(
    'poster' => array(
     'default' => array(
      'image' => View::getPath()->folder('layouts.img.poster')->file('image.png'),
      'file' => View::getPath()->folder('layouts.img.poster')->file('file.png'),
      'video' => View::getPath()->folder('layouts.img.poster')->file('video.png'),
      'audio' => View::getPath()->folder('layouts.img.poster')->file('audio.png'),
      'link' => View::getPath()->folder('layouts.img.poster')->file('link.png')
     ),
     'size' => '150x150'
    )
   ))->with('poster', array(
    'source' => true
   ))->with('formValues')->with('tags')->with(function($rows) {
    return array_map(function (&$row) use ($rows) {
     $categories = array();

     foreach ($row->category as $category) {
      $categories[$category->id] = $category->id;
     }

     $row->categories = $categories;

     $row->tags = $row->tags ? implode(', ', $row->tags) : '';
    }, $rows);
   })->get('rows.0');

  if ($content) {
   $action = 'edit';
   $actionTitle = lang('admin.label.edit', 'Düzenle');

   $manager->put('content', $content);
  }

  $manager->set('caption', lang('admin.menu.products', 'Ürünler'));
  $manager->breadcrumb($manager->prefix . '/ecommerce', lang('admin.menu.ecommerce', 'E-Ticaret'));
  $manager->breadcrumb($manager->prefix . '/ecommerce/products', lang('admin.menu.products', 'Ürünler'));
  $manager->breadcrumb($manager->prefix . '/ecommerce/products/form', lang('admin.menu.' . $action, $actionTitle));

  return View::create('modules.ecommerce.products.form')->data($manager::data())->render();
 }

 /**
  * Post form
  *
  * @param null|string $lang
  * @param null|int $id
  *
  * @return string
  */
 public function postForm($lang = null, $id = null) {
  $manager = static::$manager;

  $manager->app->response->setContentType('json');

  //Product url
  $url = implode('/', array_map(function($part) {
   return slug($part);
  }, explode('/', input('url'))));
  $url = strlen($url) ? $url : slug(input('title'));

  if (input('parent_id', 0) > 0) {
   //Get shop
   $shop = Content::init()->where('id', input('parent_id', 0))->where('type', 'shop')->load()->get('rows.0');

   if ($shop && (array_get(explode('/', $url), 0) != $shop->url)) {
    $url = $shop->url . '/' . $url;
   }
  }

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
   ->set('type', 'product')
   ->set('parent_id', (input('parent_id', 0) > 0 ? input('parent_id', 0) : null))
   ->set('language', input('language', lang()))
   ->set('url', $url)
   ->set('title', input('title'))
   ->set('publish_date', Carbon::createFromTimestamp(strtotime(input('publish_date'))))
   ->set('expire_date', (strlen(input('expire_date')) ? Carbon::createFromTimestamp(strtotime(input('expire_date'))) : null))
   ->set('version', input('version'))
   ->set('active', input('active', array('false', 'true')))
   ->save(function($id) use ($manager, &$poster) {
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

    $this->saveCategory($id, explode(',', input('category')));

    $this->saveMeta($id, array(
     'poster_id' => $poster['id'],
     'brand_id' => input('meta-brand_id', 0),
     'summary' => input('meta-summary'),
     'show_summary_inside' => input('meta-show_summary_inside', array('no', 'yes')),
     'content' => raw_input('meta-content'),
     'price' => input('meta-price', 0.0),
     'currency' => input('meta-currency'),
     'tax' => input('meta-tax', 0),
     'discount' => input('meta-discount', 0.0),
     'sell_price' => input('meta-sell_price', 0.0),
     'stock_status' => input('meta-stock_status', array('no', 'yes')),
     'stock_count' => input('meta-stock_count', 0),
     'form_id' => input('meta-form_id', 0)
    ));

    $this->saveMedia($id, explode(',', input('media_id')));

    $this->saveTags($id, explode(',', input('tags')));

    //Incoming fields and values
    $fields = array();
    $values = array();

    foreach (Property::init()
              ->where('form_id', input('meta-form_id', 0))
              ->load()->with('elements')->get('rows') as $property) {
     //Field
     $field = $property->field;

     //Meta data for validation and features
     $meta = array();

     foreach ((object) @json_decode($property->meta) as $key => $value) {
      $meta[$key] = $value;
     }

     foreach ((object) @json_decode($field->meta) as $key => $value) {
      $meta[$key] = $value;
     }

     $field->meta = $meta;

     $fields[$property->id] = $field;
     $values[$property->id] = input('field-' . $property->id);
    }

    //First reset
    $this->resetFormValues($id);

    Field::checkValues($fields, $values, function($property_id, $value, $text) use ($id) {
     $this->saveFormValue($id, $property_id, $value, $text);
    });
   });

  //Set poster
  $save->return = array_merge($save->returns(), array('poster' => $poster));

  return $save->forData();
 }

 /**
  * Delete form
  *
  * @param null|string $lang
  * @param string $ids
  *
  * @return string
  */
 public function deleteForm($lang = null, $ids = '') {
  $manager = static::$manager;

  $manager->app->response->setContentType('json');

  $ids = array_filter(explode(',', $ids), function($id) {
   return (int) $id > 0;
  });

  if (count($ids)) {
   return Content::init()->delete($ids)->forData();
  } else {
   return Message::result(lang('message.nothing_done'))->forData();
  }
 }

 /**
  * Category list by language
  *
  * @param null|string $lang
  *
  * @return string
  */
 public function categories($lang = null) {
  $manager = static::$manager;

  $manager->app->response->setContentType('json');

  $categories = array();

  foreach (Content::init()
            ->where('id', '<>', input('id', 0))
            ->only('type', 'category')
            ->only('language', input('language', $lang))
            ->orderBy('order')
            ->load()->getListIndented('&nbsp;&nbsp;') as $id => $title) {
   $categories[] = array(
    'id' => $id,
    'title' => $title
   );
  }

  return array_to($categories);
 }

 /**
  * Rename
  *
  * @param null|string $lang
  * @param int $id
  *
  * @return string
  */
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
    $save->return = $save->returns() + array('url' => (string) $url);
   }

   return $save->forData();
  } else {
   return Message::result(lang('message.nothing_done'))->forData();
  }
 }

 /**
  * Duplicate record by language
  *
  * @param null|string $lang
  *
  * @return string
  */
 public function duplicate($lang = null) {
  $manager = static::$manager;
  $manager->app->response->setContentType('json');

  //Return
  $message = Message::result(lang('message.nothing_done', 'Herhangi bir işlem yapılmadı!'));

  $ids = array_filter(explode(',', input('id')), function($id) {
   return (int) $id > 0;
  });

  if (count($ids)) {
   $duplicated = 0;

   foreach ($ids as $id) {
    $duplicate = Content::duplicate($id, input('lang'));

    if ($duplicate->success()) {
     $duplicated++;
    } else {
     return $duplicate->forData();
    }
   }

   if ($duplicated) {
    $message->success = true;
    $message->text = lang('admin.message.duplicated_total', [$duplicated], '%s kayıt çoklandı...');
   }
  }

  return $message->forData();
 }

}