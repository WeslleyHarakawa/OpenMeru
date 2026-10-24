<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Works two ways: the web root points straight at openmeru-app/public_root,
// or this folder is copied to ~/htdocs/<domain> next to ~/openmeru-app (CloudPanel).
$base = is_file(dirname(__DIR__).'/artisan') ? dirname(__DIR__) : dirname(__DIR__, 2).'/openmeru-app';

if (file_exists($maintenance = $base.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $base.'/vendor/autoload.php';

/** @var Application $app */
$app = require_once $base.'/bootstrap/app.php';
$app->usePublicPath(__DIR__);

$app->handleRequest(Request::capture());
