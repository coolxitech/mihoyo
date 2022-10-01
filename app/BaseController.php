<?php
declare (strict_types = 1);

namespace app;

use GuzzleHttp\Client;
use think\App;
use think\exception\ValidateException;
use think\response\Json;
use think\Validate;

/**
 * 控制器基础类
 */
abstract class BaseController
{
    /**
     * Request实例
     * @var \think\Request
     */
    protected $request;

    /**
     * 应用实例
     * @var App
     */
    protected $app;

    /**
     * HttpClient实例
     * @var Client
     */
    protected $client;

    /**
     * 是否批量验证
     * @var bool
     */
    protected $batchValidate = false;

    /**
     * 控制器中间件
     * @var array
     */
    protected $middleware = [];

    /**
     * 构造方法
     * @access public
     * @param  App  $app  应用对象
     */
    public function __construct(App $app)
    {
        $this->app     = $app;
        $this->request = $this->app->request;
        $this->client = new Client();
        // 控制器初始化
        $this->initialize();
    }

    // 初始化
    protected function initialize()
    {}

    /**
     * 验证数据
     * @access protected
     * @param  array        $data     数据
     * @param  string|array $validate 验证器名或者验证规则数组
     * @param  array        $message  提示信息
     * @param  bool         $batch    是否批量验证
     * @return array|string|true
     * @throws ValidateException
     */
    protected function validate(array $data, $validate, array $message = [], bool $batch = false)
    {
        if (is_array($validate)) {
            $v = new Validate();
            $v->rule($validate);
        } else {
            if (strpos($validate, '.')) {
                // 支持场景
                [$validate, $scene] = explode('.', $validate);
            }
            $class = false !== strpos($validate, '\\') ? $validate : $this->app->parseClass('validate', $validate);
            $v     = new $class();
            if (!empty($scene)) {
                $v->scene($scene);
            }
        }

        $v->message($message);

        // 是否批量验证
        if ($batch || $this->batchValidate) {
            $v->batch(true);
        }

        return $v->failException(true)->check($data);
    }

    /**
     * 获取当前13位时间戳
     * @access protected
     * @return int
     */
    protected function getUnixTimestamp (): int
    {
        list($s1, $s2) = explode(' ', microtime());
        return (int)sprintf('%.0f',(floatval($s1) + floatval($s2)) * 1000);
    }

    /**
     * 返回成功的Json请求
     * @access protected
     * @param int $code 返回代码
     * @param string $msg 返回消息
     * @param array $data 返回数据
     * @param int $response_code 返回状态码
     * @return Json
     */
    protected function success(int $code,string $msg, array $data = [], int $response_code = 200):Json
    {
        return json(['code' => $code,'msg' => $msg,'data' => $data],$response_code);
    }

    /**
     * 返回失败的Json请求
     * @access protected
     * @param int $code 返回代码
     * @param string $msg 返回消息
     * @param array $data 返回数据
     * @param int $response_code 返回状态码
     * @return Json
     */
    protected function error(int $code, string $msg,array $data = [],int $response_code = 500):Json
    {
        return json(['code' => $code,'msg' => $msg,'data' => $data],$response_code);
    }
}
