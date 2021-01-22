<?php

namespace app\api\controller;

use app\common\controller\Api;
use think\captcha\Captcha;

/**
 * 首页接口
 */
class Index 
{
    protected $noNeedLogin = ['*'];
    protected $noNeedRight = ['*'];

    /**
     * 首页
     *
     */
    public function index()
    {
        $this->success('请求成功');
    }

    /**
     * 创建验证码
     */
    public function createCode()
    {
        $captcha = new Captcha();
        return  $captcha->entry();
    }

    /**
     * 校验验证码
     */
    public function check_verify($code)
    {
        $captcha = new Captcha();
        if($captcha->check($code)){
       		$result =  true;
        }else {
       		$result =  false;
        }
        return json(['code'=>200,'msg'=>'success','data'=>$result]);
    }
}
