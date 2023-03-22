<?php
namespace app\common\model;

use think\Model;

class GroupBuy extends Model
{
    public function groupBuyUser()
    {
        return $this->belongsTo('app\\common\\model\\User', 'user_id')->bind(['user' => 'name']);
    }
}