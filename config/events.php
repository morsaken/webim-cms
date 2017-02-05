<?php
use \Webim\Library\Event;
use \Webim\Library\File;
use \Webim\Library\Log;

Event::listen('log', function ($type, $data) {
  //File path root
  $root = File::getRoot();

  //Line
  $line = date('Y-m-d H:i:s') . ' - ' . str_case($type) . ' - ' . $data . PHP_EOL;

  //Write to file
  File::setRoot('../');
  File::path('webim-logs.' . slug(str_case($type, 'lower')), 'sql-' . @date('Y-m-d') . '.log')->create()->append($line);
  File::setRoot($root);
});

Event::listen('query', function ($sql, $bindings, $time, $database) {
  //Log::write('query', $database . ' (' . $time . '): ' . $sql . ((count($bindings) > 0) ? "\n[".var_export($bindings, true)."]" : ''));
});

Event::listen('query_error', function ($sql, $bindings) {
  Log::write('query_error', $sql . ((count($bindings) > 0) ? "\n[" . var_export($bindings, true) . "]" : ''));
});