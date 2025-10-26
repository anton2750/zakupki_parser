<?php


namespace app\helpers;


class HDates
{

    /**
     * Проверяет формат UNIX Timestamp
     *
     * @param $timestamp
     *
     * @return bool
     */
    public static function isTimestamp($timestamp)
    {
        return @ctype_digit($timestamp);
    }


    /**
     * Возвращает дату в формате UNIX Timestamp
     *
     * @param mixed $timestamp - null, timestamp или string
     * @param null $format
     *
     * @return int
     */
    public static function prepareTimestamp($timestamp = null, $format = null)
    {
        if (!$timestamp) {
            return time();
        }

        if ($format) {
            $date = \DateTime::createFromFormat($format, $timestamp);

            return $date->getTimestamp();
        }

        if (!HDates::isTimestamp($timestamp)) {
            return strtotime($timestamp);
        }

        return substr($timestamp, 0, 10);
    }


    /**
     * Возвращает дату в формате Y-m-d H:i:s
     *
     * @param mixed $timestamp - null, timestamp или string
     *
     * @return string
     */
    public static function long($timestamp = null)
    {
        return date('Y-m-d H:i:s', HDates::prepareTimestamp($timestamp));
    }

    /**
     * Возвращает дату в формате Y-m-d
     *
     * @param mixed $timestamp - null, timestamp или string
     *
     * @return string
     */
    public static function short($timestamp = null)
    {
        return date('Y-m-d', HDates::prepareTimestamp($timestamp));
    }

}