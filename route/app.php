<?php
// 如果不需要指定类型请求请注释
use think\facade\Route;

Route::rule('help','index/Help');
Route::rule('image','index/Image');
Route::group('api',function (){
    Route::get('web_login','Api/web_login');
    Route::post('web_login','Api/web_login');

    Route::get('app_login','Api/app_login');
    Route::post('app_login','Api/app_login');

    Route::get('genshin_signin','Api/genshin_signIn');
    Route::post('genshin_signin','Api/genshin_signIn');

    Route::get('getgameinfo','Api/getGameInfo');
    Route::post('getgameinfo','Api/getGameInfo');
});