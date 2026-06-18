<?php

$basePath = '/home/u454992533/domains/powersab2b.com/backend';
$prefix = '/backend';

if (! empty($_SERVER['REQUEST_URI']) && str_starts_with($_SERVER['REQUEST_URI'], $prefix)) {
    $_SERVER['REQUEST_URI'] = substr($_SERVER['REQUEST_URI'], strlen($prefix)) ?: '/';
    $_SERVER['SCRIPT_NAME'] = $prefix.'/index.php';
    $_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'];
}

require $basePath.'/public/index.php';
