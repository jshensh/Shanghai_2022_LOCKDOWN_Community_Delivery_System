<?php
namespace app\common\service;

use app\common\model\UserGroupPermission as UserGroupPermissionModel;
use think\Controller;
use think\facade\Cache;

class UserGroupPermissionCache extends Controller
{
    public static function rm($groupId)
    {
        if (is_array($groupId)) {
            foreach ($groupId as $val) {
                Cache::rm("system_permission_{$val}");
            }
        } else {
            Cache::rm("system_permission_{$groupId}");
        }
        return true;
    }

    public static function build($groupId)
    {
        $permArr = UserGroupPermissionModel::where('group_id', $groupId)->column('perm', 'scope');
        if (!$permArr) {
            return false;
        }
        Cache::set("system_permission_{$groupId}", json_encode($permArr));
    }

    public static function get($groupId)
    {
        $perm = Cache::get("system_permission_{$groupId}");
        if (!$perm && !self::build($groupId)) {
            return [];
        } else {
            $perm = Cache::get("system_permission_{$groupId}");
        }

        return json_decode($perm, 1);
    }
}