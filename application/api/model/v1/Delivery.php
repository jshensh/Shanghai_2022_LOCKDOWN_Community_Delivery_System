<?php
namespace app\api\model\v1;

use think\Model;

class Delivery extends Model
{
    public function deliveryUsers()
    {
        return $this->belongsToMany('app\\api\\model\\v1\\User', 'app\\api\\model\\v1\\DeliveryUser', 'user_id', 'delivery_id')->field(['user.id', 'user.name']);
    }

    public function creatorUser()
    {
        return $this->belongsTo('app\\api\\model\\v1\\User', 'creator_user_id');
    }

    public function orderDetails()
    {
        return $this->hasMany('app\\api\\model\\v1\\OrderDetail');
    }
}