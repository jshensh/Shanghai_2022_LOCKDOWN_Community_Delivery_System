<?php
namespace app\api\model\v1;

use think\Model;

class SmsLog extends Model
{
    public function groupBuy()
    {
        return $this->belongsTo('app\\api\\model\\v1\\GroupBuy')->field(['id', 'title'])->bind(['group_buy_title' => 'title']);
    }

    public function user()
    {
        return $this->belongsTo('app\\api\\model\\v1\\User')->bind(['send_user' => 'name']);
    }
}