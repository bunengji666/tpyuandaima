<?php

namespace app\admin\model;

use think\Model;


class LogPay extends Model
{

    

    //数据库
    protected $connection = 'database';
    // 表名
    protected $name = 'log_pay';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'paidtime_text'
    ];
    

    



    public function getPaidtimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['paidtime']) ? $data['paidtime'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }

    protected function setPaidtimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }


}
