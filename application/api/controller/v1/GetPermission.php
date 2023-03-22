<?php
namespace app\api\controller\v1;

use app\api\controller\v1\DashboardApiBase;
use think\Request;
use think\facade\Session;

class GetPermission extends DashboardApiBase
{
    public function index()
    {
        return json($this->getPermission());
    }
}