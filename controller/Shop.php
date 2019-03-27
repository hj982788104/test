<?php
namespace app\controller;
use think\Db;

class Shop extends A
{
	public function listing() {
        $arr = input('');
		$arr['order'] = 1;

        //获取当前所在城市
		if (!isset($arr['city_id'])) {
            $city = $this->getCity();
			$arr['city_id'] = $city['id']; 
        }

        $vo = $this->search_shop($arr);
		
		if(isset($city)){
			$vo['city'] = $city;
		}

        $this->setValue($vo);
    }

	protected function search_shop($arr) {
        //搜索的字段
		$field = "shop_id,shop_name,shop_img,xingji,address,jingdu,weidu,keyword";
        if (isset($arr['lat']) && $arr['lat']!=0 && isset($arr['lon'])) {
            $field .= ",ROUND(6368.138*2*ASIN(SQRT(POW(SIN((" . $arr['lat'] . "*PI()/180-jingdu*PI()/180)/2),2)+COS(" . $arr['lat'] . "*PI()/180)*COS(jingdu*PI()/180)*POW(SIN((" . $arr['lon'] . "*PI()/180-weidu*PI()/180)/2),2)))*1000) AS juli";
        }
		$where = 'status=1';
		//城市搜索
		if($arr['city_id']){ $where .= ' AND city_id =' . $arr['city_id']; }
		//区域搜索
		if($arr['area_id']){ $where .= ' AND area_id =' . $arr['area_id']; }
        //以品牌搜索
        if ($arr['brand_id']) { $where .= ' AND brand_id=' . $arr['brand_id']; }
        //以关键字搜索
        if (isset($arr['key'])) { $where .= ' AND (shop_name like "%' . $arr['key'] . '%" OR keyword like "%' . $arr['key'] . '%")'; }

        //排序
        if ($arr['order'] == '1' && isset($arr['lat']) && $arr['lat']!=0 && isset($arr['lon'])) { //若已距离为条件搜索，需传三个参数：length  lat:纬度  lng:经度
            $where .= " AND jingdu is not null and weidu is not null";
            $order = "juli asc";
        } else {
            $order = "shop_id desc";
        }

        $count = Db::name('shop')->where($where)->distinct(true)->count();
		//trace(Db::name('shop')->getLastSql());
		if($count){
			$vo['page'] = paging($count,input('page',1),config('PAGE_NUM'));
			$vo['list'] = Db::name('shop')->field($field)->where($where)->order($order)->limit($vo['page']['limit'])->select();
		}else{
			$vo = array('page'=>0,'list'=>0);
		}

        return $vo;
    }
	
	//获取所有商家品牌
    public function category() {
        $vo = Db::name('ShopBrand')->where('status=1')->field('brand_id,brand_name,brand_img,brand_info')->select();
        $this->setValue($vo);
    }
	
	// 详情
    public function detail() {
		$arr = $this->needs(input(''),['shop_id']);
 
		$vo['shop'] = Db::name('Shop')->field('shop_id,brand_id,keyword,shop_name,shop_img,phone,address,img,intro,collect_total,comment_total,jingdu,weidu,xingji,money,shop_attr')->find($arr['shop_id']);
		if($vo['shop']){
			$vo['shop']['intro'] = $vo['shop']['intro'] ? change_img_path($vo['shop']['intro']) : '';

			//根据两点间的经纬度计算距离
			//if ($vo['Shop']['jingdu'] != null && $vo['Shop']['weidu'] != null && $arr['lon'] != 0 && $arr['lat'] != 0) {
			//	$vo['Shop']['length'] = getDistance($arr['lat'], $arr['lon'], $vo['Shop']['weidu'], $vo['Shop']['jingdu']);
			//}
		}

        $this->setValue($vo);
    }
	
	//商家详情2
	public function detail2(){
		$arr = $this->needs(input(''),['shop_id']);
		
		$vo['shop'] = Db::name('Shop')->field('shop_id,brand_id,keyword,shop_name,shop_img,phone,address,img,collect_total,comment_total,jingdu,weidu,xingji,money,shop_attr')->find($arr['shop_id']);
		//根据两点间的经纬度计算距离
		/*if($vo['shop']){
			if ($vo['Shop']['jingdu'] != null && $vo['Shop']['weidu'] != null && $arr['lon'] != 0 && $arr['lat'] != 0) {
				$vo['Shop']['length'] = getDistance($arr['lat'], $arr['lon'], $vo['Shop']['weidu'], $vo['Shop']['jingdu']);
			}
		}*/
		
		//分类
		$vo['category'] = Db::name('GoodsCategory')->where('status=1')->order('category_id ASC')->field('category_id,category_name')->select();

 		//该商家的所有商品
		$vo['list'] = Db::name('Goods')->where("shop_id=".$arr['shop_id']." AND status=1 AND total>0 AND start_time<".time())->field('category_id,goods_id,total,img,name,selled,selled2,price')->order('goods_id desc')->select();

		$this->setValue($vo);
	}

	//(取消)收藏
	public function collect(){
		$arr = $this->needs(input('post.'),['token','shop_id']);
		$str = '网络超时，请稍后再试';
		
		$user = Db::name('User')->field('user_id,collect_shop')->find($arr['user_id']);
		if(empty($user['collect_shop'])){
			if(Db::name('User')->where('user_id',$arr['user_id'])->setField('collect_shop', $arr['shop_id'])){
				$str = array('code'=>'YES','msg'=>'收藏成功','collect'=>$arr['shop_id']);
			}
		}else{
			$collect_shop = explode(',',$user['collect_shop']);
			if(in_array($arr['shop_id'],$collect_shop)){
				//删除指定的id
				$collect_shop = join(',',array_merge(array_diff($collect_shop, array($arr['shop_id']))));
				$str = array('code'=>'YES','msg'=>'取消收藏','collect'=>$collect_shop);
			}else{
				$collect_shop = $arr['shop_id'].','.$user['collect_shop'];
				$str = array('code'=>'YES','msg'=>'收藏成功','collect'=>$collect_shop);
			}
			Db::name('User')->where('user_id',$arr['user_id'])->setField('collect_shop', $collect_shop);
		}

		$this->setValue($str);
	}

}