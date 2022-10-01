<?php
namespace app\controller;

use app\BaseController;
use think\facade\View;


class Index extends BaseController
{
    public function index(): string
    {
        $Description = $this->client->get('https://ys.mihoyo.com/')->getBody()->getContents();
        preg_match('/<meta name="Description" content="(.*?)">/',$Description,$matches);
        $Description = $matches[1];
        View::assign('Description',$Description);
        return View::fetch();
    }
}
