<?php
namespace Task\Db;

class Version extends \OuzoMigrations\Task\Base implements \OuzoMigrations\Task\TaskInterface
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
        $output .= "[db:version]: \n";
        if (!$this->_adapter->tableExists(RUCKUSING_TS_SCHEMA_TBL_NAME)) {
            //it doesnt exist, create it
            $output .= "\tSchema version table (" . RUCKUSING_TS_SCHEMA_TBL_NAME . ") does not exist. Do you need to run 'db:setup'?";
        } else {
            //it exists, read the version from it
            // We only want one row but we cannot assume that we are using MySQL and use a LIMIT statement
            // as it is not part of the SQL standard. Thus we have to select all rows and use PHP to return
            // the record we need
            $versions_nested = $this->_adapter->selectAll(sprintf("SELECT version FROM %s", RUCKUSING_TS_SCHEMA_TBL_NAME));
            $versions = array();
            foreach ($versions_nested as $v) {
                $versions[] = $v['version'];
            }
            $num_versions = count($versions);
            if ($num_versions > 0) {
                sort($versions); //sorts lowest-to-highest (ascending)
                $version = (string) $versions[$num_versions-1];
                $output .= sprintf("\tCurrent version: %s", $version);
            } else {
                $output .= sprintf("\tNo migrations have been executed.");
            }
        }
        $output .= "\n\nFinished: " . date('Y-m-d g:ia T') . "\n\n";

        return $output;
    }

    public function help()
    {
        $output =<<<USAGE

\tTask: db:version

\tIt is always possible to ask the framework (really the DB) what version it is
\tcurrently at.

\tThis task does not take arguments.

USAGE;

        return $output;
    }
}