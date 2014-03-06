#!/usr/bin/env php
<?php
use Symfony\Component\Console\Application;
use Task\Command\Generate;

require_once 'vendor/autoload.php';

$application = new Application('Ouzo Migrations', '1.0');
$application->add(new Generate());
$application->run();