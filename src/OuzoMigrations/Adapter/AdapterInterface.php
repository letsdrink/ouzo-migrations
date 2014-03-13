<?php
namespace OuzoMigrations\Adapter;

interface AdapterInterface
{
    public function createTable($tableName, array $options);

    public function tableExists($tableName);

    public function supportsMigrations();

    public function nativeDatabaseTypes();

    public function quoteTable($table);

    public function quoteString($string);

    public function quote($value);

    public function databaseExists($db);

    public function createDatabase($db);

    public function dropDatabase($db);

    public function identifier($string);

    public function typeToSql($type, $options = array());

    public function addColumnOptions($type, $options);

    public function query($query);

    public function selectOne($query);

    public function schema($output_file);

    public function drop_table($tbl);

    public function rename_table($name, $new_name);

    public function add_column($table_name, $column_name, $type, $options = array());

    public function remove_column($table_name, $column_name);

    public function rename_column($table_name, $column_name, $new_column_name);

    public function change_column($table_name, $column_name, $type, $options = array());

    public function column_info($table, $column);

    public function add_index($table_name, $column_name, $options = array());

    public function remove_index($table_name, $column_name);

    public function has_index($table_name, $column_name, $options = array());

    public function indexes($table_name);

    public function primary_keys($table_name);

    public function set_current_version($version);

    public function remove_version($version);
}