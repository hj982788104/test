<?php
namespace app\controller;
use think\Db;

class Money extends A
{
	// 用户充值
	public function recharge(){
		$arr = $this->needs(input('post.'),['token','money','pay_method']);
		//trace($arr);
		$user=Db::name("User","money,use_money,song_money")->find($arr['user_id']);
		
		if($arr['money']){
			if($arr['pay_method']==1){//pay_method： 1微信 2支付宝
				$arr['title']='充值';//.(chongzhi_song($arr['money'])==0 ? '' : '送'.chongzhi_song($arr['money']));
				$arr['add_time']=time();
				$arr['client']=0;
				$arr['name']=$user['user_name'];
				$arr['phone']=$user['phone'];
				$arr['ip']=$this->request->ip();
				$arr['order_no'] = 'C'.order_num();
				$arr['balance'] = ($user['money']+$user['song_money']-$user['use_money']);
				$arr['amount']=$arr['money'];//$arr['money']
				$arr['song_money']=chongzhi_song($arr['money'])==0 ? '' : chongzhi_song($arr['money']);
				/* if(in_array($arr['user_id'],[4,161,33])){
					$arr['amount']=0.01;
				} */
				
				if(!Db::name("UserMoney")->insert($arr)){
					$str = array('code'=>'NO','msg'=>'充值失败');
				}
				
				$pay = new \app\controller\clas\Pay();
				if(isset($arr['type']) && $arr['type']==2){
					$str=$pay->buyH5($arr);
				}else{
					$str = $pay -> buy($arr);
				}
			}else{
				$str = array('code'=>'NO','msg'=>'支付宝未开通，敬请期待');
			}
		}else{
			$this->addLog('充值金额有误: '.$arr['money']);
			$str = array('code'=>'NO','msg'=>'充值金额有误');
		}
	
		$this->setValue($str);
	}

	
	
	//我的账单
	public function moneyBills(){
		$arr = $this->needs(input('post.'),['token']);
		//Db::name("UserMoney")->where("title='打印'")->delete();
		$w='status=1 AND user_id='.$arr['user_id'];
		$count=Db::name('UserMoney')->where($w)->count();
		if($count){
			$str['page'] = paging($count,input('page',1),config('PAGE_NUM'));
			$str['list']=Db::name('UserMoney')->field("money,title,add_time,balance")->where($w)->limit($str['page']['limit'])->order('add_time desc')->select();
		}else{
			$str=array("code"=>"NO","msg"=>"暂无账单明细记录");
		}
		$str['sss']=config('WEIXIN0')["NOTIFY_URL"];
		$str['pay'] = new \app\controller\clas\Pay();
		$this->setValue($str);
	}
	
	//微信支付成功
	public function webhook(){
		$this->addLog('【接收到的notify通知】');
		vendor('WeiXin.WeiXinPay');
		//使用通用通知接口
		$notify = new \Notify_pub();

		//存储微信的回调
		//$xml = $GLOBALS['HTTP_RAW_POST_DATA'];//这里在php7下不能获取数据，使用 php://input 代替  	
		$xml = file_get_contents("php://input");	
		$notify->saveData($xml);
		//$this->addLog('【接收到的notify通知】'.$xml);
		trace($notify->data);
		trace('做调试：1111');
		//验证签名，并回应微信。
		if($notify->checkSign() == FALSE){
			$notify->setReturnParameter("return_code","FAIL");//返回状态码
			$notify->setReturnParameter("return_msg","签名失败");//返回信息
			
			$returnXml = $notify->returnXml();
			$this->addLog($returnXml);
			echo $returnXml;
		}else{
			if($notify->checkSign() == TRUE){
				if ($notify->data["return_code"] == "FAIL") {
					$this->addLog('【通信出错】'.$xml);
				}elseif($notify->data["result_code"] == "FAIL"){
					$this->addLog('【业务出错】'.$xml);
				}else{
					//trace('【成功】'.$notify->data["out_trade_no"]);
					$pay = new \app\controller\clas\Pay();
					trace($notify->data);
					$pay->paySuccess($notify->data);
					
				}
			}
			$notify->setReturnParameter("return_code","SUCCESS");//设置返回码
			echo 'success';
			exit();
		}
	}
} 
