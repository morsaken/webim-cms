<?php
/**
 * @author Orhan POLAT
 */

namespace System\Property;

use \Webim\Database\Manager as DB;
use \Webim\Library\Controller;

class Manager extends Controller {

 /**
  * Constructor
  */
 public function __construct() {
  parent::__construct(DB::table('sys_form_property'));
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
  * Property elements
  *
  * @param array $rows
  * @param array $params
  *
  * @return array
  */
 protected function elements($rows, $params = array()) {
  if (in_array(__FUNCTION__, $this->called)) {
   return $rows;
  }

  //Add called class
  $this->called[] = __FUNCTION__;

  //Ids
  $form_ids = $this->ids('form_id');
  $group_ids = $this->ids('group_id');
  $field_ids = $this->ids('field_id');

  //Containers
  $forms = array();
  $groups = array();
  $fields = array();

  if (array_get($params, 'forms', true)) {
   $forms = Form::init()->whereIn('id', array_unique($form_ids))->load()->get('rows');
  }

  if (array_get($params, 'groups', true)) {
   $groups = Group::init()->whereIn('id', array_unique($group_ids))->load()->get('rows');
  }

  if (array_get($params, 'fields', true)) {
   $fields = Field::init()->whereIn('id', array_unique($field_ids))->load()->get('rows');
  }

  return array_map(function(&$row) use ($forms, $groups, $fields) {
   //Set default
   $row->form = new \stdClass();
   $row->group = new \stdClass();
   $row->field = new \stdClass();

   foreach ($forms as $form) {
    if ($form->id == array_get($row, 'form_id')) {
     $row->form = clone $form;
     break;
    }
   }

   foreach ($groups as $group) {
    if ($group->id == array_get($row, 'group_id')) {
     $row->group = clone $group;
     break;
    }
   }

   foreach ($fields as $field) {
    if ($field->id == array_get($row, 'field_id')) {
     $row->field = clone $field;
     break;
    }
   }
  }, $rows);
 }

 /**
  * Ordering
  *
  * @param int $id
  */
 public function saveOrders($id) {
  //Get current
  $current = DB::table('sys_form_property')->where('id', $id)->first(array(
   'form_id', 'order'
  ));

  if ($current) {
   $form_id = array_get($current, 'form_id');
   $order = array_get($current, 'order');

   //First make list by group
   $list = array();

   foreach (DB::table('sys_form_property')
             ->where('id', '<>', $id)
             ->where('form_id', $form_id)
             ->orderBy('order')->orderBy('id')->get('id', 'group_id') as $property) {
    $list[array_get($property, 'group_id')][] = array_get($property, 'id');
   }

   //Start
   $num = 1;

   foreach ($list as $group) {
    foreach ($group as $property_id) {
     if ($num == $order) {
      $num++;
     }

     DB::table('sys_form_property')->where('id', $property_id)->update(array(
      'order' => $num
     ));

     $num++;
    }
   }
  }
 }

 /**
  * Cascade properties
  *
  * @param array $forms
  *
  * @return array
  */
 public static function cascade($forms = array()) {
  //Return
  $cascaded = array();

  foreach ($forms as $group_ids) {
   //Form info
   $form = new \stdClass();

   //Groups
   $groups = array();

   foreach ($group_ids as $field_ids) {
    //Group info
    $group = new \stdClass();

    //Group properties
    $fields = array();

    foreach ($field_ids as $property) {
     //Set form info
     $form = $property->form;

     //Set group info
     $group = $property->group;

     //Field
     $field = $property->field;

     $field->property_id = $property->id;
     $field->property_meta = $property->meta;

     //Add to field list
     $fields[] = $field;
    }

    $group->fields = $fields;

    //Add to list
    $groups[] = $group;
   }

   //Add group to form
   $form->groups = $groups;

   $cascaded[] = $form;
  }

  return $cascaded;
 }

}