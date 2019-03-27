<?php
namespace app\controller\clas;
use think\Db;

class Goods
{
	public function getCartGoods($where){
		$str['goods'] = Db::view('GoodsCart','cart_id,user_id,add_time,goods_id,num,attr_id')
			->view('Goods','name,price,total,img,first_pay,shop_id','Goods.goods_id = GoodsCart.goods_id','LEFT')
			->view('Shop','shop_name,courierfee','Goods.shop_id = Shop.shop_id','LEFT')
			->where($where)->order('cart_id desc')->select();	
		if($str['goods']){
			$attr = array();
			foreach($str['goods'] as $key=>$value){
				$str['shop_name'][$value['shop_id']] = array('shop_name'=>$value['shop_name'],'courierfee'=>$value['courierfee']);
				$str['goods'][$key]['inventory'] = 0;
				$str['goods'][$key]['attr_name'] = '';
				$str['goods'][$key]['price_attr'] = 0;
				if($value['attr_id']){
					$g[$value['cart_id']] = explode(',',$value['attr_id']);
					$attr = array_merge($attr,$g[$value['cart_id']]);
				}
			}

			if($attr){
				$attr = join(',',array_unique($attr));
				
				$attr = Db::name('GoodsAttr')->field('attr_id,inventory,price_attr,attr_name')->where('attr_id in('.$attr.')')->select();
				foreach($str['goods'] as $k=>$v){
					foreach($attr as $w){
						//if($v['attr_id'] == $w['attr_id'] || in_array($w['attr_id'], $g[$v['cart_id']])){
						if(isset($g[$v['cart_id']]) && in_array($w['attr_id'], $g[$v['cart_id']])){
							$str['goods'][$k]['inventory'] = ($w['inventory'] && $str['goods'][$k]['inventory']) ? min($str['goods'][$k]['inventory'],$w['inventory']) : $w['inventory'];
							$str['goods'][$k]['attr_name'] .= ' '.$w['attr_name'];
							$str['goods'][$k]['price_attr'] += $w['price_attr'];
						}
					}
				}
			}
		}else{
			$str=array('code'=>'NO','msg'=>'未找到');
		}
		return $str;
	}
	
	//根据商品属于或商品ID查找商品
	public function getGoods($arr=''){
		if($arr['attr_id']!=0){
			$attr = Db::name('GoodsAttr')->where('is_sell=0 AND attr_id in ('.$arr['attr_id'].')')->select();
			if($attr){
				$goods = Db::view('Goods','goods_id,name,price,shop_id,total,img,first_pay')->view('Shop','shop_name,courierfee','Goods.shop_id = Shop.shop_id','LEFT')->where('Goods.status =1 AND Goods.goods_id ='.$arr['goods_id'])->find();
				$goods['attr_name'] = '';
				$goods['price_attr'] = 0;
				foreach($attr as $value){
					$attr_name = explode('|',$value['attr_name']);
					$goods['attr_name'] .= ' '.(isset($attr_name[1]) ? join(' ',$attr_name) : $value['attr_name']);
					$attr_id[] = $value['attr_id'];
					$goods['price_attr'] += $value['price_attr'];
				}
					
				$goods['attr_id'] = join(',',$attr_id);
				$str['goods'][0] = $goods;
			}
		}else if($arr['goods_id']){
			$str['goods'] = Db::view('Goods','goods_id,name,price,shop_id,total,img,first_pay')
							->view('GoodsAttr','attr_id,attr_name,price_attr,inventory',"Goods.goods_id = GoodsAttr.goods_id",'LEFT')
							->view('Shop','shop_name,courierfee','Goods.shop_id = Shop.shop_id','LEFT')
							->where('Goods.status =1 AND Goods.goods_id ='.$arr['goods_id'])->select();
		}
		
		$str['shop_name'][$str['goods'][0]['shop_id']] = array('shop_name'=>$str['goods'][0]['shop_name'],'courierfee'=>$str['goods'][0]['courierfee']);
		
		return $str;
	}
	
	//验证优惠券的有效性
	public function checkCoupon($arr){
		$coupon = array('id'=>0,'money'=>0);
		
		if($arr['coupon_id']){
			$now = time();
			$w = 'UserCoupon.id='.$arr['coupon_id'].' AND UserCoupon.user_id='.$arr['user_id'].' AND SystemCoupon.start_time<'.$now.' AND SystemCoupon.end_time>'.$now.' AND SystemCoupon.reach_money<='.$arr['price'];
			$coupon = Db::view('UserCoupon','id,coupon_id')->view('SystemCoupon','money','UserCoupon.coupon_id=SystemCoupon.coupon_id','LEFT')->where($w)->find();
		}
		
		return $coupon;
	}

}
