<?php
namespace app\api\controller\v1\admin;

use app\api\model\v1\SmsTemplate as SmsTemplateModel;
use app\api\controller\v1\DashboardApiBase;
use think\Request;
use think\Validate;
use think\facade\Session;
use app\common\service\Paginator;

class SmsTemplate extends DashboardApiBase
{
    private $permissionScope = 'dashboard_sms_template';

    public function index(Request $request)
    {
        if (!$this->isAllowed($this->permissionScope, 1)) {
            return json(['status' => 'error', 'error' => '暂无操作权限'], 400);
        }
        
        return json(Paginator::create(SmsTemplateModel::class));
    }

    public function read($id, Request $request)
    {
        if (!$this->isAllowed($this->permissionScope, 1)) {
            return json(['status' => 'error', 'error' => '暂无操作权限'], 400);
        }

        $smsTemplate = SmsTemplateModel::get($id);
        if (!$smsTemplate) {
            return json(['status' => 'error', 'error' => '请求项不存在'], 400);
        }

        return json(['status' => 'success', 'data' => $smsTemplate]);
    }

    public function save(Request $request)
    {
        if (!$this->isAllowed($this->permissionScope, 2)) {
            return json(['status' => 'error', 'error' => '暂无操作权限'], 400);
        }

        $validator = [
            'name'    => ['require', 'max:50'],
            'serial'  => ['require', 'number'],
            'content' => ['require'],
            'params'  => ['require'],
        ];

        $validate = Validate::make($validator);
        if (!$validate->check($request->only(array_keys($validator)))) {
            return json(['status' => 'error', 'error' => $validate->getError()], 422);
        }

        $params = $request->only(array_keys($validator));

        if (!@json_decode($params['params'])) {
            return json(['status' => 'error', 'error' => 'params 不合法'], 422);
        }

        $smsTemplate          = new SmsTemplateModel;
        $smsTemplate->name    = $params['name'];
        $smsTemplate->serial  = $params['serial'];
        $smsTemplate->content = $params['content'];
        $smsTemplate->params  = $params['params'];
        if ($smsTemplate->save()) {
            return json(['status' => 'success']);
        }
        return json(['status' => 'error', 'error' => '未知错误'], 400);
    }

    public function update($id = 0, Request $request)
    {
        if (!$this->isAllowed($this->permissionScope, 4)) {
            return json(['status' => 'error', 'error' => '暂无操作权限'], 400);
        }

        $smsTemplate = SmsTemplateModel::get($id);
        if (!$smsTemplate) {
            return json(['status' => 'error', 'error' => '请求项不存在'], 400);
        }

        $validator = [
            'name'    => ['require', 'max:50'],
            'serial'  => ['require', 'number'],
            'content' => ['require'],
            'params'  => ['require'],
        ];
        $params = array_intersect_key($request->put(), $validator);

        if (!@json_decode($params['params'])) {
            return json(['status' => 'error', 'error' => 'params 不合法'], 422);
        }

        $validate = Validate::make($validator);
        if (!$validate->check($params)) {
            return json(['status' => 'error', 'error' => $validate->getError()], 422);
        }

        foreach ($params as $key => $value) {
            $smsTemplate->{$key} = $value;
        }
        
        $smsTemplate->save();

        return json(['status' => 'success', 'data' => $smsTemplate]);
    }
    
    public function delete($id = 0)
    {
        if (!$this->isAllowed($this->permissionScope, 8)) {
            return json(['status' => 'error', 'error' => '暂无操作权限'], 400);
        }

        $smsTemplate = SmsTemplateModel::get($id);
        if (!$smsTemplate) {
            return json(['status' => 'error', 'error' => '请求项不存在'], 400);
        }

        $smsTemplate->delete();
        return json(['errno' => 0]);
    }
}