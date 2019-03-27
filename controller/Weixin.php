<?php
namespace app\controller;
use think\Db;

//微信接口
class Weixin extends A
{
	// 与菜单交互
	public function init(){
		//验证
		if(input('echostr')){
			$tmpArr = array(config('WEIXIN')['TOKEN'], $_GET["timestamp"], $_GET["nonce"]);
			sort($tmpArr, SORT_STRING);
			$tmpStr = sha1(implode( $tmpArr ));
			if( $tmpStr == $_GET["signature"] ){
				echo $_GET['echostr']; exit;
			}else{
				trace($tmpStr .'=='. $_GET["signature"]);
				die(false);
			}
		}
		
		//$postStr = $GLOBALS["HTTP_RAW_POST_DATA"];
		$postStr = file_get_contents("php://input");
        $postObj = simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);
        $fromUsername = $postObj->FromUserName;		//openId
        $toUsername = $postObj->ToUserName;
        $keyword = trim($postObj->Content);
		$clickKey = trim($postObj->EventKey);
        $time = time();
		$tpl = "<xml>
			<ToUserName><![CDATA[%s]]></ToUserName>
			<FromUserName><![CDATA[%s]]></FromUserName>
			<CreateTime>%s</CreateTime>
			<MsgType><![CDATA[%s]]></MsgType>
			<Content><![CDATA[%s]]></Content>
			<FuncFlag>0</FuncFlag>
			</xml>";
		
		//trace(json_encode($postObj))
		if(!empty( $clickKey )){	//回复关键字信息,qrscene_10000004是扫描二维码关注时传的值，不同公众号可能不一样

			$rs = Db::name('WeixinNav')->where('status=1 AND type="click" AND name="'.$clickKey.'"')->find();
			if($rs){
				if(!empty($rs['val'])){
					$msgType = "text";
					$resultStr = sprintf($tpl, $fromUsername, $toUsername, $time, $msgType, $rs['val']);
				}else{
					$il = $this->imgList('SystemAd.status = 1 AND SystemTag.tag_name="'.$clickKey.'"');
					if($il){
						$msgType = "news";
						$resultStr = sprintf($il, $fromUsername, $toUsername, $time, $msgType);
					}else{
						exit();
					}
				}
			}else{
				exit();
			}
        }else if(!empty( $keyword )){
			//查看图文推荐是否有完全匹配的
			$rs = Db::name('SystemTag')->order('tag_id desc')->where('p_id=4 AND tag_name="'.$keyword.'"')->find();
			if(!$rs){
				//查看微信关键词回复中是否有完全匹配的
				$rs = Db::name('WeixinKey')->order('id desc')->where('mate_type=0 AND keyword="'.$keyword.'"')->find();
				if($rs){
					//推送完全匹配的文本
					$msgType = "text";
					$resultStr = sprintf($tpl, $fromUsername, $toUsername, $time, $msgType, $rs['reply']);
				}else{
					//查看图文推荐模糊匹配的
					$rs = Db::name('SystemTag')->order('tag_id desc')->where('p_id=4 AND tag_name LIKE "%'.$keyword.'%"')->find();
					if(!$rs){
						//查看微信关键词回复中模糊匹配的
						$rs = Db::name('WeixinKey')->order('id desc')->where('mate_type=1 AND keyword="%'.$keyword.'%"')->find();
						if($rs){
							$msgType = "text";
							$resultStr = sprintf($tpl, $fromUsername, $toUsername, $time, $msgType, $rs['reply']);
						}else{
							exit();
						}
					}else{
						//推送模糊匹配的图文
						$il = $this->imgList("SystemAd.status = 1 AND SystemTag.tag_id=".$rs['tag_id']);
						if($il){
							$msgType = "news";
							$resultStr = sprintf($il, $fromUsername, $toUsername, $time, $msgType);
						}
					}
				}
			}else{
				//推送完全匹配的图文
				$il = $this->imgList("SystemAd.status = 1 AND SystemTag.tag_id=".$rs['tag_id']);
				if($il){
					$msgType = "news";
					$resultStr = sprintf($il, $fromUsername, $toUsername, $time, $msgType);
				}
			}
		}else{	
			//欢迎词
           	/* 
			$msgType = "text";
			$contentStr = config('WEIXIN')['welcome'];
			$resultStr = sprintf($tpl, $fromUsername, $toUsername, $time, $msgType, $contentStr); 
			*/
			$il=$this->sendWelcome();
			if($il){
				$msgType = "news";
				$resultStr = sprintf($il, $fromUsername, $toUsername, $time, $msgType);
			}
        }
		
		echo $resultStr;
    }
	
	
	//关注公众号推荐图文
	private function sendWelcome($w=''){
		$tpl = '<xml>
		<ToUserName><![CDATA[%s]]></ToUserName>
		<FromUserName><![CDATA[%s]]></FromUserName>
		<CreateTime>%s</CreateTime>
		<MsgType><![CDATA[%s]]></MsgType>
		<ArticleCount>1</ArticleCount>
		<Articles><item>
			<Title><![CDATA[欢迎关注集行通！]]></Title>
			<Description><![CDATA[]]></Description>
			<PicUrl><![CDATA['.IMG_PUBLIC. 'attached/201806/1537322871.jpg]]></PicUrl>
			<Url><![CDATA['.IMG_PUBLIC. 'attached/201806/1537322820.jpg]]></Url>
		</item></Articles>
		</xml>';
		
		return $tpl;
	}
	
	//欢迎加入集行通！
	
	//搜索推荐位的数据生成微信图文列表
	private function imgList($w=''){
		$imgAd = Db::view('SystemTag','tag_name')->view('SystemAd','id,tag_id,title,od,img,href,add_time,start_time,end_time,status','SystemTag.tag_id=SystemTag.tag_id','LEFT')->where($w)->order('SystemAd.add_time desc')->limit('0,10')->select();

		$tpl = '';
		if($imgAd){
			$content=count($imgAd);
			$tpl .= '<xml>
					<ToUserName><![CDATA[%s]]></ToUserName>
					<FromUserName><![CDATA[%s]]></FromUserName>
					<CreateTime>%s</CreateTime>
					<MsgType><![CDATA[%s]]></MsgType>
					<ArticleCount>'.$content.'</ArticleCount>
					<Articles>';
			foreach($imgAd as $ad){
				$tpl.='<item>
				<Title><![CDATA['.$ad["title"].']]></Title>
				<Description><![CDATA[]]></Description>
				<PicUrl><![CDATA['.IMG_PUBLIC. 'attached/min/'.$ad["img"].']]></PicUrl>
				<Url><![CDATA['.$ad["href"].']]></Url>
				</item>';
			}
			$tpl .= '</Articles> </xml>';
		}
		return $tpl;
	}
	//模板消息（需求关注后才能推到）
	/*$wx = new \WeiXin($config['APPID'],$config['SECRET'],$config['TOKEN']);
	$a = $wx->send_mode_msg($token_arr['openid'],'EqT35AOhWKP05lZ7n3J8nfw5eSZsdi1_55lhahDSias','http://www.baidu.com',array(
		'first'=>array('value'=>urlencode("欢迎回来"),'color'=>"#336699"),
		'keyword1'=>array('value'=>urlencode(date('Y-m-d H:i')),'color'=>'#336699'),
		'keyword2'=>array('value'=>urlencode('IOS'),'color'=>'#336699'),
		'keyword3'=>array('value'=>urlencode('192.168.1.30'),'color'=>'#336699'),
		'remark'=>array('value'=>urlencode('如非本人操作，请立即访问桌面版修改密码'),'color'=>'#FF0000'),
	));
	trace($a);
	*/

	//需要在“公众号设置” -> “功能设置”中设置:JS接口安全域名
	public function api(){
		$config = config('WEIXIN');
        $wx = new \WeiXin($config['APPID'],$config['SECRET'],$config['TOKEN']);
		$arr = $wx->getSignPackage(input('href'));
		$this->setValue($arr);
	}

	//获取在微信服务器中的图片
	public function getImg(){
		$arr = $this->needs(input('post.'),['token','img_id','folder','type']);

		$config = config('WEIXIN');
        $wx = new \WeiXin($config['APPID'],$config['SECRET'],$config['TOKEN']);
		$url =  "http://file.api.weixin.qq.com/cgi-bin/media/get?access_token=".$wx->getAccessToken()."&media_id=".$arr['img_id'];

		$name = time();
		//trace($url);
		$path = curlPost(IMG_HTTP.'Upload/urlImg',array('folder'=>$arr['folder'],'url'=>$url,'name'=>$name));
		//trace("11111111111111111");
		//trace($path);
		
		/* if(!isset($path['code'])){
			$path = $name.'.jpg';
			//箱封识别
		} */
		if($path){
			//箱封识别
			\think\Loader::import('.outer.OrcSeal');
			$rs = new \OrcSeal;
			//箱号
			if($arr['type']=='containerNo'){
				//trace(IMG_PUBLIC.$arr['folder'].'/'.$path);
				$re=$rs->container(['url'=>IMG_PUBLIC.$arr['folder'].'/'.$path,'type'=>'url']);
				if($re['status']=="ok"){
					$str=[
						'code'=>$re['status'],
						'tare'=>$re['results']['tare'],
						'container_code'=>!empty($re['results']['container_code']) ? $re['results']['container_code'][0] : ''
					];
				}else{
					$str=['code'=>'no','msg'=>$re['message']];
				}
			}else{//封号
				$re=$rs->seal(['url'=>IMG_PUBLIC.$arr['folder'].'/'.$path,'type'=>'url']);
				//trace($re);
				if($re['status']=="ok"){
					$str=[
						'code'=>$re['status'],
						'seal_code'=> !empty($re['results']['seal_code']) ? $re['results']['seal_code'][0] : ''
					];
				}else{
					$str=['code'=>'no','msg'=>$re['message']];
				}
			}
		}
		$str['url']=$path;
		//trace($str);
		echo json_encode($str);
		//echo json_encode(date('Ym').'/'.$path);
	}

	public function getUserInfo(){
		$arr = $this->needs(input('get.'),['code']);
        //echo '第一步获取到的参数：';
		$config = config('WEIXIN');
        $get_token_url="https://api.weixin.qq.com/sns/oauth2/access_token?appid=".$config['APPID']."&secret=".$config['SECRET']."&code=".$arr['code']."&grant_type=authorization_code";

        //echo '第二步获取的token：';
        $token_arr = json_decode(file_get_contents($get_token_url),true);
		if(isset($token_arr['openid'])){
			//echo '第三步获取的用户信息';
			$user_info = json_decode(file_get_contents("https://api.weixin.qq.com/sns/userinfo?access_token=".$token_arr['access_token']."&openid=".$token_arr['openid']."&lang=zh_CN"),true);
			//报错查询地址：http://mp.weixin.qq.com/wiki/17/fa4e1434e57290788bde25603fa2fcbd.html
			
			//已注册过用户使用open_id登录
			$arr = Db::name('User')->where('status=1 AND open_id="'.$token_arr['openid'].'"')->field(['user_id'=>'uid','user_name','user_img','open_id','sex','status','debug'])->order("user_id DESC")->find();
			if(!$arr){
				$arr = array('open_id'=>$token_arr['openid'],'sex'=>$user_info['sex'],'user_img'=>$user_info['headimgurl'],'user_name'=>$user_info['nickname']);
			}else{
				//如果系统有退出功能或不允许使用open_id自动登录，则注释该行
				$arr['token'] = hy_token($arr['uid'],'CODE');
			}
		}else{
			$arr = array('code'=>'NO','msg'=>'open_id获取失败');
		}
		$this->setValue($arr);
    }

}