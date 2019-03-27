<?php
namespace app\controller;
use think\Db;

class Within extends A
{
	
	public function smsVerify(){
		
		$arr = $this->needs(input('post.'),['phone']);//,'formV'
		if (!preg_match('/(^0?[1][3475689][0-9]{9}$)/', $arr['phone'])) {
			$str = $this->addLog('手机号填写有误');
		}else{
			$user = Db::name('User')->where('phone="'.$arr['phone'].'"')->find();
			
			if($user && $user['status']==2){
				$str = array('code'=>'NO','msg'=>'该手机号已被锁定');
			}else if(($user && !empty($user['open_id'])) && $arr['open_id']){
				$str = array('code'=>'NO','msg'=>'该账号被'.$user['user_name'].'登录');
			}else{
				$max = 3;  //验证码发送最大次数
				$now = time();
				$sCout = Db::name('LogSms')->where('phone="'.$arr['phone'].'" AND add_time>'.strtotime(date("Y-m-d",$now)).' AND add_time<'.($now+86400).' AND type="logon"')->count();

				if($sCout>=$max){
					$str = $this->addLog('短信获取过与频繁');
				}else{
					//仿止按钮被快速点击
					$info = Db::name('LogSms')->where('phone="'.$arr['phone'].'" AND add_time>'.strtotime(date("Y-m-d",$now)))->order("add_time DESC")->find();	
					if($info){
						if(($info['add_time']+60) >time()){
							$this->setValue(array("code"=>"NO",'msg'=>"短信获取过与频繁"));
						}
					}
					$validate = rand(1000, 9999); //验证码
					
					$res=$this->sendMessage(array("phone"=>$arr['phone'],'code'=>$validate),'logon',3);
					Db::name("LogUser")->insert(array("title"=>"司机注册账号:".$arr['phone'],"message"=>json_encode($res),'action'=>$this->request->module().'/'.$this->request->controller().'/'.$this->request->action(),'ip'=>$this->request->ip(),"add_time"=>time()));
		
					Db::name('LogSms')->insert(array('phone' => $arr['phone'], 'code' => $validate,'add_time'=>$now,'type'=>'logon'));
					if ('1' == $res['dx']) {
						$str = '验证码已发送，请注意查收';
					} else {
						$str = array('code'=>'NO','msg'=>'系统繁忙请稍后再试');
						$this->addLog('检查短信平台接口与短信条数'.$validate);
					}
				} 
			}
			
		}
		
		$this->setValue($str);
	}
	
	//注册 + 登录
    public function logon() {
		$arr = $this->needs(input('post.'),['phone','code']);
        
		if (!preg_match('/(^0?[1][3475689][0-9]{9}$)/', $arr['phone'])) {
			$str = array('code'=>'NO','msg'=>'手机号填写有误');
        } else {
			if(!Db::name('LogSms')->where('phone="'.$arr['phone'].'" and code="'.$arr['code'].'" and add_time >'.strtotime(date('Y-m-d')))->find()){
				if($arr['code']!=(date('md')+5)){
					$this->setValue(array('code'=>'NO','msg'=>'验证码填写错误'));
				}
			}
			//$str = '验证成功';
			$car = Db::name('User')->where('phone="'.trim($arr['phone']).'"')->field('user_id,activate')->find();
			$user['phone'] = $arr['phone'];
			$user['tel'] = $arr['phone'];
		
			//如果没有传头像，则随机选择一个昵称和头像
			if(!input('open_id')){
				//$user['user_name'] = '匿名用户';
				//$user['user_img'] = 'head.jpg';
				//$user['open_id'] = '';
				
			}else{
				$user['user_name'] = removeEmoji($arr['user_name']);
				$user['user_img'] = $arr['user_img'];
				$user['sex'] = $arr['sex'];
				$user['open_id'] = $arr['open_id'];
			}
			if(isset($arr['qr_id'])){
				$user['qr_id'] = $arr['qr_id'];
			}
			
			$user['user_num'] = substr(date('Ymd'),2,6).substr($user['phone'],7,4);
			$user['activate']=1;
			$user['role']=2;
			
			if($car){
				Db::name("User")->where("user_id=".$car['user_id'])->update($user);
				$user['user_id']=$car['user_id'];
			}else{
				$user['source']=1;
				$user['add_time'] = time();
				$user['user_id'] = Db::name('User')->insertGetId($user);
				$this->addMessage(array('user_id'=>$user['user_id'],'logon'=>true,'send_num'=>'true'));
			}
			
			if($user['user_id']){
				$user['token'] = hy_token($user['user_id'],'CODE');
				//$str['dx'] = sendSMS($user['phone'],'亲，欢迎您注册成为集行通的会员。用了集行通，取单一分钟',2);
				$str = $user;
				
			}else{
				$str = array('code'=>'NO','msg'=>"注册失败");
				$this->addLog('注册失败'.Db::name('User')->getLastSql());
			}
        }
		
        $this->setValue($str);
    }
	
	
	//退出登录清open_id
	public function logonOut(){
		$arr = $this->needs(input('post.'),['token']);
		
		Db::name("User")->where("user_id=".$arr['user_id'])->update(array("open_id"=>''));
		$this->setValue(array("code"=>"YES","msg"=>"退出成功"));
	}
	
	//验证是否被锁定,初使化数据
	public function checkUserlocked(){
		$arr = input('post.');
		//$str = array('code'=>'NO','msg'=>'Within/checkUserlocked暂无数据');

		if(input('token')){
			if(isset($arr['user_id'])){
				unset($arr['user_id']);
			}
			$arr['user_id'] = hy_token($arr['token']);
			
			$user = Db::name("User")->where('user_id='.$arr['user_id'])->field('status,user_name,sex,open_id,user_img,debug')->find();
			if($user['status']==1){
				$str['user'] = $user;
			}else{
				$str = array('code'=>'10101','msg'=>'您的账号被锁定');
			}
			
			//初使化数据
			//----------------------->
			
			//<-----------------------
			
			$this->setValue($str);
		}
	}
}