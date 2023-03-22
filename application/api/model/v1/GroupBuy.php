<?php
namespace app\api\model\v1;

use think\Model;

class GroupBuy extends Model
{
    // protected $json = ['product_short_name'];
    // protected $jsonAssoc = true;

    public function groupBuyUser()
    {
        return $this->belongsTo('app\\api\\model\\v1\\User', 'user_id')->bind(['user' => 'name']);
    }

    public function orderDetails()
    {
        return $this->hasMany('app\\api\\model\\v1\\OrderDetail');
    }
}