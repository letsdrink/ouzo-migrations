<?php
namespace OuzoMigrations\Task;

interface TaskInterface
{
    public function execute(array $args);
}