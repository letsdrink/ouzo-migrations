<?php
namespace OuzoMigrations\Util;

use OuzoMigrations\RuckusingException;

class Naming
{
    const CLASS_NS_PREFIX = 'Task_';

    public static function task_from_class_name($className)
    {
        if (!preg_match('/' . self::CLASS_NS_PREFIX . '/', $className)) {
            throw new RuckusingException('The class name must start with ' . self::CLASS_NS_PREFIX, RuckusingException::INVALID_ARGUMENT);
        }
        $className = str_replace(self::CLASS_NS_PREFIX, '', $className);
        $className = strtolower($className);
        $className = str_replace("_", ":", $className);

        return $className;
    }

    public static function task_to_class_name($task)
    {
        if (false === stripos($task, ':')) {
            throw new RuckusingException('Task name (' . $task . ') must be contains ":"', RuckusingException::INVALID_ARGUMENT);
        }
        $parts = explode(":", $task);

        return self::CLASS_NS_PREFIX . ucfirst($parts[0]) . '_' . ucfirst($parts[1]);
    }

    public static function class_from_file_name($file_name)
    {
        $file_name = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $file_name);

        $parts = explode(DIRECTORY_SEPARATOR, $file_name);
        $namespace = $parts[count($parts) - 2];
        $file_name = substr($parts[count($parts) - 1], 0, -4);

        return self::CLASS_NS_PREFIX . ucfirst($namespace) . '_' . ucfirst($file_name);
    }

    public static function class_from_migration_file($file_name)
    {
        $className = false;
        if (preg_match('/^(\d+)_(.*)\.php$/', $file_name, $matches)) {
            if (count($matches) == 3) {
                $className = $matches[2];
            }
        }

        return $className;
    }

    public static function camelcase($str)
    {
        $str = preg_replace('/\s+/', '_', $str);
        $parts = explode("_", $str);
        if (count($parts) == 0) {
            return $str;
        }
        $cleaned = "";
        foreach ($parts as $word) {
            $cleaned .= ucfirst($word);
        }

        return $cleaned;
    }

    public static function index_name($table_name, $column_name)
    {
        $name = sprintf("idx_%s", self::underscore($table_name));
        if (is_array($column_name)) {
            $column_str = join("_and_", $column_name);
        } else {
            $column_str = $column_name;
        }
        $name .= sprintf("_%s", $column_str);

        return $name;
    }

    public static function underscore($str)
    {
        $underscored = preg_replace('/\W/', '_', $str);
        return preg_replace('/\_{2,}/', '_', $underscored);
    }
}