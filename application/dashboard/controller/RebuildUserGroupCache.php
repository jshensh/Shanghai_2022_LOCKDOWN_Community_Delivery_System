<?php
namespace app\dashboard\controller;

use app\api\model\v1\UserGroup as UserGroupModel;
use app\common\service\UserGroupPermissionCache;
use app\common\controller\Base;

class RebuildUserGroupCache extends Base
{
    public function index() {
        $group = UserGroupModel::column('id');
        foreach ($group as $value) {
            UserGroupPermissionCache::build($value);
        }

        return $this->fetch();
    }
}