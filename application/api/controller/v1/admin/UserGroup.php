<?php
namespace app\api\controller\v1\admin;

use app\api\model\v1\User as UserModel;
use app\api\model\v1\UserGroup as UserGroupModel;
use app\api\model\v1\Permission as PermissionModel;
use app\api\model\v1\UserGroupPermission as UserGroupPermissionModel;
use app\api\controller\v1\DashboardApiBase;
use app\common\service\UserGroupPermissionCache;
use think\Request;
use app\common\service\Paginator;

class UserGroup extends DashboardApiBase
{
    private $permissionScope = 'dashboard_user_group';

    public function index(Request $request)
    {
        if (!$this->isAllowed($this->permissionScope, 1)) {
            return json(['status' => 'error', 'error' => '暂无操作权限'], 400);
        }

        $groups = Paginator::create(UserGroupModel::class)->allowSearch(['name']);

        return json($groups);
    }

    public function read($id)
    {
        if (!$this->isAllowed($this->permissionScope, 4)) {
            return json(['status' => 'error', 'error' => '暂无操作权限'], 400);
        }

        $userGroup = UserGroupModel::get($id);
        if (!$userGroup) {
            return json(['status' => 'error', 'error' => '请求项不存在'], 400);
        }
        return json(['name' => $userGroup->name, 'permission' => UserGroupPermissionModel::where('group_id', $id)->field(['scope_id', 'perm'])->select()]);
    }

    public function save(Request $request)
    {
        if (!$this->isAllowed($this->permissionScope, 2)) {
            return json(['status' => 'error', 'error' => '暂无操作权限'], 400);
        }

        $params = $request->post();
        if (!$params['name']) {
            return json(['status' => 'error', 'error' => '用户组名不合法'], 400);
        }

        if (UserGroupModel::get(['name' => $params['name']])) {
            return json(['status' => 'error', 'error' => '用户组已存在'], 400);
        }

        $userGroup       = new UserGroupModel;
        $userGroup->name = $params['name'];

        if (!$userGroup->save()) {
            return json(['status' => 'error', 'error' => '未知错误'], 400);
        }

        $groupId = $userGroup->id;
        $permData = [];

        if (isset($params['permission']) && is_array($params['permission'])) {
            $pid = array_keys($params['permission']);
            $scopeArr = PermissionModel::where('id', 'in', $pid)->column('scope', 'id');
            foreach ($params['permission'] as $key => $value) {
                if (isset($scopeArr[$key])) {
                    $permData[] = ['group_id' => $groupId, 'scope_id' => $key, 'scope' => $scopeArr[$key], 'perm' => array_sum($value)];
                }
            }
        }

        $userGroupPermission = new UserGroupPermissionModel;
        $userGroupPermission->saveAll($permData);

        UserGroupPermissionCache::build($groupId);

        return json(['status' => 'success']);
    }

    public function update($id = 0, Request $request)
    {
        if (!$this->isAllowed($this->permissionScope, 4)) {
            return json(['status' => 'error', 'error' => '暂无操作权限'], 400);
        }

        if (!$this->isAllowed((int)$this->getSession('gid') === (int)$id ? 'group_same' : 'group_diff', 1, $id)) {
            return json(['status' => 'error', 'error' => '暂无操作权限'], 400);
        }

        $params = $request->put();
        $userGroup = UserGroupModel::get($id);
        if (!$userGroup) {
            return json(['status' => 'error', 'error' => '请求项不存在'], 400);
        }

        if (!$params['name']) {
            return json(['status' => 'error', 'error' => '用户组名不合法'], 400);
        }

        if ($userGroup->name !== $params['name'] && UserGroupModel::get(['name' => $params['name']])) {
            return json(['status' => 'error', 'error' => '新用户组名已存在'], 400);
        }

        $userGroup->name  = $params['name'];
        $userGroup->save();

        UserGroupPermissionModel::destroy(['group_id' => $id]);

        $permData = [];

        if (isset($params['permission']) && is_array($params['permission'])) {
            $pid = array_keys($params['permission']);
            $scopeArr = PermissionModel::where('id', 'in', $pid)->column('scope', 'id');
            foreach ($params['permission'] as $key => $value) {
                if (isset($scopeArr[$key])) {
                    $permData[] = ['group_id' => $id, 'scope_id' => $key, 'scope' => $scopeArr[$key], 'perm' => array_sum($value)];
                }
            }
        }

        $userGroupPermission = new UserGroupPermissionModel;
        $userGroupPermission->saveAll($permData);

        UserGroupPermissionCache::rm($id);
        UserGroupPermissionCache::build($id);

        return json(['id' => $id, 'name' => $userGroup->name]);
    }
    
    public function delete($id = 0)
    {
        if (!$this->isAllowed($this->permissionScope, 8)) {
            return json(['status' => 'error', 'error' => '暂无操作权限'], 400);
        }

        if ((int)$this->getSession('gid') !== (int)$id) {
            if (!$this->isAllowed('group_diff', 2, $id)) {
                return json(['status' => 'error', 'error' => '暂无操作权限'], 400);
            }
        }

        $userGroup = UserGroupModel::get($id);
        if (!$userGroup) {
            return json(['status' => 'error', 'error' => '请求项不存在'], 400);
        }

        if (UserModel::get(['group_id' => $id])) {
            return json(['status' => 'error', 'error' => '被删除用户组下存在用户，不可删除'], 400);
        }

        $userGroup->delete();
        UserGroupPermissionModel::destroy(['group_id' => $id]);
        UserGroupPermissionCache::rm($id);
        return json(['errno' => 0]);
    }
}