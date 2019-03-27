<?php
namespace app\controller;
use think\Db;

class Goods extends A
{
    //宝贝列表
    public function listing() {
        $arr = input('');

 		$where = "status=1 AND total>0 AND start_time<".time();
 		//分类条件
 		if(isset($arr['category_id'])){
 			$where = "category_id=".$arr['category_id'];
 		}
 		if(isset($arr['keyword'])){
 			$where = "(name like '%".$arr['keyword']."%' OR keyword like '%".$arr['keyword']."%')";
 		}

		$count = Db::name("Goods")->where($where)->count();
		if($count){
			//分页
			$vo['page'] = paging($count,input('page',1),config('PAGE_NUM'));
			$vo['list'] = Db::name('Goods')->where($where)->limit($vo['page']['limit'])->field('goods_id,tap_total,img,name,selled,selled2,price')->order('goods_id desc')->select();
		}else{
			$vo = array('page'=>0,'list'=>'');
		}

        $this->setValue($vo);
    }
	
	//宝贝分类
	public function category(){
		$str = Db::name('GoodsCategory')->where('status=1')->order('category_id ASC')->field('category_id,category_name')->select();
		
		$this->setValue($this->toArray($str));
	}
	
    //详情
    public function detail() {
		$arr = $this->needs(input(''),['goods_id']);

		//恢复该宝贝的过期订单，并恢复库存（GoodsOrder/addOrder）
		//$this->expiredOrder($arr['goods_id']);
		
		//添加浏览量
		if(isset($arr['ugid'])){
			Db::name('Goods')->where('goods_id',$arr['ugid'])->setInc('tap_total', 1);
		}
			
		//宝贝详情
		$vo['detail'] = Db::name("Goods")->field('goods_id,category_id,shop_id,price,o_price,total,name,img,tap_total,flash_img,img_info,content,comment_total,selled,selled2,goods_attr,first_pay,attr_title')->where("status=1 AND goods_id=".$arr['goods_id'] ." AND start_time<".time())->find();
		if($vo['detail']){
			$vo['detail']['content'] = !empty($vo['detail']['content']) ? change_img_path($vo['detail']['content']) : '';
		
			if(!empty($vo['detail']['goods_attr'])){
				$vo['detail']['goods_attr'] = object_to_array(json_decode($vo['detail']['goods_attr']));
			}
		
			//组合属性
			$goods_attr = Db::name("GoodsAttr")->field("attr_id,attr_name,price_attr,inventory,type,attr_selled")->where("is_sell=0 AND goods_id=".$vo['detail']['goods_id'])->select();
			if($goods_attr){
				$an1 = '';
				foreach($goods_attr as $k=>$v){
					if($v['type']==1){
						$vo['self_attr'][] = $v;
					}else{
						//用户筛选完属性后通过该数组查询相关信息
						$vo['attr']['attr_id'][$k] = $v['attr_id'];
						$vo['attr']['attr_name'][$k] = $v['attr_name'];
						$vo['attr']['price_attr'][$k] = $v['price_attr'];
						$vo['attr']['inventory'][$k] = $v['inventory'];
						$vo['attr']['attr_selled'][$k] = $v['attr_selled'];
							
						//获取拥有库存的可选属性
						$an1[] = $v['attr_name'];
					}
				}
				
				if($an1){
					$an1 = array_unique(explode('|',join('|',$an1)));	//去除重复的属性
					
					//宝贝的可选价格属性(不做此步操作无法确定属性的标题名称，如：颜色，尺寸)
					$cate = Db::name("GoodsCategory")->field("attr_title,attr_name")->where("category_id = ".$vo['detail']['category_id'])->find();
					
					if($cate){
						$vo['category'] = '';
						$Atitle = explode("\n",$cate['attr_title']);
						$Aname = explode("\n",$cate['attr_name']);
						foreach ($Atitle as $key => $value) {
							$attr = getSameValue(array_merge(explode('|', $Aname[$key]),$an1));	//获取数组中重复的元素
							if($attr){
								$vo['category'][$key]['attr_name'] = $attr;
								$vo['category'][$key]['attr_title'] = $value;
							}
						}
					}
				}
			}else{
				$vo['attr'] = '';
			}
			
			//客服电话
			$vo['detail']['tel'] = config('KF_TEL');
		}

    	$this->setValue( $vo);
    }
    
    //查询该宝贝的过期订单，并恢复库存
    protected function expiredOrder(){
		$where = 'GoodsOrder.status=1 AND pay_status=0 AND GoodsOrder.add_time<'.(time()-(15*24*360));
		//$where = 'GoodsOrder.del=0 AND pay_status=0 AND GoodsOrder.add_time<'.(time());
		$goods = Db::view('GoodsOrder','o_id,order_id,money,pay_status,order_status,shipping_status')->view('OrderList','id,goods_id,shop_id,goods_name,attr_name,img,attr_id,num','OrderList.o_id=GoodsOrder.o_id')->where($where)->select();

		$o_id = array();//无效的订单id
		if($goods){
			foreach($goods as $v){
				$o_id[] = $v['o_id'];
				//恢复库存
				if($v['attr_id']){
					Db::name('GoodsAttr')->where('attr_id',$v['attr_id'])->setInc('inventory', $v['num']);
				}
				Db::name('Goods')->where('goods_id',$v['goods_id'])->setInc('total', $v['num']);
			}
			$o_id = array_unique($o_id);
			
			Db::name("GoodsOrder")->where("o_id in(".implode(',',$o_id).")")->update(array('del'=>1));
		}
    }
}