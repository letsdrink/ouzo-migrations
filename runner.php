#!/usr/bin/env php
<?php
use Ouzo\Config;
use Ouzo\Utilities\Path;
use Symfony\Component\Console\Application;
use Task\Command\GenerateCommand;
use Task\Command\StatusCommand;

define("OUZO_BASE", __DIR__);
define("ROOT_PATH", '');

require_once 'vendor/autoload.php';
/** @noinspection PhpIncludeInspection */
require_once Path::join(OUZO_BASE, 'config', 'database.inc.php');

Config::registerConfig(new DatabaseConfig());

$application = new Application('Ouzo Migrations', '1.0');
$application->add(new GenerateCommand());
$application->add(new StatusCommand());
$application->run();