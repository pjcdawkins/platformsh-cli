<?php

namespace Platformsh\Cli\Util;

class Util
{
    /**
     * Get a nested value in an array.
     *
     * @see Copied from \Drupal\Component\Utility\NestedArray::getValue()
     *
     * @param array $array
     * @param array $parents
     * @param bool  $keyExists
     *
     * @return mixed
     */
    public static function &getNestedArrayValue(array &$array, array $parents, &$keyExists = false)
    {
        $ref = &$array;
        foreach ($parents as $parent) {
            if (is_array($ref) && array_key_exists($parent, $ref)) {
                $ref = &$ref[$parent];
            }
            else {
                $keyExists = false;
                $null = null;
                return $null;
            }
        }
        $keyExists = true;

        return $ref;
    }

    /**
     * Set a nested value in an array.
     *
     * @see Copied from \Drupal\Component\Utility\NestedArray::setValue()
     *
     * @param array &$array
     * @param array $parents
     * @param mixed $value
     * @param bool  $force
     */
    public static function setNestedArrayValue(array &$array, array $parents, $value, $force = false)
    {
        $ref = &$array;
        foreach ($parents as $parent) {
            // PHP auto-creates container arrays and NULL entries without error if $ref
            // is NULL, but throws an error if $ref is set, but not an array.
            if ($force && isset($ref) && !is_array($ref)) {
                $ref = [];
            }
            $ref = &$ref[$parent];
        }
        $ref = $value;
    }

    /**
     * Indent lines in a string.
     *
     * @param string $string
     * @param int    $amount
     */
    public static function indent($string, $amount = 2)
    {
        $indent = str_repeat(' ', $amount);

        return preg_replace('/^/m', $indent, $string);
    }
}
