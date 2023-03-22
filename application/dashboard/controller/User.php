<?php
namespace app\dashboard\controller;

use app\dashboard\controller\DashboardBase;
use think\facade\Session;

class User extends DashboardBase
{
    public function index()
    {
        if (!$this->isAllowed('dashboard_user')) {
            $this->redirect("dashboard/AccessDenied/index");
        }
        return $this->fetch();
    }

    public function logout()
    {
        Session::delete('uid');
        Session::delete('git');

        return redirect('/');
    }
}
