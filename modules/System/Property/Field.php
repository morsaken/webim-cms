<?php
/**
 * @author Orhan POLAT
 */

namespace System\Property;

use \Webim\Database\Manager as DB;
use \Webim\Library\Controller;

class Field extends Controller {

  /**
   * Valid field types
   *
   * @var array
   */
  static public $types = array(
    'text', 'select', 'radio', 'checkbox', 'textarea', 'file'
  );

  /**
   * Constructor
   */
  public function __construct() {
    parent::__construct(DB::table('sys_form_field'));
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
   * Check value
   *
   * @param string $field
   * @param mixed $value
   *
   * @return null|string
   */
  private static function checkValue($field, $value) {
    //Return
    $error = null;

    if (array_get($field, 'meta.required') && !strlen($value)) {
      $error = lang('message.field_is_required', [array_get($field, 'label')], 'Alan adı boş geçilemez: %s');

      if (strlen(array_get($field, 'meta.required_message'))) {
        $error = array_get($field, 'meta.required_message');
      }
    }

    if (array_get($field, 'meta.min')) {
      if (array_get($field, 'meta.numeric') && intval($value) < array_get($field, 'meta.min', 0)) {
        $error = lang('message.field_is_small', [array_get($field, 'meta.min', 0), array_get($field, 'label')], 'Alan en az %s olabilir: %s');
      } elseif (strlen($value) < array_get($field, 'meta.min', 0)) {
        $error = lang('message.field_is_less_characters', [array_get($field, 'meta.min', 0), array_get($field, 'label')], 'Alan en az %s karakter olabilir: %s');
      }

      if (strlen(array_get($field, 'meta.min_message'))) {
        $error = array_get($field, 'meta.min_message');
      }
    }

    if (array_get($field, 'meta.max')) {
      if (array_get($field, 'meta.numeric') && intval($value) > array_get($field, 'meta.min', 0)) {
        $error = lang('message.field_is_big', [array_get($field, 'meta.max', 0), array_get($field, 'label')], 'Alan en fazla %s olabilir: %s');
      } elseif (strlen($value) > array_get($field, 'meta.max', 0)) {
        $error = lang('message.field_is_more_characters', [array_get($field, 'meta.max', 0), array_get($field, 'label')], 'Alan en fazla %s karakter olabilir: %s');
      }

      if (strlen(array_get($field, 'meta.max_message'))) {
        $error = array_get($field, 'meta.max_message');
      }
    }

    if ((array_get($field, 'meta.required') || strlen($value)) && array_get($field, 'meta.regex') && !@preg_match(array_get($field, 'meta.regex'), $value)) {
      $error = lang('message.field_is_not_valid', [array_get($field, 'label')], 'Alan istenilen kriterlere uymuyor: %s');

      if (strlen(array_get($field, 'meta.regex_message'))) {
        $error = array_get($field, 'meta.regex_message');
      }
    }

    return $error;
  }

  /**
   * Check values
   *
   * @param array $fields
   * @param array $values
   * @param callable $callback
   *
   * @throws \Exception
   */
  public static function checkValues($fields, $values, $callback) {
    foreach ($fields as $id => $field) {
      $value = array_get($values, $id);

      //Returning error if not null
      $error = static::checkValue($field, $value);

      if (!is_null($error) && strlen(array_get($field, 'meta.related_to'))) {
        $related_to = null;
        $related_value = null;

        foreach ($fields as $related_key => $related_field) {
          if (array_get($related_field, 'name') == array_get($field, 'meta.related_to')) {
            $related_to = $related_field;
            $related_value = array_get($values, $related_key);
          }
        }

        if ($related_to && !($related_error = static::checkValue($related_to, $related_value))) {
          $error = null;
        }
      }

      if ($error) {
        throw new \Exception($error);
      }

      $text = array_get($field, 'meta.options.' . $value, $value);

      call_user_func($callback, $id, $value, $text);
    }
  }

}