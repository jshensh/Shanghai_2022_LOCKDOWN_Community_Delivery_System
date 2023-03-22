<?php
namespace app\dashboard\controller;

use app\dashboard\controller\DashboardBase;

class GroupBuy extends DashboardBase
{
    public function index()
    {
        if (!$this->isAllowed('dashboard_group_buy')) {
            $this->redirect("dashboard/AccessDenied/index");
        }
        return $this->fetch();
    }
}
