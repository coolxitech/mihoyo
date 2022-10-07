<?php

namespace app\controller;

use app\BaseController;
use app\utils\Encrypt;
use app\utils\Response;
use app\utils\Time;
use Exception;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\GuzzleException;
use think\response\Json;
use Ramsey\Uuid\Uuid;

class Api extends BaseController
{
    protected string $referer = '';//请求来源,极验验证码识别需求

    public function index()
    {
        return Response::success(0,'欢迎来到米哈游API',[
            '网页登录' => [
                'description' => '登录米哈游论坛账号,支持国服和国际服',
                'url' => '/api/web_login',
                'params' => [
                    'username' => '账号',
                    'password' => '密码',
                    'region' => '区服(国服和国际服)'
                ]
            ],
            'APP登录' => [
                'description' => '登录米游社账号',
                'url' => '/api/app_login',
                'params' => [
                    'username' => '账号',
                    'password' => '密码'
                ]
            ],
            '签到' => [
                'description' => '原神签到,仅支持国服',
                'url' => '/api/signin',
                'headers' => [
                    'cookie' => '米哈游Cookie'
                ],
                'params' => [
                    'region' => '服务器(选填,拥有多个角色必填)',
                    'uid' => '游戏ID(选填,拥有多个角色必填)'
                ]
            ],
            '获取游戏信息' => [
                'description' => '获取游戏角色信息列表',
                'url' => '/api/getgameinfo',
                'headers' => [
                    'cookie' => '米哈游Cookie'
                ],
                'params' => []
            ]
        ]);
    }

    /**
     * 米哈游登录
     *
     * @param string $username 账号
     * @param string $password 密码
     * @param string $region 服务器(选填)
     * @return Json
     * @throws GuzzleException
     */
    public function web_login() : Json
    {
        $username = $this->request->param('username');
        $password = $this->request->param('password');
        $region = $this->request->param('region');
        //判断账号密码是否为空
        if($username == '' or $password == '') return Response::error(-1,'账号或密码不能为空');
        //根据不同的服务器区别登录来源
        $this->referer = $region == 'os' ? 'https://genshin.hoyoverse.com/' : 'https://bbs.mihoyo.com/ys';
        //获取极验验证码参数,国际服建议使用Clash进行代理加速访问,为空默认国服
        $mmt_data = $region == 'os' ? $this->hoyoverse_mmt() : $this->mihoyo_mmt();
        //识别验证码
        try{
            $code_data = $this->identification_codes($mmt_data['gt'],$mmt_data['challenge'],$this->referer);
        }catch (Exception $e){
            return Response::error(-2,'验证码识别失败',[$e->getMessage()]);
        }
        //请求登录
        try {
            $login_data = $region == 'os' ? $this->hoyoverse_login($username, $password, $code_data['challenge'], $code_data['validate'], $mmt_data['mmt_key']) : $this->mihoyo_login($username, $password, $code_data['challenge'], $code_data['validate'], $mmt_data['mmt_key']);
        } catch (GuzzleException $e) {
            return Response::error(-4,'请求发送失败:' . $e->getMessage());//国际服不使用海外代理可能会超时
        }
        if(!isset($login_data['account_info'])) return Response::error(-3,$login_data['message']);//没有账号信息即报错
        return Response::success(0,'登录成功',$login_data);
    }

    /**
     * 米游社APP登录
     *
     * @param string $username 账号
     * @param string $password 密码
     * @return Json
     * @throws GuzzleException
     */
    public function app_login() : Json
    {
        $username = $this->request->param('username');
        $password = $this->request->param('password');
        $region = $this->request->param('region');
        //判断账号密码是否为空
        if($username == '' or $password == '') return Response::error(-1,'账号或密码不能为空');

        //请求登录
        try {
            $data = [
                'password' => Encrypt::RSA($password,Config('key.mihoyo_app_public_key')),
                'account' => Encrypt::RSA($username,Config('key.mihoyo_app_public_key'))
            ];
//            halt(Encrypt::newDS(Config('key.cn_app_salt'),$data));
            $request = $this->client->post('https://passport-api.mihoyo.com/account/ma-cn-passport/app/loginByPassword',[
                'json' => $data,
                'headers' => [
                    'DS' => Encrypt::newDS(Config('key.cn_app_salt'),$data),
                    'x-rpc-app_version' => Config('key.app_version'),
                    'x-rpc-client_type' => 2,
                    'x-rpc-app_id' => 'bll8iq97cem8'
                ]
            ]);
            $result = $request->getBody()->getContents();
            $login_data = json_decode($result,true);
            if($login_data['retcode'] == -3101){
                $session_Id = json_decode($request->getHeader('X-Rpc-Aigis')[0],true)['session_id'];
                $geetest_info = json_decode(json_decode($request->getHeader('X-Rpc-Aigis')[0],true)['data'],true);
                unset($geetest_info['success'],$geetest_info['new_captcha']);
                $captcha = $this->identification_codes($geetest_info['gt'],$geetest_info['challenge'],'https://passport-api.mihoyo.com/account/ma-cn-passport/app/loginByPassword');
                $request = $this->client->post('https://passport-api.mihoyo.com/account/ma-cn-passport/app/loginByPassword',[
                    'json' => $data,
                    'headers' => [
                        'DS' => Encrypt::newDS(Config('key.cn_app_salt'),$data),
                        'x-rpc-app_version' => Config('key.app_version'),
                        'x-rpc-client_type' => 2,
                        'x-rpc-app_id' => 'bll8iq97cem8',
                        'x-rpc-aigis' => "$session_Id;" . base64_encode(json_encode(['geetest_challenge' => $captcha['challenge'],'geetest_seccode' => $captcha['validate'] . '|jordan','geetest_validate' => $captcha['validate']]))
                    ]
                ]);
                $result = $request->getBody()->getContents();
                $login_data = json_decode($result,true);
            }
        } catch (GuzzleException $e) {
            return Response::error(-4,'请求发送失败:' . $e->getMessage());//国际服不使用海外代理可能会超时
        }
        if($login_data['retcode'] != 0) return Response::error(-3,'登录错误',[$login_data['message']]);
        return Response::success(0,'登录成功',$login_data['data']);
    }

    /**
     * 获取国际服极验验证码参数
     * @return array
     * @throws GuzzleException
     */
    private function hoyoverse_mmt() : array
    {

        $request = $this->client->get("https://webapi-os.account.hoyoverse.com/Api/create_mmt?scene_type=1&region=os&now=" . Time::getUnixTimestamp(),[
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
        $request = $this->client->get("https://webapi.account.mihoyo.com/Api/create_mmt?scene_type=1&now=" . Time::getUnixTimestamp());
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
        //建议自行替换其他平台,当前打码平台不支持国际版极验,https://rrocr.com/user/register.html
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
                'password' => Encrypt::RSA($password,Config('key.mihoyo_web_public_key')),
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
            if($matches[1] == 'aliyungf_tc') continue;//剔除阿里云的Cookie
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
                'password' => Encrypt::password($password),
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

    /**
     * 原神签到
     * @access public
     * @return Json
     * @throws GuzzleException
     */
    public function genshin_signIn(): Json
    {
        $region = $this->request->param('region');
        $uid = $this->request->param('uid');
        $cookie = $this->request->cookie();
        if(empty($cookie)) return Response::error(-1,'cookie不能为空');
        $cookieJar = CookieJar::fromArray($cookie,'.mihoyo.com');
        //获取游戏信息
        $request = $this->client->request('GET','https://api-takumi.mihoyo.com/binding/api/getUserGameRolesByCookie?game_biz=hk4e_cn',[
            'cookies' => $cookieJar
        ]);
        $result = $request->getBody()->getContents();
        $game_info = json_decode($result,true);
        if($game_info['retcode'] != 0) return Response::error(-2,'获取游戏信息失败');
        //防止账号下多个游戏角色
        if(count($game_info['data']['list']) == 1){
            $region = $game_info['data']['list'][0]['region'];
            $uid = $game_info['data']['list'][0]['game_uid'];

        }else{
            if($region == '' or $uid == '') return Response::error(-3,'该账号下拥有多个游戏角色，请输入指定的区域和游戏ID进行匹配.',$game_info['data']['list'],401);
            foreach ($game_info['data']['list'] as $info) {
                if($region == $info['region'] and $uid == $info['game_uid']){
                    break;
                }
            }
        }
        if($region == '' or $uid == '') return Response::error(-4,'未能匹配到指定游戏账号');
        //签到请求提交
        $request = $this->client->request('POST','https://api-takumi.mihoyo.com/event/bbs_sign_reward/sign',[
            'json' => [
                'act_id' => 'e202009291139501',
                'region' => $region,
                'uid' => (int)$uid
            ],
            'headers' => [
                'DS' => Encrypt::oldDS(Config('key.cn_web_salt')),
                'x-rpc-app_version' => Config('key.app_version'),
                'x-rpc-client_type' => 5,
                'x-rpc-device_id' => (string)Uuid::uuid3(Uuid::NAMESPACE_URL,$this->request->header('cookie')),
                'user-agent' => 'Mozilla/5.0 (Linux; Android 7.1.2; M2011K2C Build/N2G47H; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/81.0.4044.117 Mobile Safari/537.36 miHoYoBBS/2.38.1'
            ],
            'cookies' => $cookieJar
        ]);

        $result = $request->getBody()->getContents();
        $data = json_decode($result,true);
        if($data['retcode'] == 0 and $data['data']['risk_code'] != 0){//遇到验证码,手动签到不会触发验证码
            //调用打码平台进行验证
            $validate = $this->identification_codes($data['data']['gt'],$data['data']['challenge'],'https://webstatic.mihoyo.com/bbs/event/signin-ys/index.html?bbs_auth_required=true&act_id=e202009291139501&utm_source=bbs&utm_medium=mys&utm_campaign=icon');
            //再次提交
            $request = $this->client->request('POST','https://api-takumi.mihoyo.com/event/bbs_sign_reward/sign',[
                'json' => [
                    'act_id' => 'e202009291139501',
                    'region' => $region,
                    'uid' => (int)$uid,
                ],
                'headers' => [
                    'DS' => Encrypt::oldDS(Config('key.cn_web_salt')),
                    'x-rpc-app_version' => '2.38.1',
                    'x-rpc-client_type' => 5,
                    'x-rpc-challenge' => $validate['challenge'],
                    'x-rpc-validate' => $validate['validate'],
                    'x-rpc-seccode' => $validate['validate'].'|jordan',
                    'x-rpc-device_id' => (string)Uuid::uuid3(Uuid::NAMESPACE_URL,$this->request->header('cookie'))
                ],
                'cookies' => $cookieJar
            ]);
            $result = $request->getBody()->getContents();
            $data = json_decode($result,true);
        }

        if($data['retcode'] == -5003){
            return Response::success(1,'已经签到过了');
        }elseif ($data['retcode'] == 0 and $data['data']['risk_code'] == 0){
            return Response::success(0,'签到成功');
        }else{
            return Response::error(-6,'未知错误',$data);
        }

    }

    public function getGameInfo() : Json
    {
        $cookies = $this->request->cookie();
        if(empty($cookies)) return Response::error(-1,'Cookie不能为空');
        $cookieJar = CookieJar::fromArray($cookies,'.mihoyo.com');
        $request = $this->client->request('GET','https://api-takumi.mihoyo.com/binding/api/getUserGameRolesByCookie?game_biz=hk4e_cn',[
            'cookies' => $cookieJar
        ]);
        $result = $request->getBody()->getContents();
        $game_info = json_decode($result,true);
        if($game_info['retcode'] != 0) return Response::error(-2,'获取游戏信息失败');
        return Response::success(0,'获取成功',$game_info['data']['list']);
    }
}