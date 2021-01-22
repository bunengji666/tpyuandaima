<?php
namespace app\api\logic;

use think\Db;
use tp5redis\Redis;

class EventLogic{
	public $AmountByNum = [
		2=>10,
		5=>28,
		10=>58,
		50=>288,
		100=>588,
		200=>1288,
	];

	public function add_vote($user_id,$works_id,$vote_id,$amount=0)
	{
		$score = 1;
		if($amount>0){
			if(isset($this->AmountByNum[$amount])){
				$score = $this->AmountByNum[$amount];
			}else{
				$score = $amount*5;
			}
		}
		$add = [
			'event_id'		=> $vote_id,
			'event_works_id'=> $works_id,
			'user_id'		=> $user_id,
			'createtime'	=> date("Y-m-d H:i:s",time()),
			'updatetime'	=> date("Y-m-d H:i:s",time()),
			'score'			=> $score,
		];

		Db::name('works_user')->insert($add);
		if($amount<=0){
			//记录用户投票记录
			Redis::hSet('vote_'.$vote_id,$works_id.'_'.$user_id,json_encode($add));
			//记录用户作品按天投票的次数
			Redis::hIncrBy('vote_day_num'.$vote_id,$works_id.'_'.$user_id.'_'.date("Y-m-d"),$score);
			//记录用户活动按天投票的次数
			Redis::hIncrBy('day_vote_num',$vote_id.'_'.$user_id.'_'.date("Y-m-d"),$score);
		}else{//统计支付相关
			//记录用户投票记录
			Redis::hSet('vote_pay_'.$vote_id,$works_id.'_'.$user_id,json_encode($add));
			//记录用户作品按天投票的次数
			Redis::hIncrBy('vote_day_pay_num'.$vote_id,$works_id.'_'.$user_id.'_'.date("Y-m-d"),$score);
			//记录用户活动按天投票的次数
			Redis::hIncrBy('day_vote_pay_num',$vote_id.'_'.$user_id.'_'.date("Y-m-d"),$score);
		}

		//记录活动投票总数
		Redis::hIncrBy('vote_count_num',$vote_id,$score);
		//记录活动作品的数量
		Redis::hIncrBy('vote_count_num'.$vote_id,$works_id,$score);
		//投票排行榜
		//Redis::zinCry('rank'.$vote_id,1,$works_id);
		Db::name('works')->where(['id'=>$works_id])->setInc('vote_num',$score);
		Db::name('vote')->where(['id'=>$vote_id])->setInc('vote_num',$score);
		Redis::hDel('vote_works','works_'.$works_id);
		Redis::hDel('vote','vote_'.$vote_id);
		return true;
	}

}