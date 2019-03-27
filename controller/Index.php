<?php
namespace app\controller;
use think\Db;

class Index extends A
{
	//附近的打印点（地图模式）
	public function mapPrint() {
		//$arr = $this->needs(input(''),['lat','lon']);
		$rs = array('kf_tel'=>config('KF_TEL'));
		
		//搜索的字段
       /* if (isset($arr['lat']) && isset($arr['lon'])) {
            $field = "status,address,img,wei_du,jing_du,print_sn,ROUND(6368.138*2*ASIN(SQRT(POW(SIN((" . $arr['lat'] . "*PI()/180-wei_du*PI()/180)/2),2)+COS(" . $arr['lat'] . "*PI()/180)*COS(wei_du*PI()/180)*POW(SIN((" . $arr['lon'] . "*PI()/180-jing_du*PI()/180)/2),2)))*1000) AS juli";
			$rs['print'] = Db::name('MapPrint')->field($field)->where('status!=3 and jing_du!="" and wei_du!=""')->order("juli asc")->limit('0,50')->select();
        }
		*/
		$rs['print'] = Db::name('DbPrint')->field("status,address,img,wei_du,jing_du,print_sn,bei")->where('status in(1,2,5) and jing_du!="" and wei_du!=""')->order("od ASC")->limit('0,50')->select();
        $this->setValue($rs);
    }
	
	/* 获取堆场路况
	   接口地址 http://lbsyun.baidu.com/index.php?title=webapi/direction-api
	*/
    public function DbStorage() {
		$arr = $this->needs(input('get.'),['lat','lon']);
		$vo['info'] = Db::view('DbStorage','ms_id,name,address,tel,jing_du,wei_du,city')->view("SystemTag","tag_name","SystemTag.tag_id=DbStorage.city")->where("DbStorage.status=1")->select();
		if($vo['info']){
			foreach($vo['info'] as $k=>$v){
				$url="http://api.map.baidu.com/direction/v1?mode=driving&origin=".$arr['lat'].",".$arr['lon']."&destination=".$v['jing_du'].",".$v['wei_du']."&origin_region=上海&destination_region=".$v['tag_name']."&output=json&ak=f91oqG0V5naPgkGL9uYwOgdIpFUURG4c";
			
				$re=json_decode(http_get($url),true);
				if($re['status']==0){
					$yd=0;$hx=0;$ct=0;$wlk=0;$num=0;
					foreach ($re['result']['routes'] as $key1 =>$val1){
						foreach($val1['steps'] as $key2=>$val2){
							$num =$num+count($val2['traffic_condition_detail']);
							foreach($val2['traffic_condition_detail'] as $key3=>$val3){
								if($val3['status']==0){
									$wlk=$wlk+1;
								}else if($val3['status']==1){
									$ct=$ct+1;
								}else if($val3['status']==2){
									$hx=$hx+1;
								}else{
									$yd=$yd+1;
								}
								
							}
						}
					$vo['info'][$k]['lx'][$key1]['num']=$num;//路段数量
					$vo['info'][$k]['lx'][$key1]['wlk']=$wlk;//无路况
					$vo['info'][$k]['lx'][$key1]['ct']=$ct;	//畅通
					$vo['info'][$k]['lx'][$key1]['hx']=$hx;	//缓行
					$vo['info'][$k]['lx'][$key1]['yd']=$yd;	//拥堵
					}
					
					if(isset($re['result']['traffic_condition'])){
						$vo['info'][$k]['traffic_condition']=$re['result']['traffic_condition'];
					}else{
						$vo['info'][$k]['traffic_condition']=0;
					}
				}else{
					$rs['vo'][$k]['traffic_condition']=5;
				}
			}
		}else{
			$vo['info']=array("code"=>"NO","暂无数据");
		}

		$this->setValue($vo);
    }
	
	public function mapStorage2() {
		$rs['list'] = Db::name('DbStorage')->field('ms_id,name,address,tel,tel_bei,jing_du,wei_du')->where("status=1")->select();
		
		$this->setValue($rs);
    }
	
	//每天定时将Api接口访问记录存储至数据库中
	public function addApiLog() {
		//方法(thinkphp 缓存)
		$str=Cache::get('action');//所有被访问的接口
		if($str){
			foreach($str as $k=>$v){
				$data[]=Cache::tag('tag')->get($v);
			}
			if(Db::name("SystemApiLog")->insertAll($data)){
				Cache::rm("action");
				Cache::clear('tag');
				return '保存成功';
			}
		}
		
		return '保存失败'.$str;
	}
	
	//用户指南详情
	public function articleDetail(){
		$arr = $this->needs(input(''),['id']);

		//$vo = $this->needCache('article_detail'.$arr['id'],function($arr){
			$vo['detail'] = Db::name('SystemArticle')->field('title,content')->find($arr['id']);
			/*if($vo['detail']){
				$vo['detail']['content'] = $vo['detail']['content'] ? change_img_path($vo['detail']['content']) : '';
			}*/
			//return $vo;
		//},$arr);

        $this->setValue($vo);
	}
	
	//后台扫码登录
	public function qrcodeLogin(){
		$arr = $this->needs(input('post.'),['num','open_id']);
		
		if(!empty($arr['open_id']) && Db::name('SystemAdmin')->where('open_id="'.$arr['open_id'].'"')->find()){
			Db::name('SystemAdmin')->where('open_id',$arr['open_id'])->setField('qr_num',$arr['num']);
			$this->setValue('登录成功');
		}else{
			$this->setValue('登录失败','NO');
		}
	}
	
	
}