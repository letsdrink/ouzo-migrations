<?php
namespace OuzoMigrations\Util;

use DirectoryIterator;
use Ouzo\Utilities\Arrays;
use Ouzo\Utilities\Strings;

class MigrationFile
{
    /**
     * @var DirectoryIterator
     */
    private $_file;
    private $_filename;
    private $_parts;
    private $_fullPath;

    function __construct(DirectoryIterator $filename)
    {
        $this->_file = $filename;
        $this->_filename = $filename->getFilename();
        $this->_parts = explode('_', $this->_filename);
        $this->_fullPath = $filename->getPathname();
    }

    public function getFilename()
    {
        return $this->_filename;
    }

    public function getVersion()
    {
        return Arrays::getValue($this->_parts, 0);
    }

    public function getClassName()
    {
        return Strings::remove(Arrays::getValue($this->_parts, 1), '.php');
    }

    public function getFullPath()
    {
        return $this->_fullPath;
    }
}