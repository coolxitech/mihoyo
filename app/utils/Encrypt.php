<?php

namespace app\utils;

class Encrypt
{
    /**
     * RSA加密
     *
     * 密钥并不是实时更新请到Config目录的key.php文件修改
     * @param string $data 欲加密数据
     * @param string $key 密钥
     * @return string
     */
    public static function RSA(string $data,string $key): string
    {
        openssl_public_encrypt($data,$encrypted,$key);
        return base64_encode($encrypted);
    }


    /**
     * Web版DS算法
     * @param string $salt 加密盐
     * @return string
     */
    public static function oldDS(string $salt) : string
    {
        $timestamp = (string)time();
        $random_string = Str::random_string(6);
        $ds_string = "salt=$salt&t=$timestamp&r=$random_string";
        $ds_md5 = md5($ds_string);
        return "$timestamp,$random_string,$ds_md5";
    }

    /**
     * APP版DS算法
     * @param string $salt 加密盐
     * @param array $params 请求参数
     * @return string
     */
    public static function newDS(string $salt,array $params) : string
    {
        $timestamp = (string)time();
        $random_string = Str::random_string(6);
        $params = json_encode($params);
        $ds_string = "salt=$salt&t=$timestamp&r=$random_string&b=$params&q=";
        $ds_md5 = md5($ds_string);
        return "$timestamp,$random_string,$ds_md5";
    }


}