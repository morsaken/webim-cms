<?php
/**
 * @author Orhan POLAT
 */

if (version_compare(PHP_VERSION, '5.4', '<')) {
  die('PHP version (' . PHP_VERSION . ') must be at least 5.4');
}

define('WEBIM', true);
define('VERSION', '11.0.0');
define('AUTHOR', 'Orhan POLAT');
define('COPYRIGHT', '© 2017 Powered By Masters');
define('DS', DIRECTORY_SEPARATOR);
define('PUB_ROOT', __DIR__ . DS);
define('SYS_ROOT', PUB_ROOT . '..' . DS . 'sys' . DS);
define('INDEX', basename(__FILE__));
define('EXT', strrchr(INDEX, '.'));
define('START_TIME', microtime(true));

if (!file_exists(SYS_ROOT)) {
  die('System folder not found!');
}

require(SYS_ROOT . 'Webim' . DS . 'core' . EXT);
require(PUB_ROOT . 'modules' . DS . 'helpers' . EXT);
require(PUB_ROOT . 'modules' . DS . 'app' . EXT);
