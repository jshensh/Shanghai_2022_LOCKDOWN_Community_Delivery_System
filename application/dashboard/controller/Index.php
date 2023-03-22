<?php
namespace app\dashboard\controller;

use think\facade\Session;

use app\dashboard\controller\DashboardBase;

use app\dashboard\model\User;
use app\dashboard\model\UserGroup;

class Index extends DashboardBase
{
    public function index()
    {
        $this->assign('userGroup', UserGroup::find(Session::get('gid')));
        $this->assign('user', User::find(Session::get('uid')));
        return $this->fetch();
    }
}
