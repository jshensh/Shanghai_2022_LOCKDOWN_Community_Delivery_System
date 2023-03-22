<?php
namespace app\common\controller;

use think\Controller;
use think\facade\Session;
use app\common\model\User as UserModel;
use app\common\service\UserGroupPermissionCache;

class Base extends Controller
{
    protected function getSession($key)
    {
        return Session::get($key);
    }

    protected function isLogined()
    {
        return UserModel::where('id', $this->getSession('uid'))->value('id');
    }

    protected function getPermission($groupId = 0)
    {
        $groupId = $groupId ? $groupId : $this->getSession('gid');
        if (!$groupId) {
            return false;
        }
        return UserGroupPermissionCache::get($groupId);
    }

    protected function isAllowed($permissionScope, $perm = 1, $groupId = 0)
    {
        $groupPerm = $this->getPermission($groupId);
        if (!isset($groupPerm[$permissionScope])) {
            return false;
        }
        return $groupPerm[$permissionScope] & $perm;
    }
}