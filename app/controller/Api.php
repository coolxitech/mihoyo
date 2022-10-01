<?php

namespace app\controller;

use app\BaseController;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use think\response\Json;

class Api extends BaseController
{
    protected string $referer = '';//请求来源,极验验证码识别需求

    /**
     * 米哈游登录
     *
     * @param string $username 账号
     * @param string $password 密码
     * @param string $type 服务器(选填)
     * @return Json
     * @throws GuzzleException
     */
    public function login() : Json
    {
        $username = $this->request->param('username');
        $password = $this->request->param('password');
        $type = $this->request->param('type');
        //判断账号密码是否为空
        if($username == '' or $password == '') return $this->error(-1,'账号或密码不能为空');
        //根据不同的服务器区别登录来源
        $this->referer = $type == 'os' ? 'https://genshin.hoyoverse.com/' : 'https://bbs.mihoyo.com/ys';
        //获取极验验证码参数,国际服建议使用Clash进行代理加速访问,为空默认国服
        $mmt_data = $type == 'os' ? $this->hoyoverse_mmt() : $this->mihoyo_mmt();
        //识别验证码
        try{
            $code_data = $this->identification_codes($mmt_data['gt'],$mmt_data['challenge'],$this->referer);
        }catch (Exception $e){
            return $this->error(-2,'验证码识别失败',[$e->getMessage()]);
        }
        //请求登录
        try {
            $login_data = $type == 'os' ? $this->hoyoverse_login($username, $password, $code_data['challenge'], $code_data['validate'], $mmt_data['mmt_key']) : $this->mihoyo_login($username, $password, $code_data['challenge'], $code_data['validate'], $mmt_data['mmt_key']);
        } catch (GuzzleException $e) {
            return $this->error(-4,'请求发送失败:' . $e->getMessage());//国际服不使用海外代理可能会超时
        }
        if(!isset($login_data['account_info'])) return $this->error(-3,$login_data['message']);//没有账号信息即报错
        return $this->success(0,'登录成功',$login_data);
    }

    /**
     * RSA加密
     *
     * 密钥并不是实时更新请到Config目录的key文件修改
     * @param string $data 欲加密数据
     * @return string
     */
    private function password_encrypt(string $data = ''): string
    {
        openssl_public_encrypt($data,$encrypted,Config('key.mihoyo_public_key'));
        return base64_encode($encrypted);
    }

    /**
     * 获取国际服极验验证码参数
     * @return array
     * @throws GuzzleException
     */
    private function hoyoverse_mmt() : array
    {
        $request = $this->client->get("https://webapi-os.account.hoyoverse.com/Api/create_mmt?scene_type=1&region=os&now={$this->getUnixTimestamp()}",[
            'proxy' => Config('proxy.proxy_list.0.0')
        ]);
        $result = $request->getBody()->getContents();
        $mmt = json_decode($result,true);
        if($mmt['code'] != 200) return [];
        return $mmt['data']['mmt_data'];
    }

    /**
     * 获取国服极验验证码参数
     * @return array
     * @throws GuzzleException
     */
    private function mihoyo_mmt() : array
    {
        $request = $this->client->get("https://webapi.account.mihoyo.com/Api/create_mmt?scene_type=1&now={$this->getUnixTimestamp()}");
        $result = $request->getBody()->getContents();
        $mmt = json_decode($result,true);
        if($mmt['code'] != 200) return [];
        return $mmt['data']['mmt_data'];
    }

    /**
     * 验证码识别
     *
     * 暂不支持国际服
     * @param string $gt 极验验证码参数,动态获取不能重复使用
     * @param string $challenge 本次验证码的凭证,动态获取不能重复使用
     * @param string $referer 请求验证码的来源URL
     * @throws GuzzleException
     * @throws Exception
     */
    private function identification_codes(string $gt, string $challenge, string $referer) : array
    {
        //建议自行替换其他方式,当前打码平台不支持国际版极验
        $request = $this->client->post('http://api.rrocr.com/api/recognize.html',[
            'query' => [
                'appkey' => '',
                'gt' => $gt,
                'challenge' => $challenge,
                'referer' => $referer,
                'sharecode' => '585dee4d4ef94e1cb95d5362a158ea54'
            ],
            'timeout' => 60
        ]);
        $result = $request->getBody()->getContents();
        $code_data = json_decode($result,true);
        if($code_data['status'] != 0) throw new Exception('错误代码:' . $code_data['code']);
        return $code_data['data'];
    }

    /**
     * 国服登录
     * @param string $username 账号
     * @param string $password 密码
     * @param string $challenge 极验验证码访问凭证
     * @param string $validate 验证码通过成功凭证
     * @param string $mmt_key 米哈游访问凭证
     * @return array
     * @throws GuzzleException
     */
    private function mihoyo_login(string $username, string $password, string $challenge, string $validate, string $mmt_key) : array
    {
        $request = $this->client->request('POST',"https://api-takumi.mihoyo.com/account/auth/api/webLoginByPassword",[
            'json' => [
                'account' => $username,
                'geetest_challenge' => $challenge,
                'geetest_seccode' => $validate . '|jordan',
                'geetest_validate' => $validate,
                'is_bh2' => false,
                'is_crypto' => true,
                'mmt_key' => $mmt_key,
                'password' => $this->password_encrypt($password),
                'token_type' => 6
            ]
        ]);
        $result = $request->getBody()->getContents();
        $login_data = json_decode($result,true);
        if($login_data['retcode'] != 0) return $login_data;
        $source_cookies = $request->getHeaders()['Set-Cookie'];
        $cookies = [];
        foreach ($source_cookies as $cookie){
            preg_match('/(.*?)=(.*?); Path/',$cookie,$matches);
            if($matches[1] == 'aliyungf_tc') continue;//过滤阿里云的Cookie
            $cookies[$matches[1]] = $matches[2];
        }
        return ['account_info' => $login_data['data']['account_info'],'cookies' => $cookies];
    }

    /**
     * 国际服登录
     * @param string $username 账号
     * @param string $password 密码
     * @param string $challenge 验证码访问凭证
     * @param string $validate 验证码通过成功凭证
     * @param string $mmt_key 米哈游访问凭证
     * @return array
     * @throws GuzzleException
     */
    private function hoyoverse_login(string $username,string $password,string $challenge,string $validate,string $mmt_key): array
    {
        $request = $this->client->request('POST',"https://api-account-os.hoyoverse.com/account/auth/api/webLoginByPassword",[
            'json' => [
                'account' => $username,
                'cb_url' => '',
                'geetest_challenge' => $challenge,
                'geetest_seccode' => $validate . '|jordan',
                'geetest_validate' => $validate,
                'is_crypto' => true,
                'mmt_key' => $mmt_key,
                'password' => $this->password_encrypt($password),
                'token_type' => 4
            ],
            'headers' => [
                'referer' => 'https://genshin.hoyoverse.com/'
            ],
            'proxy' => Config('proxy.proxy_list.0.0')
        ]);

        $result = $request->getBody()->getContents();
        $login_data = json_decode($result,true);
        $source_cookies = $request->getHeaders()['Set-Cookie'];
        $cookies = [];
        foreach ($source_cookies as $cookie){
            preg_match('/(.*?)=(.*?); Path/',$cookie,$matches);
            $cookies[$matches[1]] = $matches[2];
        }
        return ['account_info' => $login_data['data']['account_info'],'cookies' => $cookies];
    }
}