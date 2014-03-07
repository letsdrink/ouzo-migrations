<?php
namespace OuzoMigrations\Adapter;

use Ouzo\Utilities\Arrays;
use OuzoMigrations\Adapter\PgSQL\AdapterPgSQL;

class AdapterFactory
{
    public static function create($options)
    {
        $type = Arrays::getValue($options, 'type');
        if ($type == 'pgsql') {
            return new AdapterPgSQL($options);
        }
        return null;
    }
}