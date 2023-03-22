<?php
namespace app\api\model\v1;

use think\Model;

class OrderDetail extends Model
{
    public function groupBuy()
    {
        return $this->belongsTo('app\\api\\model\\v1\\GroupBuy');
    }

    public function delivery()
    {
        return $this->belongsTo('app\\api\\model\\v1\\Delivery', 'delivery_id');
    }

    public function deliveryUser()
    {
        return $this->belongsTo('app\\api\\model\\v1\\User', 'delivery_user_id');
    }
}