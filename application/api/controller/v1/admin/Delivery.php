<?php
namespace app\api\controller\v1\admin;

use app\api\model\v1\Delivery as DeliveryModel;
use app\api\model\v1\Config as ConfigModel;
use app\api\model\v1\User as UserModel;
use app\api\model\v1\UserGroup as UserGroupModel;
use app\api\model\v1\GroupBuy as GroupBuyModel;
use app\api\model\v1\SmsTemplate as SmsTemplateModel;
use app\api\model\v1\OrderDetail;
use app\api\controller\v1\DashboardApiBase;
use think\Request;
use think\Validate;
use think\Db;
use think\facade\Session;
use app\common\service\Paginator;
use app\common\service\TencentSms;

class Delivery extends DashboardApiBase
{
    private $permissionScope = 'dashboard_delivery';

    private function getDeliveryModel()
    {
        $delivery = DeliveryModel::join('order_detail', 'delivery.id = order_detail.delivery_id')
            ->join('group_buy', 'order_detail.group_buy_id = group_buy.id')
            ->leftJoin('delivery_user', 'delivery.id = delivery_user.delivery_id');

        if ((int) Session::get('gid') === 2) {
            $delivery = $delivery->where('group_buy.user_id', '=', Session::get('uid'));
        }

        if ((int) Session::get('gid') === 3) {
            $delivery = $delivery->where('delivery_user.user_id', '=', Session::get('uid'));
        }

        return $delivery;
    }

    public function claimBuilding($id, Request $request)
    {
        $delivery = $this->getDeliveryModel()->where('delivery.id', '=', $id)->field(['delivery.*'])->find();

        if (!$delivery) {
            return json(['status' => 'error', 'error' => '请求项不存在'], 400);
        }

        $pickupPoint = [
            "34" => '34号楼自提点',
            "14" => '14号楼自提点',
            "36" => '36号楼自提点',
            "32" => '32号楼自提点',
            "38" => '38号楼自提点',
            "2" => '2号楼自提点',
            "6" => '6号楼自提点',
            "11" => '11号楼自提点',
            "14" => '14号楼自提点',
            "19" => '19号楼自提点',
            "33" => '33号楼自提点',
            "35" => '35号楼自提点',
            "37" => '37号楼自提点',
            "54" => '54号楼自提点',
            "61" => '61号楼自提点',
            "67" => '67号楼自提点',
            "99" => '桂巷路大门自提点',
        ];

        $validator = [
            'pickup'   => ['in' => array_keys($pickupPoint)],
            'building' => ['require', 'max:50'],
        ];

        $validate = Validate::make($validator);
        if (!$validate->check($request->only(array_keys($validator)))) {
            return json(['status' => 'error', 'error' => $validate->getError()], 422);
        }

        $params = $request->only(array_keys($validator));

        if (isset($params['pickup'])) {
            $smsTemplate = SmsTemplateModel::where('name', '=', '自提通知')->value('id');

            if (!$smsTemplate) {
                throw new \Exception('“自提通知”短信模板未设置');
            }

            $notifiedCustomers = OrderDetail::rightJoin('sms_log', 'sms_log.phone = order_detail.phone')
                ->where('sms_log.created_at', '>', date('Y-m-d H:i:s', strtotime('-3 minutes')))
                ->where('order_detail.is_pickup', '=', 1)
                ->where('order_detail.delivery_id', '=', $delivery->id)
                ->where('order_detail.delivery_user_id', '=', Session::get('uid'))
                ->group('order_detail.phone')
                ->count();

            if ($notifiedCustomers >= 15) {
                return json(['status' => 'error', 'error' => '您过去三分钟内通知自提的客户已超过 15 人，为避免发生群体聚集事件，已拒绝本次认领请求'], 400);
            }
        } else {
            $smsTemplate = SmsTemplateModel::where('name', '=', '配送通知')->value('id');

            if (!$smsTemplate) {
                throw new \Exception('“配送通知”短信模板未设置');
            }

            $claimedBuildings = OrderDetail::rightJoin('sms_log', 'sms_log.phone = order_detail.phone')
                ->where('sms_log.created_at', '>', date('Y-m-d H:i:s', strtotime('-3 minutes')))
                ->where('order_detail.is_pickup', '=', 0)
                ->where('order_detail.delivery_id', '=', $delivery->id)
                ->where('order_detail.delivery_user_id', '=', Session::get('uid'))
                ->order('sms_log.created_at', 'desc')
                ->group('order_detail.building')
                ->column('order_detail.building');

            if (count($claimedBuildings) >= 4) {
                return json(['status' => 'error', 'error' => '您过去三分钟内认领的楼栋已达到四栋，分别为 ' . implode(', ', $claimedBuildings) . '，为避免发生群体聚集事件，已拒绝本次认领请求'], 400);
            }

            $claimedBuildings[] = $params['building'];
        }

        $notifyList = OrderDetail::where('delivery_id', '=', $delivery->id)
            ->where('status', '=', 1)
            ->where('building', '=', $params['building'])
            ->where('delivery_user_id', '=', 0)
            ->orderRaw('room + 0')
            ->order('phone')
            ->orderRaw('serial + 0')
            ->group('building, room, phone, product')
            ->select();

        if (!count($notifyList)) {
            return json(['status' => 'error', 'error' => '找不到需要认领的楼栋'], 422);
        }

        $updateData = ['delivery_user_id' => Session::get('uid')];

        if (isset($params['pickup'])) {
            $updateData['is_pickup'] = 1;
        }

        OrderDetail::where('delivery_id', '=', $delivery->id)
            ->where('status', '=', 1)
            ->where('building', '=', $params['building'])
            ->update($updateData);

        $resCount = ['successed' => 0, 'failed' => 0];

        $tencentSms = TencentSms::init(
            ConfigModel::where('k', 'sms_secret')->value('v'),
            ConfigModel::where('k', 'sms_key')->value('v'),
            ConfigModel::where('k', 'sms_appid')->value('v'),
            ConfigModel::where('k', 'sms_signname')->value('v')
        );

        $willNotify = ['phone' => '', 'receiver' => '', 'date' => [], 'product' => [], 'group_buy_id' => 0];

        foreach ($notifyList as $row) {
            if ($willNotify['phone'] !== $row->phone) {
                if ($willNotify['phone']) {
                    try {
                        if (isset($params['pickup'])) {
                            $tencentSms = $tencentSms->add([
                                'template'  => $smsTemplate,
                                'phones'    => $willNotify['phone'],
                                'params'    => [
                                    '收件人姓名'  => mb_substr(preg_replace('/\.(com|cn|org)/', '', $willNotify['receiver']), 0, 12),
                                    '订购时间'    => implode('、', array_values(array_unique($willNotify['date']))) . '日',
                                    '商品名称'    => TencentSms::formatSmsProduct(array_values(array_unique($willNotify['product']))),
                                    '自提点位置'  => $pickupPoint[$params['pickup']],
                                ],
                                'group_buy' => $willNotify['group_buy_id']
                            ]);
                        } else {
                            $tencentSms = $tencentSms->add([
                                'template'  => $smsTemplate,
                                'phones'    => $willNotify['phone'],
                                'params'    => [
                                    '收件人姓名'  => mb_substr(preg_replace('/\.(com|cn|org)/', '', $willNotify['receiver']), 0, 12),
                                    '订购时间'    => implode('、', array_values(array_unique($willNotify['date']))) . '日',
                                    '商品名称'    => TencentSms::formatSmsProduct(array_values(array_unique($willNotify['product']))),
                                    '预计配送剩余时间' => '五分钟',
                                ],
                                'group_buy' => $willNotify['group_buy_id']
                            ]);
                        }
                    } catch (\Exception $e) {
                        $resCount['failed']++;
                    }

                    $willNotify['date'] = [];
                    $willNotify['product'] = [];
                }
            }
            $willNotify['phone'] = $row->phone;
            $willNotify['group_buy_id'] = $row->group_buy_id;
            $willNotify['receiver'] = $row->receiver;
            $willNotify['date'][] = date('d', strtotime($row->created_at));
            $willNotify['product'][] = $row->product;
        }

        try {
            if (isset($params['pickup'])) {
                $tencentSms = $tencentSms->add([
                    'template'  => $smsTemplate,
                    'phones'    => $willNotify['phone'],
                    'params'    => [
                        '收件人姓名'  => mb_substr(preg_replace('/\.(com|cn|org)/', '', $willNotify['receiver']), 0, 12),
                        '订购时间'    => implode('、', array_values(array_unique($willNotify['date']))) . '日',
                        '商品名称'    => TencentSms::formatSmsProduct(array_values(array_unique($willNotify['product']))),
                        '自提点位置'  => $pickupPoint[$params['pickup']],
                    ],
                    'group_buy' => $willNotify['group_buy_id']
                ]);
            } else {
                $tencentSms = $tencentSms->add([
                    'template'  => $smsTemplate,
                    'phones'    => $willNotify['phone'],
                    'params'    => [
                        '收件人姓名'  => mb_substr(preg_replace('/\.(com|cn|org)/', '', $willNotify['receiver']), 0, 12),
                        '订购时间'    => implode('、', array_values(array_unique($willNotify['date']))) . '日',
                        '商品名称'    => TencentSms::formatSmsProduct(array_values(array_unique($willNotify['product']))),
                        '预计配送剩余时间' => '五分钟',
                    ],
                    'group_buy' => $willNotify['group_buy_id']
                ]);
            }
        } catch (\Exception $e) {
            $resCount['failed']++;
            // throw $e;
        }

        try {
            $notifyRes = $tencentSms->send();

            foreach ($notifyRes as $notify) {
                $resCount['successed'] += $notify['successed'];
                $resCount['failed'] += $notify['failed'];
            }

            if (isset($params['pickup'])) {
                return json(['status' => 'success', 'success' => "通知短信发送结果：成功 {$resCount['successed']} 条，失败 {$resCount['failed']} 条，您最近三分钟内已通知了 " . ($notifiedCustomers + $resCount['successed'] + $resCount['failed']) . " 位客户前来自提"]);
            } else {
                return json(['status' => 'success', 'success' => "通知短信发送结果：成功 {$resCount['successed']} 条，失败 {$resCount['failed']} 条，您最近三分钟内认领的楼栋为 " . implode(', ', $claimedBuildings)]);
            }
        } catch (\Exception $e) {
            return json(['status' => 'success', 'success' => "通知短信发送出错 " . $e->getMessage()], 400);
        }
    }

    public function getBuilding($id)
    {
        $delivery = $this->getDeliveryModel()->where('delivery.id', '=', $id)->find();

        if (!$delivery) {
            return json(['status' => 'error', 'error' => '请求项不存在'], 400);
        }

        return json(
            Paginator::create(OrderDetail::class)
                ->leftJoin('user', 'order_detail.delivery_user_id = user.id')
                ->field(['order_detail.building', 'sum(IF(order_detail.status=1,quantity,0)) as not_shipped_quantity', 'user.name as delivery_user', 'is_pickup'])
                ->where('order_detail.status', '<>', 0)
                ->where('order_detail.delivery_id', '=', $id)
                ->orderRaw('order_detail.building + 0 desc')
                ->order('order_detail.id')
                ->group('order_detail.building')
        );
    }

    public function writeOff($id, Request $request)
    {
        $delivery = $this->getDeliveryModel()->where('delivery.id', '=', $id)->find();

        if (!$delivery) {
            return json(['status' => 'error', 'error' => '该楼栋由其他配送员负责，当前账户无法操作核销'], 400);
        }

        $validator = [
            'building' => ['require'],
            'room'     => ['require'],
        ];

        $validate = Validate::make($validator);
        if (!$validate->check($request->only(array_keys($validator)))) {
            return json(['status' => 'error', 'error' => $validate->getError()], 422);
        }

        $params = $request->only(array_keys($validator));

        $order = OrderDetail::where('delivery_id', '=', $id)
            ->where('building', $params['building'])
            ->where('room', $params['room']);

        if ((int) Session::get('gid') === 3) {
            $order = $order->where('delivery_user_id', '=', Session::get('uid'));
        }
        
        if ($order->update(['status' => 2, 'writeoff_at' => date('Y-m-d H:i:s')])) {
            return json(['status' => 'success']);
        }

        return json(['status' => 'error', 'error' => '该楼栋由其他配送员负责，当前账户无法操作核销'], 400);
    }

    public function getSummaryTable($id, Request $request)
    {
        $delivery = $this->getDeliveryModel()->where('delivery.id', '=', $id)->find();

        if (!$delivery) {
            return json(['status' => 'error', 'error' => '请求项不存在'], 400);
        }

        $validator = [
            'calcSummaryTable' => ['array'],
        ];

        $validate = Validate::make($validator);
        if (!$validate->check($request->only(array_keys($validator)))) {
            return json(['status' => 'error', 'error' => $validate->getError()], 422);
        }

        $params = $request->only(array_keys($validator));

        $sum = OrderDetail::where('delivery_id', '=', $id)
            ->where('status', '<>', 0)
            ->field(['"合计" as building', 'product', 'sum(quantity) as quantity']);

        $data = OrderDetail::where('delivery_id', '=', $id)
            ->where('status', '<>', 0)
            ->field(['building', 'product', 'sum(quantity) as quantity']);

        if (isset($params['calcSummaryTable'])) {
            $sum = $sum->where('building', 'in', $params['calcSummaryTable']);
            $data = $data->where('building', 'in', $params['calcSummaryTable']);
        }
                
        $sum = $sum->orderRaw('building + 0 desc')
            ->group('product')
            ->select()
            ->toArray();

        $data = $data->orderRaw('building + 0 desc')
            ->group('building, product')
            ->select()
            ->toArray();

        return json([
            'status' => 'success',
            'data' => array_merge($sum, $data)
        ]);
    }

    public function getOrderDetails($id, Request $request)
    {
        $delivery = $this->getDeliveryModel()->where('delivery.id', '=', $id)->find();

        if (!$delivery) {
            return json(['status' => 'error', 'error' => '请求项不存在'], 400);
        }

        $building = $request->get('building');
        if (!$building) {
            return json(['status' => 'error', 'error' => '缺少 building 字段'], 422);
        }

        $data = OrderDetail::where('delivery_id', '=', $id)
            ->where('building', '=', $building);

        if ($request->get('all') === 'true') {
            $data = $data->where('status', '<>', 0);
        } else {
            $data = $data->where('status', '=', 1);
        }

        return json([
            'status' => 'success',
            'data' => $data->field(['id', 'group_buy_id', 'group_concat(serial) as serial', 'product', 'quantity', 'building', 'room', 'receiver', 'phone', 'remark', 'sum(quantity) as quantity', 'status', 'writeoff_at'])
                ->orderRaw('building + 0')
                ->orderRaw('room + 0')
                ->order('phone')
                ->orderRaw('serial + 0')
                ->group('building, room, phone, product')
                ->select()
        ]);
    }

    public function index(Request $request)
    {
        if (!$this->isAllowed($this->permissionScope, 1)) {
            return json(['status' => 'error', 'error' => '暂无操作权限'], 400);
        }

        $data = Paginator::create($this->getDeliveryModel())
            ->field(["delivery.*", "order_detail.delivery_id", 'order_detail.group_buy_id', 'order_detail.delivery_user_id', "group_buy.user_id"])
            ->order('delivery.id', 'desc')
            ->group('delivery.id')
            ->hidden(['creator_user_id', 'delivery_id', 'group_buy_id', 'delivery_user_id', 'user_id'])
            ->paginate();

        foreach ($data['data'] as $key => $value) {
            $data['data'][$key]['delivery'] = DeliveryModel::find($value['id'])->deliveryUsers;
            $groupBuyColumn = OrderDetail::where('delivery_id', '=', $value['id'])->group('group_buy_id')->column('group_buy_id');
            $data['data'][$key]['group_buy'] = GroupBuyModel::where('id', 'in', $groupBuyColumn)->column('title');
            $data['data'][$key]['order_details'] = OrderDetail::where('delivery_id', '=', $value['id'])->hidden(['id', 'delivery_id'])->field(['id', 'delivery_id', 'product', 'sum(quantity) as quantity'])->group('product')->select()->toArray();
            $data['data'][$key]['stats'] = OrderDetail::where('delivery_id', '=', $value['id'])
                ->field(['sum(IF(order_detail.status = 2, order_detail.quantity, 0)) as shipped_quantity', 'sum(IF(order_detail.status <> 0, order_detail.quantity, 0)) as quantity', 'min(order_detail.writeoff_at) as first_writeoff_at', 'max(order_detail.writeoff_at) as last_writeoff_at'])
                ->find();
        }

        return json($data);
    }

    public function read($id, Request $request)
    {
        if (!$this->isAllowed($this->permissionScope, 1)) {
            return json(['status' => 'error', 'error' => '暂无操作权限'], 400);
        }

        $delivery = $this->getDeliveryModel()
            ->hidden(['creator_user_id', 'delivery_id', 'group_buy_id', 'delivery_user_id', 'user_id'])
            ->with([
                'orderDetails' => function($query) {
                    $query->hidden(['id', 'delivery_id'])
                        ->field(['id', 'delivery_id', 'product', 'sum(quantity) as quantity'])
                        ->group('product');
                },
            ])
            ->field(["delivery.*", "order_detail.delivery_id", 'order_detail.group_buy_id', 'order_detail.delivery_user_id', "group_buy.user_id"])
            ->where('delivery.id', '=', $id)
            ->group('delivery.id')
            ->find();

        if (!$delivery) {
            return json(['status' => 'error', 'error' => '请求项不存在'], 400); 
        }

        $delivery = $delivery->toArray();
        $delivery['delivery'] = DeliveryModel::find($id)->deliveryUsers;
        $groupBuyColumn = OrderDetail::where('delivery_id', '=', $id)->group('group_buy_id')->column('group_buy_id');
        $delivery['group_buy'] = GroupBuyModel::where('id', 'in', $groupBuyColumn)->column('title');

        return json(['status' => 'success', 'data' => $delivery]);
    }

    public function getDeliveryUsers(Request $request)
    {
        if (!$this->isAllowed($this->permissionScope, 1)) {
            return json(['status' => 'error', 'error' => '暂无操作权限'], 400);
        }
        
        return json(Paginator::create(UserModel::class)->where('group_id', '=', 3)->field(['id', 'name']));
    }

    public function save(Request $request)
    {
        if (!$this->isAllowed($this->permissionScope, 2)) {
            return json(['status' => 'error', 'error' => '暂无操作权限'], 400);
        }

        $validator = [
            'groupBuy'     => ['require', 'array'],
            'product'      => ['require', 'array'],
            'deliveryUser' => ['array'],
            'deliveryTime' => ['in:10,15,20,30,60'],
        ];

        $validate = Validate::make($validator);
        if (!$validate->check($request->only(array_keys($validator)))) {
            return json(['status' => 'error', 'error' => $validate->getError()], 422);
        }

        $params = $request->only(array_keys($validator));

        Db::startTrans();

        try {
            $delivery                  = new DeliveryModel;
            $delivery->creator_user_id = Session::get('uid');
            $delivery->save();

            if (isset($params['deliveryUser'])) {
                $deliveryUsers = UserModel::where('group_id', '=', 3)
                    ->where('id', 'in', $params['deliveryUser'])
                    // ->field(["{$delivery->id} as delivery_id", 'id'])
                    ->column('id');

                if ($deliveryUsers) {
                    $delivery->deliveryUsers()->saveAll($deliveryUsers);
                }
            }

            $groupBuyIds = GroupBuyModel::where('id', 'in', $params['groupBuy']);

            if ((int) Session::get('gid') === 2) {
                $groupBuyIds = $groupBuyIds->where('user_id', '=', Session::get('uid'));
            }

            $groupBuyIds = $groupBuyIds->column('id');

            if (!$groupBuyIds) {
                throw new \Exception('没有找到可供分配的团购');
            }

            $productAssigned = false;

            foreach ($params['product'] as $product) {
                if (!is_array($product) || !isset($product['name']) || !isset($product['quantity']) || !is_numeric($product['quantity']) || (int) $product['quantity'] < 1) {
                    continue;
                }
                $product['quantity'] = (int) $product['quantity'];

                $orders = OrderDetail::where('group_buy_id', 'in', $groupBuyIds)
                    ->where('product', '=', $product['name'])
                    ->where('delivery_id', '=', 0)
                    ->where('status', '=', 1)
                    ->order('created_at')
                    ->order('serial')
                    ->lock(true)
                    ->select();

                foreach ($orders as $order) {
                    if ($product['quantity'] === 0) {
                        break;
                    }

                    if ($product['quantity'] >= $order->quantity) {
                        $order->update(['delivery_id' => $delivery->id], ['id' => $order->id]);
                        $productAssigned = true;
                        $product['quantity'] -= $order->quantity;
                    }
                }
            }

            if (!$productAssigned) {
                throw new \Exception('没有在限定团购中找到可供分配的商品');
            }

            if ($params['deliveryTime']) {
                $smsTemplate = SmsTemplateModel::where('name', '=', '到货通知')->value('id');

                if (!$smsTemplate) {
                    throw new \Exception('“到货通知”短信模板未设置');
                }

                $notifyList = OrderDetail::where('delivery_id', '=', $delivery->id)
                    ->where('status', '=', 1)
                    ->orderRaw('building + 0')
                    ->orderRaw('room + 0')
                    ->order('phone')
                    ->orderRaw('serial + 0')
                    ->group('building, room, phone, product')
                    ->select();

                $resCount = ['successed' => 0, 'failed' => 0];

                $tencentSms = TencentSms::init(
                    ConfigModel::where('k', 'sms_secret')->value('v'),
                    ConfigModel::where('k', 'sms_key')->value('v'),
                    ConfigModel::where('k', 'sms_appid')->value('v'),
                    ConfigModel::where('k', 'sms_signname')->value('v')
                );

                $willNotify = ['phone' => '', 'receiver' => '', 'date' => [], 'product' => [], 'group_buy_id' => 0];

                foreach ($notifyList as $row) {
                    if ($willNotify['phone'] !== $row->phone) {
                        if ($willNotify['phone']) {
                            try {
                                $tencentSms = $tencentSms->add([
                                    'template'  => $smsTemplate,
                                    'phones'    => $willNotify['phone'],
                                    'params'    => [
                                        '收件人姓名'  => mb_substr(preg_replace('/\.(com|cn|org)/', '', $willNotify['receiver']), 0, 12),
                                        '订购时间'    => implode('、', array_values(array_unique($willNotify['date']))) . '日',
                                        '商品名称'    => TencentSms::formatSmsProduct(array_values(array_unique($willNotify['product']))),
                                        '预计配送时间' => ([10 => '十分钟后', 15 => '十五分钟后', 20 => '二十分钟后', 30 => '三十分钟后', 60 => '一小时后'])[(int) $params['deliveryTime']],
                                    ],
                                    'group_buy' => $willNotify['group_buy_id']
                                ]);
                            } catch (\Exception $e) {
                                $resCount['failed']++;
                            }

                            $willNotify['date'] = [];
                            $willNotify['product'] = [];
                        }
                    }
                    $willNotify['phone'] = $row->phone;
                    $willNotify['group_buy_id'] = $row->group_buy_id;
                    $willNotify['receiver'] = $row->receiver;
                    $willNotify['date'][] = date('d', strtotime($row->created_at));
                    $willNotify['product'][] = $row->product;
                }

                try {
                    $tencentSms = $tencentSms->add([
                        'template'  => $smsTemplate,
                        'phones'    => $willNotify['phone'],
                        'params'    => [
                            '收件人姓名'  => mb_substr(preg_replace('/\.(com|cn|org)/', '', $willNotify['receiver']), 0, 12),
                            '订购时间'    => implode('、', array_values(array_unique($willNotify['date']))) . '日',
                            '商品名称'    => TencentSms::formatSmsProduct(array_values(array_unique($willNotify['product']))),
                            '预计配送时间' => ([10 => '十分钟后', 15 => '十五分钟后', 20 => '二十分钟后', 30 => '三十分钟后', 60 => '一小时后'])[(int) $params['deliveryTime']],
                        ],
                        'group_buy' => $willNotify['group_buy_id']
                    ]);
                } catch (\Exception $e) {
                    $resCount['failed']++;
                    // throw $e;
                }

                try {
                    $notifyRes = $tencentSms->send();

                    foreach ($notifyRes as $notify) {
                        $resCount['successed'] += $notify['successed'];
                        $resCount['failed'] += $notify['failed'];
                    }

                    Db::commit();
                    return json(['status' => 'success', 'success' => "通知短信发送结果：成功 {$resCount['successed']} 条，失败 {$resCount['failed']} 条"]);
                } catch (\Exception $e) {
                    return json(['status' => 'error', 'error' => "通知短信发送出错 " . $e->getMessage()], 400);
                }
            }

            Db::commit();
            return json(['status' => 'success', 'success' => '未发送通知短信']);
        } catch (\Exception $e) {
            Db::rollback();
            return json(['status' => 'error', 'error' => $e->getMessage(), 'trace' => $e->getTrace()], 400);
        }
    }

    public function update($id = 0, Request $request)
    {
        if (!$this->isAllowed($this->permissionScope, 4)) {
            return json(['status' => 'error', 'error' => '暂无操作权限'], 400);
        }

        $delivery = $this->getDeliveryModel()
            ->field(["delivery.*", "order_detail.delivery_id", 'order_detail.group_buy_id', 'order_detail.delivery_user_id', "group_buy.user_id"])
            ->where('delivery.id', '=', $id)
            ->find();

        if (!$delivery) {
            return json(['status' => 'error', 'error' => '请求项不存在'], 400);
        }

        $validator = [
            'deliveryUser' => ['array'],
        ];

        $params = array_intersect_key($request->put(), $validator);

        $validate = Validate::make($validator);
        if (!$validate->check($params)) {
            return json(['status' => 'error', 'error' => $validate->getError()], 422);
        }
        $params = array_intersect_key($request->put(), $validator);

        if (isset($params['deliveryUser'])) {
            $deliveryUsers = UserModel::where('group_id', '=', 3)
                ->where('id', 'in', $params['deliveryUser'])
                ->column('id');
        } else {
            $deliveryUsers = [];
        }

        $originDeliveryUsers = DeliveryModel::where('delivery.id', '=', $id)
            ->find()
            ->deliveryUsers()
            ->column('pivot.user_id');
        $intersect = array_values(array_intersect($originDeliveryUsers, $deliveryUsers));
        $diff1 = array_values(array_diff($originDeliveryUsers, $intersect));
        $diff1 && $delivery->deliveryUsers()->detach($diff1);
        $diff2 = array_values(array_diff($deliveryUsers, $intersect));
        $diff2 && $delivery->deliveryUsers()->attach($diff2);

        return json($delivery);
    }
}