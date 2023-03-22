<?php
namespace app\api\controller\v1\admin;

use app\api\model\v1\GroupBuy as GroupBuyModel;
use app\api\controller\v1\DashboardApiBase;
use think\Request;
use think\Validate;
use think\facade\Session;
use app\common\service\Paginator;

class GroupBuy extends DashboardApiBase
{
    private $permissionScope = 'dashboard_group_buy';

    public function index(Request $request)
    {
        if (!$this->isAllowed($this->permissionScope, 1)) {
            return json(['status' => 'error', 'error' => '暂无操作权限'], 400);
        }

        $data = GroupBuyModel::with('groupBuyUser');

        if ((int) Session::get('gid') === 2) {
            $data = $data->where('user_id', '=', Session::get('uid'));
        }
        
        return json(
            Paginator::create($data)
                ->order('id', 'desc')
                ->allowSearch(['title'])
        );
    }

    public function read($id, Request $request)
    {
        if (!$this->isAllowed($this->permissionScope, 1)) {
            return json(['status' => 'error', 'error' => '暂无操作权限'], 400);
        }

        $groupBuy = GroupBuyModel::where('id', '=', $id);

        if ((int) Session::get('gid') === 2) {
            $groupBuy = $groupBuy->where('user_id', '=', Session::get('uid'));
        }

        $groupBuy = $groupBuy->find();

        if (!$groupBuy) {
            return json(['status' => 'error', 'error' => '请求项不存在'], 400);
        }

        return json(['status' => 'success', 'data' => $groupBuy]);
    }

    public function save(Request $request)
    {
        if (!$this->isAllowed($this->permissionScope, 2)) {
            return json(['status' => 'error', 'error' => '暂无操作权限'], 400);
        }

        $validator = [
            'title' => ['require', 'max:255'],
        ];

        $validate = Validate::make($validator);
        if (!$validate->check($request->only(array_keys($validator)))) {
            return json(['status' => 'error', 'error' => $validate->getError()], 422);
        }

        $params = $request->only(array_keys($validator));

        $groupBuy          = new GroupBuyModel;
        $groupBuy->title   = $params['title'];
        $groupBuy->user_id = Session::get('uid');
        $groupBuy->product_short_name = [];
        if ($groupBuy->save()) {
            return json(['status' => 'success']);
        }
        return json(['status' => 'error', 'error' => '未知错误'], 400);
    }

    public function update($id = 0, Request $request)
    {
        if (!$this->isAllowed($this->permissionScope, 4)) {
            return json(['status' => 'error', 'error' => '暂无操作权限'], 400);
        }

        $groupBuy = GroupBuyModel::where('id', '=', $id);

        if ((int) Session::get('gid') === 2) {
            $groupBuy = $groupBuy->where('user_id', '=', Session::get('uid'));
        }

        $groupBuy = $groupBuy->find();

        if (!$groupBuy) {
            return json(['status' => 'error', 'error' => '请求项不存在'], 400);
        }

        $validator = [
            'title' => ['max:255']
        ];

        $params = array_intersect_key($request->put(), $validator);

        $validate = Validate::make($validator);
        if (!$validate->check($params)) {
            return json(['status' => 'error', 'error' => $validate->getError()], 422);
        }

        foreach ($params as $key => $value) {
            $groupBuy->{$key} = $value;
        }
        
        $groupBuy->save();

        return json(['status' => 'success', 'data' => $groupBuy]);
    }
    
    public function delete($id = 0)
    {
        if (!$this->isAllowed($this->permissionScope, 8)) {
            return json(['status' => 'error', 'error' => '暂无操作权限'], 400);
        }

        $groupBuy = GroupBuyModel::where('id', '=', $id);

        if ((int) Session::get('gid') === 2) {
            $groupBuy = $groupBuy->where('user_id', '=', Session::get('uid'));
        }

        $groupBuy = $groupBuy->find();
        if (!$groupBuy) {
            return json(['status' => 'error', 'error' => '团购不存在'], 400);
        }

        if ($groupBuy->orderDetails()->where('delivery_id', '<>', 0)->count()) {
            return json(['status' => 'error', 'error' => '团购中存在已分配发货任务的订单，无法删除'], 400);
        }

        $groupBuy->delete();
        return json(['errno' => 0]);
    }
}