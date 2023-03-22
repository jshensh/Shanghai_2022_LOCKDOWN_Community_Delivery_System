<?php
namespace app\api\controller\v1\admin;

use app\api\model\v1\Permission as PermissionModel;
use app\api\controller\v1\DashboardApiBase;

class Permission extends DashboardApiBase
{
    private function formatTree($data)
    {
        $tree = [];
        foreach ($data as $value) {
            $tree[$value['id']] = $value;
            $tree[$value['id']]['children'] = [];
        }

        foreach ($tree as $key => $value) {
            if ($value['parent'] !== 0) {
                $tree[$value['parent']]['children'][] = &$tree[$key];
                if (!$tree[$key]['children']) {
                    unset($tree[$key]['children']);
                }
            }
        }

        foreach ($tree as $key => $value) {
            if (!isset($value['parent']) || $value['parent'] !== 0) {
                unset($tree[$key]);
            }
        }

        return $tree;
    }

    public function index()
    {
        $data = json_decode(json_encode(PermissionModel::order('parent, id')->select()), 1);
        return json(array_values($this->formatTree($data)));
    }
}