<?php

namespace app\utils;

use think\facade\Request;
use think\response\Json;

class Response
{
    /**
     * 返回成功的Json请求
     * @access public
     * @param int $code 返回代码
     * @param string $msg 返回消息
     * @param array $data 返回数据
     * @param int $response_code 返回状态码
     * @return Json
     */
    public static function success(int $code,string $msg, array $data = [], int $response_code = 200):Json
    {
        return json(['code' => $code,'msg' => $msg,'data' => $data],$response_code);
    }

    /**
     * 返回失败的Json请求
     * @access public
     * @param int $code 返回代码
     * @param string $msg 返回消息
     * @param array $data 返回数据
     * @param int $response_code 返回状态码
     * @return Json
     */
    public static function error(int $code, string $msg,array $data = [],int $response_code = 200):Json
    {
        return json(['code' => $code,'msg' => $msg,'data' => $data],$response_code);
    }

    public static function getParams()
    {
        $params = Request::param('data');
        if($params == null or $params == '') return Response::error(-1,'参数不能为空');
        $json = Encrypt::RSA_Private_Decrypt($params);
        $data = json_decode($json,true);
        if($data === false) return Response::error(-2,'无法解析数据');
        if(time() - $data['timestamp'] >= 3600) return Response::error(-3,'无效参数');
        return $data;
    }
}