<?php
namespace app\index\controller;

use app\common\controller\Base;

class Index extends Base
{
    public function index()
    {
        if (!$this->isLogined()) {
            $this->redirect("user/index/index");
        }
        $this->redirect("dashboard/index/index");
    }
}
