<?php
namespace app\dashboard\controller;

use app\dashboard\controller\DashboardBase;

class Delivery extends DashboardBase
{
    public function index()
    {
        if (!$this->isAllowed('dashboard_delivery')) {
            $this->redirect("dashboard/AccessDenied/index");
        }
        return $this->fetch();
    }

    public function detail($id = 0)
    {
        if (!$this->isAllowed('dashboard_delivery')) {
            $this->redirect("dashboard/AccessDenied/index");
        }
        $this->assign('deliveryId', $id);
        return $this->fetch();
    }
}
