<?php
namespace app\controller;
use think\Db;

class Printer extends A
{
	//我的打印任务
	public function printList(){
		$arr = $this->needs(input('post.'),['token','type']);
		$user = Db::name('User')->field('message,phone')->find($arr['user_id']);
		$w='driver_id='.$arr['user_id'];
		if($arr['type']==1){
			$od="print_id desc";
			$w .=" AND (status in(1,2) AND add_time > ".(strtotime(date("Y-m-d"))-((config("dy_time"))*86400)).")";
		}else{
			$od="p_end_time desc";
			$w .=" AND status in(3,4,5)";
		}
		
		$count=Db::name('ShopPrint')->where($w)->count();
		if($count){
			$str['page'] = paging($count,input('page',1),config('PAGE_NUM'));
			$str['list']=Db::name("ShopPrint")->where($w)->order($od)->limit($str['page']['limit'])->select();
			$address=Db::name("DbStorage")->field("jing_du,wei_du,address,name,tel")->where("status!=3")->select();
			
			foreach($str['list'] as $k=>$v){
				$str['list'][$k]['jing_du']='';
				$str['list'][$k]['wei_du']='';
				$str['list'][$k]['address']='';
				$str['list'][$k]['phone']='';
				foreach($address as $kk=>$vv){
					if($v['storage']==$vv['name']){
						$str['list'][$k]['jing_du']=$vv['jing_du'];
						$str['list'][$k]['wei_du']=$vv['wei_du'];
						$str['list'][$k]['address']=$vv['address'];
						$str['list'][$k]['phone']=$vv['tel'];
					}
				}
			}
			$data=json_decode($user['message'],true);
			$data['O1']=0;
			Db::name("User")->where("user_id=".$arr['user_id'])->setField("message",json_encode($data));
		}else{
			$str['list']=array("code"=>"NO","msg"=>"暂无打印单");
		}
		$str['phone']=$user['phone'];
		$this->setValue($str);
	}

	//判断支付金额
	public function PayPrint(){
		$arr = $this->needs(input(''),['sn']);
		$print = Db::name('ShopPrint')->field("money,driver_id")->find($arr['sn']);
		$user=Db::name("User")->field("open_id,phone,user_name,user_id,money,song_money,use_money")->find($print['driver_id']);
		trace($user);
		trace("账户余额信息：");
		if(($user["money"]+$user["song_money"]-$user['use_money']) < $print['money']){
			/* if($print['driver_id']==4){
				$rs=['msg'=>'金额不足','code'=>"NO",'money'=>($print['money']-($user["money"]+$user["song_money"]-$user['use_money']))];
			}else{
				$rs=['msg'=>'金额不足','code'=>"NO",'money'=>$print['money']];
			} */
			$rs=['msg'=>'金额不足','code'=>"NO",'money'=>$print['money']];
			
		}else{
			$rs=['msg'=>'金额充足','code'=>"YES"];
		}
		trace($rs);
		$this->setValue($rs);
	}
	
	//点击打印
	public function setPrinter(){
		$arr = $this->needs(input(''),['sn','qr_id']);
		
		if(!Db::name('ShopPrint')->where('status=2 AND mp_id='.$arr['qr_id'])->find()){
			$data = Db::name('ShopPrint')->where('print_id='.$arr['sn'])->find();
			
			if($data){
				$user=Db::name("User")->field("open_id,user_id,money,song_money,use_money")->find($data['driver_id']);
				if(($user["money"]+$user["song_money"]-$user['use_money'])<$data['money']){
					$rs = array('code'=>'NO','msg'=>'要打印的数据不存在');
				}
			
				if($data['status']==1){
					Db::name('ShopPrint')->where('print_id='.$arr['sn'])->update(array('status'=>'2','mp_id'=>$arr['qr_id']));
					$rs = array('code'=>'YES','msg'=>'正在打印，请稍后...');
				}else if($data['status']==2){
					$rs = array('code'=>'YES','msg'=>'正在打印，请稍后...');
				}else{//status=3
					$rs = array('code'=>'NO','msg'=>'该任务已打印过');
				}
			}else{
				$rs = array('code'=>'NO','msg'=>'要打印的数据不存在');
			}
		}else{
			$rs = array('code'=>'NO','msg'=>'前面有单子未打成完成');
		}
		
		//trace($rs);
		$this->setValue($rs);
	}
	
	//打印直接付款
	public function PayRecharge(){
		$arr = $this->needs(input('post.'),['token','print_id']);
		$user=Db::name("User")->field("open_id,user_id,name,")->find($arr['user_id']);
		$bu=Dn::name("ShopPrint")->field("money")->find($arr['print_id']);
		
		$data=['open_id'=>$user['user_id'],'amount'=>$user['money'],'order_no'=>'FK'.order_num(),'title'=>'直接付款'];
		if(in_array($arr['id'],['4,5'])){
			$data['amout']=0.01;
		}
		$pay = new \app\controller\clas\Pay();
		if(isset($arr['type']) && $arr['type']==2){
			$str=$pay->buyH5($data);
		}else{
			$str = $pay -> buy($data);
		}
		
		trace($str);
		$this->setValue($str);
	}
	
	
	
	//打印错误反馈
	public function printError(){
		$arr = $this->needs(input('post.'),['token','title','print_id']);
		//trace($arr);
		$arr['add_time']=time();
		$log=Db::name("LogPrintError")->where("print_id=".$arr['print_id'])->count();
		if($log >3){
			$this->setValue(array("code"=>"NO","msg"=>"反馈次数超限"));
		}else if(Db::name('LogPrintError')->insert($arr)){
			$config=config("jxt_account");
			$user=Db::name("User")->field("phone,name,user_name,user_id,car_num,open_id")->where("user_id=".$arr['user_id'])->find();
			if(!$user){
				$this->SystemError("您派单的司机信息出错");
			}
			
			$print=Db::name("ShopPrint")->find($arr['print_id']);
			$box=$bu=Db::name("ShopBusiness")->find($print['id']);
			$t=time();
			
			unset($box['id']);
			/* if($box['is_expatriate']==1){
				$box['driver_in_name']=$box['name'];$box['driver_in_phone']=$box['phone'];$box['driver_in_num']=$user['car_num'];
			}else{
				$box['driver_out_name']=$user['name'];$box['driver_out_phone']=$user['phone'];$box['driver_out_num']=$user['car_num'];
			} */
			$box['add_time']=$t;
			$box['shop_id']=$config['user_id'];
			$box['su_id']=$config['user_id'];
			$box['shop_user_name']=$config['user_name'];
			if($id=Db::name("ShopBusiness")->insertGetId($box)){
				$data=json_decode($print['print_data'],true);
				if($data['need_print_content']==1 || $data['need_print_content']==3){
					$dd[1]=$this->save_img(array('width'=>'1000','img_type'=>'.jpg','month'=>false,'name'=>$t.'_'.$config['user_id'],'folder'=>'shebei/oimg/'.date("Ym",$t),'img_path'=>'public/shebei/oimg/'.date("Ym",$print['img_name']).'/'.$print['img_name'].'_'.$print['user_id'].'.jpg'));	
					$data['notice_url']=IMG_PUBLIC.'shebei/oimg/'.date("Ym",$t).'/'.$t.'_'.$config['user_id'].'.jpg';
				}
				if($data['need_print_content']==2 || $data['need_print_content']==3){
					$dd[2]=$this->save_img(array('width'=>'1000','img_type'=>'.jpg','month'=>false,'name'=>$t.'_'.$config["user_id"],'folder'=>'taoda/'.date("Ym",$t),'img_path'=>'public/taoda/'.date("Ym",$print['img_name']).'/'.$print['img_name'].'_'.$print['user_id'].'.jpg'));						
				}
				/* 
				$data['user_id']=$user['phone'];
				$data['datetime']=date("YmdHis");
				$data['out_order_no']='JX'.$config['user_id'].'_'.$t;
				$data['notice_no']=$id;
				$data['print_date_start']=date("Y-m-d H:i:s",$t);
				$data['print_date_end']=date("Y-m-d H:i:s",strtotime(date("Y-m-d",strtotime("+1 day")))+(config("dy_time")*86400));
				 */
				/* \think\Loader::import('.outer.Printer');//引入类文件
				$rs = new \Printer;
			
				$re=$rs -> addCreatePrint($data);
				
				if($re['result']==1){ */
					unset($print['print_id']);
					$add=[
						"order_id"=>'JX'.$config['user_id'].'_'.$t,
						"id"=>$id,
						"user_id"=>$config['user_id'],
						"user_name"=>$config['name'],
						"nick_name"=>$config['user_name'],
						"user_phone"=>$config['phone'],
						"add_time"=>$t,
						"driver_id"=>$user['user_id'],
						"driver_name"=>$user['name'],
						"driver_phone"=>$user['phone'],
						"driver_num"=>$user['car_num'],
						"jp_sn"=>1,
						"jp_order_no"=>$print['jp_order_no'],
						"money"=>0,//config("dy_money")
						"storage"=>$box['suitcase_port'],
						"note"=>$box['note'],
						"print_time"=> strtotime(date("Y-m-d",strtotime("+1 day")))+(config("dy_time")*86400),
						'img_name'=>$t,
						"need_print_content"=>$print['need_print_content'],
					];
					$add['print_data']=json_encode($add,JSON_UNESCAPED_UNICODE);
					Db::name("ShopPrint")->insert($add);
					//$str=$this->sendMessage(array("open_id"=>$user['open_id'],"name"=>$config['name'],"phone"=>$user['phone']),'task',1);
					$str=array("code"=>"YES","msg"=>"亲，我们已收到您的反馈，请重新打印您的箱单。");
				/* }else{
					$this->SystemError("加派单失败");
				} */
			}else{
				$str=array("code"=>"NO","msg"=>"反馈失败");
			}
			
			/* $box=Db::view("ShopBusiness",'*')->view("ShopPrint","driver_id,driver_name,driver_num,need_print_content,driver_phone","ShopPrint.business_id=ShopBusiness.id")->where("ShopBusiness.id=1")->find();

			$img_url=date('Ym',$box['add_time']).'/'.$box['add_time'].'_'.$box['driver_id'].'.jpg';
			$t=time();
			\think\Loader::import('.printer.PhpPrint');//引入类文件
			$rs = new \PhpPrint;
			$re=$rs -> createPrint(array(
				"box"=>$box ,
				'id'=>'Sas'.$config['user_id'].'_'.$t,'add_time'=>$t,'end_time'=>(strtotime(date("Y-m-d",strtotime("+1 day")))+(config("dy_time")*86400)),
				'phone'=>$box['driver_phone'],
				'img'=>$box['need_print_content']
			));
			trace($re);
			$str=array("code"=>"NO","msg"=>"反馈失败，请稍后再试");
			if($re['result']==1){
				$box['add_time']=$t;
				$box['shop_id']=$config['user_id'];
				$box['status']=1;
				unset($box['id']);
				$box['id']=Db::name("ShopBusiness")->insertGetId($box);
			
				$print_id=Db::name("ShopPrint")->insertGetId(array(
					"shop_id"=>$config['user_id'],
					"order_id"=>'Sas'.$config['user_id'].'_'.$t,
					"id"=>$box['id'],
					"img_name"=>$t,
					"need_print_content"=>$box['print_content'],
					"user_id"=>$config['user_id'],
					"user_name"=>$config['name'],
					"nick_name"=>$config['user_name'],
					"user_phone"=>$config['phone'],
					"add_time"=>$t,
					"driver_id"=>$box['driver_id'],
					"driver_name"=>$box['driver_name'],
					"driver_phone"=>$box['driver_phone'],
					"driver_num"=>$box['driver_num'],
					"jp_sn"=>$re['print_code'],
					"jp_order_no"=>$re['jp_order_no'],
					"money"=>config("dy_money"),
					"storage"=>$box['suitcase_port'],
					"note"=>$box['note'],
					"print_time"=>strtotime(date("Y-m-d",strtotime("+1 day")))+(config("dy_time")*86400)
				));
				
				if($print_id){
					if($box['print_content']==3){
						$dd[1]=$this->save_img(array('width'=>'1000','img_type'=>'.jpg','month'=>true,'name'=>$t.'_'.$config['user_id'],'folder'=>'shebei/oimg','img_path'=>'/public/shebei/oimg/'.$img_url,'width1'=>'50',"folder1"=>'shebei/min'));
						$dd[2]=$this->save_img(array('width'=>'1500','img_type'=>'.jpg','month'=>true,'name'=>$t.'_'.$config['user_id'],'folder'=>'box/oimg','img_path'=>'/public/box/oimg/'.$img_url,'width1'=>'50',"folder1"=>'box/min'));
					}else if($box['print_content']==1){
						$dd[1]=$this->save_img(array('width'=>'1000','img_type'=>'.jpg','month'=>true,'name'=>$t.'_'.$config['user_id'],'folder'=>'shebei/oimg','img_path'=>'/public/shebei/oimg/'.$img_url,'width1'=>'50',"folder1"=>'shebei/min'));
					}else{
						$dd[2]=$this->save_img(array('width'=>'1500','img_type'=>'.jpg','month'=>true,'name'=>$t.'_'.$config['user_id'],'folder'=>'box/oimg','img_path'=>'/public/box/oimg/'.$img_url,'width1'=>'50',"folder1"=>'box/min'));
					}
					
					$str=$this->sendMessage(array("name"=>$config['name'],"phone"=>$box['driver_phone']),'print_code',3);
					//Db::name("UserLog")->insert(array("title"=>"派发成功，司机账号:".$car['phone'].'，open_id：'.$car['open_id'].',派单人：'.$config['phone'],"message"=>json_encode($str),'action'=>'team/'.$this->request->controller().'/'.$this->request->action(),'ip'=>$this->request->ip(),"add_time"=>time()));
					$this->addMessage(array('user_id'=>$box['driver_id'],'send_num'=>true));//添加消息条数
					$str=array("code"=>"YES","msg"=>"亲，我们已收到您的反馈，请重新打印您的箱单。");
				}
			} */
		}else{
			$str=array("code"=>"NO","msg"=>"反馈失败");
		}
		$this->setValue($str);
	}
	
	//获取箱封号
	public function getXfh(){
		$arr = $this->needs(input('post.'),['token','print_id']);
		
		$info=Db::name("ShopPrint")->field("title_no_img,id,container_no_img,title_no,container_no,container_weight")->where("print_id=".$arr['print_id'])->find();
		$this->setValue($info);
		
	}
	
	//上传箱封号
	public function uploadXfh(){
		$arr = $this->needs(input('post.'),['token','name','container_no','title_no','business_id','print_id','container_no_img','title_no_img','upload_times']);
		trace($arr);
		$cc=[];
		$str=['code'=>"NO",'msg'=>'提交失败'];
		$t=time();
		if($arr['container_no_img']!=$arr['containerName']){
			if($arr['container_no_img']){
			$img1=$this->save_img(array('width'=>'800','img_type'=>'.jpg','month'=>true,'name'=>$t,'folder'=>'containerNo','img_path'=>'/public/temp/'.$arr['container_no_img']));
			trace($img1);
			if(!isset($img1['code']) && $img1['code']!="YES"){
				$this->setValue(['code'=>"NO",'msg'=>'当前网络缓慢，请稍后再试']);
			}
			$arr['container_no_img']=$t;
			$cc['container_no_img']=$t;
			}else{
				$arr['container_no_img']='';
				$cc['container_no_img']='' ;
			}
		}else{
			unset($arr['container_no_img']);
		}
		if($arr['title_no_img']!=$arr['titleName']){
			if($arr['title_no_img']){
				$img2=$this->save_img(array('width'=>'800','img_type'=>'.jpg','month'=>true,'name'=>$t,'folder'=>'titleNo','img_path'=>'/public/temp/'.$arr['title_no_img']));
				trace($img2);
				if(!isset($img2['code']) && $img2['code']!="YES"){
					$this->setValue(['code'=>"NO",'msg'=>'当前网络缓慢，请稍后再试']);
				}
			
				$arr['title_no_img']=$t;
				$cc['title_no_img']=$t;
			}else{
				$arr['title_no_img']='';
				$cc['title_no_img']='';
			}
		}else{
			unset($arr['title_no_img']);
		}
		unset($arr['user_id']);
		if(Db::name("ShopPrint")->where("print_id=".$arr['print_id'])->update($arr)){
			//if($arr['upload_times']==1){
				$info=Db::name("ShopBusiness")->field("box_bill1")->find($arr['business_id']);
				$data=json_decode($info['box_bill1'],true);
				$data['container_no']=$arr['container_no'];
				$data['title_no']=$arr['title_no'];
				
				Db::name("ShopBusiness")->where("id=".$arr['business_id'])->update(array_merge($cc,['box_bill1'=>json_encode($data)]));
				trace(Db::name("ShopBusiness")->getLastSql());
			//}
			$str=['code'=>"YES",'msg'=>'提交成功'];
		}
		
		$this->setValue($str);
	}

	
	
	
	//远程图片缩小处理
	public function save_img($arr){
		$rs = sendPost(IMG_HTTP.'Upload/minImg',$arr);
		
		return  $rs=json_decode($rs,true);
	}
	
} 
