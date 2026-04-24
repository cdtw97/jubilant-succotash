<?php
declare(strict_types=1);

use MyFrancis\Core\Application;
use MyFrancis\Core\Request;

ini_set('display_errors', '0');
error_reporting(E_ALL);

$basePath = dirname(__DIR__);
$autoloadPath = $basePath . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

if (! is_file($autoloadPath)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Dependencies are not installed. Run composer install.';
    exit(1);
}

require $autoloadPath;

$application = require $basePath . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'bootstrap.php';

if (! $application instanceof Application) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Application bootstrap failed.';
    exit(1);
}

$application
    ->loadRoutes($basePath . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'web.php')
    ->loadRoutes($basePath . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'internal.php')
    ->handle(Request::capture())
    ->send();
