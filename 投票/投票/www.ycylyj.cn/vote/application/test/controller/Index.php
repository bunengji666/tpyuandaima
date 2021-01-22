<?php

namespace app\test\controller;

use app\api\controller\Weixin;
use app\common\library\token\driver\Redis;
use EasyWeChat\Foundation\Application;
use EasyWeChat\Payment\Order;
use think\Controller;
use think\Db;
use think\Request;

class Index extends Controller
{

	protected $userInfo = "";
    public function index(Request $request)
    {
		$param = $request->param();

//		if(empty($param['amount']) || empty($param['vote_id']) ||empty($param['works_id'])){
//			return $this->error('参数异常');
//
//		}
		$app = new Application(Weixin::$config);

		$js = $app->js;

		//保留两位小数
		$amount = intval($param['amount']*100);
		$token = '32f97fdc6f0f899bc3dc8326bde9e6978cc8fdbc';
		$user = \tp5redis\Redis::hGet('h_user_list', $token);
		$this->userInfo = json_decode($user,true);
		$app = new Application(Weixin::$config);
		$payment = $app->payment;

		$order_num = \tp5redis\Redis::hGet('h_order',date("Ymd",time()));
		$order_num = 10000000+intval($order_num);
		$orderSn = 'app'.date("Ymd",time()).time();
		$attributes = [
			'trade_type'       => 'JSAPI', // JSAPI，NATIVE，APP...
			'body'             => '钻石充值'.$param['amount'],
			'detail'           => '钻石充值'.$param['amount'],
			'out_trade_no'     => $orderSn,
			'total_fee'        => 1, // 单位：分
			'notify_url'       => 'http://www.ssjunjun.com/api/Weixin/pay_callback', // 支付结果通知网址，如果不设置则会使用配置里的默认地址
			'openid'           => $this->userInfo['wx_id'], // trade_type=JSAPI，此参数必传，用户在商户appid下的唯一标识，
			// ...
		];

		$order = new Order($attributes);
		$result = $payment->prepare($order);
		$prepayId = '';
		$attributes['createtime'] = time();
		$attributes['user_id'] = $this->userInfo['id'];
		$attributes['works_id'] = $param['works_id'];
		$attributes['vote_id'] = $param['vote_id'];
		Db::name('log_pay')->insert($attributes);
		if ($result->return_code == 'SUCCESS' && $result->result_code == 'SUCCESS'){
			$prepayId = $result->prepay_id;
		}
		if(empty($prepayId)){
			//$this->error($result->err_code_des);
			die($result->err_code_des) ;
		}
		//Redis::hIncrBy('h_order',date("Ymd",time()),1);
		$config = $payment->configForJSSDKPayment($prepayId); // 返回数组
		$this->assign('config',$config);
		$this->assign('js',$js);
		return $this->fetch();
    }
}
