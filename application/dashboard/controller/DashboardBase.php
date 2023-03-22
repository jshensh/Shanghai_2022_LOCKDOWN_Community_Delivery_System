<?php
namespace app\dashboard\controller;

use think\facade\Session;
use app\common\controller\Base;

class DashboardBase extends Base
{
    public function initialize()
    {
        if (!$this->isLogined()) {
            $this->redirect("user/index/index");
        }
        if (!$this->isAllowed('dashboard')) {
            Session::clear();
            $this->redirect("user/index/index");
        }
    }
}