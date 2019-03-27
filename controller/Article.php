<?php
namespace app\controller;
use think\Db;
use think\Cache;

class Article extends A
{
	// 资讯列表
	public function listing(){
		$arr = input('');

		$where='status=1 AND start_time<'.time();
		if(isset($arr['id']) && $arr['id']!=0){
			$where .= ' AND find_in_set('.$arr['id'].',tag_id)';
		}

		$count = Db::name('Article','article_id')->where($where)->count();
		if($count){
			$rs['page'] = paging($count,input('page',1),config('PAGE_NUM'));
			$rs['list'] = Db::name('Article','article_id,title,img,url,add_time,locked,tap_total,taklimat,od')->limit($rs['page']['limit'])->order('article_id desc')->where($where)->select();
		}else{
			$rs = $this->addLog('暂无数据');
		}

        $this->setValue($rs);
	}
	
	//获取文章分类
	public function getArticleCategory(){
		$rs = Db::name('SystemTag')->order('tag_od ASC')->where('status=1 AND p_id='.config('article_cat'))->field('tag_id,tag_name')->select();	//所属活动
		
		$this->setValue($rs);
	}
	
	public function listing2(){
		$this->listing();
	}
	
	public function listing3(){
		$this->listing();
	}

    // 活动详情
    public function detail() {
		$arr = $this->needs(input(''),['article_id']);

		//添加浏览量
		if(isset($arr['uaid'])){
			Db::name('Article')->where('article_id',$arr['uaid'])->setInc('tap_total', 1);
		}

		$vo = $this->needCache('article_detail'.$arr['article_id'],function($arr){
			$vo['detail'] = Db::name('Article')->field('article_id,title,img,start_time,taklimat,img_info,content,tap_total,comment_total,zan_total,type,url,attr')->find($arr['article_id']);
			if($vo['detail']){
				$vo['detail']['content'] = $vo['detail']['content'] ? change_img_path($vo['detail']['content']) : '';
			}
			return $vo;
		},$arr);

        $this->setValue($vo);
    }

}
