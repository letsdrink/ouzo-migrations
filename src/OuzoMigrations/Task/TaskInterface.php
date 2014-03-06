<?php
namespace OuzoMigrations\Task;

interface TaskInterface
{
    public function execute($args);

    public function help();

    public function setMigrationsDirectory($migrationDir);

    public function setAdapter($adapter);
}