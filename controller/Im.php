<?php
namespace app\controller;

use think\worker\Server;
use think\Db;
use Workerman\Worker;
use Workerman\Lib\Timer;

class Im extends Server
{
    //protected $socket = 'websocket://192.168.1.30:2222';
    protected $socket = 'websocket://10.223.3.30:2222';
	
	// 新增加一个属性，用来保存uid到connection的映射
	protected $uidConnections = array();
	
	protected function init(){
		Worker::$logFile=RUNTIME_PATH.'log/log.txt';	// 日志路径
		define('HEARTBEAT_TIME', 25);		// 心跳间隔25秒
    }

    // 当连接建立时触发的回调函数
    public function onConnect($connection){
		//Worker::log("connect succeed: ".$connection->id.'  connect_count: '.(count($this->uidConnections)+1));
    }

    // 当客户端的连接上发生错误时触发
    public function onError($connection, $code, $msg){
		Worker::log("ERROR: $code $msg");
    }

	// 当连接断开时触发的回调函数
    public function onClose($connection){
		//Worker::log(count($this->uidConnections)." close ".$connection->id);
		// 连接断开时删除映射
		if(isset($connection->uid) && isset($this->uidConnections[$connection->uid])){
			unset($this->uidConnections[$connection->uid]);
		}
		//Worker::log('count: '.count($this->uidConnections));
    }

    // 收到信息
    public function onMessage($connection, $data) {
		$arr = json_decode($data,true);
		if( isset($arr['token']) && isset($arr['type']) ){
			$connection->lastMessageTime = time();		// 给connection临时设置一个lastMessageTime属性，用来记录上次收到消息的时间
			$uid = hy_token($arr['token']);
			
			if('login' == $arr['type']){
				//用户登陆
				if(!isset($this->uidConnections[$uid])){
					$connection->uid = $uid;
					$this->uidConnections[$uid] = $connection;
				}
				//用户端心跳检测回馈
				$connection->send( $this->setValue(array('code'=>'YES','message'=>'PON')) );
				//Worker::log('count-connect: '.count($this->uidConnections));	//现有连接数
			}else if('friend_message' == $arr['type'] ){
				//点对点单聊
				if( isset($arr['friend_id']) && isset($this->uidConnections[$arr['friend_id']]) ){
					$this->uidConnections[$arr['friend_id']]->send($this->setValue(array(
						'message'=>$arr['message'],'friend_id'=>$uid, 'msgType'=>$arr['msgType'], 'type'=>$arr['type']
					)));
					$rs = array('code'=>'YES', 'id'=>$arr['friend_id'], 'type'=>'friend_message_back', 'log_id'=>$arr['log_id']);
				}else{
					$rs = array('code'=>'NO', 'id'=>$arr['friend_id'], 'type'=>'friend_message_back', 'log_id'=>$arr['log_id']);
				}
				$connection->send( $this->setValue($rs) );
			}else if( isset($arr['friend_id'])){
				//添加好友,同意加为好友
				if(isset($this->uidConnections[$arr['friend_id']])){
					//用户在线
					$this->uidConnections[$arr['friend_id']]->send($this->setValue(array('from_id'=>$uid,'add_time'=>time(),'message'=>$arr['message'],'type'=>$arr['type'])));
				}else{
					//用户不在线
					//Db::name('ChatLog')->insert(array('chat_from'=>$uid,'chat_to'=>$arr['friend_id'],'add_time'=>time(),'content'=>$arr['message']));
				}
			}else{
				Worker::log("非法请求".$data);
			}
		}else{
			Worker::log("非法请求");
		}
    }

    // 每个进程启动
    public function onWorkerStart($worker){
		Worker::log("new course: ".$worker->id);
		
		//每30秒(最好大于前台的心跳时间)查检一次心跳
		//即30内客户如果没有操作(没有发送心跳或心跳的时间大于30秒会自动认为用户已退出系统)
		Timer::add(30,function(){
			$time_now = time();
			foreach($this->uidConnections as $k=>$v){
				// 有可能该connection还没收到过消息，则lastMessageTime设置为当前时间
				if (empty($v->lastMessageTime)) {
					$this->uidConnections[$k]->lastMessageTime = $time_now;
					continue;
				}
				// 上次通讯时间间隔大于心跳间隔，则认为客户端已经下线，关闭连接
				//Worker::log("heartAAAA".($time_now - $v->lastMessageTime));
				if ($time_now - $v->lastMessageTime > HEARTBEAT_TIME) {
					//Worker::log("heart".$k);
					$v->close();
				}
			}
		});
    }
	
	protected function setValue($list='',$status='YES') {
		if(empty($list)){
			$list = $this->addLog('暂无数据');
		}else if(is_string($list)){
			$list = array("code"=>$status,'msg'=>$list);
		}
       
        return json_encode($list);
    }
	/*
	protected function saveTxt($str){
		if($str != ''){
			$f = fopen(RUNTIME_PATH.'log/Im.txt', 'a+');//我猜测你应该是想累加存储，在文件的结尾插入，所以用了fopen和a+，
			fwrite($f, $str);
			fclose($f);
		}
	}
	*/
}