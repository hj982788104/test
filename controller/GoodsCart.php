<?php
namespace app\controller;
use think\Db;

class GoodsCart extends A
{
	//将宝贝加入购物车
	public function putCart(){
		$arr = $this->needs(input('post.'),['token']);

		$shoppingCart = Db::name("GoodsCart")->where('user_id='.$arr['user_id'])->select();		//搜索我的购物车
			
		$arr['add_time'] = time();
		if(!$shoppingCart){
			if(Db::name('GoodsCart')->insert($arr)){
				$str = '添加成功';
			}else{
				$str = $this->addLog('网络超时，请稍后再试');
			}
		}else if(count($shoppingCart) < config('goods_cart_max_num')) {
			$goods = Db::view('GoodsCart','cart_id,user_id,add_time,goods_id,num,attr_id')
					->view('Goods','total','GoodsCart.goods_id = Goods.goods_id','LEFT')
					->view('GoodsAttr','inventory','GoodsAttr.goods_id = GoodsCart.goods_id','LEFT')
					->where('GoodsCart.goods_id='.$arr['goods_id'].' AND GoodsCart.attr_id="'.$arr['attr_id'].'" AND GoodsCart.user_id='.$arr['user_id'])->find();		
			if(!$goods){
				if(Db::name('GoodsCart')->insert($arr)){
					$str = '添加成功';
				}else{
					$str = $this->addLog('网络超时，请稍后再试');
				}
			}else if($goods){//购物车有物品，需要判断是否有相同款式
				$goods['num'] += (int)$arr['num'];
				$ku_cun = $goods['inventory'] ? $goods['inventory'] : $goods['total'];
				
				if($goods['num'] > $ku_cun){
					$str = $this->addLog('库存不足,购物车中已有同一产品');
				}else{
					$data = move_array_value($goods,['total','inventory']);
					$data['add_time'] = $arr['add_time'];
					Db::name('GoodsCart')->update($data);
					$str = '添加成功';
				}
			}
		}else{
			$str = $this->addLog('购物车中宝贝数量不能超过' . config('goods_cart_max_num') . '件');
		}
		
        $this->setValue($str);
	}

	//购物车列表
    public function cart() {
		$arr = $this->needs(input('post.'),['token']);

		$goods = new \app\app\controller\clas\Goods();
		$str = $goods -> getCartGoods('Goods.status =1 AND GoodsCart.user_id='.$arr['user_id']);

        $this->setValue($str);
    }
    
	//修改购物车中宝贝的数量
	public function subtractNum(){
		$arr = $this->needs(input('post.'),['token','num','cart_id','type']);

		if($arr['num']>0){
			if($arr['type']=='add'){
				if($arr['attr_id']){
					$attr = Db::name('GoodsAttr')->where('attr_id in('.$arr['attr_id'].')')->select();
					$goodsAttr['inventory'] = 0;
					foreach($attr as $value){
						$goodsAttr['inventory'] = ($goodsAttr['inventory'] && $value['inventory']) ? min($value['inventory'],$goodsAttr['inventory']) : $value['inventory'];
					}
				}else{
					$goodsAttr = Db::name('Goods')->where('goods_id='.$arr['goods_id'])->find();
					$goodsAttr['inventory'] = $goodsAttr['total'];
				}
				
				if($goodsAttr['inventory']<$arr['num']){
					$str = array('code'=>'TS','msg'=>$goodsAttr['inventory']);		//库存不足
				}else{
					Db::name('GoodsCart')->where('cart_id',$arr['cart_id'])->setField('num',$arr['num']);
					$str = '修改成功';
				}
			}else{
				//Db::name('GoodsCart')->where('cart_id',$arr['cart_id'])->setField('num',$arr['num']);
				Db::name('GoodsCart')->where('cart_id',$arr['cart_id'])->setDec('num',1);
				$str = '修改成功';
			}
		}else{
			$str = $this->addLog('购买的宝贝数量必须大于0');
		}
		
		$this->setValue($str);
	}

    //删除购物车单个宝贝
    public function delCartGoods() {
		$arr = $this->needs(input('post.'),['token','cart_id']);

		if(Db::name('GoodsCart')->delete($arr['cart_id'])){
			$str = '删除成功';
		}else{
			$str = $this->addLog('删除失败');
		}

		$this->setValue($str);
    }
	
}