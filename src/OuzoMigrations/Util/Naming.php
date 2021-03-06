<?php
namespace OuzoMigrations\Util;

use Ouzo\Utilities\Arrays;
use Ouzo\Utilities\Strings;
use OuzoMigrations\OuzoMigrationsException;

class Naming
{
    const CLASS_NS_PREFIX = 'Task_';

    public static function task_from_class_name($className)
    {
        if (!preg_match('/' . self::CLASS_NS_PREFIX . '/', $className)) {
            throw new OuzoMigrationsException('The class name must start with ' . self::CLASS_NS_PREFIX, OuzoMigrationsException::INVALID_ARGUMENT);
        }
        $className = str_replace(self::CLASS_NS_PREFIX, '', $className);
        $className = strtolower($className);
        $className = str_replace("_", ":", $className);

        return $className;
    }

    public static function task_to_class_name($task)
    {
        if (false === stripos($task, ':')) {
            throw new OuzoMigrationsException('Task name (' . $task . ') must be contains ":"', OuzoMigrationsException::INVALID_ARGUMENT);
        }
        $parts = explode(":", $task);

        return self::CLASS_NS_PREFIX . ucfirst($parts[0]) . '_' . ucfirst($parts[1]);
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

    public static function generateMigrationClassName($fileName)
    {
        return Strings::underscoreToCamelCase($fileName);
    }

    public static function generateMigrationFileName($fileName)
    {
        $timestamp = self::generateTimestamp();
        $name = self::generateMigrationClassName($fileName);
        return $timestamp . '_' . $name . '.php';
    }

    public static function generateTimestamp()
    {
        return gmdate('YmdHis', time());
    }

    public static function classFromFileToName($fileName)
    {
        $parts = explode('_', $fileName);
        return Strings::remove(Arrays::getValue($parts, 1), '.php');
    }
}