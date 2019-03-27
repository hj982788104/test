<?php
namespace app\controller;
use think\Db;

class PayPingxx extends A
{

	public function buy($arr) {
		$arr = array_merge (array('title'=>'标题','body'=>'宝贝描述','order_no'=>time(),'amount'=>1), $arr);
		import('pingxx/init', EXTEND_PATH);
		$pay_config = config('ping');

		//支付渠道类型
		$channel = strtolower($arr['channel']);
		$this->addLog('支付参数：'.json_encode($arr));

		//$extra 在使用某些渠道的时候，需要填入相应的参数，其它渠道则是 array() .具体见以下代码或者官网中的文档。其他渠道时可以传空值也可以不传。
		switch ($channel) {
			case 'alipay_wap':
				$extra = array(
					'success_url' =>$_SERVER['HTTP_HOST'].url('Pay/webhook'),
					'cancel_url' => $_SERVER['HTTP_HOST'].url('Pay/cancel')
				);
				break;
			case 'wx_pub':
				$extra = array( 'open_id' => $arr['open_id'] ); break;
			case 'wx_pub_qr':
				$extra = array( 'product_id' => 'Productid' ); break;
			/*case 'upmp_wap':		//银联(upacp)
				$extra = array( result_url' => 'http://www.yourdomain.com/result?code=' ); break;
			case 'upacp_wap':		//银联(wap)
				$extra = array( 'result_url' => 'http://www.yourdomain.com/result' ); break;*/
			default:
				$extra = array(); break;
		}
		
		//设置app_key,参考文档 https://pingxx.com/document/api#api-c-new 创建 Charge 对象
		\Pingpp\Pingpp::setApiKey($pay_config['key']);	//正式
		try {
			$ch = \Pingpp\Charge::create(
				array(
					'subject' => $arr['title'],	//宝贝的标题，该参数最长为 32 个 Unicode 字符，银联全渠道（upacp/upacp_wap）限制在 32 个字节。
					'body' => $arr['body'],		//宝贝的描述信息，该参数最长为 128 个 Unicode 字符，yeepay_wap 对于该参数长度限制为 100 个 Unicode 字符。
					'amount' => $arr['amount']*100,		//订单总金额, 单位为对应币种的最小货币单位，例如：人民币为分（如订单总金额为 1 元，此处请填 100）。
					'order_no' => $arr['order_no'],
					'currency' => 'cny',		//三位 ISO 货币代码，目前仅支持人民币 cny。
					'extra' => $extra,			//特定渠道发起交易时需要的额外参数以及部分渠道支付成功返回的额外参数。
					'channel' => $channel,		//支付使用的第三方支付渠道
					'client_ip' => $_SERVER['REMOTE_ADDR'],	//发起支付请求终端的 IP 地址，格式为 IPV4，如: 127.0.0.1。
					'app' => array('id' => $pay_config['app_id'])	//支付使用的 app 对象的 id
				)
			);
			return $ch;
		} catch (\Pingpp\Error\Base $e) {
			$this->addLog('异常信息：'.$e->getMessage());
			header('Status: ' . $e->getHttpStatus());
			//echo($e->getHttpBody());
		}
    }
		
	//转账提现,最小１元
	public function tiXuan($arr){
		if($arr['money'] && $arr['open_id'] && $arr['order']){
			import('pingxx/init', EXTEND_PATH);
			
			//$this->addLog('转账参数：'.json_encode($arr));
			$pay_config = config('ping');
			\Pingpp\Pingpp::setApiKey($pay_config['key']);	//正式
			try {
				$ch = \Pingpp\Transfer::create(
					array(
						'order_no'    => $arr['order'],
						'app'         => array('id' => $pay_config['app_id']),
						'channel'     => 'wx_pub',
						'amount'      => $arr['money']*100,	//订单总金额, 单位为对应币种的最小货币单位，例如：人民币为分（如订单总金额为 1 元，此处请填 100）。
						'currency'    => 'cny',
						'type'        => 'b2c',
						'recipient'   => $arr['open_id'],
						'description' => '转账'
					)
				);
				$this->addLog('转账返回：'.$ch);
				return 'succeeded';
			} catch (\Pingpp\Error\Base $e) {
				$this->addLog('转账异常信息：'.$e->getMessage());
				header('Status: ' . $e->getHttpStatus());
				//$this->addLog('转账异常返回：'.$e->getHttpBody());
				return $e->getMessage();
			}
		}else{
			$this->addLog('转账参数有误：'.json_encode($arr));
		}
	}
	
	public function textPay(){
		trace($_POST);
	}
	
	//微信红包
	/*
	public function honBao(){
		import('pingxx/init', EXTEND_PATH);
		$input_data = json_decode(file_get_contents('php://input'), true);
		$this->addLog('红包参数：'.json_encode($input_data));
		$pay_config = config('ping');
		\Pingpp\Pingpp::setApiKey($pay_config['key']);	//正式
		try {
			$ch = \Pingpp\RedEnvelope::create(
				array(
					'order_no'  => time(),
					'app'       => array('id' => $pay_config['app_id']),
					'channel'   => 'wx_pub',	//红包基于微信公众帐号，所以渠道是 wx_pub
					'amount'    => 100,			//订单总金额, 单位为对应币种的最小货币单位，例如：人民币为分（如订单总金额为 1 元，此处请填 100）。
					'currency'  => 'cny',
					'subject'   => '红包',		//暂没发现在哪里会显示
					'body'      => '恭喜您获得环亿的大红包',
					'extra'     => array(
						'nick_name' => 'Nick Name',		//暂没发现在哪里会显示
						'send_name' => '上海环亿'
					),
					'recipient'   => $input_data['open_id'],
					'description' => '微信红包'
				)
			);
			echo $ch;
		} catch (\Pingpp\Error\Base $e) {
			$this->addLog('异常信息：'.$e->getMessage());
			header('Status: ' . $e->getHttpStatus());
			echo($e->getHttpBody());
		}
	}

    public function cancel(){
		echo '支付失败!!!';
       //$this->display('cancel');
    }
	*/
	
	//异步回调
	public function webhook(){  
		$event = json_decode(file_get_contents("php://input"));

		$this->addLog('webhook：'.json_encode($event));
		// 对异步通知做处理
		if (!isset($event->type)) {
			header($_SERVER['SERVER_PROTOCOL'] . ' 400 Bad Request');
			exit("fail");
		}
		switch ($event->type) {
			case "charge.succeeded":
				// 支付成功,如同一订单分多次付款可以添加订单号前缀来判断属于第几次付款
				$get_order_id = explode('X',$event->data->object->order_no);
				$order_list = '';
				$now = time();
				$channel = $event->data->object->channel=='wx_pub' ? 1 : 2;	//1:微信  2:支付宝
				$data = array('ip'=>$event->data->object->client_ip,'pay_status'=>1,'pay_time'=>$now,'pay_method'=>$channel,'charge_id'=>$event->data->object->id);
				//更新订单状态
				if(isset($get_order_id[1]) && $get_order_id[1]!='0'){
					$order = Db::name('GoodsOrder')->field('o_id,order_id,money,pay_status,order_status,shipping_status,tel,next_state')->where('o_id='.$get_order_id[1])->find();
					if($order){
						$order_list = $this->updateOrder($get_order_id[1],$order['next_state'],$data);
					}
				}else{
					$orderAll = Db::name('GoodsOrder')->field('o_id,order_id,money,pay_status,order_status,shipping_status,tel,next_state')->where('order_id="'.$get_order_id[0].'"')->select();
					if($orderAll){
						$order = $orderAll[0];
						if(count($orderAll)==1){
							$order_list = $this->updateOrder($order['o_id'],$order['next_state'],$data);
						}else if(count($orderAll)>1){
							foreach($orderAll as $value){
								Db::name('GoodsOrder')->where('o_id='.$value['o_id'])->update(array_merge($data,json_decode($value['next_state'])));
								$o_id[] = $value['o_id'];
							}
							$order_list = Db::name('OrderList')->field('id,goods_id,goods_name,attr_name,first_money,end_money,img,attr_id,num')->where('o_id in ('.join(',',$o_id).')')->select();
						}
					}
				}
				
				//减少库存并添加售出记录
				if($order_list){
					$o_id = array();
					foreach($order_list as $v){
						if($v['attr_id']){
							if(strpos(',', $v['attr_id'])=== false){
								$attrId = explode(',',$v['attr_id']);
								foreach($attrId as $value){
									Db::name('GoodsAttr')->where('attr_id =' . $value)->setInc('attr_selled', $v['num']);
								}
							}else{
								Db::name('GoodsAttr')->where('attr_id =' . $v['attr_id'])->setInc('attr_selled', $v['num']);
							}
						}
						Db::name('Goods')->where('goods_id =' . $v['goods_id'])->setInc('selled', $v['num']);
						
						//订单状态日志
						if(!array_key_exists($v['o_id'], $o_id)){
							$o_id[$v['o_id']] = $v['o_id'];
							Db::name('OrderLog')->insert(array('o_id'=>$v['o_id'],'action_user'=>'用户','order_status'=>0,'shipping_status'=>0,'pay_status'=>1,'log_time'=>$now));
						}
					}
				}else{
					$order['tel'] = '00000(测试)';		//测试时
				}

				//进行优惠券及短信或邮件通过等其它操作
				sendSMS(config('ORDER_TEL'), '手机号为：'.$order['tel'].'的用户成功支付了:'.($event->data->object->amount/100).'元,订单号：'.$event->data->object->order_no,2);
				//Log::write('订单号:'.$event->data->object->order_no.' 成功支付了:'.$order['first_money']);
				
				header($_SERVER['SERVER_PROTOCOL'] . ' 200 OK');
				break;
			case "transfer.succeeded":
				//提现成功
				Log::write('订单号:'.$event->data->object->order_no.' 提现了:'.$event->data->object->amount);
				header($_SERVER['SERVER_PROTOCOL'] . ' 200 OK');
				break;
			case "refund.succeeded":
				// 退款成功
				$get_order_id = explode('X',$event->data->object->charge_order_no);
				
				$order = Db::name('GoodsOrder')->where('o_id='.$get_order_id[1])->find();
				if($order){
					$data = orderSate(10,array('o_id'=>$get_order_id[1]));
					Db::name('GoodsOrder')->update($data);
					trace('webhook：退款');
					//添加消息
					$this->addMessage(array('user_id'=>$order['user_id'],'o_id'=>$get_order_id[1]));
				}else{
					trace('webhook：退款订单不存在');
				}
				header($_SERVER['SERVER_PROTOCOL'] . ' 200 OK');
				break;
			default:
				header($_SERVER['SERVER_PROTOCOL'] . ' 400 Bad Request');
				break;
		}
	}
	
	//更新订单状态
	protected function updateOrder($o_id,$state,$data){
		$data = array_merge($data,json_decode($state,true));

		Db::name('GoodsOrder')->where('o_id='.$o_id)->update($data);
		
		return Db::name('OrderList')->field('id,o_id,goods_id,goods_name,attr_name,img,attr_id,num')->where('o_id='.$o_id)->select();
	}

}
