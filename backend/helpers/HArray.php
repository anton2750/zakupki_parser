<?php

namespace app\helpers;

use ArrayAccess;
use Yii;
use yii\base\Arrayable;
use yii\helpers\ArrayHelper;
use yii\helpers\Json;
use yii\helpers\StringHelper;

class HArray extends ArrayHelper
{
    public static function multiget($array, $attrs, $key = null)
    {
        $row = [];
        foreach ($attrs as $oldAttr => $attr) {
            if (is_integer($oldAttr)) {
                $oldAttr = $attr;
            }
            if ($key && $attr == ':key') {
                $row[$oldAttr] = $key;
            } else {
                $row[$oldAttr] = ArrayHelper::getValue($array, $attr) ?? null;
            }
        }
        return $row;
    }

    public static function passCondition($row, array $filters, $strict = false)
    {
        return self::checkFilters($row, $filters, $strict);
    }

    public static function compareValues($value, $filter, $operator, $strict = false)
    {
        if (in_array($operator, ['=', '=='])) {
            $result = $strict ? $value === $filter : $value == $filter;
        } elseif ($operator == 'in') {
            $result = in_array($value, $filter, $strict);
        } elseif ($operator == 'not in') {
            $result = !in_array($value, $filter, $strict);
        } elseif (in_array($operator, ['!=', '<>'])) {
            $result = $strict ? $value !== $filter : $value != $filter;
        } elseif (in_array($operator, ['>'])) {
            $result = $value > $filter;
        } elseif (in_array($operator, ['>='])) {
            $result = $value >= $filter;
        } elseif (in_array($operator, ['<'])) {
            $result = $value < $filter;
        } elseif (in_array($operator, ['<='])) {
            $result = $value < $filter;
        } elseif (in_array($operator, ['LIKE'])) {
            $result = mb_stripos($value, $filter) !== false;
        } else {
            $result = false;
        }
        return $result;
    }

    public static function checkFilters($row, array $filters, $strict = false)
    {
        foreach ($filters as $column => $filter) {
            if (is_numeric($column) && is_array($filter) && count($filter) == 3) {
                list($operator, $column, $filter) = $filter;
            } elseif (is_array($filter)) {
                $operator = 'in';
            } else {
                $operator = '=';
            }
            $val = HArray::getValue($row, $column);
            $result = self::compareValues($val, $filter, $operator, $strict);

            if (!$result) {
                return false;
            }
        }
        return true;
    }

    public static function multiset(array $rows, $attributes, $filters = [])
    {
        foreach ($rows as &$row) {
            if ($filters && !self::passCondition($row, $filters)) {
                continue;
            }
            foreach ($attributes as $attr => $value) {
                if (is_callable($value)) {
                    $value = call_user_func($value, $row);
                }
                if (is_array($row)) {
                    $row[$attr] = $value;
                } else {
                    $row->$attr = $value;
                }
            }
        }
        return $rows;
    }

    public static function indexWithMerge($array, $key, $mergeCol)
    {
        $result = [];
        foreach ($array as $element) {
            $lastArray = &$result;
            $value = static::getValue($element, $key);
            if ($value !== null) {
                if (is_float($value)) {
                    $value = StringHelper::floatToString($value);
                }

                if (!empty($result[$value])) {
                    $lastArray[$value][$mergeCol] = array_merge($element[$mergeCol], $lastArray[$value][$mergeCol]);
                } else {
                    $lastArray[$value] = $element;
                }
            }
            unset($lastArray);
        }

        return $result;
    }

    public static function getCols($rows, $cols, $keepKeys = true)
    {
        $result = [];

        foreach ($rows as $i => $row) {
            if ($keepKeys) {
                $result[$i] = HArray::multiget($row, $cols, $i);
            } else {
                $result[] = HArray::multiget($row, $cols, $i);
            }
        }

        return $result;
    }

    public static function eqAttrs($newRow, $oldRow, $cmpCols, $debug = false)
    {
        foreach ($cmpCols as $col) {
            if (is_scalar($newRow[$col]) && is_scalar($oldRow[$col])) {
                $neq = "{$newRow[$col]}" != "{$oldRow[$col]}";
            } else {
                $neq = $newRow[$col] != $oldRow[$col];
            }
            if ($neq) {
                if ($debug) {
                    $newVal = HArray::render($newRow[$col]);
                    $oldVal = HArray::render($oldRow[$col]);
                }
                return false;
            }
        }
        return true;
    }

    public static function val($array, $key, $default = null)
    {
        return $array[$key] ?? $default;
    }

    public static function modGet(array $array, $number)
    {
        $number = intval($number);
        $array = array_values($array);
        return $array[$number % count($array)];
    }

    /**
     * @param array $items
     * @param int|null $seed - при одинаковом будет одинаковый результат
     * @param int|null $max
     *
     * @return array
     */
    public static function sameShuffle(array $items, $seed = null, $max = null)
    {
        $max = $max ? min($max, count($items)) : count($items);
        mt_srand($seed);
        $items = array_values($items);
        $result = [];
        for ($i = $max - 1; $i >= 0; $i--) {
            $j = mt_rand(0, $i);
            $result[] = $items[$j];
            unset($items[$j]);
            $items = array_values($items);
        }
        return $result;
    }

    public static function inc(&$array, string $key, $value = 1)
    {
        if (isset($array[$key])) {
            $array[$key] += $value;
        } else {
            $array[$key] = $value;
        }
        return $array[$key];
    }

    public static function render($keyArr, $glue = ', ', $template = '%s: %s')
    {
        if (is_scalar($keyArr)) {
            return $keyArr;
        }
        $rows = [];
        foreach ($keyArr ?: [] as $key => $value) {
            if (is_null($value)) {
                $strVal = 'null';
            } elseif (is_bool($value)) {
                $strVal = $value ? 'true' : 'false';
            } elseif (is_array($value) || is_object($value)) {
                $strVal = Json::encode($value);
            } else {
                $strVal = $value;
            }
            $rows[] = sprintf($template, $key, $strVal);
        }
        return implode($glue, $rows);
    }


    /**
     * @param $array
     * @param null $default
     *
     * @return mixed
     */
    public static function first($array, $default = null)
    {
        if ($array && is_array($array)) {
            return array_shift($array);
        }
        return $default;
    }

    public static function last($array, $default = null)
    {
        if ($array && is_array($array)) {
            return array_pop($array);
        }
        return $default;
    }

    public static function equal($var1, $var2)
    {
        if (is_array($var1) && is_array($var2)) {
            return count($var2) == count($var1) && !array_diff($var1, $var2) && !array_diff($var2, $var1);
        } elseif (!is_scalar($var1) || !is_scalar($var2)) {
            return $var1 == $var2;
        } else {
            return (string)$var1 == (string)$var2;
        }
    }


    public static function compare($array1, $array2, $excludedFields = [])
    {
        $diffAttrs = [];
        foreach ($array1 as $attr => $value1) {
            if (in_array($attr, $excludedFields)) {
                continue;
            }

            $value2 = HArray::val($array2, $attr);
            $equal = false;
            if (is_numeric($value1) && is_numeric($value2) && abs($value1 - $value2) < 0.01) {
                $equal = true;
            }
            if (is_string($value1) && is_string($value2) && trim($value1) == trim($value2)) {
                $equal = true;
            }
            if (!$value1 && !$value2) {
                $equal = true;
            }
            if (is_array($value1) and is_array($value2)) {
                $diffSubAttrs = self::compare($value1, $value2, $excludedFields);
                foreach ($diffSubAttrs as $subAttr => $diff) {
                    $diffAttrs["{$attr}.{$subAttr}"] = $diff;
                }
            } elseif (!$equal) {
                $diffAttrs[$attr][] = is_array($value1) ? Json::encode($value1) : $value1;
                $diffAttrs[$attr][] = is_array($value2) ? Json::encode($value2) : $value2;
            }
        }
        return $diffAttrs;
    }

    public static function diff(array $oldArr, $newArr, $skipNull = true)
    {
        if ($skipNull && is_null($newArr)) {
            return false;
        }
        return array_diff($oldArr, $newArr ?: []) || array_diff($newArr ?: [], $oldArr);
    }

    public static function filterRows($rows, array $filters, $strict = false)
    {
        foreach ($rows ?: [] as $i => $row) {
            if (!self::passCondition($row, $filters, $strict)) {
                unset($rows[$i]);
            }
        }
        return $rows;
    }

    public static function filterKeys(array $cols, array $keys)
    {
        return array_intersect_key($cols, array_flip($keys));
    }

    /**
     * {@inheritDoc}
     */
    public static function getValue($array, $key, $default = null)
    {
        if ($key instanceof \Closure) {
            return $key($array, $default);
        }

        if (is_array($key)) {
            $lastKey = array_pop($key);
            foreach ($key as $keyPart) {
                $array = static::getValue($array, $keyPart);
            }
            $key = $lastKey;
        }

        if (is_array($array) && (isset($array[$key]) || array_key_exists($key, $array))) {
            return $array[$key];
        }

        if (($pos = strrpos($key, '.')) !== false) {
            $array = static::getValue($array, substr($key, 0, $pos), $default);
            $key = substr($key, $pos + 1);
        }

        if (is_array($array) && (isset($array[$key]) || array_key_exists($key, $array))) {
            return $array[$key];
        }

        if (is_object($array)) {
            // this is expected to fail if the property does not exist, or __get() is not implemented
            // it is not reliably possible to check whether a property is accessible beforehand
            try {
                return $array->$key;
            } catch (\Exception $e) {
                if ($array instanceof ArrayAccess) {
                    return $default;
                }
                throw $e;
            }
        }

        return $default;
    }
}