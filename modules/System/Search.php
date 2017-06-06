<?php
/**
 * @author Orhan POLAT
 */

namespace System;

use \Webim\Database\Manager as DB;
use \Webim\Library\Carbon;

class Search {

  /**
   * Target content types
   *
   * @var array
   */
  protected static $types = array();

  /**
   * Target language
   *
   * @var string
   */
  protected static $language;

  /**
   * Word to search
   *
   * @var string
   */
  protected $keyword;

  /**
   * Constructor
   *
   * @param string $keyword
   */
  public function __construct($keyword) {
    $this->keyword = $keyword;
  }

  /**
   * Init class
   *
   * @param string $keyword
   *
   * @return static
   */
  public static function init($keyword) {
    return new static($keyword);
  }

  /**
   * Get list
   *
   * @param null|int $offset
   * @param null|int $limit
   * @param null|\Closure $order
   *
   * @return \stdClass
   */
  public function get($offset = null, $limit = null, $order = null) {
    $keyword = $this->keyword;

    $query = DB::table('sys_content as c');

    if (count(static::$types)) {
      $query->whereIn('c.type', static::$types);
    }

    if (strlen(static::$language)) {
      $query->where('c.language', static::$language);
    }

    $query = $query->where('publish_date', '<=', DB::val(Carbon::now()))->where(DB::func('IFNULL', array(
      'expire_date',
      DB::val(Carbon::now())
    )), '>=', DB::val(Carbon::now()))->where('c.active', 'true');

    $query = $query->where(function ($query) use ($keyword) {
      $query->where('c.title', 'like', '%' . $keyword . '%');

      $query->orWhereExists(function ($exists) use ($keyword) {
        $exists->select('m.content_id')
          ->from('sys_content_meta as m')
          ->where('m.content_id', DB::func(null, 'c.id'))
          ->where(function ($query) use ($keyword) {
            $query->where('m.value', 'like', '%' . $keyword . '%');
          });
      });
    });

    if ($order instanceof \Closure) {
      $query = call_user_func($order, $query);
    } else {
      $query = $query->orderBy('publish_date', 'desc');
    }

    $query = $query->lists('c.id');

    $result = new \stdClass();
    $result->offset = $offset;
    $result->limit = $limit;
    $result->total = count($query);
    $result->ids = array_slice($query, (int) $offset, $limit);

    return $result;
  }

  /**
   * Set types
   *
   * @param array $types
   *
   * @return $this
   */
  public function types($types = array()) {
    static::setTypes($types);

    return $this;
  }

  /**
   * Set types
   *
   * @param array $types
   */
  public static function setTypes($types = array()) {
    static::$types = $types;
  }

  /**
   * Set language
   *
   * @param string $language
   *
   * @return $this
   */
  public function language($language) {
    static::setLanguage($language);

    return $this;
  }

  /**
   * Set language
   *
   * @param string $language
   */
  public static function setLanguage($language) {
    static::$language = $language;
  }

}