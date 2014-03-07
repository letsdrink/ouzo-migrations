<?php
namespace Task\Db;

class Setup extends \OuzoMigrations\Task\Base implements \OuzoMigrations\Task\TaskInterface
{
    /**
     * @var \OuzoMigrations\Adapter\AdapterBase
     */
    private $_adapter = null;

    public function __construct($adapter)
    {
        parent::__construct($adapter);
        $this->_adapter = $adapter;
    }

    public function execute($args)
    {
        $output = "Started: " . date('Y-m-d g:ia T') . "\n\n";
        $output .= "[db:setup]: \n";
        //it doesnt exist, create it
        if (!$this->_adapter->table_exists(RUCKUSING_TS_SCHEMA_TBL_NAME)) {
            $output .= sprintf("\tCreating table: %s", RUCKUSING_TS_SCHEMA_TBL_NAME);
            $this->_adapter->createSchemaVersionTable();
            $output .= "\n\tDone.\n";
        } else {
            $output .= sprintf("\tNOTICE: table '%s' already exists. Nothing to do.", RUCKUSING_TS_SCHEMA_TBL_NAME);
        }
        $output .= "\n\nFinished: " . date('Y-m-d g:ia T') . "\n\n";

        return $output;
    }

    public function help()
    {
        $output = <<<USAGE

\tTask: db:setup

\tA basic task to initialize your DB for migrations is available. One should
\talways run this task when first starting out.

\tThis task does not take arguments.

USAGE;

        return $output;
    }
}