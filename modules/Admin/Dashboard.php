<?php
/**
 * @author Orhan POLAT
 */

namespace Admin;

use \System\Access;
use \System\Content;
use \System\Mail;
use \Webim\Database\Manager as DB;
use \Webim\View\Manager as View;

class Dashboard {

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
    $manager->addRoute(array(
      $manager->prefix,
      $manager->prefix . '/dashboard'
    ), __CLASS__ . '::getIndex', 'GET');

    //Refresh session
    $manager->addRoute($manager->prefix . '/refresh', __CLASS__ . '::refresh', 'POST');

    $manager->addMenu(lang('admin.menu.system', 'Sistem'), $manager->prefix, lang('admin.menu.dashboard', 'Panel'), null, 'fa fa-home');

    static::$manager = $manager;
  }

  /**
   * Get
   *
   * @param array $params
   *
   * @return string
   */
  public function getIndex($params = array()) {
    $manager = static::$manager;

    $manager->put(array(
      'newsCount' => Content::init()->where('type', 'news')->count(),
      'pageCount' => Content::init()->where('type', 'page')->count(),
      'mediaCount' => Content::init()->where('type', 'media')->count(),
      'inboxCount' => Mail::inbox()->count()
    ));

    $referrals = array();

    foreach (Access::init()->whereNotNull('referer')->groupBy('referer')->orderBy('total', 'desc')->addSelect(array(
        'referer',
        DB::func('COUNT', DB::raw('*'), 'total'))
    )->load(0, 5)->get('rows') as $row) {
      $referral = new \stdClass();
      $referral->url = $row->referer;
      $referral->total = $row->total;

      $referrals[] = $referral;
    }

    $data = array();
    $months = array();

    for ($m = 1; $m <= 12; $m++) {
      $months[] = str_case(substr(lang('date.months.' . $m), 0, 3));
      $data[$m - 1] = 0;
    }

    $manager->set('months', $months);

    $directly = $data;
    $referral = $data;

    foreach (Access::init()->whereYear('accessed_at', '=', date('Y'))->whereNull('referer')->groupBy(DB::func('MONTH', 'accessed_at', null))->addSelect(array(
        DB::func('MONTH', 'accessed_at', 'month'),
        DB::func('COUNT', DB::raw('*'), 'total'))
    )->load()->get('rows') as $row) {
      $directly[$row->month - 1] = (int) $row->total;
    }

    foreach (Access::init()->whereYear('accessed_at', '=', date('Y'))->whereNotNull('referer')->groupBy(DB::func('MONTH', 'accessed_at', null))->addSelect(array(
        DB::func('MONTH', 'accessed_at', 'month'),
        DB::func('COUNT', DB::raw('*'), 'total'))
    )->load()->get('rows') as $row) {
      $referral[$row->month - 1] = (int) $row->total;
    }

    $manager->set('stats', array(
      'access' => Access::init()->count(),
      'unique' => DB::table(DB::raw('(' . DB::table('sys_access')->groupBy('ip_address')->addSelect(DB::raw('COUNT(*)'))->toSql() . ') AS t'))->count(),
      'referrals' => $referrals,
      'data' => array(
        'access' => array(
          'directly' => $directly,
          'referral' => $referral
        )
      )
    ));

    $manager->set('caption', lang('admin.menu.dashboard', 'Panel'));

    return View::create('dashboard')->data($manager::data())->render();
  }

  /**
   * Refresh session
   *
   * @return string
   */
  public function refresh() {
    $manager = static::$manager;

    $manager->app->response->setContentType('json');

    return array_to(array('success' => true));
  }

}