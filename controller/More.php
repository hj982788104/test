<?php
namespace app\controller;
use think\Db;

class More extends A
{
	// 我的
	public function index(){
		$arr = $this->needs(input('post.'),['token']);

		$user = Db::name('User')->field('user_id,user_name,name,sex,phone,user_img,money,song_money,open_id,state,use_money,message,car_num')->find($arr['user_id']);
		
		if(!$user){
			$user = $this->addLog('网络超时，请稍后再试');
		}
		$user['order_num']=0;
		if(!empty($user['message'])){
			$ss= json_decode($user['message'],true);
			if(isset($ss['O1'])){
				$user['order_num'] =$ss['O1'];
			}
		}
		
		
		
		$user['HTTP_PATH']=HTTP_PATH;
		$this->setValue($user);
	}

	//修改个人资料
	public function update(){
		$arr = $this->needs(input('post.'),['token']);
		
		if($arr['user_id']){
			Db::name('User')->where('user_id',$arr['user_id'])->update($arr);

			$arr = array('code'=>'YES','msg'=>'修改成功');
		}

		$this->setValue($arr);
	}
	
	// 评论或意见反馈
	// @param $type  0对系统的反馈
    public function feedback(){
  		$arr = $this->needs(input('post.'),['token','message']);
		$arr["add_time"] = time();

  		if(Db::name('UserMessage')->insert($arr)){
  			$str = '非常感谢您提供宝贵的意见!';
  		}else{
  			$str = '网络超时，请稍后再试';
  		}

  		$this->setValue($str);
    }
	
	// 我的消息
	public function message(){
		$arr = $this->needs(input('post.'),['token']);
		
        $where ='user_id='.$arr['user_id'];	//系统消息
		$count = Db::name('UserNews')->where($where)->count();
		
		if($count){
			$vo['page'] = paging($count,input('page',1),config('PAGE_NUM'));
			$vo['list'] = Db::name('UserNews')->limit($vo['page']['limit'])->order('id DESC')->field('id,title,content,add_time,reading,url,type')->where($where)->select();
		}else{
			$vo = array('code'=>'NO','msg'=>'暂无消息');
		}
		
		/*
		//测试
		if($arr['user_id']==315){
			$config = config('WEIXIN');
			$wx = new \WeiXin($config['APPID'],$config['SECRET'],$config['TOKEN']);
			$wx->sendTxt('o2RIVwAyv9d3NKoXxKXBDmpS0E0M','亲，欢迎您注册成为集行通的会员。用了集行通，取单一分钟。',1);	
		}
		*/
		$this->setValue($vo);
	}
	
	//我的优惠券
	public function coupons(){
		$arr = $this->needs(input('post.'),['token']);
		
		$w = 'UserCoupon.user_id='.$arr['user_id'];
    		
		if(isset($arr['price']) && $arr['price']!=""){
			//有传price是订单生成时可选的优惠券
			$now = time();
			$w .=" AND UserCoupon.user_time is NULL AND SystemCoupon.reach_money <= ".$arr['price'].' AND SystemCoupon.start_time <'.$now.' AND SystemCoupon.end_time >'.$now;
		}else{
			//显示没过期和已过期七天内的优惠券
			$w .= ' AND SystemCoupon.end_time>'.(time()-(7*24*360));
		}
		$count=Db::view('UserCoupon','user_id')->view('SystemCoupon','money','SystemCoupon.coupon_id=UserCoupon.coupon_id','LEFT')->where($w)->count();
		if($count){
			$str['page'] = paging($count,input('page',1),config('PAGE_NUM'));
			$str['list']=Db::view('UserCoupon','user_id,user_time,add_time,id,is_read')->view('SystemCoupon','money,reach_money,name,start_time,end_time','SystemCoupon.coupon_id=UserCoupon.coupon_id','LEFT')->where($w)->limit($str['page']['limit'])->order('UserCoupon.add_time desc')->select();
			Db::name('UserCoupon')->where('user_id='.$arr['user_id'].' AND is_read=0')->setField('is_read', 1);
		}else{
			$str['list']=array("code"=>"NO","msg"=>"暂无优惠券");
		}
    	
    	$this->setValue($str);
	}
	
	
	//用户指南
	public function UserArticle(){
		$str['info']=Db::name("SystemArticle")->where("tag_id=29 AND status=1")->select();
		if(!$str['info']){
			$str['info']=array("code"=>"NO","暂无数据");
		}
    	
    	$this->setValue($str);
	}
	
} 
