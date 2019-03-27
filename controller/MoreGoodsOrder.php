<?php
namespace app\controller;
use think\Db;

class MoreGoodsOrder extends A
{

	//我的订单
    public function orderList() {
		$arr = $this->needs(input('post.'),['token']);

		$where = 'GoodsOrder.status=1 AND GoodsOrder.user_id='.$arr['user_id'];
			
		//状态
		if(isset($arr['type'])){
			if($arr['type']=='pay0'){//未付款
				$where .= ' AND GoodsOrder.pay_status=0';
			}else if($arr['type']=='sed0'){//待发货
				$where .= ' AND GoodsOrder.order_status=4';
			}else if($arr['type']=='get0'){//待收货
				$where .= ' AND GoodsOrder.order_status=5';
			}else if($arr['type']=='comment0'){//待评论
				$where .= ' AND GoodsOrder.order_status=6';
			}
		}
		
		//订单查找
		$count = Db::view('GoodsOrder','o_id')->view('Shop','shop_id','GoodsOrder.shop_id=Shop.shop_id','LEFT')->where($where)->count();
		if($count){
			$str['page'] = paging($count,input('page',1),config('PAGE_NUM'));
			$str['list'] = Db::view('GoodsOrder','o_id,order_id,shop_id,money,pay_status,yun_money,coupon_money,shipping_status,order_status,add_time')->view('Shop','shop_id,shop_name','GoodsOrder.shop_id=Shop.shop_id','LEFT')->where($where)->limit($str['page']['limit'])->order('o_id DESC')->select();
			
			if(count($str['list'])==1){
				$w_order = 'o_id='.$str['list'][0]['o_id'];
			}else{
				foreach($str['list'] as $value){
					$oid[] = $value['o_id'];
				}
				$oid = array_unique($oid);
				$w_order = (count($oid)==1) ? 'o_id='.$oid[0] : 'o_id in ('.join(',',$oid).')';
			}
			//订单详情
			$str['orderList'] = Db::name('OrderList')->field('id,o_id,goods_id,shop_id,goods_name,attr_name,first_money,end_money,img,attr_id,num,is_comment')->where($w_order)->select();
		}else{
			$str = array('code'=>'NO','msg'=>'暂无订单');
		}
		
		$this->setValue($str);
    }
	
	//我的订单->订单详情
	public function moreOrderDetail(){
		$arr = $this->needs(input('post.'),['token','o_id']);

		$str['address'] = Db::name('UserAddress')->where('status=1 AND user_id='.$arr['user_id'])->find();
		$str['order'] = Db::name('GoodsOrder')->field('o_id,order_id,money,money1,money2,pay1,pay2,coupon_money,pay_status,shipping_status,order_status,yun_money')->where('o_id ='.$arr['o_id'])->find();
		$str['order_list'] = Db::name('OrderList')->field('id,goods_id,shop_id,goods_name,attr_name,first_money,end_money,img,attr_id,num')->order('id DESC')->where('o_id ='.$arr['o_id'])->select();

		$this->setValue($str);
	}
	
	//确认收货
	public function takeOver(){
		$arr = $this->needs(input('post.'),['token','o_id']);
		
		$arr = orderSate(6,$arr);
		if(Db::name('GoodsOrder')->update($arr)){
			$str = 'OK';
		}else{
			$str = array('code'=>'NO','msg'=>'网络超时，请稍后再试');
		}
		
		$this->setValue($str);
	}
	
	//订单评论
	public function orderComment(){
		$arr = $this->needs(input('post.'),['token','id','olid','shop_id','goods_id','user_name','user_img','message']);

		$data = array(
			'o_id'		=> $arr['id'],
			'ol_id'		=> $arr['olid'],
			'shop_id'	=> $arr['shop_id'],
			'goods_id'	=> $arr['goods_id'],
			'user_id'	=> $arr['user_id'],
			'user_name'	=> $arr['user_name'],
			'user_img'	=> $arr['user_img'],
			'add_time'	=> time(),
			'img'		=> input('img'),
			'message'	=> $arr['message']
		);
		
		if(Db::name('OrderComment')->insert($data)){
			Db::name('OrderList')->where('id='.$arr['olid'])->setField('is_comment',1);
				
			if(!Db::name('OrderList')->where('is_comment=0 AND id='.$arr['olid'])->find()){
				Db::name('GoodsOrder')->where('o_id='.$arr['id'])->setField('order_status',7);
					
				//添加评论数
				Db::name('Shop')->where('shop_id =' . $arr['shop_id'])->setInc('comment_total', 1);
				Db::name('Goods')->where('goods_id =' . $arr['goods_id'])->setInc('comment_total', 1);
			}
			$str = array('code'=>'YES','msg'=>'评论成功');	
		}else{
			$str = $this->addLog('评论失败');
		}
		
		$this->setValue($str);
	}

	//评论列表
	public function comment(){
		$arr = input('');

		$where = '';
		if(isset($arr['goods_id'])){
			$where = 'goods_id='.$arr['goods_id'];
		}else if(isset($arr['shop_id'])){
			$where = 'shop_id='.$arr['shop_id'];
		}
		
		$count = Db::name('OrderComment')->where($where)->count();
		if($count){
			$vo['page'] = paging($count,input('page',1),config('PAGE_NUM'));
			$vo['list'] = Db::name('OrderComment')->order('id desc')->limit($vo['page']['limit'])->where($where)->select();
		}else{
			$vo = $this->addLog('暂无数据');
		}

        $this->setValue($vo);
	}
	
	//支付(通过订单ID计算宝贝的总价是否与前端上传总价一至)
	public function buy(){
		$arr = $this->needs(input('post.'),['token','order_list_id','money']);

		$goods = Db::name('GoodsOrder')->field('o_id,order_id,pay_status,money,yun_money')->where('o_id='.$arr['order_list_id'])->find();
		if($goods){
			$arr['order_no'] = $goods['order_id'].'X'.$arr['order_list_id'];

			if($goods['pay_status']==0){
				$goods_list = Db::name('OrderList')->field(['SUM(first_money*num)'=>'first_total'])->where('o_id='.$arr['order_list_id'])->find();
				$arr['order_no'] .= 'X1';	//付首款
				
				//验证优惠券
				$G = new \app\app\controller\clas\Goods();
				$arr['price'] = $goods_list['first_total'];
				$coupon = $G->checkCoupon($arr);
		
				$money = $goods_list['first_total'] + $goods['yun_money'] - $coupon['money'];
				//仅首付款有权限使用优惠券
				$orderArr = array('next_state'=>json_encode(orderSate(2)), 'coupon_money'=>$coupon['money'], 'coupon_id'=>$coupon['id'] );
			}else{
				$goods_list = Db::name('OrderList')->field(['SUM(end_money*num)'=>'end_total'])->where('o_id='.$arr['order_list_id'])->find();
				$arr['order_no'] .= 'X2';	//付尾款
				$money = $goods_list['end_total'];
				$orderArr = array('next_state'=>json_encode(orderSate(3)));
			}

			Db::name('GoodsOrder')->where('o_id='.$arr['order_list_id'])->update($orderArr);
			
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
				$str = array('code'=>'NO','msg'=>'网络超时，请稍后再试!');
			}
		}else{
			$this->addLog('订单出错：'.Db::name('GoodsOrder')->getLastSql());
			$str = array('code'=>'NO','msg'=>'订单失败，请重新下单!');
		}

		$this->setValue($str);
	}
}