<?php
namespace app\controller;
use think\Db;

class Prize extends A
{
	
	// 检查用户抽奖还能参与的次数  
    public function game_times() {
		$arr = $this->needs(input(''),['token','game_id','type']);
		
		$arr = $this->config($arr);
		$arr['end_time'] = time() + (30*60); //如果进入时已返回奖品，抽奖指定过期时间，以免手机时间有误，服务器删除数据时间延长至30分钟

		$this->setValue($arr);
    }
	
	// 形势一：抽奖并返回所剩抽奖次数(适用于摇一摇)
	public function game_prize() {
		$arr = $this->needs(input(''),['token','game_id']);
		$arr['type'] = 1;
		$rs = array('alert_type'=>0,'msg'=>'未中奖');
		
		$arr = $this->config($arr);
		
		if(isset($arr['df']) && $arr['df']==0 && $arr['num1']<=$arr['num']){
			$rs = array('alert_type'=>1,'title'=>$arr['title'],'img'=>$arr['img']);
		}
		
		$this->setValue($rs);
	}
	
	// 形势二：将未激活的奖品做处理(适用于刮刮卡)
	// @param prizer_id  中奖记录id
	// @param df  该奖项是否是奖品，0：是奖品('激活该奖项')  1:不是奖品('删除该奖项')
	public function game_over(){
		$arr = $this->needs(input(''),['token','prizer_id']);
		$str = array('alert_type'=>0,'msg'=>'未中奖');

		$prizer = Db::view('Prizer','prizer_id')->view('Prize','df,title,img','Prizer.prize_id=Prize.prize_id','LEFT')->where('Prizer.user_id='.$arr['user_id'].' AND Prizer.jihuo=0')->find();
		
		//激活中奖的奖品并添加日志
		if($prizer){
			Db::name('Prizer')->where('user_id='.$arr['user_id'].' AND prizer_id='.$arr['prizer_id'])->setField('jihuo', '1');
			if($prizer['df']==0){
				$str = array('alert_type'=>1,'title'=>$prizer['title'],'img'=>$prizer['img']);
			}
		}

		$this->setValue($str);
	}
	
	//type => 1:只获取抽奖次数，不抽奖;   2:获取抽奖次数,并且抽奖
	protected function config($arr){
		$game = Db::name('PrizeGame')->where('status=1 AND game_id='.$arr['game_id'])->find();
		if(!$game){
			$this->setValue(array('code'=>'NO','msg'=>'活动不存在'));
		}else{
			$now = time();
			
			if($game['start_time']>$now){
				$this->setValue(array('code'=>'NO','msg'=>'活动还未开始'));
			}else if($game['end_time']<$now){
				$this->setValue(array('code'=>'NO','msg'=>'活动已结束'));
			}else{
				//查询当天已抽奖次数
				$start = strtotime(date('Y-m-d 0:0',time()));
				$prizeCount = Db::name('Prizer')->where('status=1 AND user_id='.$arr['user_id'].' AND game_id='.$arr['game_id'].' AND add_time>'.$start)->count();
				//还可以抽奖的次数
				$times = $game['num']- $prizeCount;
				if($times>0){
					if($arr['type']==1){
						//$arr['is_prize'] = 1;
						$rs = $this->prize($arr['user_id'], $arr['game_id'],'1'); //抽奖
						$rs['times'] = $times-1;
					}else if($arr['type']==2){
						Db::name('Prizer')->where('jihuo=0 AND user_id='.$arr['user_id'])->delete();	//奖品5分钟内未激活
						Db::name('Prizer')->where('jihuo=0 AND add_time<'.(time()-2*60*60))->delete();	//奖品5分钟内未激活
						$rs = $this->prize($arr['user_id'], $arr['game_id'],'0'); //抽奖,不激活
						$rs['times'] = $times;
					}else if($arr['type']==3){
						$this->setValue(array('code'=>'YES','times'=>$times));
					}else{
						$this->setValue(array('code'=>'NO','msg'=>'请指定游戏类型'));
					}
				}else{
					$this->setValue(array('code'=>'NO','msg'=>'今天已无抽奖机会'));
				}
			}
		}
		return $rs;
	}
	
	// 用户抽奖
    // @param $uid 用户的id
    // @param $game_id  游戏id
    // @param $jihuo  抽到奖品时是否激活
    protected function prize($uid, $game_id,$jihuo=0) {
		$prize_not_activated = Db::name('Prizer')->where('jihuo=0')->select();
		
		//恢复至奖品库存中
		/*foreach($prize_not_activated as $n){
			if($n['add_time'] < (time()-2*60*60)){
				$PrizerView->decPrize(array('prize_id'=>$n['prize_id']));
			}
		}*/
		
		//可抽的奖品
		$list = Db::name('Prize')->where('status=1 and num1<num and game_id =' . $game_id)->field('prize_id,title,img,game_id,num,num1,df')->order('num-num1 asc')->select();

        //如果有奖品可抽
        $result = '';//抽奖结果
        if ($list) {
            $prizeSum=0;//可抽奖品总数量
            foreach($list as $l){
                $prizeSum+=($l['num']-$l['num1']);
            }

            foreach($list as $k=>$l){
                $randNum = mt_rand(1, $prizeSum);  //抽取随机数
				trace('从'.$prizeSum.'抽取的随机数'.$randNum);
                if ($randNum <= $l['num']-$l['num1']) {
                    $result = $l;
                    $l['num1'] += 1; //已送出数加1
					Db::name('Prize')->update($l);	//增加抽奖次数
					$user = Db::name('User')->where('user_id='.$uid)->field('user_name,name,phone')->find();
					$result['prizer_id'] = Db::name('Prizer')->insertGetId(array('prize_id' => $l['prize_id'], 'user_id' => $uid, 'game_id' => $game_id, 'user_name' => $user['user_name'], 'user_phone' => $user['phone'],'jihuo'=>$jihuo,'add_time'=>time()));
                    break;
                } else {
                    $prizeSum -= ($l['num']-$l['num1']);
                }
            }
        }else{//无奖品可抽
        	//所有非奖项
			$noprize = Db::name('Prize')->where('df=1 AND status=1 and game_id =' . $game_id)->find();
			if($noprize){
				$result['prizer_id'] = Db::name('Prizer')->insertGetId(array('prize_id' => $noprize['prize_id'],'user_id' => $uid, 'game_id' => $game_id, 'jihuo'=>$jihuo,'add_time'=>time()));
			}
			$this->addLog('奖池中已无奖品');
        }

        unset ($list);
		trace('用户【'.$uid.'】抽奖的结果是：'.json_encode($result));
        return $result;
    }
}