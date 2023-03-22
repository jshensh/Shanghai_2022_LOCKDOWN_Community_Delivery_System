<?php
namespace app\api\controller\v1;

use app\common\controller\Base;

class DashboardApiBase extends Base
{
    public function initialize()
    {
        if (!$this->isLogined()) {
            json(['status' => 'error', 'error' => '未登录'], 400)->send();
        }
        if (!$this->isAllowed('dashboard')) {
            json(['status' => 'error', 'error' => '暂无操作权限'], 400)->send();
        }
    }
}