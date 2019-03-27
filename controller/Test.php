<?php
namespace app\controller;
use think\Db;

class Test extends A
{
	public function surl(){
		$url = urlencode('http://www.320xx.com/tj/U.php?g=More_printDetail_lg&print_id=1');
		$new_url = json_decode(http_get('http://api.t.sina.com.cn/short_url/shorten.json?source=1876937016&url_long='.$url));
		dump($new_url);
		echo HTTP_PATH;
		
	}
	//支付
	public function test($arr) {
		$arr = array_merge (array('title'=>'标题','body'=>'宝贝描述','order_no'=>time(),'amount'=>0.01,'channel'=>'wx_pub'), $arr);

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
			$unifiedOrder->setParameter("out_trade_no",rand(100000,999999));//商户订单号
			$unifiedOrder->setParameter("total_fee",1);//总金额
			$unifiedOrder->setParameter("trade_type","MWEB");//交易类型
			$unifiedOrder->setParameter("scene_info",'{"h5_info": {"type":"Android","wap_url": "wx.ijx56.com/TEST.php/app/Test/webhook","wap_name": "集行充值"} }');//场景信息
			
			//非必填参数，商户可根据实际情况选填
			//$unifiedOrder->setParameter("sub_mch_id","XXXX");//子商户号
			//$unifiedOrder->setParameter("device_info","XXXX");//设备号
			//$unifiedOrder->setParameter("attach","XXXX");//附加数据
			//$unifiedOrder->setParameter("time_start","XXXX");//交易起始时间
			//$unifiedOrder->setParameter("time_expire","XXXX");//交易结束时间
			//$unifiedOrder->setParameter("goods_tag","XXXX");//商品标记
			//$unifiedOrder->setParameter("product_id","XXXX");//商品ID
			//设置统一支付接口参数
			
			//$prepay_id = $unifiedOrder->getPrepayId();
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
/*
<a class="btn-green" id="getBrandWCPayRequest" href="https://wx.tenpay.com/cgi-bin/mmpayweb-bin/checkmweb?prepay_id=wx20171205173217ef7855a6d80196624197&amp;package=973839565">立即购买</a>
	
	
	*/
	

   

	//微信支付测试
	public function testPay(){
		$arr = $this->needs(input('post.'),['open_id','money','pay_type']);
		$data = $this->test(array('title'=>'充值','amount'=>$arr['money'],'channel'=>$arr['pay_type']));
		
		
		$this->setValue($data);
	}
	
	
	function getClientIp(){
		$cip="unknown";
		if($_SERVER['REMOTE_ADDR']){
			$cip=$_SERVER['REMOTE_ADDR'];
		}else if(getenv("REMOTE_ADDR")){
			$cip=getenv("REMOTE_ADDR");
		}
		return $cip;
	}
	
	
	public function webhook(){
		vendor('WeiXin.WeiXinPay');
		//使用通用通知接口
		$notify = new \Notify_pub();
		trace("1111111111111111111");
		//存储微信的回调
		$xml = $GLOBALS['HTTP_RAW_POST_DATA'];	
		$notify->saveData($xml);
		//$this->addLog('【接收到的notify通知】'.$xml);
		
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
					trace("ssssssssssssssssss");
					trace($notify->data);
				}
			}
		
			$notify->setReturnParameter("return_code","SUCCESS");//设置返回码
			echo 'success';
			exit();
		}
	}
	
	
	
	
	
	
	
	
	
	
	
}