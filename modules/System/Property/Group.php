<?php
/**
 * @author Orhan POLAT
 */

namespace System\Property;

use \Webim\Database\Manager as DB;
use \Webim\Library\Controller;

class Group extends Controller {

  /**
   * Constructor
   */
  public function __construct() {
    parent::__construct(DB::table('sys_form_group'));
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