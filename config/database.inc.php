<?php
use Ouzo\Utilities\Path;

class DatabaseConfig
{
    public function getConfig()
    {
        return array(
            'db' => array(
                'development' => array(
                    'type' => 'pgsql',
                    'host' => 'localhost',
                    'port' => 5432,
                    'database' => 'ouzo_migrations',
                    'user' => 'postgres',
                    'password' => '',
                ),
                'pg_test' => array(
                    'type' => 'pgsql',
                    'host' => 'localhost',
                    'port' => 5432,
                    'database' => 'ouzo_migrations_test',
                    'user' => 'postgres',
                    'password' => ''

                )
            ),
            'migrations_dir' => array('default' => Path::join(OUZO_BASE, 'migrations'))
        );
    }
}

return array(
    'db' => array(
        'development' => array(
            'type' => 'mysql',
            'host' => 'localhost',
            'port' => 3306,
            'database' => 'ruckusing_migrations',
            'user' => 'root',
            'password' => '',
            //'charset' => 'utf8',
            //'directory' => 'custom_name',
            //'socket' => '/var/run/mysqld/mysqld.sock'
        ),
        'pg_test' => array(
            'type' => 'pgsql',
            'host' => 'localhost',
            'port' => 5432,
            'database' => 'ruckusing_migrations_test',
            'user' => 'postgres',
            'password' => '',
            //'directory' => 'custom_name',

        ),
        'mysql_test' => array(
            'type' => 'mysql',
            'host' => 'localhost',
            'port' => 3306,
            'database' => 'ruckusing_migrations_test',
            'user' => 'root',
            'password' => '',
            //'directory' => 'custom_name',
            //'socket' => '/var/run/mysqld/mysqld.sock'
        ),
        'sqlite_test' => array(
            'type' => 'sqlite',
            'database' => sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ruckusing_migrations_test.sqlite3',
            'host' => 'localhost',
            'port' => '',
            'user' => '',
            'password' => ''
        )

    ),
    'migrations_dir' => array('default' => Path::join(OUZO_BASE, 'migrations')),
//    'db_dir' => RUCKUSING_WORKING_BASE . DIRECTORY_SEPARATOR . 'db',
//    'log_dir' => RUCKUSING_WORKING_BASE . DIRECTORY_SEPARATOR . 'logs',
//    'ruckusing_base' => dirname(__FILE__) . DIRECTORY_SEPARATOR . '..'
);