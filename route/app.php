<?php
// 如果不需要指定类型请求请注释
use think\facade\Route;

Route::group('api',function (){
    Route::get('login','Api/login');//支持GET类型请求
    Route::post('login','Api/login');//支持POST类型请求
});