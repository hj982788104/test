<?php
namespace app\controller;
use think\Controller;
use think\Db;
use think\Cache;
use think\cache\driver\Redis;

class A extends Controller
{
    protected function _initialize() {
		config(['cache'=>['type'=>'File']]);

		//跨域设置
		access_allow(config('allowOrigin'));
		
		debug('begin');//访问开始时间
		//开启调试模式时则触发日志记录
		$is_debug = input('param.log',false) || config('app_debug');
		
		if(strpos(config('DEBUG_IP'),$this->request->ip()) !== false){
			$is_debug = true;
		}
		if($is_debug){
			trace(var_export($this->request->param(), true), 'param');
			is_debug($is_debug);
		}
		
		if (config('CLOSE')==='0') {	//系统被关闭
			$this->setValue(config('CAUSE'),'10000');
        //}else if($this->request->controller()!='Pay' || $this->request->controller()!='Printer'){
        }else if(!in_array($this->request->controller(), array('Pay','Printer','Test','TaskApi','Money'))){
			/* if(!$is_debug && !$this->request->isMobile() && !input('t')){
				//echo 'PC端制作中...';exit;
				$this->setValue('请在微信中打开...','10000');
			} */
		}
		
		//判断同一IP是不是在指定时间段内一直被调用，可以禁止掉
		if(strpos(config('STOP_IP'),$this->request->ip()) !== false){
			$this->setValue('操作存在异常','10000');
		}
		
		//接口在指定的时间段一直被调用可视为被功击，需要禁止掉
		if(config("is_cache")==true){
			$this->api=$this->apiLog();
		}
		

		//临时接口监听
		/*
		$api= $this->request->ip().$this->request->module().'/'.$this->request->controller().'/'.$this->request->action();
	
		if($api== $this->request->ip().$this->request->module()."/Within/smsVerify"){
			$log=Cache::get($api);
			$num=array_reverse($log);
			if(isset($num)){
				if($num[0]['add_time']+300>time()){
					trace($num);
					$this->setValue(array("code"=>"NO","msg"=>"访问频繁"));
				}
				if(date('Y-m-d')!=date("Y-m-d",$num['0']['add_time'])){
					Cache::rm($api); 
				}
				//$data=array("add_time"=>time(),'api'=>$api);
				$log[]=array("add_time"=>time(),'api'=>$api);
				Cache::set($api,$log);
				
			}
		}
		*/
	
    }
	
	protected function apiLog() {
		$api= $this->request->ip().'/'.$this->request->controller().'/'.$this->request->action();
		$jz_time=$this->request->ip().'#'.$this->request->controller().'/'.$this->request->action();
		if(!in_array($this->request->action(), config("immune_api"))){//部分接口不做处理
			$jz=Cache::get($jz_time);
			if($jz>time()){
				$this->setValue(array("code"=>"NO","msg"=>"你已被禁止访问该页面了"));
			}
			$log=Cache::get($api);
			$num=0;
			if($log){
				foreach($log as $k=>$v){
					if($v-time() < 5){
						$num=$num+1;
					}
				}
			}
			if($num>3){
				Cache::set($jz_time,time()+(config("pause_time")));
				Cache::rm($api); 
			}else{
				$log[]=time();
				Cache::set($api , $log);
			}
			$this->forbiddenTime=Cache::get($api);
		}
    }

	//缓存接口访问信息(次数，时间)
	protected function saveApiLog() {
		$api= $this->request->controller().'/'.$this->request->action();
		
		$str=Cache::tag('tag')->get($api);
		if(!in_array($this->request->action(), config("immune_api"))){//部分接口不做处理
			debug('end');
			if($str!==false){
				$t=round($str['use_time']+debug('begin','end','4'),4);
				
				$data=array(
					'action'=>$api,
					'num'=>$str['num']+1,
					'add_time'=>date("Ymd"),
					'use_time'=>$t/2
				);
			}else{
				$data=array(
					'action'=>$api,
					'num'=>1,
					'add_time'=>date("Ymd"),
					'use_time'=>debug('begin','end','4')
				);

				$action=Cache::get('action');
				if($action){
					$da=$action;
				}
				$da[]=$api;
				Cache::set('action',array_unique($da));
				Cache::get('action');
			}
			
			Cache::tag('tag')->set($api,$data);
			
			return Cache::tag('tag')->get($api);
		}
	}

	protected function setValue($list='',$status='YES') {
		if(empty($list)){
			$list = $this->addLog('暂无数据');
		}else if(is_string($list)){
			$list = array("code"=>$status,'msg'=>$list);
		}
		$list['formV'] = hy_token('ok','CODE',300);	//表单令牌验证,页面停留时间5分钟内有效
		
		if(config("is_cache")==true){
			$this->saveApiLog();
		}
        if (input('callback')) {
            echo $_GET['callback'] . "(" . json_encode($list) . ")";
        } else {
			echo json_encode($list);	
        }
       
        exit();
    }

	protected function isEmpty($str='') {
		if(empty($str)){
			return array("code"=>'NO','msg'=>'暂无数据');
		}else{
			return $str;
		}
	}
	
	protected function toArray($str='') {
		if(empty($str)){
			return array("code"=>'NO','list'=>'暂无数据');
		}else{
			return array("code"=>'YES','list'=>$str);
		}
	}
	
	//日志记录
	protected function addLog($str,$saveSql=true){
		config(['log'=>['type'=>'File']]);
		$arr = array(
			'ip'	=> $this->request->ip(),
			'add_time'=> time(),
			'action'=> $this->request->controller().'/'.$this->request->action(),
			'title' => $str,
			'message' => json_encode(input('')),
		);
		if($saveSql){
			Db::name('LogUser')->insert($arr);
		}
		trace($str.'('.$arr['ip'].')::'.$arr['message']);
		return array('code'=>'NO','msg'=>'系统繁忙请稍后再试');
	}
	
	//判断必传的接口参数是否传了
	//is_cut:是否中断操作
	protected function needs($data='',$isset='',$is_cut=true){
		unset($data['scene']);
		$str = '';
		if(isset($data['user_id'])){
			unset($data['user_id']);
			$str = '请不要使用系统保留关键词';
		}else{
			foreach($isset as $value){
				if($value=='formV'){
					if(hy_token($data['formV']) == 'ok'){
						unset($data['formV']);
					}else{
						$this->setValue(array('code' => 10101, 'msg' => '非法请求'));
					}
				}else if($value=='token'){
					if(isset($data['token'])){
						$data['user_id'] = hy_token($data['token'],'DECODE');
						if(!$data['user_id'] && $is_cut){
							$this->addLog('token失效或非法登录:'.$data['token']);
							$this->setValue(array('code' => 10100, 'msg' => 'token已失效'));
						}
						unset($data['token']);
					}else{
						$str = '缺少token';
					}
				}else if(!array_key_exists($value,$data)){
					$str .= '缺参数:'.$value.' ';
				}else{
					$data[$value] = is_string($data["$value"]) ? trim($data["$value"]) : $data["$value"];
				}
			}
			if(isset($data['log'])){
				unset($data['log']);
			}
		}
		
		if(!empty($str)){
			$this->setValue($this->addLog($str));
		}else{
			return $data;
		}
	}
	
	//“注册成功”后和“支付成功”后执行 array('user_id'=>'','o_id'=>'')
	protected function addMessage($arr){
		if(isset($arr['user_id'])){
			$user = Db::name('User')->field('user_id,message')->where('user_id='.$arr['user_id'])->find();
			$order_start = array();
			if(!empty($user['message'])){
				$order_start = json_decode($user['message'],true);
			}

			//注册成功
			/*if(isset($arr['logon'])){
				//送优惠券
				
			}*/
			if($arr['send_num']=='true'){//派单数量
				$order_start['O1']=0;
				$order_start['s0']=1;
			}
			if(!empty($order_start)){
				Db::name('User')->where('user_id',$arr['user_id'])->setField('message', json_encode($order_start));
			}
		}
	}
	
	
	/*
	消息推送
	type         print_code：派单  feedback：取消派单   logon：注册 password：改密码
	channel      1:微信+短信; 2:微信; 3:短信
	*/
	protected function sendMessage($arr = array('content'=>'','open_id'=>'','phone'=>''),$type='',$channel=1){
		$msg = '';
		$wx_msg = '';
		$title = '';
		switch($type){
			case 'print_code':
				$wx_msg=$msg = '集行通已收到您的反馈，您可重新扫描二维码打印您的箱单，此次打印免费';
				break;
			case 'logon':
				$msg='您的动态验证码为:'.$arr['code'].' 请在页面输入完成验证。如非本人操作请忽略。';
				break;
		}

		if($channel==3){
			$str['dx']=sendSMS($arr['phone'],$msg);
		}else{
			if($wx_msg){
				$config = config('WEIXIN');
				$wx = new \WeiXin($config['APPID'],$config['SECRET'],$config['TOKEN']);
				$url = U_M."More_printListing_lg";
				$str['wx']=$wx->sendTemplate($arr['open_id'],$wx_msg,$url,$title);
			}
			if($channel==1){
				$str['dx']=sendSMS($arr['phone'],$msg);
			}
		}

		return $str;
	}

	function needCache($cache_name='',$fun,$arr='',$arr2=''){
		config('cache',array('type'=>'File'));	//切换缓存模式
		//Cache::clear(); 
		$rs = Cache::get($cache_name);
		if(!$rs){
			$rs = $fun($arr,$arr2);
			Cache::set($cache_name,$rs);
		}
		
		return $rs;
	}

	//通过ip地址获取所在城市
	protected function getCity() {
        //调用淘宝接口获取信息
        $data = file_get_contents("http://ip.taobao.com/service/getIpInfo.php?ip=" . $this->request->ip());
        $city = json_decode($data, true);
        if (is_array($city)) {
            $city = $city['data']['city'];
        } else {
            $city = '上海'; //默认为上海
        }
		//Db::name('SystemCity')->field('id,name')->where('name like "%' . str_replace("市", "", $city) . '%"')->find();
		return $city;
    }
	
	
}