<?php
namespace app\api\controller\v1\admin;

use app\api\controller\v1\DashboardApiBase;
use think\Request;
use think\Validate;
use think\facade\Session;

use app\common\service\TencentSms;
use app\common\service\Paginator;
use app\api\model\v1\Config as ConfigModel;
use app\api\model\v1\SmsLog as SmsLogModel;
use app\api\model\v1\SmsTemplate as SmsTemplateModel;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use \PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;

class Sms extends DashboardApiBase
{
    private $permissionScope = 'dashboard_sms';

    public function initialize()
    {
        if (!$this->isAllowed($this->permissionScope)) {
            json(['status' => 'error', 'error' => '暂无操作权限'], 400)->send();
        }
    }

    public function log()
    {
        if (!$this->isAllowed('dashboard_sms_log', 1)) {
            return json(['status' => 'error', 'error' => '暂无操作权限'], 400);
        }

        $data = SmsLogModel::with(['groupBuy', 'user'])
            ->field(['sms_log.id', 'sms_log.content', 'sms_log.send_status', 'sms_log.failed_reason', 'sms_log.length', 'sms_log.phone', 'sms_log.created_at', 'sms_log.group_buy_id', 'sms_log.user_id'])
            ->hidden(['group_buy_id', 'user_id']);

        if ((int) Session::get('gid') === 2) {
            $data = $data->join('group_buy', 'sms_log.group_buy_id = group_buy.id')
                ->where('group_buy.user_id', '=', Session::get('uid'))
                ->whereOr('sms_log.user_id', '=', Session::get('uid'));
        }

        return json(
            Paginator::create($data)
                ->order('sms_log.id', 'desc')
                ->allowSearch(['title', 'group_buy_id', 'send_status', 'phone'])
                ->search('group_buy_id', function ($model, $value, $inputFields) {
                    if (!is_array($value)) {
                        return $model;
                    }
                    foreach ($value as $key => $groupBuy) {
                        if (!$groupBuy) {
                            unset($value[$key]);
                        }
                    }
                    if (!$value) {
                        return $model;
                    }
                    return $model->where('sms_log.group_buy_id', 'in', $value);
                })
                ->search('phone', function ($model, $value, $inputFields) {
                    if (!$value || !is_string($value)) {
                        return $model;
                    }
                    return $model->where('sms_log.phone', 'like', "%{$value}%");
                })
                ->search('send_status', function ($model, $value, $inputFields) {
                    if (!in_array($value, ['0', '1', true])) {
                        return $model;
                    }
                    return $model->where('sms_log.send_status', '=', $value);
                })
        );
    }

    public function send(Request $request)
    {
        if (!$this->isAllowed('dashboard_sms_test', 1)) {
            return json(['status' => 'error', 'error' => '暂无操作权限'], 400);
        }

        try {            
            $resp = TencentSms::init(
                    ConfigModel::where('k', 'sms_secret')->value('v'),
                    ConfigModel::where('k', 'sms_key')->value('v'),
                    ConfigModel::where('k', 'sms_appid')->value('v'),
                    ConfigModel::where('k', 'sms_signname')->value('v')
                )
                // ->add([
                //     'params'   => ['测试用户1', ' 4 月 26 日', '测试商品', '一小时后'],
                //     'phones'   => ['13113023146'],
                //     'template' => 1
                // ])
                // ->add([
                //     'params'   => ['测试用户2', ' 4 月 26 日', '测试商品', '一小时后'],
                //     'phones'   => ['18565811792'],
                //     'template' => 1
                // ])
                ->add($request->post())
                ->send(true);

            return json(['status' => 'success', 'data' => $resp])
                ->options(['json_encode_param' => JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE]);
        } catch(\Exception $e) {
            return json(['status' => 'error', 'error' => $e->getMessage(), 'trace' => $e->getTrace()], 400)
                ->options(['json_encode_param' => JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE]);
        }
    }

    private function parseTemplateFile($smsTemplate, $file)
    {
        $reader = IOFactory::createReader("Xlsx");
        $reader->setReadDataOnly(true);
        $reader->setLoadSheetsOnly(["Worksheet"]);
        $spreadsheet = $reader->load($file);
        $worksheet = $spreadsheet->getActiveSheet();
        $highestRow = $worksheet->getHighestRow(); // e.g. 10
        $highestColumnIndex = Coordinate::columnIndexFromString($worksheet->getHighestColumn()); // e.g. 5

        if ($highestRow <= 1) {
            throw new \Exception("数据错误，可能缺少“Worksheet”数据表");
        }
        
        $smsTemplateParams = json_decode($smsTemplate->params, 1);
        $column = array_flip($smsTemplateParams);
        $columnIndex = array_combine(array_keys($column), array_pad([], count($column), 0));
        $columnIndex['手机号'] = 0;

        for ($col = 1; $col <= $highestColumnIndex; $col++) {
            $tmpTHeadCell = $worksheet->getCellByColumnAndRow($col, 1)->getValue();
            if (isset($columnIndex[$tmpTHeadCell])) {
                $columnIndex[$tmpTHeadCell] = $col;
            }
        }

        $missingKey = array_search(0, $columnIndex, true);
        if ($missingKey) {
            throw new \Exception("数据错误，缺少“{$missingKey}”字段");
        }

        $data = [];

        for ($row = 2; $row <= $highestRow; $row++) {
            $data[$row - 2] = [
                'template' => $smsTemplate->id,
                'params'   => [],
                'phones'   => [$worksheet->getCellByColumnAndRow($columnIndex['手机号'], $row)->getValue()]
            ];

            foreach ($columnIndex as $key => $col) {
                if ($key === '手机号') {
                    continue;
                }
                $data[$row - 2]['params'][$column[$key]] = $worksheet->getCellByColumnAndRow($col, $row)->getValue();
            }
        }

        return $data;
    }

    public function preview2(Request $request)
    {
        if (!$this->isAllowed('dashboard_sms_test', 1)) {
            return json(['status' => 'error', 'error' => '暂无操作权限'], 400);
        }

        $validator = [
            'template'  => ['require', 'number'],
            'file'      => ['require', 'file']
        ];

        $validate = Validate::make($validator);
        if (!$validate->check(array_merge($request->only(array_keys($validator)), $request->file() ?? []))) {
            return json(['status' => 'error', 'error' => $validate->getError()], 422);
        }
        $params = array_merge($request->only(array_keys($validator)), $request->file());
        
        $smsTemplate = SmsTemplateModel::get($params['template']);
        if (!$smsTemplate) {
            return json(['status' => 'error', 'error' => '短信模板不存在'], 400);
        }

        try {
            $data = $this->parseTemplateFile($smsTemplate, $params['file']->getPathname());
            return json(['count' => count($data), 'firstRow' => $data[0]]);
        } catch (\Exception $e) {
            return json(['status' => 'error', 'error' => $e->getMessage()], 400);
        }
    }

    public function send2(Request $request)
    {
        if (!$this->isAllowed('dashboard_sms_test', 1)) {
            return json(['status' => 'error', 'error' => '暂无操作权限'], 400);
        }

        $validator = [
            'template'  => ['require', 'number'],
            'file'      => ['require', 'file']
        ];

        $validate = Validate::make($validator);
        if (!$validate->check(array_merge($request->only(array_keys($validator)), $request->file() ?? []))) {
            return json(['status' => 'error', 'error' => $validate->getError()], 422);
        }
        $params = array_merge($request->only(array_keys($validator)), $request->file());
        
        $smsTemplate = SmsTemplateModel::get($params['template']);
        if (!$smsTemplate) {
            return json(['status' => 'error', 'error' => '短信模板不存在'], 400);
        }

        try {
            $data = $this->parseTemplateFile($smsTemplate, $params['file']->getPathname());
            
            $resp = TencentSms::init(
                    ConfigModel::where('k', 'sms_secret')->value('v'),
                    ConfigModel::where('k', 'sms_key')->value('v'),
                    ConfigModel::where('k', 'sms_appid')->value('v'),
                    ConfigModel::where('k', 'sms_signname')->value('v')
                );

            foreach ($data as $row) {
                $resp = $resp->add($row);
            }
            
            $resp = $resp->send(true);

            return json(['status' => 'success', 'data' => $resp])
                ->options(['json_encode_param' => JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE]);
        } catch (\Exception $e) {
            return json(['status' => 'error', 'error' => $e->getMessage()], 400);
        }
    }

    // public function pullStatus()
    // {
    //     try {            
    //         $resp = TencentSms::pullStatus();

    //         return json(['status' => 'success', 'data' => $resp])
    //             ->options(['json_encode_param' => JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE]);
    //     } catch(\Exception $e) {
    //         return json(['status' => 'error', 'error' => $e->getMessage()], 400)
    //             ->options(['json_encode_param' => JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE]);
    //     }
    // }
}
