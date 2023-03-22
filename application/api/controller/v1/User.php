<?php
namespace app\api\controller\v1;

use app\api\model\v1\User as UserModel;
use app\api\model\v1\UserGroup as UserGroupModel;
use app\api\model\v1\Config as ConfigModel;
use app\common\controller\Base;
use think\Request;
use think\facade\Session;

class User extends Base
{
    public function login(Request $request)
    {
        if ($this->isLogined()) {
            return json(['status' => 'error', 'error' => '您已登录'], 400);
        }
        if (true !== $this->validate($request->post(), [
            'code' => ['require', 'captcha']
        ])) {
            return json(['status' => 'error', 'error' => '验证码错误'], 400);
        }
        if (true !== $this->validate($request->post(), [
            'name' => 'require',
            'pwd'  => 'require'
        ])) {
            return json(['status' => 'error', 'error' => '用户名或密码格式不正确'], 400);
        }
        $user = UserModel::get(['name' => $request->post('name')]);
        if (!$user) {
            return json(['status' => 'error', 'error' => '用户未注册'], 400);
        }
        if (password_verify($request->post('pwd'), $user['pwd'])) {
            if (!$this->isAllowed('dashboard', 1, $user['group_id'])) {
                return json(['status' => 'error', 'error' => '暂无访问权限，请您联系管理员处理'], 400);
            }
            Session::set('uid', $user['id']);
            Session::set('gid', $user['group_id']);
            return json(array('status' => 'success'));
        }
        return json(['status' => 'error', 'error' => '用户名或密码错误'], 400);
    }

    public function register(Request $request)
    {
        if ($this->isLogined()) {
            return json(['status' => 'error', 'error' => '您已登录'], 400);
        }
        if (true !== $this->validate($request->post(), [
            'code' => ['require', 'captcha']
        ])) {
            return json(['status' => 'error', 'error' => '验证码错误'], 400);
        }
        if (true !== $this->validate($request->post(), [
            'name' => 'require',
            'pwd'  => 'require'
        ])) {
            return json(['status' => 'error', 'error' => '用户名或密码格式不正确'], 400);
        }
        $user = UserModel::get(['name' => $request->post('name')]);
        if ($user) {
            return json(['status' => 'error', 'error' => '用户已存在，请直接登录'], 400);
        }

        $groupId = ConfigModel::where('k', 'sys_user_register_group')->value('v');

        $user           = new UserModel;
        $user->name     = $request->post('name');
        $user->pwd      = password_hash($request->post('pwd'), PASSWORD_BCRYPT);
        $user->group_id = $groupId;

        if ($user->save()) {
            if (!$this->isAllowed('dashboard', 1, $groupId)) {
                return json(['status' => 'error', 'error' => '暂无访问权限，请您联系管理员处理'], 400);
            }
            Session::set('uid', $user->id);
            Session::set('gid', $groupId);
            return json(array('status' => 'success'));
        }
        return json(['status' => 'error', 'error' => '未知错误'], 400);
    }
}