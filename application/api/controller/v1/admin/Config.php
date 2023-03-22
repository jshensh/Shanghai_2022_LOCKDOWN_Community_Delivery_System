<?php
namespace app\api\controller\v1\admin;

use app\api\model\v1\Config as ConfigModel;
use app\api\controller\v1\DashboardApiBase;
use think\Request;
use think\facade\Session;

class Config extends DashboardApiBase
{
    private $permissionScope = 'dashboard_config';

    public function initialize()
    {
        if (!$this->isAllowed($this->permissionScope)) {
            json(['status' => 'error', 'error' => '暂无操作权限'], 400)->send();
        }
    }

    public function index()
    {
        return json([
            "data" => ConfigModel::column('v', 'k')
        ]);
    }

    public function update(Request $request)
    {
        $params = $request->post();

        $list = [];

        foreach ($params as $key => $value) {
            $list[] = ['k' => $key, 'v' => $value];
        }

        $config = new ConfigModel;
        $config->saveAll($list, true);

        return json(['status' => 'success']);
    }
}
