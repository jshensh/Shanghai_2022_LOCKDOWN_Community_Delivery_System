<?php
namespace app\dashboard\controller;

use app\dashboard\controller\DashboardBase;

class Order extends DashboardBase
{
    public function index()
    {
        if (!$this->isAllowed('dashboard_order')) {
            $this->redirect("dashboard/AccessDenied/index");
        }
        return $this->fetch();
    }
}
