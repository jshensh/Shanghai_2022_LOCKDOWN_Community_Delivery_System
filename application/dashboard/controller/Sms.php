<?php
namespace app\dashboard\controller;

use app\dashboard\controller\DashboardBase;
use app\dashboard\model\Config as ConfigModel;

class Sms extends DashboardBase
{
    public function test()
    {
        if (!$this->isAllowed('dashboard_sms_test')) {
            $this->redirect("dashboard/AccessDenied/index");
        }
        $this->assign('smsSignName', ConfigModel::where('k', 'sms_signname')->value('v'));
        return $this->fetch();
    }

    public function test2()
    {
        if (!$this->isAllowed('dashboard_sms_test')) {
            $this->redirect("dashboard/AccessDenied/index");
        }
        $this->assign('smsSignName', ConfigModel::where('k', 'sms_signname')->value('v'));
        return $this->fetch();
    }

    public function template()
    {
        if (!$this->isAllowed('dashboard_sms_template')) {
            $this->redirect("dashboard/AccessDenied/index");
        }
        return $this->fetch();
    }

    public function log()
    {
        if (!$this->isAllowed('dashboard_sms_log')) {
            $this->redirect("dashboard/AccessDenied/index");
        }
        return $this->fetch();
    }
}
