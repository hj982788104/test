<?php
namespace app\controller\clas;
use think\Request;
use think\Db;

class Pay
{
	//支付
	public function buy($arr) {
		$arr = array_merge (array('title'=>'标题','body'=>'宝贝描述','order_no'=>time(),'amount'=>100,'channel'=>'wx_pub'), $arr);

		if($arr['channel']=='wx_pub'){
			vendor('WeiXin.WeiXinPay');
			
			//使用jsapi接口
			$jsApi = new \JsApi_pub();
			//使用统一支付接口
			$unifiedOrder = new \UnifiedOrder_pub();
			//设置必填参数
			$unifiedOrder->setParameter("openid",$arr['open_id']);
			$unifiedOrder->setParameter("body",$arr['title']);//商品描述
			$unifiedOrder->setParameter("out_trade_no",$arr['order_no']);//商户订单号
			$unifiedOrder->setParameter("total_fee",$arr['amount']*100);//总金额
			$unifiedOrder->setParameter("trade_type","JSAPI");//交易类型
			//非必填参数，商户可根据实际情况选填
			//$unifiedOrder->setParameter("sub_mch_id","XXXX");//子商户号
			//$unifiedOrder->setParameter("device_info","XXXX");//设备号
			//$unifiedOrder->setParameter("attach","XXXX");//附加数据
			//$unifiedOrder->setParameter("time_start","XXXX");//交易起始时间
			//$unifiedOrder->setParameter("time_expire","XXXX");//交易结束时间
			//$unifiedOrder->setParameter("goods_tag","XXXX");//商品标记
			//$unifiedOrder->setParameter("product_id","XXXX");//商品ID
			//设置统一支付接口参数
			/*
			$prepay_id = $unifiedOrder->getPrepayId();
			$jsApi->setPrepayId($prepay_id);
			$jsApiParameters = $jsApi->getParameters();
			*/
			$data = $unifiedOrder->getPrepayId();
			$jsApi->setPrepayId($data);
			$jsApiParameters = $jsApi->getParameters();
			if($jsApiParameters['paySign']){
				trace($jsApiParameters);
				return $jsApiParameters;
			}else{
				trace($jsApiParameters);
			}
		}
    }
	
	//支付
	public function buyH5($arr) {
		$arr = array_merge (array('title'=>'标题','body'=>'宝贝描述','order_no'=>time(),'amount'=>0.01,'channel'=>'wx_pub'), $arr);
		trace($arr);
		if($arr['channel']=='wx_pub'){
			vendor('WeiXin.WeiXinPay');
			
			//使用jsapi接口
			$jsApi = new \JsApi_pub();
			
			//trace(json_decode($jsApi,true));
			//使用统一支付接口
			$unifiedOrder = new \UnifiedOrder_pub();
			//设置必填参数
			$unifiedOrder->setParameter("spbill_create_ip",$this->getClientIp());
			$unifiedOrder->setParameter("body",$arr['title']);//商品描述
			$unifiedOrder->setParameter("out_trade_no",$arr['order_no']);//商户订单号
			$unifiedOrder->setParameter("total_fee",$arr['amount']*100);//总金额
			$unifiedOrder->setParameter("trade_type","MWEB");//交易类型
			$unifiedOrder->setParameter("scene_info",'{"h5_info": {"type":"Android","wap_url": "wx.ijx56.com/TEST.php/app/Test/webhook","wap_name": "集行充值"} }');//场景信息
			
			
			//设置统一支付接口参数
			$data=$unifiedOrder->getPrepayId();
			$jsApi->setPrepayId($data);
			$jsApiParameters = $jsApi->getParameters();
			trace($data);
			if($jsApiParameters['paySign']){
				return $jsApiParameters;
			}else{
				trace($jsApiParameters);
			}
		}
    }
	
	//获取ip
	function getClientIp(){
		$cip="unknown";
		if($_SERVER['REMOTE_ADDR']){
			$cip=$_SERVER['REMOTE_ADDR'];
		}else if(getenv("REMOTE_ADDR")){
			$cip=getenv("REMOTE_ADDR");
		}
		return $cip;
	}
	
	
	//支付成功
	public function paySuccess($arr,$channel=1){	//1:微信  2:支付宝
		//$this->addLog('【成功】'.$arr["out_trade_no"]);out_trade_no
		
		$data = array('status'=>1,'channel'=>$channel,'transaction_id'=>$arr['transaction_id']);
		if(strstr($arr["out_trade_no"],'C')){
			$this->user=$user=Db::view("UserMoney","user_id,money,status")->view("User","open_id,tel,use_money,money as yue_money,song_money","User.user_id=UserMoney.user_id")->where("UserMoney.order_no='".$arr["out_trade_no"]."'")->find();
			trace(Db::name("UserMoney")->getLastSql());
			trace($user);
			$this->order_no=$arr['out_trade_no'];
			if(!strstr("FK",$this->order_no)){
				$this->ccc=$data;
				if($user['status']==1){
					trace("支付成功");
				}else{
					Db::transaction(function(){
						$str[1]=Db::name("UserMoney")->where("order_no='".$this->order_no."'")->update($this->ccc);
						$str[2]=Db::name("User")->where("user_id=".$this->user['user_id'])->setInc("money",$this->user['money']);
						trace($str);
					});
					
					$this->songMoney($user);
					//$config = config('WEIXIN');
					//$wx = new \WeiXin($config['APPID'],$config['SECRET'],$config['TOKEN']);
					//$content='亲，您已成功充值'.$user['money'].'元，具体金额以及充值记录可在明细中查询。';
					//$str['wx'] = $wx->sendTemplate($user['open_id'],$content);
					//$str['dx'] = sendSMS($user['tel'],$content,2);
					//return 1;
				}
			}
		}else{
			trace("NO");
		}
	}
	
	
	//充值送money
	public function songMoney($arr){
		if($arr['money']==50){
			$money=10;
		}else if($arr['money']==100){
			$money=30;
		}else{
			$money=0;
		}
		if($money>0){
			if(Db::name("User")->where("user_id=".$arr['user_id'])->setInc("song_money",$money)){
				return 'OK';
			}else{
				return "NO";
			}
			//Db::name("UserMoney")->insert();
		}
	}
	
}
