<?php
namespace app\controller;
use think\Db;
use \app\controller\clas\Goods;

class GoodsOrder extends A
{
	//直接购买 -> 订单详情
	public function orderDetailGoods(){
		$arr = $this->needs(input('post.'),['token','goods_id']);
		
		$G = new Goods();
		$goods = $G->getGoods($arr);
		
		$str = $this->orderDetail($arr['user_id'],$goods);
		$this->setValue($str);
	}
	
	//购物车购买 -> 订单详情
	public function orderDetailCart(){
		$arr = $this->needs(input('post.'),['token','cart_id']);

		$G = new Goods();
		$goods = $G->getCartGoods('Goods.status =1 AND GoodsCart.cart_id in('.join(',',$arr['cart_id']).')');
		
		$str = $this->orderDetail($arr['user_id'],$goods);
		$this->setValue($str);
	}
	
	protected function orderDetail($user_id,$goods=''){
		if($goods){
			$str['goods'] = $goods;
			//收货信息
			$str['address'] = Db::name('UserAddress')->where('status=1 AND user_id='.$user_id)->find();
			//运费
			//$str['courierfee'] = config('courierfee');
		}else{
			$str = array('code'=>'NO','msg'=>'宝贝不存在');
		}
		
		return $str;
	}

	//生成订单数据到数据库
	public function addOrder(){
		$arr = $this->needs(input('post.'),['token','money','user_name','tel','address']);
		$G = new Goods();
		
		if($arr['cart_id'] && $arr['cart_id']!=0){//购买购物车中宝贝
			$cart_id = join(',',$arr['cart_id']);
			$goods_cart = $G->getCartGoods('Goods.status =1 AND GoodsCart.cart_id in('.$cart_id.')');
		}else if(isset($arr['goods_id'])){	//直接购买
			$goods_cart = $G->getGoods($arr);
		}else{
			$str = $this->addLog('系统错误');
			$this->setValue();
		}

		if(!$goods_cart){
			$str = $this->addLog('您购买的宝贝已下架或库存不足');
		}else{
			$order_id = order_num();	//生成订单号,不同商家的宝贝被同一个订单购买，订单号是相同的
			$time = time();
			$order_list = array();
			
			foreach($goods_cart['shop_name'] as $key=>$value){
				$first_money = 0;
				$end_money = 0;
				unset($order_list_temp);
				
				foreach($goods_cart['goods'] as $v){
					if($key==$v['shop_id']){
						$money = $v['price_attr'] ? $v['price_attr'] : $v['price'];
						$num = $arr['cart_id'] ? $v['num'] : $arr['num'];
						$fp = $this->first_pay($money,$v['first_pay']);
						$first_money += ($fp[0]*$num);
						$end_money += ($fp[1]*$num);
						
						$order_list_temp[] = ['goods_id'=>$v['goods_id'],'shop_id'=>$v['shop_id'],'attr_id'=>$v['attr_id'],'goods_name'=>$v['name'],'attr_name'=>$v['attr_name'],'first_money'=>$fp[0],'end_money'=>$fp[1],'num'=>$num,'img'=>$v['img']];
					}
				}
				
				//添加新订单
				$o_id = Db::name('GoodsOrder')->insertGetId(array(
					'user_name'=>$arr['user_name'],
					'tel'=>$arr['tel'],
					'address'=>$arr['address'],
					'order_id'=>$order_id,
					'user_id'=>$arr['user_id'],
					'shop_id'=>$key,
					'money'=>($first_money+$end_money),
					'money1'=>$first_money,
					'money2'=>$end_money,
					'add_time'=>$time,
					'yun_money'=>$value['courierfee'],			//不同商家发货都应该收取官方指定的运费
					//'yun_money'=>config("courierfee"),			//不同商家发货都应该收取官方指定的运费
					'ip'=>$this->request->ip()
				));
				
				//添加订单明细
				foreach($order_list_temp as $kk=>$w){
					$order_list_temp[$kk]['o_id'] = $o_id;
					//减去相应的库存（Goods/detail对应）
					//$this->dec($v['goods_id'], $v['attr_id'], $num );
				}
				$order_list = array_merge($order_list,$order_list_temp);
			}
			
			if(Db::name('OrderList')->insertAll($order_list)){
				if($arr['cart_id']){
					Db::name('GoodsCart')->where('cart_id in('.$cart_id.')')->delete();		//删除购物车中相应的宝贝
				}
				$str = array('code'=>'YES','msg'=>$order_id);
			}else{
				$str = array('code'=>'NO','msg'=>'添加失败');
				$this->addLog('订单添加失败'.json_encode($arr));
			}

			
			//计算总价
			/*$shopId = array();
			$order_list = array();
			foreach($goods_cart as $value){
				//如果宝贝来源于不同的商家则分成多个订单存储（有利于订单管理）
				$money = $value['price_attr'] ? $value['price_attr'] : $value['price'];
				$num = $arr['cart_id'] ? $value['num'] : $arr['num'];
				$fp = $this->first_pay($money,$value['first_pay']);
				$lib_money = $num*$money;
					
				if(!array_key_exists($value['shop_id'], $shopId)){
					$shopId[$value['shop_id']] = array('money'=>$lib_money, 'money1'=>$fp[0]*$num, 'money2'=>$fp[1]*$num);
				}else{
					$shopId[$value['shop_id']]['money'] += $lib_money;
					$shopId[$value['shop_id']]['money1'] += $fp[0]*$num;
					$shopId[$value['shop_id']]['money2'] += $fp[1]*$num;
				}
				$order_list[] = ['goods_id'=>$value['goods_id'],'shop_id'=>$value['shop_id'],'attr_id'=>$value['attr_id'],'goods_name'=>$value['name'],'attr_name'=>$value['attr_name'],'first_money'=>$fp[0],'end_money'=>$fp[1],'num'=>$num,'img'=>$value['img']];
			}

			$order_id = order_num();	//生成订单号,不同商家的宝贝被同一个订单购买，订单号是相同的
			foreach($shopId as $k=>$w){
				//添加新订单
				$o_id = Db::name('GoodsOrder')->insertGetId(array(
					'user_name'=>$arr['user_name'],
					'tel'=>$arr['tel'],
					'address'=>$arr['address'],
					'order_id'=>$order_id,
					'user_id'=>$arr['user_id'],
					'shop_id'=>$k,
					'money'=>$w['money'],
					'money1'=>$w['money1'],
					'money2'=>$w['money2'],
					'add_time'=>time(),
					'yun_money'=>config("courierfee"),			//不同商家发货都应该收取官方指定的运费
					'ip'=>$this->request->ip()
				));

				//添加订单明细
				foreach($order_list as $key=>$v){
					if($k == $v['shop_id']){
						$order_list[$key]['o_id'] = $o_id;
						//减去相应的库存（Goods/detail对应）
						//$this->dec($v['goods_id'], $v['attr_id'], $num );
					}
				}
			}
			
			if(Db::name('OrderList')->insertAll($order_list)){
				if($arr['cart_id']){
					Db::name('GoodsCart')->where('cart_id in('.$cart_id.')')->delete();		//删除购物车中相应的宝贝
				}
				$str = array('code'=>'YES','msg'=>$order_id);
			}else{
				$str = array('code'=>'NO','msg'=>'添加失败');
				$this->addLog('订单添加失败'.json_encode($arr));
			}*/
		}

		$this->setValue($str);
	}

	//减去数据库中相应的宝贝库存
	protected function dec($goods_id=0,$attr_id=0,$num=0){
		if($num){
			if($attr_id){
				Db::name('GoodsAttr')->where('attr_id in('.$attr_id.')')->setDec('inventory', $num);			
			}
			if($goods_id){
				Db::name('Goods')->where('goods_id', $goods_id)->setDec('total', $num);
			}
		}
	}
	
	/*=0：货到付款
	<1：按比例付款
	=1：全款，一次付清
	>1：指定首付款
	*/
	protected function first_pay($money,$bili){
		$m = 0;
		if($bili==0){
			$m = [0,$money];
		}else if($bili<1){
			$m = [$money*$bili,$money*(1-$bili)];
		}else if($bili>1 && $bili<$money){
			$m = [$bili,$money-$bili];
		}else{
			$m = [$money,0];
		}
		
		return $m;
	}
	
	//购买
	public function buy(){
		$arr = $this->needs(input('post.'),['token','order_id','money']);

		//$goods = Db::name('GoodsOrder')->field(['pay_status','money','yun_money','group_concat(o_id)'=>'o_id'])->where('status=1 AND user_id='.$arr['user_id'].' AND order_id="'.$arr['order_id'].'"')->find();
		$goods = Db::name('GoodsOrder')->field('shop_id,pay_status,money,yun_money,o_id')->where('status=1 AND user_id='.$arr['user_id'].' AND order_id="'.$arr['order_id'].'"')->select();
		if($goods){
			$money = 0;
			$arr['order_no'] = $arr['order_id'].'X0X1';//按订单号支付第一笔费用
			$orderSate = json_encode(orderSate(2));
			$G = new Goods();
			
			foreach($goods as $v){
				$goods_list = Db::name('OrderList')->field(['SUM((first_money+end_money)*num)'=>'total','SUM(first_money*num)'=>'first_total'])->where('o_id ='.$v['o_id'])->find();
				$money += ($goods_list['first_total']+$v['yun_money']);
				$orderArr = array('next_state'=>$orderSate, 'coupon_money'=>0, 'coupon_id'=>0);
				$key = array_keys($arr['shop_id'],$v['shop_id'],true);
				
				//验证优惠券
				if($arr['coupon_id'][$key[0]]){
					$coupon = $G->checkCoupon(array('user_id'=>$arr['user_id'], 'coupon_id'=>$arr['coupon_id'][$key[0]], 'price'=>$goods_list['total']));
					$orderArr['coupon_money'] = $coupon['money'];
					$orderArr['coupon_id'] = $coupon['id'];
					$money -= $coupon['money'];
				}
				Db::name('GoodsOrder')->where('o_id ='.$v['o_id'])->update($orderArr);
			}
				
			$money = number_format($money,2,'.','');
			if($money==$arr['money']){
				//$arr['amount'] = $arr['money'];
				$arr['amount'] = 0.01;	
				/*$pay = new \app\app\controller\Pay();
				echo $pay -> buy($arr);
				exit();
				*/
				$pay = new \app\app\controller\clas\Pay();
				$str = $pay -> buy($arr);
			}else if($money<=0 && $arr['money']=='0.00'){
				$this->addLog('0付款'.$money.'=='.$arr['money']);
				$pay = new \app\app\controller\clas\Pay();
				if($pay->paySuccess(array('out_trade_no'=>$arr['order_no'],'total_fee'=>0))){
					$str = array('code'=>'YES','msg'=>'成功');
				}else{
					$str = array('code'=>'NO','msg'=>'0支付失败');
				}
			}else{
				$this->addLog('前后端支付价格不统一'.$money.'=='.$arr['money']);
				$str = array('code'=>'NO','msg'=>'价格异常，请稍后再试!');
			}
		}else{
			$this->addLog('订单出错：'.Db::name('GoodsOrder')->getLastSql());
			$str = array('code'=>'NO','msg'=>'订单失败，请重新下单!');
		}

		$this->setValue($str);
	}

}