<?php
namespace OuzoMigrations\Adapter;

interface AdapterInterface
{
    public function get_database_name();

    public function quote($value);

    public function supports_migrations();

    public function native_database_types();

    public function schema($output_file);

    public function execute($query);

    public function quote_string($str);

    public function database_exists($db);

    public function create_table($table_name, $options = array());

    public function drop_database($db);

    public function table_exists($tbl);

    public function drop_table($tbl);

    public function rename_table($name, $new_name);

    public function rename_column($table_name, $column_name, $new_column_name);

    public function add_column($table_name, $column_name, $type, $options = array());

    public function remove_column($table_name, $column_name);

    public function change_column($table_name, $column_name, $type, $options = array());

    public function remove_index($table_name, $column_name);

    public function add_index($table_name, $column_name, $options = array());

    public function query($query);
}