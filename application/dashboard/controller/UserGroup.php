<?php
namespace app\dashboard\controller;

use app\dashboard\controller\DashboardBase;

class UserGroup extends DashboardBase
{
    public function index()
    {
        if (!$this->isAllowed('dashboard_user_group')) {
            $this->redirect("dashboard/AccessDenied/index");
        }
        return $this->fetch();
    }

    public function permission($id = 0)
    {
        if ($id) {
            if (!$this->isAllowed('dashboard_user_group', 4)) {
                $this->redirect("dashboard/AccessDenied/index");
            }
            if (!$this->isAllowed((int)$this->getSession('gid') === (int)$id ? 'group_same' : 'group_diff', 1, $id)) {
                $this->redirect("dashboard/AccessDenied/index");
            }
        } else {
            if (!$this->isAllowed('dashboard_user_group', 2)) {
                $this->redirect("dashboard/AccessDenied/index");
            }
        }
        return $this->fetch();
    }
}
