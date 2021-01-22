<?php

namespace app\index\controller;

use app\common\controller\Frontend;
use app\common\library\Token;
use EasyWeChat\Foundation\Application;
use Overtrue\Socialite\FactoryInterface;

class Index
{

    protected $noNeedLogin = '*';
    protected $noNeedRight = '*';
    protected $layout = '';

    public function index()
    {
	$this->redirect('/index.html');
		/*$config = [
			'debug'  => false,
			'app_id'  => 'wx581ac1620490998c',         // AppID
			'secret'  => '5c940f017284548a3d4327048046aeb3',     // AppSecret
			'token'   => 'chenrj123',          // Token
			'aes_key' => 'rIzk8EgIGS4gtNFOFw324eaiPXME2PiHM5yl9yVCoFv',                    // EncodingAESKey，安全模式与兼容模式下请一定要填写！！！
			'oauth' => [
				'scopes'   => ['snsapi_userinfo'],
				'callback' => '/index/index/callback',
			],
			// ..
		];
		$app = new Application($config);
		$oauth = $app->oauth;
		if (empty(session('wechat_user'))) {
			session('target_url','/index/index/index');
			return $oauth->redirect();
			// 这里不一定是return，如果你的框架action不是返回内容的话你就得使用
			// $oauth->redirect()->send();
		}

		// 已经登录过
		$user = session('wechat_user');
		*/
		return $this->view->fetch();
    }

	public function callback()
	{
		$config = [
			'debug'  => false,
			/**
			 * 账号基本信息，请从微信公众平台/开放平台获取
			 */
			'app_id'  => 'wx581ac1620490998c',         // AppID
			'secret'  => '5c940f017284548a3d4327048046aeb3',     // AppSecret
			'token'   => 'chenrj123',          // Token
			'aes_key' => 'rIzk8EgIGS4gtNFOFw324eaiPXME2PiHM5yl9yVCoFv',                    // EncodingAESKey，安全模式与兼容模式下请一定要填写！！！
		];
		$app = new Application($config);
		$oauth = $app->oauth;
		// 获取 OAuth 授权结果用户信息
		$user = $oauth->user();
		session('wechat_user',$user->toArray());
		header('location:'. '/index/index/index/token/'.$user['original']['openid']); // 跳转到 user/profile
		print_r($user);die;
		$targetUrl = empty(session('target_url')) ? '/' : session('target_url');
		header('location:'. $targetUrl); // 跳转到 user/profile
    }

    public function news()
    {
        $newslist = [];
        return jsonp(['newslist' => $newslist, 'new' => count($newslist), 'url' => 'https://www.fastadmin.net?ref=news']);
    }

}
