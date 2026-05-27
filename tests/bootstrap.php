<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

// Hard defaults for the functional test environment. These must be set BEFORE
// Dotenv::bootEnv runs, because Symfony's Dotenv does not override existing
// $_SERVER vars when loading .env / .env.test, and we need these specific
// values regardless of what's in .env.
$_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = 'test';
$_SERVER['KERNEL_CLASS'] = $_ENV['KERNEL_CLASS'] = 'App\\Kernel';
$_SERVER['MAILER_DSN'] = $_ENV['MAILER_DSN'] = 'null://null';

if (file_exists(dirname(__DIR__).'/config/bootstrap.php')) {
    require dirname(__DIR__).'/config/bootstrap.php';
} elseif (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}
