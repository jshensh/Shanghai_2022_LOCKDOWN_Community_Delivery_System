<?php
namespace app\api\controller\v1\admin;

use app\api\model\v1\User as UserModel;
use app\api\model\v1\UserGroup as UserGroupModel;
use app\api\controller\v1\DashboardApiBase;
use think\Request;
use think\facade\Session;
use app\common\service\Paginator;

class User extends DashboardApiBase
{
    private $permissionScope = 'dashboard_user';

    public function index(Request $request)
    {
        if (!$this->isAllowed($this->permissionScope, 1)) {
            return json(['status' => 'error', 'error' => '暂无操作权限'], 400);
        }

        $users = UserModel::with('userGroup')->field('pwd', true);
        
        return json(Paginator::create($users));
    }

    public function save(Request $request)
    {
        if (!$this->isAllowed($this->permissionScope, 2)) {
            return json(['status' => 'error', 'error' => '暂无操作权限'], 400);
        }

        if (!$request->post('name')) {
            return json(['status' => 'error', 'error' => '用户名不合法'], 400);
        }

        if (UserModel::get(['name' => $request->post('name')])) {
            return json(['status' => 'error', 'error' => '用户已存在'], 400);
        }

        if (!$request->post('pwd')) {
            return json(['status' => 'error', 'error' => '密码不合法'], 400);
        }

        if (!(int)$request->post('group_id')) {
            return json(['status' => 'error', 'error' => '用户组不合法'], 400);
        }

        if (!UserGroupModel::get((int)$request->post('group_id'))) {
            return json(['status' => 'error', 'error' => '用户组不存在'], 400);
        }

        if ((int)$this->getSession('gid') === (int)$request->post('group_id')) {
            if (!$this->isAllowed('group_same', 2, (int)$request->post('group_id'))) {
                return json(['status' => 'error', 'error' => '您暂无将新用户设置为该组成员的权限'], 400);
            }
        } else {
            if (!$this->isAllowed('group_diff', 4, (int)$request->post('group_id'))) {
                return json(['status' => 'error', 'error' => '您暂无将新用户设置为该组成员的权限'], 400);
            }
        }

        $user           = new UserModel;
        $user->name     = $request->post('name');
        $user->group_id = $request->post('group_id');
        $user->pwd      = password_hash($request->post('pwd'), PASSWORD_BCRYPT);
        if ($user->save()) {
            return json(['status' => 'success']);
        }
        return json(['status' => 'error', 'error' => '未知错误'], 400);
    }

    public function update($id = 0, Request $request)
    {
        if (!$this->isAllowed($this->permissionScope, 4)) {
            return json(['status' => 'error', 'error' => '暂无操作权限'], 400);
        }

        $params = $request->put();
        $user = UserModel::get($id);
        if (!$user) {
            return json(['status' => 'error', 'error' => '请求项不存在'], 400);
        }

        if (!$params['name']) {
            return json(['status' => 'error', 'error' => '用户名不合法'], 400);
        }

        if ($user->name !== $params['name'] && UserModel::get(['name' => $params['name']])) {
            return json(['status' => 'error', 'error' => '新用户名已存在'], 400);
        }

        if (!(int)$params['group_id']) {
            return json(['status' => 'error', 'error' => '用户组不合法'], 400);
        }

        if (!UserGroupModel::get((int)$params['group_id'])) {
            return json(['status' => 'error', 'error' => '用户组不存在'], 400);
        }

        if ((int)$this->getSession('gid') === (int)$user->group_id) {
            if (!$this->isAllowed('group_same', 4, (int)$user->group_id)) {
                return json(['status' => 'error', 'error' => '您暂无编辑该组成员的权限'], 400);
            }
        } else {
            if (!$this->isAllowed('group_diff', 8, (int)$user->group_id)) {
                return json(['status' => 'error', 'error' => '您暂无编辑该组成员的权限'], 400);
            }
        }

        if ((int)$user->group_id !== (int)$params['group_id']) {
            if ((int)$this->getSession('gid') === (int)$params['group_id']) {
                if (!$this->isAllowed('group_same', 2, (int)$params['group_id'])) {
                    return json(['status' => 'error', 'error' => '您暂无将用户设置为该组成员的权限'], 400);
                }
            } else {
                if (!$this->isAllowed('group_diff', 4, (int)$params['group_id'])) {
                    return json(['status' => 'error', 'error' => '您暂无将用户设置为该组成员的权限'], 400);
                }
            }
            $user->group_id = $params['group_id'];
        }

        $user->name = $params['name'];
        
        if ($params['pwd']) {
            $user->pwd = password_hash($params['pwd'], PASSWORD_BCRYPT);
        }
        
        $user->save();

        if ((int)$id === (int)Session::get('uid')) {
            Session::set('gid', $params['group_id']);
        }

        return json(['id' => $user->id, 'name' => $user->name]);
    }

    public function modifySmsAmount($id = 0, Request $request)
    {
        $params = $request->put();
        $user = UserModel::get($id);
        if (!$user) {
            return json(['status' => 'error', 'error' => '请求项不存在'], 400);
        }

        if (!isset($params['quantity']) || !is_numeric($params['quantity'])) {
            return json(['status' => 'error', 'error' => '请求数量不合法'], 400);
        }

        if (UserModel::get($id)->setInc('sms_amount', (int) $params['quantity'])) {
            return json(['status' => 'success']);
        }
        
        return json(['status' => 'error', 'error' => '未知错误'], 400);
    }
    
    public function delete($id = 0)
    {
        if (!$this->isAllowed($this->permissionScope, 8)) {
            return json(['status' => 'error', 'error' => '暂无操作权限'], 400);
        }

        $user = UserModel::get($id);

        if (!$user) {
            return json(['status' => 'error', 'error' => '请求项不存在'], 400);
        }

        if ((int)$this->getSession('gid') === (int)$user->group_id) {
            if (!$this->isAllowed('group_same', 8, (int)$user->group_id)) {
                return json(['status' => 'error', 'error' => '您暂无删除该组成员的权限'], 400);
            }
        } else {
            if (!$this->isAllowed('group_diff', 16, (int)$user->group_id)) {
                return json(['status' => 'error', 'error' => '您暂无删除该组成员的权限'], 400);
            }
        }

        if ((int)$id === (int)Session::get('uid')) {
            return json(['status' => 'error', 'error' => '被删除用户为当前操作用户，不可删除'], 400);
        }

        $user->delete();
        return json(['errno' => 0]);
    }
}