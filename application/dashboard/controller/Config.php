<?php
namespace app\dashboard\controller;

use app\dashboard\model\Config as ConfigModel;
use app\dashboard\model\UserGroup as UserGroupModel;
use app\dashboard\controller\DashboardBase;

class Config extends DashboardBase
{
    public function index()
    {
        if (!$this->isAllowed('dashboard_config')) {
            $this->redirect("dashboard/AccessDenied/index");
        }
        $this->assign('config', ConfigModel::column('v', 'k'));
        $this->assign('userGroup', UserGroupModel::order('id')->column('name', 'id'));
        return $this->fetch();
    }
}
