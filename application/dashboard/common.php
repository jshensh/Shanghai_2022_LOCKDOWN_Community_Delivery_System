<?php
use think\facade\Session;
use app\common\service\UserGroupPermissionCache;

if (!function_exists('getPermission')) {
    function getPermission()
    {
        $groupId = Session::get('gid');
        if (!$groupId) {
            return false;
        }
        return UserGroupPermissionCache::get($groupId);
    }
}

if (!function_exists('isAllowed')) {
    function isAllowed($permissionScope, $perm = 1)
    {
        $groupPerm = getPermission();
        if (!isset($groupPerm[$permissionScope])) {
            return false;
        }
        return $groupPerm[$permissionScope] & $perm;
    }
}