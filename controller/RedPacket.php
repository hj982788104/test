<?php
namespace app\controller;
use think\Db;

class RedPacket extends A
{
	
	/* 
		1：第一次活动 领取5元
	*/
	// 领取红包
	public function Receive(){
		$arr = $this->needs(input('get.'),['phone','time']);
		if (!preg_match('/(^0?[1][3475689][0-9]{9}$)/', $arr['phone'])) {
			$str = array('code'=>'NO','msg'=>'手机号填写有误','status'=>2);
        }else {
			$l_shop = Db::view('ShopUser','su_id,shop_id,type,name')->view('Shop','shop_name,money,song_money,use_money','Shop.shop_id=ShopUser.shop_id','LEFT')->where('ShopUser.phone="'.trim($arr['phone']).'"')->find();
			if(!$l_shop){
				$this->setValue(['code'=>'NO','msg'=>'你还没有注册','status'=>3]);
			}
			
			if(Db::name("ShopRedPacket")->where("l_shop_id=".$l_shop['shop_id']." AND z_time=".strtotime(date("Y-m-d",($arr['time']/1000))))->find()){
				$this->setValue(['code'=>'NO','msg'=>'车队今天已经领取过红包','status'=>4]);
			}
			
			$money=5;$t=time();
			$z_shop = Db::view('ShopUser','shop_id,type')->view('Shop','shop_name','Shop.shop_id=ShopUser.shop_id','LEFT')->where('ShopUser.open_id="'.trim($arr['z_open_id']).'"')->find();
			
			// 启动事务
			Db::startTrans();
			try{
				Db::name("ShopRedPacket")->insert([
					'z_shop_id'=>empty($z_shop['shop_id']) ? '' : $z_shop['shop_id'],
					'z_shop_name'=>empty($z_shop['shop_name']) ? '' : $z_shop['shop_name'],
					'l_shop_id'=>$l_shop['shop_id'],
					'l_shop_name'=>$l_shop['shop_name'],
					'l_open_id'=>$arr['l_open_id'],
					'phone'=>$arr['phone'],
					'status'=>1,
					'money'=>$money,
					'z_time'=>strtotime(date("Y-m-d",($arr['time']/1000))),
					'add_time'=>$t
				]);
				Db::name("Shop")->where("shop_id=".$l_shop['shop_id'])->setInc('song_money',$money);
				Db::name('ShopMoney')->insert(array(
					'shop_id'=>$l_shop['shop_id'],
					'add_time'=>$t,
					'song_money'=>$money,
					'status'=>1,
					'title'=>'红包',
					'shop_name'=>$l_shop['shop_name'],
					'phone'=>$arr['phone'],
					'ip'=>$this->request->ip(),
					'balance'=>($l_shop['money']+$l_shop['song_money']-$l_shop['use_money']),
					'pay_method'=>3,
					'name'=>$l_shop['name'],
					'order_no'=>order_num(),
				));
				Db::name("ShopUser")->where('su_id='.$l_shop['su_id'])->setField("open_id",$arr['l_open_id']);
				// 提交事务
				Db::commit(); 
				$str=['code'=>"YES",'msg'=>'领取成功','status'=>1];
			} catch (\Exception $e) {
				// 回滚事务
				Db::rollback();
				trace('领红包：'.$e->getMessage());
				$str=['code'=>"NO",'msg'=>'系统繁忙','status'=>5];
			}
		}
        $this->setValue($str);
	}

	
	/* 
		1：第二次活动 领取中免年费99VIP
		2：点击量
	*/
	//领取Vip
	public function ReceiveVip(){
		$arr = $this->needs(input('get.'),['phone']);
		if (!preg_match('/(^0?[1][3475689][0-9]{9}$)/', $arr['phone'])) {
			$str = array('code'=>'NO','msg'=>'手机号填写有误','status'=>1);
			$this->setValue($str);
        }else {
			$shop=Db::name("ShopUser")->where("phone=".$arr['phone'])->field("shop_id,su_id")->find();
			if($shop){
				$user = Db::view('ShopUser','shop_id,su_id,name,role_id')->view('Shop','shop_name','Shop.shop_id=ShopUser.shop_id','LEFT')->view("ShopRole","role,role_id","ShopRole.role_id=ShopUser.role_id","LEFT")->where('ShopUser.type=1 AND ShopUser.shop_id='.$shop['shop_id'])->find();
			}
			
			if(!Db::name("ActShopRole")->where("phone=".$arr['phone'])->find()){
				Db::name("ActShopRole")->insert([
					"shop_name"=>isset($user['shop_name']) ? $user['shop_name'] : '',
					"shop_id"=>isset($user['shop_id']) ? $user['shop_id'] : '',
					"phone"=>$arr['phone'],
					"role"=>isset($user['role']) ? $user['role'] : '',
					"add_time"=>time()
				]);
				
			}
			$str=['code'=>"YES","msg"=>"",'status'=>(!empty($user['role']) && $user['role_id']==3) ? 2 : 3];
			trace($str);
			$this->setValue($str);
		}
	}
	
	// vip活动点击量
    public function  ReceiveActSmLq(){
		$arr = $this->needs(input('get.'),['type']);
		if(!Db::name("ActShopRoleLog")->where("add_time=".strtotime(date("Y-m-d")))->find()){
			Db::name("ActShopRoleLog")->insert(['add_time'=>strtotime(date("Y-m-d")),$arr['type']=>1]);
		}else{
			Db::name("ActShopRoleLog")->where("add_time=".strtotime(date("Y-m-d")))->setInc($arr['type'],1);
		}
		
		$this->setValue(['code'=>'YES','msg'=>'成功']);
    }
	
	
	/* 
		1：第三次活动 领取红包10元（一周一次）
		2：通过分享链接进入领取中免年费99VIP  ReceiveVip
	*/
	// 领取10元红包
    public function  TenYuanReceive(){
		$arr = $this->needs(input('get.'),['shop_id']);
		trace($arr);
		$data=date('Y-m-d');
		//dump(strtotime("$data Sunday"));
		$lastday=date('Y-m-d',strtotime("$data Sunday"));
		//$firstday=date('Y-m-d',strtotime("$lastday -6 days"));
		
		$firstday=strtotime("$lastday -6 days");
		
		//dump("add_time >".$firstday.' AND add_time <'.($lastday+86400).' AND l_shop_id='.$arr['shop_id']);exit;
		if(Db::name("ShopRedPacket")->where("add_time >".$firstday.' AND add_time <'.(strtotime($lastday)+86400).' AND l_shop_id='.$arr['shop_id'])->find()){
			$this->setValue(['code'=>'NO','msg'=>'这一周已经领取过了']);
		}else{
			$t=time();$money=10;
			// 启动事务
			Db::startTrans();
			try{
				$shop=Db::name("shop")->field('shop_name,shop_id,phone,use_money,money,song_money')->find($arr['shop_id']);
					trace(['l_shop_id'=>$arr['shop_id'],'add_time'=>$t,'money'=>$money,'phone'=>$shop['phone'],'z_time'=>$t,'l_shop_name'=>$shop['shop_name'],'type'=>2]);
				Db::name("ShopRedPacket")->insert(['l_shop_id'=>$arr['shop_id'],'add_time'=>$t,'money'=>$money,'phone'=>$shop['phone'],'z_time'=>$t,'l_shop_name'=>$shop['shop_name'],'type'=>2]);
				
				Db::name("Shop")->where("shop_id=".$arr['shop_id'])->setInc('song_money',$money);
				Db::name('ShopMoney')->insert(array(
					'shop_id'=>$arr['shop_id'],
					'add_time'=>$t,
					'song_money'=>$money,
					'status'=>1,
					'title'=>'红包',
					'shop_name'=>$shop['shop_name'],
					'phone'=>$shop['phone'],
					'ip'=>$this->request->ip(),
					'balance'=>($shop['money']+$shop['song_money']-$shop['use_money']),
					'pay_method'=>3,
					'order_no'=>order_num(),
				));
			
				// 提交事务
				Db::commit(); 
				$str=['code'=>"YES",'msg'=>'领取成功'];
			} catch (\Exception $e) {
				// 回滚事务
				Db::rollback();
				trace('领红包：'.$e->getMessage());
				$str=['code'=>"NO",'msg'=>'系统繁忙'];
			}
		
			$this->setValue($str);
		}
    }
	
}