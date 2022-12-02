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
        $data = Response::getParams();
        $username = $data['username'];
        $password = $data['password'];
        //判断账号密码是否为空
        if($username == '' or $password == '') return Response::error(-1,'账号或密码不能为空');
        //根据不同的服务器区别登录来源
        $this->referer = 'https://bbs.mihoyo.com/ys';
        //请求登录
        try {
            $login_data = $this->mihoyo_login($username, $password);
        } catch (GuzzleException $e) {
            return Response::error(-4,'请求发送失败:' . $e->getMessage());
        }
        if(!isset($login_data['account_info'])) return Response::error(-3,'登录失败',$login_data);//没有账号信息即报错
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
        //判断账号密码是否为空
        if($username == '' or $password == '') return Response::error(-1,'账号或密码不能为空');

        //请求登录
        try {
            $data = [
                'password' => Encrypt::RSA($password,Config('key.mihoyo_app_public_key')),
                'account' => Encrypt::RSA($username,Config('key.mihoyo_app_public_key'))
            ];
            $request = $this->client->post('https://passport-api.mihoyo.com/account/ma-cn-passport/app/loginByPassword',[
                'json' => $data,
                'headers' => [
                    'DS' => Encrypt::newDS(Config('key.cn_app_salt'),$data),
                    'x-rpc-app_version' => Config('key.app_version'),
                    'x-rpc-client_type' => 2,
                    'x-rpc-app_id' => Config('key.app_id')
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
                        'x-rpc-app_id' => Config('key.app_id'),
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
        $cookieJAR = CookieJar::fromArray([
            'stoken' => $login_data['data']['token']['token'],
            'mid' => $login_data['data']['user_info']['mid']
        ],'.mihoyo.com');
        $request = $this->client->get('https://passport-api.mihoyo.com/account/auth/api/getLTokenBySToken',[
            'headers' => [
                'DS' => Encrypt::newDS(config('key.cn_app_salt'),[
                    'stoken=' . $login_data['data']['token']['token'].';mid='.$login_data['data']['user_info']['mid']
                ])
            ],
            'cookies' => $cookieJAR
        ]);
        $result = $request->getBody()->getContents();
        $cookies = json_decode($result,true);
        if($cookies['retcode'] != 0) return Response::error(-5,'获取cookie失败');
        return Response::success(0,'登录成功',['account_info' => $login_data['data'],'cookies' => $cookies['data']]);
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
        //建议自行替换其他平台,我用这个打码平台不支持国际版极验,https://rrocr.com/user/register.html
        //公益打码接口,觉得好用请自行采购,价格不是很贵,充个十块钱能用个一两个月.需要对接其他平台请注释公益接口
        //公益接口注册地址:https://ocr.kuxi.tech/user/login

        //公益打码代码开始
        $token = '';
        $request = $this->client->post('https://api.ocr.kuxi.tech/api/recognize',[
            'form_params' => [
                'token' => $token,
                'gt' => $gt,
                'challenge' => $challenge,
                'referer' => $referer
            ],
            'timeout' => 60
        ]);
        $result = $request->getBody()->getContents();
        $code_data = json_decode($result,true);
        if($code_data['code'] != 0) throw new Exception('错误信息:' . $code_data['msg']);
        return $code_data['data'];
        //公益打码代码结束

        //下面是我目前所用平台的调用代码,如果你买了这个平台的服务请注释上面的代码
//        $appkey = '';
//        $request = $this->client->get('http://api.rrocr.com/api/integral.html?appkey=' . $appkey);
//        $result = $request->getBody()->getContents();
//        $integral_data = json_decode($result,true);
//        if($integral_data['status'] == -1) return Response::error(-2,'打码积分查询失败');
//        if($integral_data['integral'] <= 10) Response::error(-2,'积分不足,请联系管理员');
//        $request = $this->client->post('http://api.rrocr.com/api/recognize.html',[
//            'form_params' => [
//                'appkey' => $appkey,
//                'gt' => $gt,
//                'challenge' => $challenge,
//                'referer' => $referer,
//                'sharecode' => '585dee4d4ef94e1cb95d5362a158ea54'//平台的邀请密钥勿删谢谢
//            ],
//            'timeout' => 60
//        ]);
//        $result = $request->getBody()->getContents();
//        $code_data = json_decode($result,true);
//        return $code_data['data'];
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
    public function mihoyo_login(string $username, string $password) : array
    {
        $this->referer = 'https://user.miyoushe.com/';
        //开始第一次登录
        $request = $this->client->request('POST',"https://passport-api.miyoushe.com/account/ma-cn-passport/web/loginByPassword",[
            'json' => [
                'account' => Encrypt::RSA($username,Config('key.mihoyo_web_public_key')),
                'password' => Encrypt::RSA($password,Config('key.mihoyo_web_public_key')),
                'token_type' => 4
            ],
            'headers' => [
                'x-rpc-app_version' => Config('key.app_version'),
                'x-rpc-client_type' => 4,
                'x-rpc-app_id' => Config('key.app_id'),
            ]
        ]);
        $result = $request->getBody()->getContents();
        $login_data = json_decode($result,true);
        if($login_data['retcode'] == -3101){//遇到验证码
            //获取头部参数
            $session_Id = json_decode($request->getHeader('X-Rpc-Aigis')[0],true)['session_id'];
            $geetest_info = json_decode(json_decode($request->getHeader('X-Rpc-Aigis')[0],true)['data'],true);
            unset($geetest_info['success'],$geetest_info['new_captcha']);//过滤无用参数
            try{
                $captcha = $this->identification_codes($geetest_info['gt'],$geetest_info['challenge'],$this->referer);
            }catch (Exception $e){
                throw new Exception('验证码识别失败');
            }
            $request = $this->client->post('https://passport-api.miyoushe.com/account/ma-cn-passport/web/loginByPassword',[
                'json' => [
                    'account' => Encrypt::RSA($username,Config('key.mihoyo_web_public_key')),
                    'password' => Encrypt::RSA($password,Config('key.mihoyo_web_public_key')),
                    'token_type' => 4
                ],
                'headers' => [
                    'x-rpc-app_version' => Config('key.app_version'),
                    'x-rpc-client_type' => 4,
                    'x-rpc-app_id' => Config('key.app_id'),
                    'x-rpc-aigis' => "$session_Id;" . base64_encode(json_encode(['geetest_challenge' => $captcha['challenge'],'geetest_seccode' => $captcha['validate'] . '|jordan','geetest_validate' => $captcha['validate']]))
                ]
            ]);
            $result = $request->getBody()->getContents();
            $login_data = json_decode($result,true);
        }
        $source_cookies = $request->getHeaders()['Set-Cookie'];
        $cookies = [];
        foreach ($source_cookies as $cookie){
            preg_match('/(.*?)=(.*?);/',$cookie,$matches);
            if($matches[1] == 'aliyungf_tc' or $matches[1] == 'acw_tc') continue;//剔除阿里云的Cookie
            $cookies[$matches[1]] = $matches[2];
        }
        //二次验证
        $request = $this->client->request('POST','https://bbs-api.miyoushe.com/user/wapi/login',[
            'json' => [
                'gids' => 2
            ],
            'headers' => [
                'referer' => 'https://www.miyoushe.com/',
                'x-rpc-app_version' => Config('key.app_version'),
                'x-rpc-client_type' => 4,
                'x-rpc-device_id' => '8bb1ee27003fea25f39bc6fab9fb2083',
            ],
            'cookies' => CookieJar::fromArray($cookies,'.miyoushe.com')

        ]);
        $result = $request->getBody()->getContents();
        $miyoushe_valid_data = json_decode($result,true);
        if($miyoushe_valid_data['retcode'] != 0) return [];
        $miyoushe_v2_source_cookies = $request->getHeaders()['Set-Cookie'];
        foreach ($miyoushe_v2_source_cookies as $cookie){
            preg_match('/(.*?)=(.*?);/',$cookie,$matches);
            if($matches[1] == 'aliyungf_tc' or $matches[1] == 'acw_tc') continue;//剔除阿里云的Cookie
            $cookies[$matches[1]] = $matches[2];
        }
        //开始第二次登录
        $mihoyo_user_mmt = $this->mihoyo_mmt();
        try{
            $code_data = $this->identification_codes($mihoyo_user_mmt['gt'],$mihoyo_user_mmt['challenge'],'https://user.mihoyo.com/');
        }catch (\Exception $e){
            return [];
        }
        $request = $this->client->request('POST','https://webapi.account.mihoyo.com/Api/login_by_password',[
            'form_params' => [
                'account' => $username,
                'password' => Encrypt::RSA($password,Config('key.mihoyo_web_public_key')),
                'mmt_key' => $mihoyo_user_mmt['mmt_key'],
                'is_crypto' => true,
                'geetest_challenge' => $code_data['challenge'],
                'geetest_validate' => $code_data['validate'],
                'geetest_seccode' => $code_data['validate'] . '|jordan',
                'source' => 'user.mihoyo.com',
                't' => Time::getUnixTimestamp()
            ]
        ]);
        $result = $request->getBody()->getContents();
        $mohoyo_user_login_data = json_decode($result,true);
        if($mohoyo_user_login_data['code'] != 200) return $mohoyo_user_login_data;
        $mohoyo_user_source_cookies = $request->getHeaders()['Set-Cookie'];
        foreach ($mohoyo_user_source_cookies as $cookie){
            preg_match('/(.*?)=(.*?);/',$cookie,$matches);
            if($matches[1] == 'aliyungf_tc' or $matches[1] == 'acw_tc') continue;//剔除阿里云的Cookie
            $cookies[$matches[1]] = $matches[2];
        }
        return ['account_info' => $login_data['data']['user_info'],'cookies' => $cookies];
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
                'password' => Encrypt::RSA($password,Config('key.mihoyo_web_public_key')),
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
        $cookieJar = CookieJar::fromArray($cookie,'.miyoushe.com');
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
        $cookieJar = CookieJar::fromArray($cookies,'.miyoushe.com');
        $request = $this->client->request('GET','https://api-takumi.mihoyo.com/binding/api/getUserGameRolesByCookie?game_biz=hk4e_cn',[
            'cookies' => $cookieJar
        ]);
        $result = $request->getBody()->getContents();
        $game_info = json_decode($result,true);
        if($game_info['retcode'] != 0) return Response::error(-2,'获取游戏信息失败');
        return Response::success(0,'获取成功',$game_info['data']['list']);
    }
}