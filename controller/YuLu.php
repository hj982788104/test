<?php
namespace app\controller;
use think\Db;

class YuLu extends A
{
	// 我的预录单列表
	public function YuLiList(){
		$arr = $this->needs(input('post.'),['token']);
		 
		$count = Db::name('BoxYuLu')->where('user_id='.$arr['user_id'])->count();
		if($count){
			$vo['page'] = paging($count,input('page',1),config('PAGE_NUM'));
			$vo['list'] =Db::name('BoxYuLu')->field("img,user_id,add_time,id,status,bei")->where('user_id='.$arr['user_id'])->limit($vo['page']['limit'])->order('id DESC')->where('user_id='.$arr['user_id'])->select();
		}else{
			$vo = array('code'=>'NO','msg'=>'暂无消息');
		}

		$this->setValue($vo);
	}

	// 上传预录单
	public function uploadYuLu(){
		$arr = $this->needs(input('post.'),['token','img']);
		$arr['add_time']=time();
		$arr['status']=2;

		if(Db::name("BoxYuLu")->insert($arr)){
			$vo = array('code'=>'YES','msg'=>'上传成功');
		}else{
			$vo = array('code'=>'NO','msg'=>'上传失败');
		}

		$this->setValue($vo);
	}
} 
