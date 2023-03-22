<?php
namespace app\api\controller\v1\admin;

use app\api\controller\v1\DashboardApiBase;
use think\Request;
use think\Validate;
use think\facade\Session;
use app\common\service\Paginator;
use app\common\service\ExportOrder;
use app\api\model\v1\OrderDetail;
use app\api\model\v1\GroupBuy as GroupBuyModel;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use \PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;

class Order extends DashboardApiBase
{
    private $permissionScope = 'dashboard_order';

    private function getOrderDetailModel()
    {
        $order = OrderDetail::join('group_buy', 'order_detail.group_buy_id = group_buy.id')
            ->field(["order_detail.*", "group_buy.user_id"]);

        if ((int) Session::get('gid') === 2) {
            $order = $order->where('group_buy.user_id', '=', Session::get('uid'));
        }

        return $order;
    }

    public function index(Request $request)
    {
        if (!$this->isAllowed($this->permissionScope, 1)) {
            return json(['status' => 'error', 'error' => '暂无操作权限'], 400);
        }
        
        return json(Paginator::create($this->getOrderDetailModel())
            ->allowSearch(['group_buy_id', 'product', 'phone', 'building', 'serial', 'status'])
            ->search('group_buy_id', function ($model, $value, $inputFields) {
                if (!$value || !is_numeric($value)) {
                    return $model;
                }
                return $model->where('order_detail.group_buy_id', '=', $value);
            })
            ->search('product', function ($model, $value, $inputFields) {
                if (!is_array($value)) {
                    return $model;
                }
                foreach ($value as $key => $product) {
                    if (!$product) {
                        unset($value[$key]);
                    }
                }
                if (!$value) {
                    return $model;
                }
                return $model->where('product', 'in', $value);
            })
            ->search('phone', function ($model, $value, $inputFields) {
                if (!$value || !is_string($value)) {
                    return $model;
                }
                return $model->where('phone', 'like', "%{$value}%");
            })
            ->search('serial', function ($model, $value, $inputFields) {
                if (!$value || !is_numeric($value)) {
                    return $model;
                }
                return $model->where('serial', '=', $value);
            })
            ->search('building', function ($model, $value, $inputFields) {
                if (!$value || !is_numeric($value)) {
                    return $model;
                }
                return $model->where('building', '=', $value);
            })
            ->search('status', function ($model, $value, $inputFields) {
                if ($value === '' || !in_array($value, ['0', '1_1', '1_2', '2'])) {
                    return $model;
                }
                switch ($value) {
                    case '0':
                    case '2':
                        return $model->where('status', '=', $value);
                    case '1_1':
                    case '1_2':
                        return $model->where('status', '=', '1')->where('delivery_id', substr($value, -1) === '1' ? '=' : '<>', '0');
                }
            })
        );
    }

    public function productList(Request $request)
    {
        if (!$this->isAllowed($this->permissionScope, 1)) {
            return json(['status' => 'error', 'error' => '暂无操作权限'], 400);
        }

        $order = OrderDetail::join('group_buy', 'order_detail.group_buy_id = group_buy.id')
            ->field(["order_detail.group_buy_id", "order_detail.product", "order_detail.delivery_id", "sum(IF(order_detail.delivery_id = 0 AND order_detail.status = 1, quantity, 0)) as unshipped_quantity", "sum(IF(order_detail.delivery_id <> 0, quantity, 0)) as shipped_quantity", "group_buy.user_id"]);

        if ((int) Session::get('gid') === 2) {
            $order = $order->where('group_buy.user_id', '=', Session::get('uid'));
        }
        
        return json(Paginator::create($order)
            ->group("product")
            ->allowSearch(['group_buy_id'])
            ->search('group_buy_id', function ($model, $value, $inputFields) {
                if (is_numeric($value)) {
                    return $model->where('group_buy_id', '=', $value);
                }
                if (is_array($value)) {
                    return $model->where('group_buy_id', 'in', $value);
                }
                return $model->where('group_buy_id', '=', '');
            })
            ->order("order_detail.product")
        );
    }

    public function read($id, Request $request)
    {
        if (!$this->isAllowed($this->permissionScope, 1)) {
            return json(['status' => 'error', 'error' => '暂无操作权限'], 400);
        }

        $order = $this->getOrderDetailModel()->where('order_detail.id', '=', $id)->find();

        if (!$order) {
            return json(['status' => 'error', 'error' => '请求项不存在'], 400);
        }

        return json(['status' => 'success', 'data' => $order]);
    }

    public function save(Request $request)
    {
        if (!$this->isAllowed($this->permissionScope, 2)) {
            return json(['status' => 'error', 'error' => '暂无操作权限'], 400);
        }

        $validator = [
            'group_buy_id' => ['require', 'number'],
            'serial'       => ['require', 'number'],
            'product'      => ['require', 'max:255'],
            'quantity'     => ['require', 'number'],
            'receiver'     => ['require', 'max:50'],
            'phone'        => ['require', 'number', 'length:11'],
            'building'     => ['require', 'max:50'],
            'room'         => ['max:255'],
            'remark'       => ['max:255'],
        ];

        $validate = Validate::make($validator);
        if (!$validate->check($request->only(array_keys($validator)))) {
            return json(['status' => 'error', 'error' => $validate->getError()], 422);
        }

        $params = $request->only(array_keys($validator));

        $groupBuy = GroupBuyModel::where('id', '=', $params['group_buy_id']);

        if ((int) Session::get('gid') === 2) {
            $groupBuy = $groupBuy->where('user_id', '=', Session::get('uid'));
        }

        $groupBuy = $groupBuy->find();

        if (!$groupBuy) {
            return json(['status' => 'error', 'error' => '请求项不存在'], 400);
        }

        if ($groupBuy->orderDetails()->save($params)) {
            return json(['status' => 'success']);
        }
        return json(['status' => 'error', 'error' => '未知错误'], 400);
    }

    public function export(Request $request)
    {
        if (!$this->isAllowed($this->permissionScope, 1)) {
            return json(['status' => 'error', 'error' => '暂无操作权限'], 400);
        }

        $validator = [
            'groupBuyId' => ['require', 'number'],
            'mergeCell'  => ['in:true,false']
        ];

        $validate = Validate::make($validator);
        if (!$validate->check($request->only(array_keys($validator)))) {
            return json(['status' => 'error', 'error' => $validate->getError()], 422);
        }
        $params = $request->only(array_keys($validator));

        $groupBuy = GroupBuyModel::where('id', '=', $params['groupBuyId']);

        if ((int) Session::get('gid') === 2) {
            $groupBuy = $groupBuy->where('user_id', '=', Session::get('uid'));
        }

        $groupBuy = $groupBuy->find();

        if (!$groupBuy) {
            return json(['status' => 'error', 'error' => '请求项不存在'], 400);
        }

        $now = date('YmdHis');
        $title = "{$now} - {$groupBuy->title}";

        $xlsData = ExportOrder::create($groupBuy->orderDetails(), (isset($params['mergeCell']) && $params['mergeCell'] !== 'false') ? true : false)
            ->allowSearch(['product'])
            ->setProperties(function($properties) use ($title) {
                return $properties->setCreator("403 Forbidden <admin@imjs.work>")
                    ->setLastModifiedBy("403 Forbidden <admin@imjs.work>")
                    ->setTitle($title);
            })
            ->search('product', function ($model, $value, $inputFields) {
                if (!is_array($value)) {
                    return $model;
                }
                foreach ($value as $key => $product) {
                    if (!$product) {
                        unset($value[$key]);
                    }
                }
                if (!$value) {
                    return $model;
                }
                return $model->where('product', 'in', $value);
            });

        return response()->data($xlsData)->header([
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$title}.xlsx\"",
            'Cache-control'       => 'no-cache,must-revalidate'
        ]);
    }

    public function import(Request $request)
    {
        if (!$this->isAllowed($this->permissionScope, 10)) {
            return json(['status' => 'error', 'error' => '暂无操作权限'], 400);
        }

        $validator = [
            'groupBuyId' => ['require', 'number'],
            'excel'      => ['require', 'file'],
        ];

        $validate = Validate::make($validator);
        if (!$validate->check(array_merge($request->only(array_keys($validator)), $request->file() ?? []))) {
            return json(['status' => 'error', 'error' => $validate->getError()], 422);
        }
        $params = array_merge($request->only(array_keys($validator)), $request->file());

        $groupBuy = GroupBuyModel::where('id', '=', $params['groupBuyId']);

        if ((int) Session::get('gid') === 2) {
            $groupBuy = $groupBuy->where('user_id', '=', Session::get('uid'));
        }

        $groupBuy = $groupBuy->find();
        if (!$groupBuy) {
            return json(['status' => 'error', 'error' => '团购不存在'], 400);
        }

        if ($groupBuy->orderDetails()->where('delivery_id', '<>', 0)->count()) {
            return json(['status' => 'error', 'error' => '团购中存在已分配发货任务的订单，无法重新导入'], 400);
        }

        $reader = IOFactory::createReader("Xlsx");
        $reader->setReadDataOnly(true);
        $reader->setLoadSheetsOnly(["商品列表"]);
        $spreadsheet = $reader->load($params['excel']->getPathname());
        $worksheet = $spreadsheet->getActiveSheet();
        $highestRow = $worksheet->getHighestRow(); // e.g. 10
        $highestColumnIndex = Coordinate::columnIndexFromString($worksheet->getHighestColumn()); // e.g. 5

        if ($highestRow <= 1) {
            return json(['status' => 'error', 'error' => "数据错误，可能缺少“商品列表”数据表"], 400);
        }

        $columnIndex = [
            'serial'     => 0,
            'remark'     => 0,
            'created_at' => 0,
            'product'    => 0,
            'quantity'   => 0,
            'status'     => 0,
            'receiver'   => 0,
            'phone'      => 0,
            'room'       => 0,
            'building'   => 0,
        ];
        $column = [
            '跟团号' => 'serial',
            '团员备注' => 'remark',
            '支付时间' => 'created_at',
            '商品' => 'product',
            '数量' => 'quantity',
            '订单状态' => 'status',
            '收货人' => 'receiver',
            '联系电话' => 'phone',
            '门牌号' => 'room',
            '门牌号（如 606）' => 'room',
            '门牌号（如606）' => 'room',
            '房间号' => 'room',
            '房号' => 'room',
            '房号（如 606）' => 'room',
            '房号（如606）' => 'room',
            '楼号' => 'building',
            '楼号（如 10）' => 'building',
            '楼号（如10）' => 'building',
        ];

        for ($col = 1; $col <= $highestColumnIndex; $col++) {
            $tmpTHeadCell = $worksheet->getCellByColumnAndRow($col, 1)->getValue();
            if (isset($column[$tmpTHeadCell])) {
                $columnIndex[$column[$tmpTHeadCell]] = $col;
            }
        }

        $missingKey = array_search(0, $columnIndex, true);
        if ($missingKey) {
            return json(['status' => 'error', 'error' => "数据错误，缺少“{$missingKey}”字段"], 400);
        }

        $data = [];

        for ($row = 2; $row <= $highestRow; $row++) {
            if (!(int) $worksheet->getCellByColumnAndRow($columnIndex['serial'], $row)->getValue()) {
                continue;
            }

            $data[$row - 2] = ['group_buy_id' => $params['groupBuyId']];

            foreach ($columnIndex as $key => $col) {
                switch ($key) {
                    case 'serial':
                    case 'quantity':
                        if ((int) $worksheet->getCellByColumnAndRow($col, $row)->getValue() <= 0 && $worksheet->getCellByColumnAndRow($columnIndex['status'], $row)->getValue() === '已支付') {
                            return json(['status' => 'error', 'error' => Coordinate::stringFromColumnIndex($col) . "{$row} 单元格数据错误，该单元格应为大于等于 0 的数字"], 400);
                        }
                        $data[$row - 2][$key] = (int) $worksheet->getCellByColumnAndRow($col, $row)->getValue();
                        break;
                    case 'remark':
                    case 'product':
                        $data[$row - 2][$key] = mb_substr(preg_replace('/([0-9|#][\x{20E3}])|[\x{00ae}|\x{00a9}|\x{203C}|\x{2047}|\x{2048}|\x{2049}|\x{3030}|\x{303D}|\x{2139}|\x{2122}|\x{3297}|\x{3299}][\x{FE00}-\x{FEFF}]?|[\x{2190}-\x{21FF}][\x{FE00}-\x{FEFF}]?|[\x{2300}-\x{23FF}][\x{FE00}-\x{FEFF}]?|[\x{2460}-\x{24FF}][\x{FE00}-\x{FEFF}]?|[\x{25A0}-\x{25FF}][\x{FE00}-\x{FEFF}]?|[\x{2600}-\x{27BF}][\x{FE00}-\x{FEFF}]?|[\x{2600}-\x{27BF}][\x{1F000}-\x{1FEFF}]?|[\x{2900}-\x{297F}][\x{FE00}-\x{FEFF}]?|[\x{2B00}-\x{2BF0}][\x{FE00}-\x{FEFF}]?|[\x{1F000}-\x{1F9FF}][\x{FE00}-\x{FEFF}]?|[\x{1F000}-\x{1F9FF}][\x{1F000}-\x{1FEFF}]?/u', '', trim($worksheet->getCellByColumnAndRow($col, $row)->getValue())), 0, 255);
                        break;
                    case 'room':
                    case 'building':
                    case 'receiver':
                        $data[$row - 2][$key] = mb_substr(preg_replace('/([0-9|#][\x{20E3}])|[\x{00ae}|\x{00a9}|\x{203C}|\x{2047}|\x{2048}|\x{2049}|\x{3030}|\x{303D}|\x{2139}|\x{2122}|\x{3297}|\x{3299}][\x{FE00}-\x{FEFF}]?|[\x{2190}-\x{21FF}][\x{FE00}-\x{FEFF}]?|[\x{2300}-\x{23FF}][\x{FE00}-\x{FEFF}]?|[\x{2460}-\x{24FF}][\x{FE00}-\x{FEFF}]?|[\x{25A0}-\x{25FF}][\x{FE00}-\x{FEFF}]?|[\x{2600}-\x{27BF}][\x{FE00}-\x{FEFF}]?|[\x{2600}-\x{27BF}][\x{1F000}-\x{1FEFF}]?|[\x{2900}-\x{297F}][\x{FE00}-\x{FEFF}]?|[\x{2B00}-\x{2BF0}][\x{FE00}-\x{FEFF}]?|[\x{1F000}-\x{1F9FF}][\x{FE00}-\x{FEFF}]?|[\x{1F000}-\x{1F9FF}][\x{1F000}-\x{1FEFF}]?/u', '', trim($worksheet->getCellByColumnAndRow($col, $row)->getValue())), 0, 50);
                        if (!trim($data[$row - 2][$key]) && $key !== 'room') {
                            return json(['status' => 'error', 'error' => Coordinate::stringFromColumnIndex($col) . "{$row} 单元格数据错误，该单元格不能为空"], 400);
                        }
                        break;
                    case 'phone':
                        $data[$row - 2][$key] = mb_substr($worksheet->getCellByColumnAndRow($col, $row)->getValue(), 0, 11);
                        break;
                    case 'status':
                        $data[$row - 2][$key] = $worksheet->getCellByColumnAndRow($col, $row)->getValue() === '已支付' ? 1 : 0;
                        break;
                    default:
                        $data[$row - 2][$key] = $worksheet->getCellByColumnAndRow($col, $row)->getValue();
                }
            }
        }

        OrderDetail::where('group_buy_id', '=', $params['groupBuyId'])->delete();
        (new OrderDetail)->saveAll($data);

        return json(['status' => 'success', 'rows' => $highestRow - 1]);
    }

    public function update($id = 0, Request $request)
    {
        if (!$this->isAllowed($this->permissionScope, 4)) {
            return json(['status' => 'error', 'error' => '暂无操作权限'], 400);
        }

        $order = $this->getOrderDetailModel()->where('order_detail.id', '=', $id)->find();

        if (!$order) {
            return json(['status' => 'error', 'error' => '请求项不存在'], 400);
        }

        $validator = [
            'group_buy_id' => ['number'],
            'serial'       => ['number'],
            'product'      => ['max:255'],
            'quantity'     => ['number'],
            'receiver'     => ['max:50'],
            'phone'        => ['number', 'length:11'],
            'building'     => ['max:50'],
            'room'         => ['max:255'],
            'remark'       => ['max:255'],
        ];

        $params = array_intersect_key($request->put(), $validator);

        $validate = Validate::make($validator);
        if (!$validate->check($params)) {
            return json(['status' => 'error', 'error' => $validate->getError()], 422);
        }

        foreach ($params as $key => $value) {
            $order->{$key} = $value;
        }
        
        $order->save();

        return json(['status' => 'success', 'data' => $order]);
    }
    
    public function delete($id = 0)
    {
        if (!$this->isAllowed($this->permissionScope, 8)) {
            return json(['status' => 'error', 'error' => '暂无操作权限'], 400);
        }

        $order = $this->getOrderDetailModel()->where('order_detail.id', '=', $id)->find();

        if (!$order) {
            return json(['status' => 'error', 'error' => '请求项不存在'], 400);
        }

        $order->delete();
        return json(['errno' => 0]);
    }
}