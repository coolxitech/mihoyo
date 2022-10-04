<?php

namespace app\utils;

class Time
{
    /**
     * 获取当前13位时间戳
     * @access public
     * @return int
     */
    public static function getUnixTimestamp(): int
    {
        list($s1, $s2) = explode(' ', microtime());
        return (int)sprintf('%.0f',(floatval($s1) + floatval($s2)) * 1000);
    }
}