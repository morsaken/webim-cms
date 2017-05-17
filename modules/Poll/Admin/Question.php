<?php
/**
 * @author Orhan POLAT
 */

namespace Poll\Admin;

use \Admin\Manager;
use \Webim\Database\Manager as DB;
use \Webim\Library\Controller;
use \Webim\View\Manager as View;

class Question extends Controller {

  /**
   * Manager
   *
   * @var \Admin\Manager
   */
  protected static $manager;

  /**
   * Constructor
   */
  public function __construct() {
    parent::__construct(DB::table('app_poll_question'));
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
   * Register current class and routes
   *
   * @param \Admin\Manager $manager
   */
  public static function register(Manager $manager) {
    $manager->addRoute($manager->prefix . '/poll', __CLASS__ . '::getIndex');
    $manager->addRoute($manager->prefix . '/poll/form/?:id', __CLASS__ . '::getForm');
    $manager->addRoute($manager->prefix . '/poll/form/?:id', __CLASS__ . '::postForm', 'POST');
    $manager->addRoute($manager->prefix . '/poll/form/?:id', __CLASS__ . '::deleteForm', 'DELETE');
    $manager->addRoute($manager->prefix . '/poll/stats', __CLASS__ . '::getStats');

    $manager->addMenu(lang('admin.menu.modules', 'Modüller'), $manager->prefix . '/poll', lang('admin.menu.polls', 'Anketler'), null, 'fa fa-question-circle');

    static::$manager = $manager;
  }

  public function getIndex($params = array()) {
    $manager = static::$manager;

    $manager->put('subnavs', array(
      btn(lang('admin.menu.create', 'Yeni Oluştur'), url($manager->prefix . '/poll/form'), 'fa-plus'),
      btn(lang('admin.menu.poll_stats', 'İstatistikler'), url($manager->prefix . '/poll/stats'), 'fa-line-chart')
    ));

    $manager->breadcrumb($manager->prefix . '/poll', lang('admin.menu.polls', 'Anketler'));

    $manager->set('list', static::init()->orderBy('id', 'desc')->load(input('offset', 0), input('limit', 20))->with(function ($rows) {
      return array_map(function (&$row) {
        $row->active = $row->active == 'true';
        $row->status = $row->active ? lang('admin.label.active', 'Aktif') : lang('admin.label.passive', 'Pasif');
      }, $rows);
    })->get());

    return View::create('modules.poll.list')->data($manager::data())->render();
  }

  public function postIndex($params = array()) {
    $manager = static::$manager;

    $manager->app->response->setContentType('json');

    $list = static::init()->orderBy(input('orderby', 'id'), input('order', ['desc', 'asc']))
      ->load(input('offset', 0), input('limit', 20))
      ->with(function ($rows) {
        return array_map(function (&$row) use ($rows) {
          $row->active = $row->active == 'true';
          $row->status = $row->active ? lang('admin.label.active', 'Aktif') : lang('admin.label.passive', 'Pasif');
        }, $rows);
      })->get();

    return array_to($list);
  }

  protected function politicians() {
    $politicians = array();

    foreach (DB::table('app_poll_politician')->where('active', 'true')->get() as $row) {
      $politicians[] = (object) $row;
    }

    return $politicians;
  }

  public function getForm($params = array()) {
    $manager = static::$manager;

    $manager->set('politicians', $this->politicians());

    $id = array_get($params, 'id', 0);
    $action = 'new';
    $actionTitle = lang('admin.label.create_new', 'Yeni Oluştur');

    $poll = static::init()->where('id', $id)->load()->with(function ($rows) {
      $thinks = array();

      if (count($this->ids())) {
        foreach (DB::table('app_poll_politician_think')
                   ->whereIn('question_id', $this->ids())
                   ->get() as $row) {
          $think = new \stdClass();
          $think->think = $row['think'];
          $think->description = $row['description'];

          $thinks[$row['question_id']][$row['politician_id']] = $think;
        }
      }

      return array_map(function (&$row) use ($thinks) {
        $row->politicians = array();

        foreach ($thinks as $question_id => $politicians) {
          if ($row->id == $question_id) {
            $row->politicians = $politicians;
          }
        }
      }, $rows);
    })->get('rows.0');

    if ($poll) {
      $action = 'edit';
      $actionTitle = lang('admin.label.edit', 'Düzenle');

      $manager->put('poll', $poll);
    }

    $manager->breadcrumb($manager->prefix . '/poll', lang('admin.menu.polls', 'Anketler'));
    $manager->breadcrumb($manager->prefix . '/poll/form', lang('admin.menu.' . $action, $actionTitle));

    return View::create('modules.poll.form')->data($manager::data())->render();
  }

  public function postForm($params = array()) {
    $manager = static::$manager;

    $manager->app->response->setContentType('json');

    $id = array_get($params, 'id');

    $politicians = $this->politicians();

    return static::init()->validation(array(
      'question' => 'required'
    ), array(
      'question.required' => 'Soruyu yazın!'
    ))->set('id', !is_null($id) ? $id : input('id', 0))
      ->set('question', input('question'))
      ->set('version', input('version', 0))
      ->set('active', input('active', array('false', 'true')))
      ->save(function ($id) use ($politicians) {
        DB::table('app_poll_politician_think')->where('question_id', $id)->delete();

        foreach ($politicians as $politician) {
          DB::table('app_poll_politician_think')->insert(array(
            'politician_id' => $politician->id,
            'question_id' => $id,
            'think' => input('think-' . $politician->id, array('agree', 'disagree', 'neutral')),
            'description' => input('description-' . $politician->id)
          ));
        }
      })->forData();
  }

  public function deleteForm($params = array()) {
    $manager = static::$manager;

    $manager->app->response->setContentType('json');

    $id = array_get($params, 'id');

    return static::init()->delete($id)->forData();
  }

  public function getStats($params = array()) {
    $manager = static::$manager;

    /*
    //Total
    $total = DB::table('app_poll_vote_result')->count();

    //Stat 1
    $stat1 = array(
     1 => 0, 0, 0, 0
    );

    foreach (DB::table('app_poll_politician as p')
     ->join('app_poll_politician_think as t', 't.politician_id', '=', 'p.id')
     ->join('app_poll_vote_result as r', function($join) {
      $join->on('r.question_id', '=', 't.question_id');
      $join->on('r.result', '=', 't.think');
     })->groupBy('p.id')->get(array(
      'p.id', DB::func('COUNT', '*', 'total')
     )) as $row) {
     $stat1[$row['id']] = $row['total'];
    }

    //Stat 2
    $stat2 = array(
     '0' => array(
      1 => 0, 0, 0, 0
     ),
     '18-24' => array(
      1 => 0, 0, 0, 0
     ),
     '25-29' => array(
      1 => 0, 0, 0, 0
     ),
     '30-35' => array(
      1 => 0, 0, 0, 0
     ),
     '36-45' => array(
      1 => 0, 0, 0, 0
     ),
     '46-54' => array(
      1 => 0, 0, 0, 0
     ),
     '55-..' => array(
      1 => 0, 0, 0, 0
     )
    );

    foreach (DB::table('app_poll_politician as p')
     ->join('app_poll_politician_think as t', 't.politician_id', '=', 'p.id')
     ->join('app_poll_vote_result as r', function($join) {
      $join->on('r.question_id', '=', 't.question_id');
      $join->on('r.result', '=', 't.think');
     })->join('app_poll_vote as v', 'v.id', '=', 'r.vote_id')->groupBy('p.id', 'v.age')->get(array(
      'v.age', 'p.id', DB::func('COUNT', '*', 'total')
     )) as $row) {
     $stat2[(is_null($row['age']) ? '0' : $row['age'])][$row['id']] = $row['total'];
    }

    $stat3 = array(
     'unknown' => array(
      1 => 0, 0, 0, 0
     ),
       'male' => array(
      1 => 0, 0, 0, 0
     ),
       'female' => array(
      1 => 0, 0, 0, 0
     )
    );

    $manager->put(array(
     'stat1_1' => ceil($stat1[1] / $total * 100),
     'stat1_2' => ceil($stat1[2] / $total * 100),
     'stat1_3' => ceil($stat1[3] / $total * 100),
     'stat1_4' => ceil($stat1[4] / $total * 100),
     'stat2_1' => array()
    ));*/

    $stat1 = array();

    $n = 0;

    foreach (DB::table('app_poll_vote as v')
               ->join('app_poll_vote_result as r', 'r.vote_id', '=', 'v.id')
               ->join('app_poll_question as q', 'q.id', '=', 'r.question_id')
               ->groupBy('r.question_id', 'r.result')
               ->get(array(
                 'q.question', 'r.question_id', 'r.result', DB::func('COUNT', '*', 'total')
               )) as $row) {
      if (!isset($stat1[$row['question_id']])) {
        $stat1[$row['question_id']] = array(
          'number' => ++$n,
          'question' => $row['question'],
          'agree' => 0,
          'disagree' => 0,
          'neutral' => 0
        );
      }

      $stat1[$row['question_id']][$row['result']] = $row['total'];
    }

    $manager->set('stat1', $stat1);

    $stat2 = array();

    $n = 0;

    foreach (DB::table('app_poll_vote_result as r')
               ->join('app_poll_politician_think as t', function ($join) {
                 $join->on('t.question_id', '=', 'r.question_id');
                 $join->on('t.think', '=', 'r.result');
               })->join('app_poll_politician as p', 'p.id', '=', 't.politician_id')
               ->groupBy('t.politician_id')->orderBy('total', 'desc')->get(array(
        't.politician_id', 'p.full_name', DB::func('COUNT', '*', 'total')
      )) as $row) {
      $stat2[$row['politician_id']] = array(
        'number' => ++$n,
        'politician' => $row['full_name'],
        'total' => $row['total']
      );
    }

    $manager->set('stat2', $stat2);

    $stat3 = array(
      1 => array(),
      array(), array(), array()
    );
    /*
      $n = 0;

      foreach (DB::select(DB::raw('SELECT
      `v`.`politician_id`,
      `q`.`question`,
      `r`.`question_id`,
      `r`.`result`,
      COUNT(*) AS `total`
    FROM
      (SELECT
          `v2`.`id`, `t`.`politician_id`
        FROM
          `app_poll_vote` AS `v2`
        INNER JOIN `app_poll_vote_result` AS `r2` ON (`r2`.`vote_id` = `v2`.`id`)
        INNER JOIN `app_poll_politician_think` AS `t` ON (
          `t`.`question_id` = `r2`.`question_id`
          AND `t`.`think` = `r2`.`result`
        )
        INNER JOIN `app_poll_politician` AS `p` ON (
          `p`.`id` = `t`.`politician_id`
        )
    ) AS `v`
    INNER JOIN `app_poll_vote_result` AS `r` ON (`r`.`vote_id` = `v`.`id`)
    INNER JOIN `app_poll_question` AS `q` ON (`q`.`id` = `r`.`question_id`)
    GROUP BY
      `v`.`politician_id`,
      `r`.`question_id`,
      `r`.`result`')) as $row) {
       if (!isset($stat3[$row['question_id']])) {
        $stat3[$row['politician_id']][$row['question_id']] = array(
         'number' => ++$n,
         'question' => $row['question'],
         'agree' => 0,
         'disagree' => 0,
         'neutral' => 0
        );
       }

       $stat3[$row['politician_id']][$row['question_id']][$row['result']] = $row['total'];
      }
    */
    $manager->set('stat3', $stat3);

    return View::create('modules.poll.stats')->data($manager::data())->render();
  }

}