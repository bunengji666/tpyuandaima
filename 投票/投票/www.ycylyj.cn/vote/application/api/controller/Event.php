<?php

namespace app\api\controller;

use app\api\logic\EventLogic;
use app\common\controller\Api;
use EasyWeChat\Foundation\Application;
use EasyWeChat\Payment\Order;
use think\Db;
use think\Log;
use think\Request;
use tp5redis\Redis;

/**
 * 活动接口
 */
class Event extends Api
{

	protected  static  $pageNum=10;
	//如果$noNeedLogin为空表示所有接口都需要登录才能请求
	//如果$noNeedRight为空表示所有接口都需要验证权限才能请求
	//如果接口已经设置无需登录,那也就无需鉴权了
	//
	// 无需登录的接口,*表示全部
	protected $noNeedLogin = ['test', 'test1','get_info','get_works_list','get_works_info'];
	// 无需鉴权的接口,*表示全部
	protected $noNeedRight = ['test2'];
    //protected  $userId = date('Y-m-d H:i',time());
	/**
	 * 获取活动列表
	 *
	 * @ApiTitle    (获取活动列表)
	 * @ApiSummary  (获取活动列表)
	 * @ApiMethod   (GET)
	 * @ApiRoute    (/api/event/get_list/page/{page})
	 * @ApiHeaders  (name=token, type=string, required=true, description="请求的Token")
	 * @ApiParams   (name="page", type="string", required=true, description="页数")
	 * @ApiReturnParams   (name="code", type="integer", required=true, sample="0")
	 * @ApiReturnParams   (name="msg", type="string", required=true, sample="返回成功")
	 * @ApiReturnParams   (name="data", type="object", data="", description="扩展数据返回")
	 * @ApiReturn   ({'code':'1','msg':'返回成功'})
	 */
	public function get_list(Request $request)
	{
		$param = $request->param();
      	Redis::hSet('request_list',time().rand(1,10000),var_export($param,true));
		$param['page'] = isset($param['page'])?intval($param['page'])<=0?1:intval($param['page']):1;
		$pageStart = ($param['page']-1)*self::$pageNum;
		$list = Db::name('vote')->limit($pageStart,self::$pageNum)->order('id desc')->select();
      	//echo Db::name('vote')->getlastSql();die;
		$this->success('获取成功',$list);
	}

	/**
	 * 获取活动详情
	 *
	 * @ApiTitle    (获取活动详情)
	 * @ApiSummary  (获取活动详情)
	 * @ApiMethod   (GET)
	 * @ApiRoute    (/api/event/get_info/id/{id})
	 * @ApiHeaders  (name=token, type=string, required=true, description="请求的Token")
	 * @ApiParams   (name="id", type="integer", required=true, description="活动id")
	 * @ApiReturnParams   (name="code", type="integer", required=true, sample="0")
	 * @ApiReturnParams   (name="msg", type="string", required=true, sample="返回成功")
	 * @ApiReturnParams   (name="data", type="object", data="", description="扩展数据返回")
	 * @ApiReturn   ({'code':'1','msg':'返回成功'})
	 */
	public function get_info(Request $request)
	{

		$param = $request->param();
		Redis::hSet('get_info',time().rand(1,10000),var_export($param,true));
		if(!isset($param['id']) || empty($param['id'])){
			$this->error('该活动不存在');
		}

		$num = Db::name('works')->where('vote_id',$param['id'])->count();
		Db::name('vote')->where('id',$param['id'])->update(['join_num'=>$num]);
		Db::name('vote')->where('id',$param['id'])->setInc('look_num',3);
		$info = Db::name('vote')->where('id',$param['id'])->find();

		if(empty($info)){
			$this->error('该投票活动不存在');
		}

        if(($info['end_time'])<=time()){
            $this->error('投票活动已经结束');
        }


		$vote_num = Db::name('works')->where(['vote_id'=>$param['id']])->sum('vote_num');
		Db::name('vote')->where(['id'=>$param['id']])->update(['vote_num'=>$vote_num]);
		//$info['vote_num'] = intval(Redis::hGet('vote_count_num',$info['id']));
		$info['vote_num'] = $vote_num;


		$banner = Db::name('banner')->where('vote_id',$param['id'])->field('images')->find();
		$banner = explode(",",$banner['images']);
		$banners = [];
		foreach ($banner as $key => $val){
			$banners[$key]['image']=$val;
		}
		$info['banner'] = $banners;

        $info['share_title'] = $info['name'];
        $info['share_desc'] = $info['name'];
        $info['share_image'] = $info['banner'][0]['image'];
		$info['notice_text'] = '活动开始投票时间为：'.date("Y年m月d日 H时i分",$info['start_time']).'开始，'.date("Y年m月d日 H时i分",$info['end_time']).'结束，每人每天可以投3票，分别投给不同的3个学员。人人有奖，希望大家踊跃参与哦！';
		$info['desc'] = htmlspecialchars_decode($info['content']);
		$this->success('获取成功',$info);
	}


    public function add_works(Request $request)
    {
        try{
            $param = $request->param();
            $rule = [
                //'title'  => 'require',
                'vote_id'  => 'require',
                'images'   => 'require',
                //'content' => 'require',
                //'mobile' => 'require',
                //'user_intro' => 'require',
                'user_name' => 'require',
            ];
            $msg = [
                //'title.require' => '标题不能为空',
                'vote_id.require' => '活动id必填',
                'images.require'     => '封面图片不能为空',
                //'content.require'   => '作品介绍不能为空',
                //'mobile.require' => '手机号码不能为空',
                //'user_intro.require'  => '用户介绍不能为空',
                'user_name.require'  => '参选者名称不能为空',
            ];
			Redis::hIncrBy('count_vote_id',$param['vote_id'],1);
			$param['sort_id'] = Redis::hGet('count_vote_id',$param['vote_id']);
            $info = Db::name('works')->where(['vote_id'=>$param['vote_id'],'wx'=>$this->userInfo['wx_id']])->find();
            if($info){
                $this->error('当前活动每人只能参加一次哦');
            }
            $validate = new \think\Validate($rule, $msg);
            $result   = $validate->check($param);
            if(!$result){
                $this->error($validate->getError());
            }
            $param['wx'] = $this->userInfo['wx_id'];
            $param['user_image'] = $this->userInfo['avatar'];
            $param['createtime'] = time();
            $param['updatetime'] = time();
            $result = Db::name('works')->insertGetId($param);
            if(!$result){
                $this->error('系统异常 请稍后重试');
            }
            Redis::zinCry('rank'.$param['vote_id'],0,$result);
            $this->success('添加成功');
        }catch (\Error $e){
            $this->error('系统异常 请稍后重试');
        }

	}





	/**
	 * 获取活动案例列表
	 *
	 * @ApiTitle    (获取活动案例列表)
	 * @ApiSummary  (获取活动案例列表)
	 * @ApiMethod   (GET)
	 * @ApiRoute    (/api/event/get_works_list/id/{id}/name/{name}/page/{page})
	 * @ApiHeaders  (name=token, type=string, required=true, description="请求的Token")
	 * @ApiParams   (name="id", type="integer", required=true, description="活动id")
	 * @ApiParams   (name="name", type="string", required=true, description="用户名")
	 * @ApiParams   (name="page", type="string", required=true, description="页数")
	 * @ApiReturnParams   (name="code", type="integer", required=true, sample="0")
	 * @ApiReturnParams   (name="msg", type="string", required=true, sample="返回成功")
	 * @ApiReturnParams   (name="data", type="object", data="", description="扩展数据返回")
	 * @ApiReturn   ({'code':'1','msg':'返回成功'})
	 */
	public function get_works_list(Request $request)
	{
		$param = $request->param();
		Redis::hSet('get_works_list',time().rand(1,10000),var_export($param,true));
		if(!isset($param['id']) || empty($param['id'])){
			$this->error('该活动不存在');
		}

		$info = Db::name('vote')->where('id',$param['id'])->find();
		if(($info['end_time'])<=time()){
			$this->error('投票活动已经结束');
		}


		$param['page'] = isset($param['page'])?intval($param['page'])<=0?1:intval($param['page']):1;
		$pageStart = ($param['page']-1)*self::$pageNum;

		$where = ['vote_id'=>$param['id']];
		if(isset($param['name'])){
            $where['title|sort_id|user_name'] = ['like','%'.$param['name'].'%'];
        }

		$list = Db::name('works')->where($where)->limit($pageStart,self::$pageNum)->select();
		foreach ($list as $key => $val){
//			if($val['sort_id']<=0){
//				$last_info =  Db::name('works')->where(['vote_id'=>$param['vote_id']])->order('id desc')->find();
//				$last_info['sort_id'] = isset($last_info['sort_id'])?intval($last_info['sort_id']):0;
//				$sort_id = intval($last_info['sort_id'])+1;
//				Db::name('works')->where(['vote_id'=>$param['vote_id']])->update('sort_id',$sort_id);
//			}
			//Redis::zAdd('rank'.$val['vote_id'],1,$param['id']);
			$images = explode(",",$val['images']);
            $list[$key]['images'] = $images[0];
            $list[$key]['image_list'] = explode(",",$val['images']);
			//$list[$key]['vote_num'] = intval(Redis::hGet('vote_count_num'.$val['vote_id'],$val['id']));
		}
		$this->success('获取成功',$list);
	}

	/**
	 * 获取活动案例详情
	 *
	 * @ApiTitle    (获取活动案例详情)
	 * @ApiSummary  (获取活动案例详情)
	 * @ApiMethod   (GET)
	 * @ApiRoute    (/api/event/get_works_info/id/{id})
	 * @ApiHeaders  (name=token, type=string, required=true, description="请求的Token")
	 * @ApiParams   (name="id", type="integer", required=true, description="活动id")
	 * @ApiReturnParams   (name="code", type="integer", required=true, sample="0")
	 * @ApiReturnParams   (name="msg", type="string", required=true, sample="返回成功")
	 * @ApiReturnParams   (name="data", type="object", data="", description="扩展数据返回")
	 * @ApiReturn   ({'code':'1','msg':'返回成功'})
	 */
	public function get_works_info(Request $request)
	{
		$param = $request->param();
		Redis::hSet('get_works_info',time().rand(1,10000),var_export($param,true));
		if(!isset($param['id']) || empty($param['id'])){
			$this->error('该作品不存在');
		}
		Db::name('works')->where('id',$param['id'])->setInc('hot_num');
		$info = Db::name('works')->where('id',$param['id'])->find();
		
		if(empty($info)){
			$this->error('该作品不存在');
		}
        $user_id = $this->userInfo['id'];
		//$rank_num = Redis::zrevrank('rank'.$info['vote_id'],$info['id']);
        //$info['rank'] = ($rank_num+1);
		$vote = Db::name('vote')->where('id',$info['vote_id'])->find();
		if(($vote['end_time'])<=time()){
			$this->error('投票活动已经结束');
		}


		$rank = Db::query("SELECT b.* FROM(
								SELECT t.*, @rownum := @rownum + 1 AS rownum
								FROM (SELECT @rownum := 0) r,
								(SELECT * FROM v_works where vote_id=".$info['vote_id']." ORDER BY vote_num DESC,id desc) AS t
		  ) AS b WHERE b.id =".$info['id']);

		$info['rank'] = $rank[0]['rownum'];
        $day_num = Redis::hGet('vote_day_num'.$info['vote_id'],$info['id'].'_'.$user_id.'_'.date("Y-m-d"));
        $info['is_vote'] = $day_num>=1?1:0;

		$info['event'] = Db::name('vote')->where('id',$info['vote_id'])->find();
		$info['event']['notice_text'] = '活动开始投票时间为：'.date("Y年m月d日 H时i分",$info['event']['start_time']).'开始，'.date("Y年m月d日 H时i分",$info['event']['end_time']).'结束，每人每天可以投3票，分别投给不同的3个学员。人人有奖，希望大家踊跃参与哦！';
		$info['share_title'] = '我是'.$info['user_name'].',正在参加'.$info['event']['name'];
        $info['share_desc'] = $info['event']['name'];
        $images = explode(",",$info['images']);
        $info['share_image'] = $images[0];

		$info['user_image'] = $images[0];
        $info['images'] = $images;
		$this->success('获取成功',$info);
	}


	public function test(Request $request)
	{


//		Redis::hIncrBy('count_vote_id','chernj001',1);
//		$num = Redis::hGet('count_vote_id','chernj001');
//		echo $num;die;

		$id = $request->param('id');

		$list = Db::name('works')->where(['vote_id'=>$id])->order('id asc')->select();
		echo "<pre>";
		foreach ($list as $key => $val){
			Redis::hIncrBy('count_vote_id',$id,1);
			echo $key;
			Db::name('works')->where('id',$val['id'])->update(['sort_id'=>$key+1]);
		}
		$list = Db::name('works')->where('vote_id',$id)->order('id asc')->select();
		print_r($list);
		echo "</pre>";
		die;
		$info['vote_id'] = 7;
		$info['id'] = 28;
		$rank = Db::query("SELECT b.* FROM(
								SELECT t.*, @rownum := @rownum + 1 AS rownum
								FROM (SELECT @rownum := 0) r,
								(SELECT * FROM v_works where vote_id=".$info['vote_id']." ORDER BY vote_num DESC) AS t
		  ) AS b WHERE b.id =".$info['id']);
		print_r($rank);
	}

    /**
     * 排行榜
     * @param Request $request
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
	public function rank(Request $request)
	{
		$param = $request->param();
		Redis::hSet('rank',time().rand(1,10000),var_export($param,true));
		if(!isset($param['vote_id'])){
			$this->error('该活动不存在');
		}

        $param['page'] = isset($param['page'])?intval($param['page'])<=0?1:intval($param['page']):1;
        $pageStart = ($param['page']-1)*self::$pageNum;

        $where = ['vote_id'=>$param['vote_id']];
        $list = Db::name('works')->where($where)->limit($pageStart,self::$pageNum)->order('vote_num desc,id desc')->select();
        $data = [];
        foreach ($list as $key => $val){
			$images = explode(",",$val['images']);
            $info = $val;
			$info['user_image'] = $images[0];
            $info['rank'] = $pageStart+$key+1;
            $info['last_num'] = $this->getPreNum($val['vote_id'], $val['vote_num']);
            $data[]= $info;
        }//
		$this->success('获取成功',$data);
	}

    /**
     * 获取排名
     * @param $vote_id
     * @param $works_id
     * @return int
     */
    public function getRank($vote_id,$works_id)
    {


//		$num_count = Db::name('works')->where(['vote_id'=>$vote_id,'vote_num'=>['gt',$num]])->count();
//		return $num_count+1;
		$rank =  Redis::zrevrank('rank'.$vote_id,$works_id);
        if($rank>=0){
            return $rank+1;
        }else{
            return 999;
        }
	}

    /**
     * 获取距离上家排名的票数
     * @param $vote_id
     * @param $works_id
     * @return float|int
     */
    public function getLastNum($vote_id,$works_id)
    {

    	//Db::name('work')->where()->order()->find();
        $rank =  Redis::zrevrank('rank'.$vote_id,$works_id);
        if($rank==0){return 0;}

        if($rank>0){
            $list = Redis::zRevRange('rank'.$vote_id, $rank-1,$rank);
            return Redis::zScore('rank'.$vote_id,$list[0])-Redis::zScore('rank'.$vote_id,$list[1]);
        }else{
            return 999;
        }

	}

	public function getPreNum($vote_id,$num)
	{
		$info = Db::name('works')->where(['vote_id'=>$vote_id,'vote_num'=>['gt',$num]])->order('vote_num asc')->find();
		if(empty($info)){
			return 0;
		}
		return $info['vote_num']-$num;

	}

    /**
     * 投票
     * @param Request $request
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
	public function vote(Request $request)
	{
//		if(!strpos($_SERVER['HTTP_REFERER'],'http://www.rainbowz.com/vote.php/Vote/index.html')){
//			$this->success('投票成功');
//		}
        //防止ip
        $ip_count = Redis::hGet('user_ip',$request->ip().'_'.date('Y-m-d'));
        if($ip_count>100){
			$this->success('投票成功');
        }
        Redis::hIncrBy('user_ip',$request->ip().'_'.date('Y-m-d'),1);

        $param = $request->param();
		if(!isset($param['vote_id']) || empty($param['works_id'])){
			$this->error('该作品不存在');
		}
		$vote = Db::name('vote')->where('id',$param['vote_id'])->find();
		if(($vote['end_time'])<=time()){
			$this->error('投票活动已经结束');
		}

        $works = Db::name('works')->where(['id'=>$param['works_id'],'vote_id'=>$param['vote_id']])->find();
		if(empty($works)){
            $this->error('该作品不存在');
        }

		$user_id = $this->userInfo['id'];
		$day_num = Redis::hGet('vote_day_num'.$param['vote_id'],$param['works_id'].'_'.$user_id.'_'.date("Y-m-d"));
		if($day_num>0){
			$this->error('已经投票 请勿重复投票');
		}
        $day_vote_num = Redis::hGet('day_vote_num',$param['vote_id'].'_'.$user_id.'_'.date("Y-m-d"));
        // if($day_vote_num>=3){
        //     $this->error('每人每天只能投票3次,请明天再来哦');
        // }
		$logic = new EventLogic();
		$logic->add_vote($this->userInfo['id'],$param['works_id'],$param['vote_id']);
		$this->success('投票成功');
	}

    /**
     * 微信支付
     * @param Request $request
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
	public function pay(Request $request)
	{
		$param = $request->param();
		if(empty($param['amount']) ||empty($param['works_id'])){
			$this->error('参数异常');
		}
		$logic = new EventLogic();
		if(!in_array($param['amount'],array_keys($logic->AmountByNum))){
			$this->error('价格填写错误');
		}

		$works = Db::name('works')->find($param['works_id']);
		$info = Db::name('vote')->find($works['vote_id']);
		if($info['start_time']>time()){
			$this->error('投票活动还没有开始');
		}
		if($info['end_time']<time()){
			$this->error('投票活动已经结束');
		}

		//保留两位小数
		$amount = intval($param['amount'])*100;
		$userInfo = $this->userInfo;
		$app = new Application(Weixin::$config);
		$payment = $app->payment;
		$order_num = Redis::hGet('h_order',date("Ymd",time()));
		Redis::hIncrBy('h_order',date("Ymd",time()),1);
		$order_num = 10000000+intval($order_num);
		$orderSn = 'app'.date("Ymd",time()).$order_num;
		$attributes = [
			'trade_type'       => 'JSAPI', // JSAPI，NATIVE，APP...
			'body'             => '充值'.$param['amount'].'元,兑换票数',
			'detail'           => '充值'.$param['amount'].'元,兑换票数',
			'out_trade_no'     => $orderSn,
			'total_fee'        => $amount, // 单位：分
			'notify_url'       => 'http://www.ssjunjun.com/api/Weixin/pay_callback', // 支付结果通知网址，如果不设置则会使用配置里的默认地址
			'openid'           => $userInfo['wx_id'], // trade_type=JSAPI，此参数必传，用户在商户appid下的唯一标识，
			// ...
		];
		$info = Db::name('works')->where('id',$param['works_id'])->find();
		$order = new Order($attributes);
		$result = $payment->prepare($order);
		$prepayId = '';
		$attributes['createtime'] = time();
		$attributes['total_fee'] = $param['amount'];
		$attributes['user_id'] = $this->userInfo['id'];
		$attributes['works_id'] = $info['id'];
		$attributes['vote_id'] =  $info['vote_id'];
		Db::name('log_pay')->insert($attributes);
		if ($result->return_code == 'SUCCESS' && $result->result_code == 'SUCCESS'){
			$prepayId = $result->prepay_id;
		}
		if(empty($prepayId)){
			Log::error($result->err_code_des);
			//Log::error(var_export(Weixin::$config,true));
			$this->error($result->err_code_des);
		}

		$config = $payment->configForJSSDKPayment($prepayId); // 返回数组
		//Log::info(var_export($config.true));
		$this->success('获取成功',$config);
	}

    public function complaint(Request $request)
    {
		$param = $request->only(['type','description']);
		$param['wx_id']= $this->userInfo['wx_id'];
		Db::name('complaint')->insert($param);
		$this->success('添加成功');
    }
}
