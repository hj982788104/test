<?php
namespace app\controller;
use think\Db;

class Pay extends A
{
	//微信支付成功
	public function webhook(){
		vendor('WeiXin.WeiXinPay');
		//使用通用通知接口
		$notify = new \Notify_pub();

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
					$pay = new \app\app\controller\clas\Pay();
					$pay->paySuccess($notify->data);
				}
			}
		
			$notify->setReturnParameter("return_code","SUCCESS");//设置返回码
			echo 'success';
			exit();
		}
	}

}
