<?php
/**
 * @author Orhan POLAT
 */

namespace Admin;

use System\Account;
use Webim\Library\Message;
use Webim\View\Manager as View;

class User {

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
    $manager->addRoute($manager->prefix . '/system/users', __CLASS__ . '::getIndex');
    $manager->addRoute($manager->prefix . '/system/users/form/?:id', __CLASS__ . '::getForm');
    $manager->addRoute($manager->prefix . '/system/users/form/?:id', __CLASS__ . '::postForm', 'POST');
    $manager->addRoute($manager->prefix . '/system/users/form/?:id+', __CLASS__ . '::deleteForm', 'DELETE');

    $parent = $manager->addMenu(lang('admin.menu.system', 'Sistem'), $manager->prefix . '/system', lang('admin.menu.system', 'Sistem'), null, 'fa fa-cogs');
    $manager->addMenu(lang('admin.menu.system', 'Sistem'), $manager->prefix . '/system/users', lang('admin.menu.users', 'Kullanıcılar'), $parent, 'fa fa-user');

    static::$manager = $manager;
  }

  public function getIndex($params = array()) {
    $manager = static::$manager;

    $manager->put('subnavs', array(
      btn(lang('admin.menu.create', 'Yeni Oluştur'), url($manager->prefix . '/system/users/form'), 'fa-plus')
    ));

    $manager->set('roles', array(
      'user' => lang('admin.label.role.user', 'Kullanıcı'),
      'admin' => lang('admin.label.role.admin', 'Yönetici'),
      'root' => lang('admin.label.role.root', 'Süper Yönetici')
    ));

    $manager->set('caption', lang('admin.menu.users', 'Kullanıcılar'));
    $manager->breadcrumb($manager->prefix . '/system', lang('admin.menu.system', 'Sistem'));
    $manager->breadcrumb($manager->prefix . '/system/users', lang('admin.menu.users', 'Kullanıcılar'));

    $manager->put('list',
      Account::init()->where('type', 'user')
        ->orderBy(input('orderby', 'id'), input('order', ['desc', 'asc']))
        ->load(input('offset', 0), input('limit', 20))
        ->with('groups')
        ->with(function ($rows) use ($manager) {
          return array_map(function (&$row) use ($rows, $manager) {
            $row->role = $manager->get('roles.' . $row->role, $row->role);
            $row->active = $row->active == 'true';
            $row->status = $row->active ? lang('admin.label.active', 'Aktif') : lang('admin.label.passive', 'Pasif');
          }, $rows);
        })->get()
    );

    if ($manager->app->request->isAjax()) {
      return array_to($manager->get('list'));
    }

    return View::create('system.users.list')->data($manager::data())->render();
  }

  public function getForm($params = array()) {
    $manager = static::$manager;

    $manager->set('roles', array(
      'user' => lang('admin.label.role.user', 'Kullanıcı'),
      'admin' => lang('admin.label.role.admin', 'Yönetici'),
      'root' => lang('admin.label.role.root', 'Süper Yönetici')
    ));

    $manager->set('groups', Account::init()->where('type', 'group')->load()->getList('first_name'));

    $id = array_get($params, 'id', 0);
    $action = 'new';
    $actionTitle = lang('admin.label.create_new', 'Yeni Oluştur');

    $user = Account::init()
      ->where('type', 'user')
      ->where('id', $id)
      ->load()
      ->with('meta')
      ->with('groups')->with(function ($rows) {
        return array_map(function (&$row) {
          $groups = array();

          foreach ($row->groups as $group) {
            $groups[$group->id] = $group->first_name;
          }

          $row->groups = $groups;
        }, $rows);
      })->get('rows.0');

    if ($user) {
      $action = 'edit';
      $actionTitle = lang('admin.label.edit', 'Düzenle');

      $manager->put('user', $user);
    }

    $manager->set('caption', lang('admin.menu.users', 'Kullanıcılar'));
    $manager->breadcrumb($manager->prefix . '/system', lang('admin.menu.system', 'Sistem'));
    $manager->breadcrumb($manager->prefix . '/system/users', lang('admin.menu.users', 'Kullanıcılar'));
    $manager->breadcrumb($manager->prefix . '/system/users/form', lang('admin.menu.' . $action, $actionTitle));

    return View::create('system.users.form')->data($manager::data())->render();
  }

  public function postForm($params = array()) {
    $manager = static::$manager;

    $manager->app->response->setContentType('json');

    $id = array_get($params, 'id');

    return Account::init()->validation(array(
      'name' => 'required',
      'email' => 'required|email',
      'first_name' => 'required'
    ), array(
      'name.required' => lang('admin.message.user.name_required', 'Kullanıcı adını girin!'),
      'email.required' => lang('admin.message.user.email_required', 'E-Posta adresini adını girin!'),
      'email.email' => lang('admin.message.user.valil_email_required', 'Geçerli e-posta adresini girin!'),
      'first_name.required' => lang('admin.message.user.first_name_required', 'Adı girin!')
    ))->set('id', !is_null($id) ? $id : input('id', 0))
      ->set('type', 'user')
      ->set('role', input('role', array('user', 'admin', 'root')))
      ->set('name', input('name'))
      ->set('email', input('email'))
      ->set('first_name', input('first_name'))
      ->set('last_name', input('last_name'))
      ->set('version', input('version', 0))
      ->set('active', input('active', array('false', 'true')))
      ->save(function ($id) {
        if (!input('id', 0) && !strlen(raw_input('meta-pass'))) {
          throw new \ErrorException(lang('admin.message.user.pass_required', 'Giriş için parola girmelisiniz!'));
        } elseif (strlen(raw_input('meta-pass'))) {
          $this->saveMeta($id, array(
            'pass' => md5(raw_input('meta-pass'))
          ));
        }

        $this->saveGroups($id, input('groups'));
      })->forData();
  }

  public function deleteForm($params = array()) {
    $manager = static::$manager;

    $manager->app->response->setContentType('json');

    $ids = array_filter(explode(',', array_get($params, 'id', '')), function ($id) {
      return (int) $id > 0;
    });

    if (count($ids)) {
      return Account::init()->delete($ids)->forData();
    }

    return Message::result(lang('message.nothing_done', 'Herhangi bir işlem yapılmadı!'))->forData();
  }

}