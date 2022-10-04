<?php
// 如果不需要指定类型请求请注释
use think\facade\Route;

Route::group('api',function (){
    Route::get('login','Api/login');
    Route::post('login','Api/login');

    Route::get('signin','Api/signIn');
    Route::post('signin','Api/signIn');

    Route::get('getgameinfo','Api/getGameInfo');
    Route::post('getgameinfo','Api/getGameInfo');
});