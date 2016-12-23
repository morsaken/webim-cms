<?php
/**
 * @author Orhan POLAT
 */

namespace System;

use \Webim\Database\Manager as DB;
use \Webim\Library\Controller;
use \Webim\Library\Message;
use \Webim\Library\Str;

class Object extends Controller {

 /**
  * Block size
  */
 const SIZE = 254;

 /**
  * Constructor
  */
 public function __construct() {
  parent::__construct(DB::table('sys_object'));
 }

 /**
  * Static creator
  *
  * @return \System\Object
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
   if (!parent::unique('name')) {
    throw new \Exception(lang('message.duplicate_entry', [array_get($this->data, 'name')], 'Bu isimde bir kayıt var: %s'));
   } elseif (!parent::unique('email')) {
    throw new \Exception(lang('message.duplicate_entry', [array_get($this->data, 'email')], 'Bu isimde bir kayıt var: %s'));
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
  switch ($only) {
   case 'type':

    $this->where('type', (string) $params);

    break;
   case 'role':

    $this->whereIn('role', (array) $params);

    break;
   case 'active':

    $this->where('active', 'true');

    break;
   case 'meta':

    if (count((array) $params)) {
     $this->where(function ($query) use ($params) {
      //Number of sql
      $num = 0;

      foreach ((array) $params as $key => $value) {
       $query->whereExists(function ($exists) use ($num, $key, $value) {
        $exists->select('meta' . $num . '.object_id')
         ->from('sys_object_meta as meta' . $num)
         ->where('meta' . $num . '.object_id', DB::func(null, 'sys_object.id'))
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

  return $this;
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

  foreach (DB::table('sys_object_meta')
            ->whereIn('object_id', $this->ids())
            ->get() as $row) {
   //Change key into array
   $key = implode('.', array_pad(explode('.', $row['key']), 2, 0));

   //Set
   array_set($values, $row['object_id'] . '.' . $key, $row['value']);
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
  * @param int $object_id
  * @param array $meta
  * @param bool $reset
  */
 public function saveMeta($object_id, $meta = array(), $reset = true) {
  if ($object_id > 0) {
   if ($reset) {
    //Remove all
    DB::table('sys_object_meta')->where('object_id', $object_id)->delete();
   } else {
    foreach (array_keys($meta) as $key) {
     DB::table('sys_object_meta')
      ->where('object_id', $object_id)
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
       'object_id' => $object_id,
       'key' => $newKey,
       'value' => $text
      );
     }
    }
   }

   foreach ($inserts as $insert) {
    DB::table('sys_object_meta')->insert($insert);
   }
  }
 }

 /**
  * Group members
  *
  * @param array $rows
  * @param array $params
  *
  * @return array
  */
 protected function members($rows, $params = array()) {
  if (in_array(__FUNCTION__, $this->called)) {
   return $rows;
  }

  //Add called class
  $this->called[] = __FUNCTION__;

  //Get member ids
  $ids = array();

  foreach (DB::table('sys_object_member')
            ->whereIn('parent_id', $this->ids())
            ->get() as $row) {
   $ids[array_get($row, 'child_id')][array_get($row, 'parent_id')] = array_get($row, 'parent_id');
  }

  //Set user ids of the user list
  $user_ids = array_keys($ids);

  //Category list
  $list = array();

  if (count($user_ids)) {
   foreach (self::init()->whereIn('id', $user_ids)->load()->with('meta')->get('rows') as $row) {
    foreach (array_get($ids, array_get($row, 'id')) as $group_id) {
     $list[$group_id][] = $row;
    }
   }
  }

  return array_map(function (&$row) use ($list) {
   $row->members = array();

   foreach ($list as $id => $members) {
    if ($id == $row->id) {
     $row->members = $members;
    }
   }
  }, $rows);
 }

 /**
  * Total group members
  *
  * @param array $rows
  * @param array $params
  *
  * @return array
  */
 protected function totalMembers($rows, $params = array()) {
  if (in_array(__FUNCTION__, $this->called)) {
   return $rows;
  }

  //Add called class
  $this->called[] = __FUNCTION__;

  //List
  $list = array();

  foreach (DB::table('sys_object_member')
            ->whereIn('parent_id', $this->ids())
            ->groupBy('parent_id')
            ->get(array(
             'parent_id',
             DB::func('COUNT', DB::raw('*'), 'total')
            )) as $row) {
   $list[array_get($row, 'parent_id')] = array_get($row, 'total', 0);
  }

  return array_map(function (&$row) use ($list) {
   $row->totalMembers = 0;

   foreach ($list as $id => $total) {
    if ($id == $row->id) {
     $row->totalMembers = $total;
    }
   }
  }, $rows);
 }

 /**
  * Save members
  *
  * @param int $group_id
  * @param array $members
  * @param bool $reset
  */
 public function saveMembers($group_id, $members = array(), $reset  = true) {
  if ($group_id > 0) {
   if ($reset) {
    //Remove all
    DB::table('sys_object_member')->where('parent_id', $group_id)->delete();
   }

   //Id list for save
   $user_ids = array_filter((array) $members, function ($id) {
    return is_numeric($id) && ($id > 0);
   });

   //Maybe members come with name
   $user_names = array_filter((array) $members, function ($id) {
    return is_string($id) && strlen($id);
   });

   if (count($user_names)) {
    $query = DB::table('sys_object')->whereType('user')->whereIn('name', $user_names)->lists('id');

    foreach ($query as $id) {
     if (!in_array($id, $user_ids)) {
      $user_ids[] = $id;
     }
    }
   }

   //Remove repeats
   array_unique($user_ids);

   //Add
   $inserts = array();

   foreach ($user_ids as $user_id) {
    $inserts[] = array(
     'parent_id' => $group_id,
     'child_id' => $user_id
    );
   }

   if (count($inserts)) {
    DB::table('sys_object_member')->insert($inserts);
   }
  }
 }

 /**
  * User groups
  *
  * @param array $rows
  * @param array $params
  *
  * @return array
  */
 protected function groups($rows, $params = array()) {
  if (in_array(__FUNCTION__, $this->called)) {
   return $rows;
  }

  //Add called class
  $this->called[] = __FUNCTION__;

  //Get member ids
  $ids = array();

  foreach (DB::table('sys_object_member')
            ->whereIn('child_id', $this->ids())
            ->get() as $row) {
   $ids[array_get($row, 'parent_id')][array_get($row, 'child_id')] = array_get($row, 'child_id');
  }

  //Set user ids of the user list
  $group_ids = array_keys($ids);

  //Category list
  $list = array();

  if (count($group_ids)) {
   foreach (self::init()->whereIn('id', $group_ids)->load()->with('meta')->get('rows') as $row) {
    foreach (array_get($ids, array_get($row, 'id')) as $user_id) {
     $list[$user_id][] = $row;
    }
   }
  }

  return array_map(function (&$row) use ($list) {
   $row->groups = array();

   foreach ($list as $id => $groups) {
    if ($id == $row->id) {
     $row->groups = $groups;
    }
   }
  }, $rows);
 }

 /**
  * Save groups
  *
  * @param int $user_id
  * @param array $groups
  * @param bool $reset
  */
 public function saveGroups($user_id, $groups = array(), $reset = true) {
  if ($user_id > 0) {
   if ($reset) {
    //Remove all
    DB::table('sys_object_member')->where('child_id', $user_id)->delete();
   }

   //Id list for save
   $group_ids = array_filter((array) $groups, function ($id) {
    return is_numeric($id) && ($id > 0);
   });

   //Maybe members come with name
   $group_names = array_filter((array) $groups, function ($id) {
    return is_string($id) && strlen($id);
   });

   if (count($group_names)) {
    $query = DB::table('sys_object')->whereType('group')->whereIn('name', $group_names)->lists('id');

    foreach ($query as $id) {
     if (!in_array($id, $group_ids)) {
      $group_ids[] = $id;
     }
    }
   }

   //Remove repeats
   array_unique($group_ids);

   //Add
   $inserts = array();

   foreach ($group_ids as $group_id) {
    $inserts[] = array(
     'parent_id' => $group_id,
     'child_id' => $user_id
    );
   }

   if (count($inserts)) {
    DB::table('sys_object_member')->insert($inserts);
   }
  }
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

  if (in_array(me('id'), $id)) {
   $message->text = lang('message.cannot_delete_yourself', 'Kendi kaydınızı silemezsiniz!');
  } elseif (DB::table('sys_object_member')->whereIn('parent_id', $id)->count()) {
   $message->text = lang('message.record_is_parent_of_another', 'Mevcut nesne başka bir nesnenin üst sınıfı!');
  } else {
   try {
    $deleted = parent::delete(function() use ($id) {
     DB::table('sys_settings')->whereIn('owner_id', $id)->delete();

     $mail_ids = DB::table('sys_mail')->whereIn('sender_id', $id)->lists('id');

     if (count($mail_ids)) {
      DB::table('sys_mail_attachment')->whereIn('id', $mail_ids)->delete();
      DB::table('sys_mail_recipient')->whereIn('recipient_id', $id)->delete();
      DB::table('sys_mail')->whereIn('id', $mail_ids)->delete();
     }

     DB::table('sys_content_permission')->whereIn('object_id', $id)->delete();
     DB::table('sys_object_member')->whereIn('child_id', $id)->delete();
     DB::table('sys_object_member')->whereIn('parent_id', $id)->delete();
     DB::table('sys_object_meta')->whereIn('object_id', $id)->delete();
     DB::table('sys_object_session')->whereIn('object_id', $id)->delete();

     $delete = DB::table('sys_object')->whereIn('id', $id)->delete();

     return $delete->return;
    });

    if ($deleted) {
     $message->success = true;
     $message->text = choice('message.deleted', $deleted, null, array(
      'Kayıt silindi...',
      'Kayıtlar silindi...'
     ));
    }
   } catch (\Exception $e) {
    $message->text = $e->getMessage();
   }
  }

  return $message;
 }

 /**
  * Find user
  *
  * @param mixed $value
  * @param string $key
  *
  * @return mixed
  */
 public static function findUser($value, $key = 'id') {
  $object = new self();

  return $object->where($key, $value)->load()->with('meta')->with('groups')->get('rows.0');
 }

 /**
  * Find user or group
  *
  * @param array $value
  * @param string $key
  *
  * @return mixed
  */
 public static function findUsers($value, $key = 'id') {
  $objects = new self();

  if (!is_array($value)) {
   $value = array($value);
  }

  //Returning list
  $list = array();

  foreach ($objects->whereIn($key, $value)->load()->with('meta')->with('groups')->with('members')->get('rows') as $row) {
   if ($row->type == 'group') {
    foreach ($row->members as $member) {
     $list[] = $member;
    }
   } else {
    unset($row->members);
    $list[] = $row;
   }
  }

  return $list;
 }

}