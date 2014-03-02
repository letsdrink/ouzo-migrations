<?php
namespace OuzoMigrations\Adapter;

interface AdapterInterface
{
    public function get_database_name();

    public function supports_migrations();

    public function native_database_types();

    public function quote_table($table);

    public function database_exists($db);

    public function create_database($db);

    public function drop_database($db);

    public function schema($output_file);

    public function table_exists($tbl);

    public function select_one($query);

    public function create_table($table_name, $options = array());

    public function drop_table($tbl);

    public function quote_string($str);

    public function identifier($str);

    public function quote($value);

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

    public function type_to_sql($type, $options = array());

    public function primary_keys($table_name);

    public function add_column_options($type, $options, $performing_change = false);

    public function set_current_version($version);

    public function remove_version($version);

    public function query($query);
}