<?php
/**
 * @author Orhan POLAT
 */

namespace Commerce\Admin;

use Admin\Manager;
use System\Content;
use System\Media as SystemMedia;
use System\Property\Field;
use System\Property\Manager as Property;
use Webim\Library\Carbon;
use Webim\Library\Message;
use Webim\View\Manager as View;

class Product {

  /**
   * Manager
   *
   * @var Admin\Manager
   */
  protected static $manager;

  /**
   * Register current class and routes
   *
   * @param Admin\Manager $manager
   */
  public static function register(Manager $manager) {
    $manager->addRoute($manager->prefix . '/ecommerce/products', __CLASS__ . '::getIndex');
    $manager->addRoute($manager->prefix . '/ecommerce/products/form/?:id', __CLASS__ . '::getForm');
    $manager->addRoute($manager->prefix . '/ecommerce/products/form/?:id', __CLASS__ . '::postForm', 'POST');
    $manager->addRoute($manager->prefix . '/ecommerce/products/form/?:id+', __CLASS__ . '::deleteForm', 'DELETE');
    $manager->addRoute($manager->prefix . '/ecommerce/products/categories/?:id', __CLASS__ . '::categories');
    $manager->addRoute($manager->prefix . '/ecommerce/products/rename/?:id+', __CLASS__ . '::renameURL', 'POST');
    $manager->addRoute($manager->prefix . '/ecommerce/products/duplicate', __CLASS__ . '::duplicate', 'POST');
    $manager->addRoute($manager->prefix . '/ecommerce/products/orders', __CLASS__ . '::getOrders');
    $manager->addRoute($manager->prefix . '/ecommerce/products/orders', __CLASS__ . '::postOrders', 'POST');

    $parent = $manager->addMenu(lang('admin.menu.modules', 'Modüller'), $manager->prefix . '/ecommerce', lang('admin.menu.product_management', 'Ürün Yönetimi'), null, 'fa fa-shopping-cart');
    $manager->addMenu(lang('admin.menu.modules', 'Modüller'), $manager->prefix . '/ecommerce/products', lang('admin.menu.products', 'Ürünler'), $parent, 'fa fa-tags');

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

    $manager->put('subnavs', array(
      btn(lang('admin.menu.create', 'Yeni Oluştur'), url($manager->prefix . '/ecommerce/products/form'), 'fa-plus'),
      btn(lang('admin.menu.orders', 'Sıralamalar'), url($manager->prefix . '/ecommerce/products/orders'), 'fa-sort')
    ));

    $manager->set('caption', lang('admin.menu.products', 'Ürünler'));
    $manager->breadcrumb($manager->prefix . '/ecommerce', lang('admin.menu.product_management', 'Ürün Yönetimi'));
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
        ->only('category', input('categories'))
        ->only(function ($content) {
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
        ->with(function ($rows) {
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
   * @param array $params
   *
   * @return string
   */
  public function getForm($params = array()) {
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

    $id = array_get($params, 'id', 0);
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
      ))->with('formValues')->with('tags')->with(function ($rows) {
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
    $manager->breadcrumb($manager->prefix . '/ecommerce', lang('admin.menu.product_management', 'Ürün Yönetimi'));
    $manager->breadcrumb($manager->prefix . '/ecommerce/products', lang('admin.menu.products', 'Ürünler'));
    $manager->breadcrumb($manager->prefix . '/ecommerce/products/form', lang('admin.menu.' . $action, $actionTitle));

    return View::create('modules.ecommerce.products.form')->data($manager::data())->render();
  }

  /**
   * Post form
   *
   * @param array $params
   *
   * @return string
   */
  public function postForm($params = array()) {
    $manager = static::$manager;

    $manager->app->response->setContentType('json');

    $id = array_get($params, 'id');

    //Product url
    $url = implode('/', array_map(function ($part) {
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

        $this->saveCategory($id, input('category'));

        $this->saveMeta($id, array(
          'poster_id' => $poster['id'],
          'brand_id' => input('meta-brand_id', 0),
          'summary' => input('meta-summary'),
          'show_summary_inside' => input('meta-show_summary_inside', array('no', 'yes')),
          'content' => raw_input('meta-content'),
          'currency' => input('meta-currency'),
          'price' => input('meta-price', 0.0),
          'tax' => input('meta-tax', 0),
          'discount' => input('meta-discount', 0.0),
          'sell_price' => input('meta-sell_price', 0.0),
          'stock_code' => input('meta-stock_code'),
          'stock_count' => input('meta-stock_count', 0),
          'stock_control' => input('meta-stock_control', array('no', 'yes')),
          'form_id' => input('meta-form_id', 0),
          'options' => raw_input('meta-options')
        ));

        $this->saveMedia($id, input('media_id'));

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

        Field::checkValues($fields, $values, function ($field, $value, $text) use ($id) {
          $this->saveFormValue($id, $field->property_id, $value, $text);
        });
      });

    //Set poster
    $save->return = array_merge($save->returns(), array('poster' => $poster));

    return $save->forData();
  }

  /**
   * Delete form
   *
   * @param array $params
   *
   * @return string
   */
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

  /**
   * Rename
   *
   * @param array $params
   *
   * @return string
   */
  public function renameURL($params = array()) {
    $manager = static::$manager;

    $manager->app->response->setContentType('json');

    $message = Message::result(lang('message.nothing_done', 'Herhangi bir işlem yapılmadı!'));

    if (strlen(input('url'))) {
      //ID
      $id = array_get($params, 'id');

      //New url
      $url = Content::makeUrl(input('url'), input('url'), substr(input('url'), 0, 10), (conf('news.url_with_date', 'no') == 'yes'));

      $current = Content::init()->where('id', $id)->load()->get('rows.0');

      if ($current) {
        $message = Content::init()->set(array(
          'id' => $current->id,
          'parent_id' => $current->parent_id,
          'type' => $current->type,
          'language' => $current->language,
          'url' => $url
        ))->save();

        if ($message->success()) {
          $message->return = $message->returns() + array('url' => $url);
        }
      }
    }

    return $message->forData();
  }

  /**
   * Duplicate record by language
   *
   * @param array $params
   *
   * @return string
   */
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

  /**
   * Order list
   *
   * @param array $params
   *
   * @return string
   */
  public function getOrders($params = array()) {
    $manager = static::$manager;

    $manager->set('caption', lang('admin.menu.orders', 'Sıralamalar'));
    $manager->breadcrumb($manager->prefix . '/ecommerce', lang('admin.menu.product_management', 'Ürün Yönetimi'));
    $manager->breadcrumb($manager->prefix . '/ecommerce/products', lang('admin.menu.products', 'Ürünler'));
    $manager->breadcrumb($manager->prefix . '/ecommerce/products/orders', lang('admin.menu.orders', 'Sıralamalar'));

    $manager->put('categories',
      Content::init()
        ->only('type', 'category')
        ->only('language', input('language'))
        ->only('parent.name', 'products')
        ->load()
        ->getListIndented('&nbsp;&nbsp;')
    );

    if ($manager->app->request->isAjax()) {
      $manager->app->response->setContentType('json');

      return array_to(Content::init()
        ->only('type', 'product')
        ->only('language', input('language'))
        ->only('category', input('category', 0))
        ->only('active')
        ->orderByCategory(input('category', 0), 'asc')
        ->orderBy('order')->orderBy('publish_date', 'desc')
        ->load()->with('poster', array(
          'size' => '150x150',
          'default' => View::getPath()->folder('layouts.assets.poster')->file('image.png')
        ))
        ->with(function ($rows) {
          return array_map(function (&$row) use ($rows) {
            $row->poster = $row->poster->image->src();
            $row->language = lang('name', $row->language, $row->language);
            $row->active = $row->active == 'true';
            $row->status = $row->active ? lang('admin.label.active', 'Aktif') : lang('admin.label.passive', 'Pasif');
          }, $rows);
        })->get());
    }

    return View::create('modules.ecommerce.products.orders')->data($manager::data())->render();
  }

  /**
   * Order records
   *
   * @param array $params
   *
   * @return string
   */
  public function postOrders($params = array()) {
    $manager = static::$manager;

    $manager->app->response->setContentType('json');

    return array_to(Content::saveCategoryOrders(input('category_id', 0), explode(',', input('content_ids'))));
  }

}