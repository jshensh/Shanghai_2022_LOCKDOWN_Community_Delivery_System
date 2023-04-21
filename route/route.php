<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

Route::resource('api/:version/admin/User', 'api/:version.admin.User');
Route::resource('api/:version/admin/UserGroup', 'api/:version.admin.UserGroup');
Route::resource('api/:version/admin/SmsTemplate', 'api/:version.admin.SmsTemplate');
Route::resource('api/:version/admin/GroupBuy', 'api/:version.admin.GroupBuy');
Route::resource('api/:version/admin/Order', 'api/:version.admin.Order');
Route::resource('api/:version/admin/Delivery', 'api/:version.admin.Delivery');

Route::group('api/:version', function() {
    Route::put('admin/User/:id/:action', 'api/:version.admin.User/:action?id=:id')->pattern(['id' => '\d+']);
    Route::get('admin/Delivery/:id/:action', 'api/:version.admin.Delivery/:action?id=:id')->pattern(['id' => '\d+']);
    Route::post('admin/Delivery/:id/:action', 'api/:version.admin.Delivery/:action?id=:id')->pattern(['id' => '\d+']);
    Route::get('admin/SmsTemplate/:id/:action', 'api/:version.admin.SmsTemplate/:action?id=:id')->pattern(['id' => '\d+']);

    Route::get('admin/:controller/:action', 'api/:version.admin.:controller/:action');
    Route::post('admin/:controller/:action', 'api/:version.admin.:controller/:action');
    Route::get('admin/:controller', 'api/:version.admin.:controller/index');
    Route::get(':controller/:action', 'api/:version.:controller/:action');
    Route::post(':controller/:action', 'api/:version.:controller/:action');
    Route::get(':controller', 'api/:version.:controller/index');
});

Route::get('dashboard/user_group/permission/:id', 'dashboard/UserGroup/permission?id=:id');
Route::get('dashboard/delivery/detail/:id', 'dashboard/Delivery/detail?id=:id');

return [

];

