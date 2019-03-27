<?php
namespace app\controller;
use think\Db;

class Bbs extends A
{
	public function bbs(){
		$arr = $this->needs(input('post.'),['token']);

		$where1 = 'status=1';
		$count = Db::name('UserBbs')->where($where1)->count();
		if($count){
			$vo['page'] = paging($count,input('page',1),config('PAGE_NUM'));
			$where2 = 'UserBbs.status=1';
			$vo['list'] = Db::view('User','user_id,user_name,user_img')->view('UserBbs','id,content,img,add_time,address,zan,comment_name,comment',"UserBbs.user_id = User.user_id")->order('id desc')->limit($vo['page']['limit'])->where($where2)->select();
			
			//trace(Db::view('User','user_id,user_name,user_img')->view('UserBbs','id,content,img,add_time,address,zan,comment_name,comment',"UserBbs.user_id = User.user_id")->order('id desc')->limit($vo['page']['limit'])->where($where)->fetchSql(true)->select());
		}else{
			$vo = '暂无数据';
		}

        $this->setValue($vo);
	}
	
	//发布朋友圈
	public function add(){
		$arr = $this->needs(input('post.'),['token']);

		if(!empty($arr['content']) || !empty($arr['img'])){
			//$arr['tag_id'] = config('feedback');
			$user = Db::name("User")->find($arr['user_id']);
			
			$arr["add_time"] = time();
			if(Db::name('UserBbs')->insert($arr)){
				$str = '发布成功';
			}else{
				$str = '网络超时，请稍后再试';
			}
		}else{
			$str = $this->addLog('内容不能为空');
		}

  		$this->setValue($str);
	}
	
	//点赞
	public function zan(){
		$arr = $this->needs(input('post.'),['token','id','name']);

		$ub = Db::name('UserBbs')->find($arr['id']);
		if($ub){
			if(empty($ub['zan'])){
				$ub['zan'] = json_encode(array('z'.$arr['user_id']=>$arr['name']));
				$str = array('code'=>'YES','msg'=>'1');
			}else{
				$ub['zan'] = json_decode($ub['zan'],true);
				if(array_key_exists('z'.$arr['user_id'],$ub['zan'])){
					unset($ub['zan']['z'.$arr['user_id']]);
					$str = array('code'=>'YES','msg'=>'0');
				}else{
					$ub['zan'] = array_merge(array('z'.$arr['user_id']=>$arr['name']),$ub['zan']);
					$str = array('code'=>'YES','msg'=>'1');
				}
				$ub['zan'] = json_encode($ub['zan']);
			}

			Db::name('UserBbs')->where('id',$arr['id'])->update($ub);
		}else{
			$str = $this->addLog('ID不存在');
		}

		$this->setValue($str);
	}
	
	//添加评论
	public function addComment(){
		$arr = $this->needs(input('post.'),['token','id','name','value']);

		$ub = Db::name('UserBbs')->find($arr['id']);
		if($ub){
			$time = time();
				
			if(empty($ub['comment_name'])){
				$ub['comment_name'] = json_encode(array($time.'-'.$arr['user_id']=>$arr['name']));
				$ub['comment'] = json_encode(array($time.'-'.$arr['user_id']=>$arr['value']));
			}else{
				$ub['comment_name'] = array_merge(array($time.'-'.$arr['user_id']=>$arr['name']),json_decode($ub['comment_name'],true));
				$ub['comment'] = array_merge(array($time.'-'.$arr['user_id']=>$arr['value']),json_decode($ub['comment'],true));

				$ub['comment_name'] = json_encode($ub['comment_name']);
				$ub['comment'] = json_encode($ub['comment']);
			}
			
			if(Db::name('UserBbs')->where('id',$arr['id'])->update($ub)){
				$str = array('code'=>'YES','msg'=>'1');
			}else{
				$str = $this->addLog('发布失败');
			}
		}else{
			$str = $this->addLog('发布失败');
		}

		$this->setValue($str);
	}
	
	//删除自己添加的评论
	public function delComment(){
		$arr = $this->needs(input('post.'),['token','id','cid']);

		$ub = Db::name('UserBbs')->find($arr['id']);
		if($ub){
			$ub['comment_name'] = json_decode($ub['comment_name'],true);
			if(array_key_exists($arr['cid'],$ub['comment_name'])){
				$ub['comment'] = json_decode($ub['comment'],true);
				unset($ub['comment_name'][$arr['cid']]);
				unset($ub['comment'][$arr['cid']]);

				$ub['comment_name'] = json_encode($ub['comment_name']);
				$ub['comment'] = json_encode($ub['comment']);
				
				Db::name('UserBbs')->where('id',$arr['id'])->update($ub);
				
				$str = array('code'=>'YES','msg'=>'1');
			}else{
				$str = $this->addLog('删除失败');
			}
		}else{
			$str = $this->addLog('评论不存在');
		}
			
		$this->setValue($str);
	}
}