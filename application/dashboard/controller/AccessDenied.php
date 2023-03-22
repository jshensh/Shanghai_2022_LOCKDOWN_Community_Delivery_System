<?php
namespace app\dashboard\controller;

class AccessDenied extends DashboardBase
{
    public function index()
    {
        return $this->fetch();
    }
}