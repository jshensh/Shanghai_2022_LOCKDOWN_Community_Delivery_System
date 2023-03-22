<?php
namespace app\user\controller;

use app\common\controller\Base;

class Index extends Base
{
    public function index()
    {
        if ($this->isLogined()) {
            $this->redirect("dashboard/index/index");
        }
        return $this->fetch();
    }
}