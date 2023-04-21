<?php
namespace app\api\controller\v1\admin;

use app\api\model\v1\SmsTemplate as SmsTemplateModel;
use app\api\controller\v1\DashboardApiBase;
use think\Request;
use think\Validate;
use think\facade\Session;
use app\common\service\Paginator;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use \PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;
use \PhpOffice\PhpSpreadsheet\Cell\DataType;

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

    public function download($id = 0)
    {
        if (!$this->isAllowed($this->permissionScope, 1)) {
            return json(['status' => 'error', 'error' => '暂无操作权限'], 400);
        }

        $smsTemplate = SmsTemplateModel::get($id);
        if (!$smsTemplate) {
            return json(['status' => 'error', 'error' => '请求项不存在'], 400);
        }

        $now = date('YmdHis');
        $title = "{$now} - {$smsTemplate->name}模板";

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $spreadsheet->getDefaultStyle()->getFont()->setName('宋体')->setSize(11);
        $spreadsheet->getDefaultStyle()->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT);
        $spreadsheet->getDefaultStyle()->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
        $worksheet = $spreadsheet->getActiveSheet();
        $worksheet->getPageSetup()->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4);
        $worksheet->getDefaultRowDimension()->setRowHeight(15);

        $worksheet->setCellValueExplicitByColumnAndRow(1, 1, "手机号", DataType::TYPE_STRING);
        $worksheet->getColumnDimension(Coordinate::stringFromColumnIndex(1))->setWidth(15);

        $params = json_decode($smsTemplate->params, 1);
        for ($i = 0; $i < count($params); $i++) {
            $worksheet->setCellValueExplicitByColumnAndRow($i + 2, 1, $params[$i], DataType::TYPE_STRING);
            $worksheet->getColumnDimension(Coordinate::stringFromColumnIndex($i + 2))->setWidth(mb_strlen($params[$i]) * 2 + 2);
        }

        ob_start();
        $writer = IOFactory::createWriter($spreadsheet, "Xlsx");
        $writer->save('php://output');
        $xlsData = ob_get_contents();
        ob_end_clean();

        return response()->data($xlsData)->header([
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$title}.xlsx\"",
            'Cache-control'       => 'no-cache,must-revalidate'
        ]);
    }
}