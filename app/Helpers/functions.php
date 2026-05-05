<?php

/**
 * 将字符串转换为大写（示例）
 */
if (!function_exists('toupper')) {
    function toupper($string)
    {
        return mb_strtoupper($string, 'UTF-8');
    }
}

/**
 * date format = dd/mm/yyyy
 */
if (!function_exists('dateFormat')) {
    function dateFormat($date)
    {
        return \Carbon\Carbon::parse($date)->format('d/m/Y');
    }
}