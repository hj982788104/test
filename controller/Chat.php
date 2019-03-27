<?php
namespace app\controller;
use think\Db;

class Chat extends A
{
	// 所有未读消息,好友列表(包含没同意的)
    public function friendAll() {
		$arr = $this->needs(input('post.'),['token']);
		
		//获取所有的好友
		$count = Db::name('ChatFriend')->where('user_id='.$arr['user_id'])->count();
		$rs['friend'] = '';
		if($arr['need_friends'] != $count){
			$rs['friend'] = Db::view('ChatFriend','agree,del_time')->view('User','user_id,user_name,sex,user_img','ChatFriend.friend_id=User.user_id','LEFT')->where('ChatFriend.user_id='.$arr['user_id'])->select();
		}
		
		//获取所有的未读消息和最近30天内的消息
		$time = strtotime("-1 month");
		//$rs['log'] = Db::view('ChatLog','chat_to,chat_from,content,add_time,reading,chat_type')->view('User','user_img,user_name','ChatLog.chat_to=User.user_id','LEFT')->where('(ChatLog.reading=0 OR ChatLog.add_time>"'.$time.'") AND (ChatLog.chat_to='.$arr['user_id'].' OR ChatLog.chat_from='.$arr['user_id'].')')->select();
		$rs['log'] = Db::name('ChatLog','chat_to,chat_from,content,add_time,reading,chat_type')->where('(reading=0 OR add_time>"'.$time.'") AND (chat_to='.$arr['user_id'].' OR chat_from='.$arr['user_id'].')')->select();
		
		if($rs['log']){
			Db::name('ChatLog')->where('reading=0 AND chat_to='.$arr['user_id'])->setField('reading',1);
		}

        $this->setValue($rs);
    }
	
	//同步指定好友及未读消息（有人请求加为好友时）
	public function friendOne(){
		$arr = $this->needs(input('post.'),['token','friend_id']);
		
		//获取所有的好友
		$rs['friend'] = Db::view('ChatFriend','agree,del_time')->view('User','user_id,user_name,sex,user_img','ChatFriend.friend_id=User.user_id','LEFT')->where('User.user_id='.$arr['friend_id'])->find();

		//获取所有的未读消息和最近30天内的消息
		//$time = strtotime("-1 month");
		$rs['log'] = Db::name('ChatLog','chat_to,chat_from,content,add_time,reading')->where('reading=0 AND chat_to='.$arr['user_id'].' AND chat_from='.$arr['friend_id'])->select();
		//$rs['log'] = Db::view('ChatLog','chat_to,chat_from,content,add_time,reading')->view('User','user_img,user_name','ChatLog.chat_to=User.user_id','LEFT')->where('ChatLog.reading=0 AND ChatLog.chat_to='.$arr['user_id'].' AND ChatLog.chat_from='.$arr['friend_id'])->select();
		//'(ChatLog.reading=0 OR ChatLog.add_time>"'.$time.'") AND (ChatLog.chat_to='.$arr['user_id'].' OR ChatLog.chat_from='.$arr['friend_id'].')')->select();
		
		if($rs['log']){
			Db::name('ChatLog')->where('reading=0 AND chat_to='.$arr['user_id'].' AND chat_from='.$arr['friend_id'])->setField('reading',1);
		}

        $this->setValue($rs);
	}
	
	//添加聊天记录
	public function addChatLog(){
		$arr = $this->needs(input(''),['token','friend_id','message']);
		
		$id = Db::name('ChatLog')->insertGetId(array(
			'chat_from'	=> $arr['user_id'],
			'chat_to'	=> $arr['friend_id'],
			'content'	=> $arr['message'],
			'chat_type'	=> 1,
			'add_time'	=> time()
		));
		if($id){
			$this->setValue(array('code'=>'YES','id'=>$id));
		}else{
			$this->setValue(array('code'=>'NO','msg'=>'失败'));
		}
	}
	
	//删除聊天记录
	public function delChatLog(){
		$arr = $this->needs(input(''),['token','friend_id']);
		
		if(Db::name('ChatFriend')->where('user_id='.$arr['user_id'].' AND friend_id='.$arr['friend_id'])->setField('del_time',time())){
			$str = '成功';
		}else{
			$str = $this->addLog('网络超时，请稍后再试');
		}
		
		$this->setValue($str);
	}
	
	//添加好友
	public function addFriend(){
		$arr = $this->needs(input(''),['token','friend_id']);
		
		$friend = Db::name('ChatFriend')->where('user_id='.$arr['user_id'].' AND friend_id='.$arr['friend_id'])->find();
		$now = time();
		if(!$friend){
			$obj = array(
				0=>array('user_id'=>$arr['user_id'],'friend_id'=>$arr['friend_id'],'add_time'=>$now,'agree'=>2),
				1=>array('user_id'=>$arr['friend_id'],'friend_id'=>$arr['user_id'],'add_time'=>'','agree'=>0)
			);
			Db::name('ChatFriend')->insertAll($obj);
		}else if($friend['agree']==0){
			Db::name('ChatFriend')->where('user_id='. $arr['user_id'].' AND friend_id='.$arr['friend_id'])->update(array('agree'=>1,'add_time'=>$now));
			Db::name('ChatFriend')->where('user_id='. $arr['friend_id'].' AND friend_id='.$arr['user_id'])->setField('agree', 1);
		}
		Db::name('ChatLog')->insert(array('chat_from'=>$arr['user_id'],'chat_to'=>$arr['friend_id'],'add_time'=>$now,'content'=>$arr['message']));
		
		$this->setValue(array('code'=>'YES','add_time'=>$now));
	}

	//同意加为好友
	public function agreeAddFriend(){
		$arr = $this->needs(input(''),['token','friend_id']);
		$now = time();

		if(Db::name('ChatFriend')->where('user_id='.$arr['user_id'].' AND friend_id=' . $arr['friend_id'])->update(array('agree'=>1,'add_time'=>$now))){
			Db::name('ChatFriend')->where('user_id=' . $arr['friend_id'].' AND friend_id='.$arr['user_id'])->setField('agree', 1);
			Db::name('ChatLog')->insert(array('chat_from'=>$arr['user_id'],'chat_to'=>$arr['friend_id'],'add_time'=>$now,'content'=>$arr['message']));

			$this->setValue(array('code'=>'YES','add_time'=>$now));
		}else{
			$this->setValue('失败','NO');
		}
	}
	
	//未通过请求的好友
	public function newFriend(){
		$arr = $this->needs(input(''),['token']);

		$count = Db::name('ChatFriend','agree')->where('agree =0 AND user_id=' . $arr['user_id'])->count();
		if($count){
			$where = 'ChatFriend.agree =0 AND ChatFriend.user_id=' . $arr['user_id'];
			$rs['page'] = paging($count,input('page',1),config('PAGE_NUM'));
			$rs['list'] = Db::view("ChatFriend",'agree')->view('User','user_name,user_id,user_img,name,sex','ChatFriend.friend_id=User.user_id','LEFT')->limit($rs['page']['limit'])->where($where)->select();
		}else{
			$rs = array("code"=>'NO','msg'=>'暂无数据');
		}

		$this->setValue($rs);
	}
	
	//
	/*public function index(){
		$arr = $this->needs(input('post.'),['token']);
		
		$rs = Db::view('ChatLog','chat_from,content,add_time,reading')->view('User','user_img,user_name','ChatLog.chat_to=User.user_id','LEFT')->where('ChatLog.reading=0 AND ChatLog.chat_to='.$arr['user_id'])->select();
		
		if($rs){
			//Db::name('ChatLog')->where('reading=0 AND chat_to='.$arr['user_id'])->setField('reading',1);
		}
		
		$this->setValue($this->isEmpty($rs));
	}*/
	
	// 好友列表
    public function friend() {
		$arr = $this->needs(input('post.'),['token']);

		$where = 'ChatFriend.user_id='.$arr['user_id'].' AND ChatFriend.agree=1';
		$keyword = input('keyword','');
		if($keyword){
			$where .= " AND (User.user_name like '%$keyword%' OR User.phone like '%$keyword%')";
		}
		$vo = Db::view('ChatFriend','agree')->view('User','user_id,user_name,sex,user_img','ChatFriend.friend_id=User.user_id','LEFT')->where($where)->select();

        $this->setValue($this->isEmpty($vo));
    }
	
	//搜索会员资料
    public function friendinfo() {
		$arr = $this->needs(input(''),['token']);

		$where = 'user_id !='.$arr['user_id'];
		if(isset($arr['uid'])){
			$vo['info'] = Db::name('User')->field('user_name,user_id,user_img,name,sex')->find($arr['uid']);
		}else if($arr['key']){
			$vo['info'] = Db::name('User')->field('user_name,user_id,user_img,name,sex')->where('user_id !='.$arr['user_id'] .' AND (user_name="'.$arr['key'].'" OR phone="'.$arr['key'].'")')->find();
		}
		
		if($vo['info']){
			$vo['is_friend'] = Db::name("ChatFriend")->where('user_id='.$arr['user_id'].' AND friend_id='.$vo['info']['user_id'].' AND agree=1')->field('add_time')->find();
		}

        $this->setValue($vo);
    }
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	/*
	//配置通讯录与会员表中同时存在的手机号
	public function phone(){
		$arr = $this->getValue;

		$this->tVar['user'] = D('ChatView')->phoneFriendAll($arr);
		$this->setValue(array('jm_body' => $this->display('Chat_phone', '', '', false)));
	}

	//用户的群
	public function group(){
		$arr = $this->getValue;

		$this->tVar['group'] = D("ChatGroupView")->groupAll(array('user_id' => $arr['user_id']));
		//Log::write(D("ChatGroupView")->getLastSql());
        $this->setValue(array('jm_body' => $this->display('Chat_group', '', '', false)));
	}
	
	//建群
	public function addGroup(){
		$arr = $this->getValue;

		$user = D("ChatView")->myFriendAll(array('user_id' => $arr['user_id']));
        $this->setValue($user);
	}
	
	//建群
	public function createGroup(){
		$arr = $this->getValue;

		$user = M("ChatGroup")->add($arr);
		if($user){
			$sql = array();
            foreach ($arr['group_user'] as $_v) {
				$sql[] = "(" . $user . ",".$_v.")";
			}
			$sql = implode(',', $sql);
			M('ChatGroupUser')->adds($sql);
			//Log::write(M('ChatGroupUser')->getLastSql());
			
			$this->setValue($user);
		}
	}
	
    //消息的显示条数
    public function badge() {
        $arr = $this->getValue;
        $num = D("ChatView")->reading_infor_num(array("user_id" => $arr['user_id'])); //查询未读数量
        $this->setValue($num);
    }
	*/
}
