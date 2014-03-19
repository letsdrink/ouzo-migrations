<?php
use Ouzo\Config;
use Ouzo\Utilities\Path;

require __DIR__ . '/../vendor/autoload.php';

define("OUZO_BASE", Path::join(__DIR__, '..'));
define("OUZO_BASE_TEST", Path::join(__DIR__));
define("ROOT_PATH", '');

/** @noinspection PhpIncludeInspection */
require_once Path::join(OUZO_BASE, 'config', 'database.inc.php');

Config::registerConfig(new DatabaseConfig());