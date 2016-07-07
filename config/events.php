<?php
use \Webim\Library\Event;
use \Webim\Library\Log;

Event::listen('query', function($sql, $bindings, $time, $database){
 Log::write('query', $database . ' (' . $time . '): ' . $sql . ((count($bindings) > 0) ? "\n[".var_export($bindings, true)."]" : ''));
});

Event::listen('query_error', function($sql, $bindings) {
 Log::write('query_error', $sql . ((count($bindings) > 0) ? "\n[".var_export($bindings, true)."]" : ''));
});