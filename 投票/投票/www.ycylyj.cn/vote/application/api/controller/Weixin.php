<?php
namespace app\api\controller;

use app\api\logic\EventLogic;
use app\common\model\Config;
use EasyWeChat\Foundation\Application;
use think\Db;
use think\Log;
use think\Request;
use tp5redis\Redis;

class Weixin{

	public static $config = [
		'debug'  => false,
		//www 3178654423@qq.com       13579wangfajun
//		'app_id'  => 'wx6a0eb033efd229c0',         // AppID
//		'secret'  => '2cbd96c288d7726a530965907e79f882',     // AppSecret


		//test1
		//test1 2948471960@qq.com  13579wangfajun
//		'app_id'  => 'wxe27d25bc3f84aab9',         // AppID
//		'secret'  => '1c99f54802c76aa09eb687d432d12ece',     // AppSecret

		//test 730543330@qq.com      13579wangfajun
		'app_id'  => 'wxa6cbea2e7840e13d',         // AppID
		'secret'  => 'd47e44a5b255ccc8f740df473b7bf03b',     // AppSecret

		//test 21406074229@qq.com 13579wangfajun
//		'app_id'  => 'wx54b9eb7dcfbf53d2',         // AppID
//		'secret'  => 'ea534cc120e2dd484f21cd12dcb3b008',     // AppSecret

		'token'   => 'chenrj123',          // Token
		'aes_key' => 'oU6TT8MrVKho9g4FGBpXFHuE6iuV501V0m5QUg1eH9a',                    // EncodingAESKey，安全模式与兼容模式下请一定要填写！！！
		'oauth' => [
			'scopes'   => ['snsapi_userinfo'],
			'callback' => '/index/index/callback',
		],
		'payment' => [
			'merchant_id'        => '1534006331',
			'key'                => '158D7A04427ED33BCB3DAFB7A2779101',
			'cert_path'          => '/wwwroot/vote/apiclient_cert.pem', // XXX: 绝对路径！！！！
			'key_path'           => '/wwwroot/vote/apiclient_key.pem',      // XXX: 绝对路径！！！！
			'notify_url'         => 'http://test1.ssjunjun.com/api/Weixin/pay_callback',       // 你也可以在下单时单独设置来想覆盖它
		],
	];

	public function callback()
	{
		$app = new Application(self::$config);
		$oauth = $app->oauth;
		// 获取 OAuth 授权结果用户信息
		$user = $oauth->user();
        $info = Db::name('vote_user')->where('wx_id',$user['original']['openid'])->find();
        if(empty($info)){
            $add = [
                'wx_id' => $user['original']['openid'],
                'avatar' => $user['avatar'],
                'name' => $user['name'],
                'nickname' => $user['nickname'],
                'wx_content' => json_encode($user),
                'createtime' => time(),
                'updatetime' => time(),
            ];
            Db::name('vote_user')->insert($add);
            $info = Db::name('user')->where('wx_id',$user['original']['openid'])->find();
        }
        $token = sha1($info['name'].$info['wx_id'].$info['updatetime']);
        Redis::set($token,json_encode($info));
        Redis::expire($token,7000);
		header('location:'. '/#/login/token/'.$token); // 跳转到 user/profile
	}

    public function jsSignature(Request $request)
    {
        $url = $request->param('url');
        $url = urldecode($url);
        $data = Redis::get('wx_js_sign'.$url);
        $data = json_decode($data,true);
		$data = '';
        if(empty($data)){
            $app = new Application(self::$config);
            $js = $app->js;
            $js->setUrl($url);
            $data = $js->config(array('updateTimelineShareData','onMenuShareAppMessage','onMenuShareQQ', 'onMenuShareWeibo'), false,false,false);
            $ti = $js->ticket();
            $data['ticket']=$ti;
            Redis::set('wx_js_sign'.$url,json_encode($data));
            Redis::expire('wx_js_sign'.$url,7000);
        }
        $result = [
            'code' => 200,
            'msg'  => '返回成功',
            'data' => $data,
        ];
        die(json_encode($result));
	}

	public function pay_callback()
	{
		$app = new Application(self::$config);
		$response = $app->payment->handleNotify(function($notify, $successful){
			$order = Db::name('log_pay')->where('out_trade_no',$notify->out_trade_no)->find();
			//response
			if (!$order) { // 如果订单不存在
				return 'Order not exist.'; // 告诉微信，我已经处理完了，订单没找到，别再通知我了
			}
			// 如果订单存在
			// 检查订单是否已经更新过支付状态
			if ($order['paidtime']) { // 假设订单字段“支付时间”不为空代表已经支付
				return true; // 已经支付成功了就不再更新了
			}
			// 用户是否支付成功
			if ($successful) {
				// 不是已经支付状态则修改为已经支付状态
				$order['paidtime'] = time(); // 更新支付时间为当前时间
				$order['status'] = '支付成功';
				$logic = new EventLogic();
				$logic->add_vote($order['user_id'],$order['works_id'],$order['vote_id'],$order['total_fee']);
			} else { // 用户支付失败
				$order['status'] = '支付失败';
			}
			$order['response'] = json_encode($notify);
			Db::name('log_pay')->where('out_trade_no',$notify->out_trade_no)->update($order);
			return true; // 返回处理完成
		});
		$response->send();
	}


	public function query()
	{
		$order['status'] = '未支付';
		$order['check_time'] = 0;
		$list = Db::name('log_pay')->where($order)->limit(50)->order('id desc')->select();
		$app = new Application(self::$config);
		foreach ($list as $key => $val){
			$info = $app->payment->query($val['out_trade_no']);
			if('SUCCESS' == $info->trade_state && '未支付' == $val['status']){
				print_r($info);
				print_r($val);

				$order['paidtime'] = time(); // 更新支付时间为当前时间
				$order['check_time'] = time(); // 更新支付时间为当前时间
				$order['status'] = '支付成功';
				$logic = new EventLogic();
				$logic->add_vote($val['user_id'],$val['works_id'],$val['vote_id'],$val['total_fee']);
				Db::name('log_pay')->where('out_trade_no',$info->out_trade_no)->update($order);
			}else{
				Db::name('log_pay')->where('id',$val['id'])->update(['check_time'=>time()]);
			}
		}

		$bill = $app->payment->downloadBill('20190801', 'SUCCESS')->getContents(); // type: SUCCESS
		// bill 为 csv 格式的内容
		// 保存为文件
		file_put_contents('./20190801.csv', $bill);
	}


	public function mini_program_login(Request $request)
	{
		$code = $request->get('code',0);
		$options = [
			// ...
			'mini_program' => [
				'app_id'   => 'wx6038b4888d7900c5',
				'secret'   => '55537d28d579aea1a9a90f74436d2356',
				'token'    => 'chenrj123',
				'aes_key'  => 'oU6TT8MrVKho9g4FGBpXFHuE6iuV501V0m5QUg1eH9a'
			],
			// ...
		];
		$app = new Application($options);
		$miniProgram = $app->mini_program;
		$session = $miniProgram->sns->getSessionKey($code);
		if($session['openid']){
			$info = Db::name('vote_user')->where('wx_id',$session['openid'])->find();
			if(empty($info)){
				$add = [
					'wx_id' => $session['openid'],
					'avatar' => '',
					'name' => '',
					'nickname' => '',
					'wx_content' => json_encode($session),
					'createtime' => time(),
					'updatetime' => time(),
				];
				Db::name('vote_user')->insert($add);
				$info = Db::name('user')->where('wx_id',$session['openid'])->find();
			}
			$token = sha1($info['name'].$info['wx_id'].$info['updatetime']);
			Redis::set($token,json_encode($info));
	        Redis::expire($token,7000);
			$result = [
				'code' => 200,
				'msg'  => '返回成功',
				'data' => ['token'=>$token],
			];
			die(json_encode($result));
		}else{
			$result = [
				'code' => 500,
				'msg'  => '登录失败',
				'data' => null,
			];
			die(json_encode($result));
		}


	}

	public function test()
	{
		$logic = new EventLogic();
		//$logic->add_vote(6,6,5,10);
	}


}