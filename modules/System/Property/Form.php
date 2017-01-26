<?php
/**
 * @author Orhan POLAT
 */

namespace System\Property;

use \Webim\Database\Manager as DB;
use \Webim\Library\Controller;

class Form extends Controller {

  /**
   * Constructor
   */
  public function __construct() {
    parent::__construct(DB::table('sys_form'));
  }

  /**
   * Static creator
   *
   * @return self
   */
  public static function init() {
    return new self();
  }

}