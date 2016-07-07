<?php
/**
 * @author Orhan POLAT
 */

namespace Admin;

use \System\Property\Manager as Property;
use \System\Property\Form as FormProperty;
use \System\Property\Group as GroupProperty;
use \System\Property\Field as FieldProperty;
use \Webim\Library\Message;
use \Webim\View\Manager as View;

class Form {

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
  $manager->addRoute($manager->prefix . '/system/forms', __CLASS__ . '::getIndex');
  $manager->addRoute($manager->prefix . '/system/forms/:type+', __CLASS__ . '::getForm');
  $manager->addRoute($manager->prefix . '/system/forms/:type+', __CLASS__ . '::postForm', 'POST');
  $manager->addRoute($manager->prefix . '/system/forms/:type+/:id+', __CLASS__ . '::deleteForm', 'DELETE');

  $parent = $manager->addMenu(lang('admin.menu.system', 'Sistem'), $manager->prefix . '/system', lang('admin.menu.system', 'Sistem'), null, 'fa fa-cogs');
  $manager->addMenu(lang('admin.menu.system', 'Sistem'), $manager->prefix . '/system/forms', lang('admin.menu.forms', 'Formlar'), $parent, 'fa fa-code-fork');

  static::$manager = $manager;
 }

 public function getIndex($lang = null) {
  $manager = static::$manager;

  $manager->set('caption', lang('admin.menu.forms', 'Formlar'));
  $manager->breadcrumb($manager->prefix . '/system', lang('admin.menu.system', 'Sistem'));
  $manager->breadcrumb($manager->prefix . '/system/forms', lang('admin.menu.forms', 'Formlar'));

  return View::create('system.forms')->data($manager::data())->render();
 }

 public function getForm($lang = null, $type) {
  $manager = static::$manager;
  $manager->app->response->setContentType('json');

  $form = array();

  switch ($type) {
   case 'forms':

    $form = FormProperty::init();

    if (input('id', 0)) {
     $form->where('id', input('id', 0));
    } else {
     $form->where('language', input('language', $lang));
    }

    $form = $form->load()->get('rows');

    if ((input('properties', array('false', 'true')) == 'true') && count($form)) {
     //Last form
     $last = count($form) - 1;

     $properties = array();

     foreach (Property::init()->where('form_id', array_get($form, $last . '.id', 0))->orderBy('order')->load()->with('elements', array(
      'forms' => false
     ))->get('rows') as $property) {
      $properties[$property->form_id][$property->group_id][$property->field_id] = $property;
     }

     //Set form element from cascade array
     $form[$last]->properties = array_get(Property::cascade($properties), '0.groups', array());
    }

    if (count($form) && input('id', 0)) {
     $form = array_get($form, 0);
    }

    break;
   case 'groups':

    $form = GroupProperty::init();

    if (input('id', 0)) {
     $form->where('id', input('id', 0));
    } else {
     $form->where('language', input('language', $lang));
    }

    $form = $form->orderBy('label')->load()->get('rows');

    break;
   case 'fields':

    $form = FieldProperty::init();

    if (input('id', 0)) {
     $form->where('id', input('id', 0));
    } else {
     $form->where('language', input('language', $lang));
    }

    $form = $form->orderBy('label')->load()->get('rows');

    break;
   case 'properties':

    $properties = array();

    foreach (Property::init()->where('form_id', input('form_id', 0))->orderBy('order')->load()->with('elements')->get('rows') as $property) {
     $properties[$property->form_id][$property->group_id][$property->field_id] = $property;
    }

    //Set form elements
    $form = array_get(Property::cascade($properties), '0.groups', array());

    break;
   case 'meta':

    $element = Property::init()->where('id', input('id', 0))->load()->get('rows.0');

    if ($element) {
     $form[] = $element->meta;
    }

    break;
   case 'orders':

    $element = Property::init()->where('id', input('id', 0))->load()->get('rows.0');

    if ($element) {
     $form_id = $element->form_id;

     $form = Property::init()
      ->where('form_id', $form_id)
      ->orderBy('order')->orderBy('id')
      ->load()->with('elements')->get('rows');
    }

    break;
  }

  return array_to($form);
 }

 public function postForm($lang = null, $type) {
  $manager = static::$manager;
  $manager->app->response->setContentType('json');

  $message = Message::result(lang('message.nothing_done', 'Herhangi bir işlem yapılmadı!'));

  try {
   switch ($type) {
    case 'form':

     $save = FormProperty::init()->validation(array(
      'language' => 'required',
      'name' => 'required',
      'label' => 'required'
     ), array(
      'language.required' => lang('message.language_must_set', 'Dil seçilmelidir!'),
      'name.required' => lang('message.form_name_must_set', 'Form için isim belirtilmelidir!'),
      'label.required' => lang('message.form_label_must_set', 'Form için başlık belirtilmelidir!')
     ))->set(array(
      'id' => input('id', 0),
      'language' => input('language', $lang),
      'name' => (strlen(input('name')) ? slug(input('name')) : null),
      'label' => input('label'),
      'version' => input('version', 0),
      'active' => input('active', array('true', 'false'))
     ))->save();

     break;
    case 'group':

     $save = GroupProperty::init()->validation(array(
      'language' => 'required',
      'label' => 'required'
     ), array(
      'language.required' => lang('message.language_must_set', 'Dil seçilmelidir!'),
      'label.required' => lang('message.group_label_must_set', 'Grup için başlık belirtilmelidir!')
     ))->set(array(
      'id' => input('id', 0),
      'language' => input('language', $lang),
      'label' => input('label'),
      'version' => input('version', 0)
     ))->save();

     break;
    case 'field':

     $save = FieldProperty::init()->validation(array(
      'language' => 'required',
      'label' => 'required'
     ), array(
      'language.required' => lang('message.language_must_set', 'Dil seçilmelidir!'),
      'label.required' => lang('message.field_label_must_set', 'Alan için başlık belirtilmelidir!')
     ))->set(array(
      'id' => input('id', 0),
      'language' => input('language', $lang),
      'type' => input('type', FieldProperty::$types),
      'name' => (strlen(input('name')) ? slug(input('name')) : null),
      'label' => input('label'),
      'default' => (strlen(input('default')) ? input('default') : null),
      'meta' => (strlen(raw_input('meta')) ? raw_input('meta') : null),
      'version' => input('version', 0)
     ))->save();

     break;
    case 'property':

     $save = Property::init()->validation(array(
      'form_id' => 'numeric|min:1',
      'group_id' => 'numeric|min:1',
      'field_id' => 'numeric|min:1',
     ), array(
      'form_id.numeric' => lang('message.form_must_set', 'Form seçilmelidir!'),
      'form_id.min' => lang('message.form_must_set', 'Form seçilmelidir!'),
      'group_id.numeric' => lang('message.group_must_set', 'Grup seçilmelidir!'),
      'group_id.min' => lang('message.group_must_set', 'Grup seçilmelidir!'),
      'field_id.numeric' => lang('message.field_must_set', 'Alan seçilmelidir!'),
      'field_id.min' => lang('message.field_must_set', 'Alan seçilmelidir!')
     ))->set(array(
      'form_id' => input('form_id', 0),
      'group_id' => input('group_id', 0),
      'field_id' => input('field_id', 0),
      'order' => Property::init()->where('form_id', input('form_id', 0))->where('group_id', input('group_id', 0))->count() + 1
     ))->save(function($id) {
      $this->saveOrders($id);
     });

     break;
    case 'meta':

     $save = Property::init()->set(array(
      'id' => input('id', 0),
      'meta' => (strlen(raw_input('meta')) ? raw_input('meta') : null)
     ))->save();

     break;
    case 'order':

     $save = Property::init()->set(array(
      'id' => input('id', 0),
      'order' => (input('order', 0) ? input('order', 0) : null)
     ))->save(function($id) {
      $this->saveOrders($id);
     });

     break;
    default:

     throw new \Exception(lang('admin.message.type_not_found', 'Tür bulunamadı!'));
   }

   $message->success = true;
   $message->text = lang('message.saved', 'Kaydedildi...');
   $message->return = array(
    'id' => array_get($save, 'id', 0),
    'version' => array_get($save, 'version', 0)
   );
  } catch (\Exception $e) {
   $message->text = $e->getMessage();
  }

  return $message->forData();
 }

 public function deleteForm($lang = null, $type, $id) {
  $manager = static::$manager;
  $manager->app->response->setContentType('json');

  $message = Message::result(lang('message.nothing_done', 'Herhangi bir işlem yapılmadı!'));

  try {
   switch ($type) {
    case 'form':

     $total = FormProperty::init()->whereIn('id', explode(',', $id))->delete();

     break;
    case 'group':

     $total = GroupProperty::init()->whereIn('id', explode(',', $id))->delete();

     break;
    case 'field':

     $total = FieldProperty::init()->whereIn('id', explode(',', $id))->delete();

     break;
    default:

     $total = Property::init()->whereIn('id', explode(',', $id))->delete();

     break;

   }

   if (!$total) {
    throw new \Exception($message->text);
   }

   $message->success = true;
   $message->text = choice('message.deleted', $total, [$total], array(
    'Kayıt silindi...',
    'Kayıtlar silindi...'
   ));
  } catch (\Exception $e) {
   $message->text = $e->getMessage();
  }

  return $message->forData();
 }

}