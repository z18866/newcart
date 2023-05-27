<?php

namespace app\index\controller;

use addons\wechat\model\WechatCaptcha;
use app\common\controller\Frontend;
use app\common\library\Ems;
use app\common\library\Sms;
use app\common\model\Attachment;
use think\Config;
use think\Cookie;
use think\Hook;
use think\Session;
use think\Validate;
use think\Db;
use think\Request;

/**
 * 会员中心
 */
class Cart extends User
{
    //protected $layout = 'default';
   // protected $noNeedLogin = ['checkout','head_info','firecheckout','cartinfo','updatainfo','get_firecheckout'];
    protected $noNeedLogin = '*';
    protected $noNeedRight = ['*'];
  	public function __construct() {
		parent::__construct();
		/*定义优惠券返回错误提示*/
		$this->use_coupon_alert='Coupon code error';
		$this->site=Config("site");
  	}
  	/* 购物车
     * $cache 是否开启缓存
	 */   
  	public function cart($cache='1'){	
  	         $unitname='USD';
             if(isset($this->auth->id)){
                 	$user_id=$this->auth->id;
                 	$sqlname='cart';
                 	$where=["a.user_id"=> $user_id,"a.status"=>"normal"];
                 	$SessionID=$user_id;
                 	
                 	 /*注册用户先判断是否需要改变缓存id*/
                 	/*$ip=getUserCartId();
                 	if(Session::has('user_cart_'.$ip)){   
                 		Session::set('user_cart_'.$SessionID,Session::get('user_cart_'.$ip));
                    	Session::set('user_cart_'.$ip,null);    
                 	}*/
             }else{
                    $ip=getUserCartId();	
                 	$user_id=0;
                 	$sqlname='cart_nologin';
                 	$where=["a.userip"=>$ip,"a.status"=>"normal"];
                 	$SessionID=$ip;
             }
             
             /*配件关闭不调取*/
               $site =$this->site;
            if(!$site['accessories__on']){
                 $where['a.type']=['not in','2'];
          
             }
        	if(!Session::has('user_cart_'.$SessionID) || !$cache){   
    
    		$info=Db::name($sqlname)
    		 ->alias("a")
    		 ->field("b.category_id,b.diy_url,b.discount as shop_discount,b.price as b_price,b.sale_price as b_sale_price,c.price as c_price,c.sale_price as c_sale_price,c.id as sku_id, c.goods_sn,a.*,b.image,b.title,b.price,b.sale_price,b.tag,b.title,b.image,c.price,c.sale_price,c.sku_attr")
    		 ->join("shop b" ,"FIND_IN_SET(b.id,a.shop_id)","LEFT")
    		 ->join("sku_shop c" ,"FIND_IN_SET(c.id,a.sku_shop_id)","LEFT")
    		 ->where($where)
    		 ->select(); 
    		 
    
    		 $doller_total=$total=$nums=$old_total=0;
    		 
    		
    		 if(!empty($info)){
    	
    		  foreach($info as $key=>$v){
    		  
    		          
    		     
    			  $info[$key]=$v;
    			  /*配件不获取运费价格*/
    			  if($v['type']==2){
    			  $info[$key]['price']=$v['b_price'];
    			  $info[$key]['sale_price']=$v['b_sale_price'];    	
    		
    			  }else{
    			  $info[$key]['price']=$v['b_price']+$v['c_price'];
    			  $info[$key]['sale_price']=$v['b_sale_price']+$v['c_sale_price'];     
    			
    			  }
    		
    			
    			  $info[$key]['discount']=$v['shop_discount'];
    			  /*参数为空不显示产品*/
    			  if(empty($v['sku_attr']) && !empty($v['sku_shop_id']) || !empty($v['sku_attr']) && empty($v['sku_shop_id'])){
    			      unset($info[$key]);
    		      }
    		  }
    
    		 $cart['cart']=1;
    		 $unit='';
    		 $cart_type=$group_id=$group_ids=[];
    
             $info_data=$info;	 
             $info=$activity=[];
             /*分类和商品，区分优惠券使用*/
             $shop_category_shop=[];
    		 foreach($info_data as $key=>$v){
  
    		    
    			 $info[$key]=calculated_amount($v,$cart);
    			 $info[$key]['current_price']= $info[$key]['unit'].number_format($info[$key]['price_unit']*$v['num'],2);
    			 $info[$key]['current_sale_price']= $info[$key]['unit'].number_format($info[$key]['sale_price_unit']*$v['num'],2);
    			 /*根据有无优惠来计算总和*/
    			 if($info[$key]['discount']>0){
    			    $old_total+=$info[$key]['price_unit']*$v['num'];  
    			 }else{
    			     /*无优惠时市场价强制与出售价同步*/
    			     $info[$key]['price_unit']=$info[$key]['sale_price_unit'];
    			     $old_total+=$info[$key]['sale_price_unit']*$v['num']; 
    			 }

    		   
    		     
    			  $total+=$info[$key]['sale_price_unit']*$info[$key]['num'];
    			  //$doller_total+=$v['sale_price']*$v['num'];
    			  $unitname=$info[$key]['unit_name'];
    			  $unit=$info[$key]['unit'];
    			  $nums+=$info[$key]['num'];
    			  if(!empty($v['group_id'])){
    				  $group_id[$v['group_id']]=$v['type'];
    				  $group_ids[$v['group_id']]=$v;
    			  }
    			 
    			  /*优惠价格*/
    			  if(isset($info[$key]['active_name'])){
    				$activity[$info[$key]['shop_id']]=['active_type'=>$info[$key]['type'],'active_disconut'=>$info[$key]['active_price'],'active_name'=>$info[$key]['active_name'],'nums'=>$info[$key]['num']];      
    			  }
     
    			  /*统计类型组*/
    			  $cart_type[$v['type']][]=$v;
    			 
    		 }
    		

    	
           	/*获取用户礼物数据*/
        	$gift=$this->getUserGift(); 
        	$this->assign('gift',$gift); 	
        	
            /*购物车商品种类数量*/
            $shop_num=count($info);
            
            $gift_nums=count($gift);
            
            if($gift_nums>0){
                $nums+= $gift_nums;
                $shop_num+=$gift_nums;
            }
    		 // 	var_dump($info);exit;
    
                /*old_total 商品原出售价总和，不包含活动价格*/	
    		$return=['num'=>$nums,'shop_num'=>$shop_num,'unit_name'=>$unitname,'total'=>$unit.sprintf("%01.2f",$old_total),'money'=>$total,'old_total'=>$old_total,'sale_total'=>$unit.number_format($total,2),'group_id'=>$group_id,'group_ids'=>$group_ids,'activity'=>$activity,'cart_type'=>$cart_type,'data'=>$info];
    		 }else{
    			$return=['num'=>0,'shop_num'=>0,'total'=>0,'money'=>0,'unit_name'=>$unitname,'sale_total'=>0,'old_total'=>$old_total,'data'=>''];
    		 }
     	     Session::set('user_cart_'.$SessionID,$return);
		}else{
		    /*获取用户礼物数据*/
        	$gift=$this->getUserGift(); 
        	$this->assign('gift',$gift); 	
		    $return=Session::get('user_cart_'.$SessionID);
		   
		} 
	
        /*定义全局购物车现有数据*/
         $this->cart_data=$return['data'];
		return $return;
	
	}
    /**
     * 获取头部信息
     */
    public function head_info()
    {
     if(request()->isAjax()){ 
      $cart=$this->cart();
      $this->assign('cart',$cart); 
     // if($this->auth->id){
		$referer = $this->request->server('HTTP_REFERER');
		
	   if(empty($cart)){
	     	$html['r_cart']=$this->fetch('user/common/right_cart_empty');		  
	   }
		
	
		$html['status']='200';
		$html['type']='1';
		$html['msg']='Operation success';
		if(!IS_WAP){
		    if(!isset($this->auth->id)){
		       	$html['user']=$this->fetch('user/common/no_login'); 
		    }else{
		      	$html['user']=$this->fetch('user/common/head_info');	  
		    }
	
		}
		
		if (strpos($referer, 'firecheckout') !== false) {
		}else{
		$html['cart']=$this->fetch('user/common/head_cart');
		if(!IS_WAP){

			if(!empty($cart['data'])){
			$html['r_cart']=$this->fetch('user/common/right_cart');	
			}else{
			$html['r_cart']=$this->fetch('user/common/right_cart_empty');		
			}
		}
		}
		return $html;
     }
	
	 // }
	  /*else{
	    
		 if(!IS_WAP){
		     
		$html['user']=$this->fetch('user/common/no_login');
	//	$html['r_cart']=$this->fetch('user/common/right_cart_nouser');
		if(isset($cart['data']) && !empty($cart['data'])){
			$html['r_cart']=$this->fetch('user/common/right_cart');	
			}else{
			$html['r_cart']=$this->fetch('user/common/right_cart_empty');		
			}
		 }
	
		$html['cart']=$this->fetch('user/common/no_cart');
	
		return $html;

	  }*/
       
    }
  
	/* 结算页购物车信息
	 */ 
    public function cartinfo()
    {
		
	
		$this->assign('cart',$this->cart()); 
		$html['status']='200';
		$html['review']=$this->fetch('cart/firecheckout/cartinfo');
		return $html;
	}	
	
	/**
     *	订单使用积分减免费用
	 *  $money   订单总价
	 */ 
    protected function use_point_num($money)
    {
	$site =$this->site;
 

	$data=[];
	/*最大可兑换30%的费用*/
	$pay_dis=sprintf("%01.2f",$money*0.3);
	$point=$this->auth->point;
	$point_moeny=sprintf("%01.2f",$point/$site['point_exchange']*$this->unit['num']);
	if($point_moeny>$pay_dis){
		/*可减免费用*/
		$data['unit_money']=$pay_dis;
		$data['money']=$this->Currency_tag.$pay_dis;
		/*可兑换积分数*/
		$data['point']=intval($pay_dis*$site['point_exchange']);
		if($data['point']<1 && $data['point']>0){
		    	$data['point']=1;
		}
		
	}else{
	    //$price_changge['empty_tag']='1';
		$data['unit_money']=$point_moeny;
		$data['money']=$this->unit['tag'].$point_moeny;
		$data['point']=intval($point);
	}
	
	return $data;
	}	
	 
	/* 计算额外价格信息
	 *@discount  优惠信息
	 *@vat           增值信息
	 *@$proportion      优惠比例
	 */ 
    protected function CalculateAdditionalPrice($cart,$parm=array())
    {
  
		$site = $this->site;
		$plus=$this->auth->plus;
		$use_plus=0;
		
		$data=[];
		/*前台提交数据*/
		$inputs=input();
        
        		
		/*是否购买会员*/
		if(Session::has('use_plus') && !$plus){
		   
	    	$use_plus=Session::get('use_plus');
	   	// var_dump(	$use_plus);exit;
	
		}
	//	var_dump($use_plus);exit;
		$this->assign('is_plus', $use_plus); 
		

    	 /*根据货币计算活动显示价格*/ 
		 $change_price_tag['empty_tag']='1';
		   
		/*定义订单优惠金额百分比数据*/
		$order_discount=['discount'=>0,'money'=>0,'activity_money'=>[],'plus_money'=>0];
		$order_tal=$total=$use_plus=0;
		
	    $data['plus_price']=changeStringPrice($site['plus_price']);

		if(isset($inputs['plus_buy']) || $use_plus){

		  $plus=1;  
		  $total+=changeStringPrice($site['plus_price'],  $change_price_tag);
		 $order_tal+=changeStringPrice($site['plus_price'],  $change_price_tag);
		}
		
		/*优惠卷和积分等全部减免*/
		$code_piont=0;
		/*优惠卷和积分减免*/
		$coupon_piont=0;
		
		$data['proportion']=0;
		/*定义类型*/
		/*优惠*/
		$data['type']['0']=['name'=>'Discount','price'=>$this->Currency_tag."0.00",'price_unit'=>0,'mode'=>'-','data'=>[]];
		/*运费*/
		$data['type']['1']=['name'=>'FREE SHIPPING','price'=>$this->Currency_tag."0.00",'price_unit'=>0,'mode'=>'','data'=>[]];
		/*增值费用*/
		$data['type']['2']=['name'=>'Tax','price'=>$this->Currency_tag."0.00",'price_unit'=>0,'mode'=>'','data'=>[]];

      
		/*用户是大会员*/
		$plus_price=$cart['money']*($site['pay_discount']/100);
		$plus_price=sprintf("%01.2f",$plus_price);
		//var_dump($cart['money']);exit;
		if($plus){
		    $order_discount['plus_money']=$plus_price;
		    /*加入优惠信息*/
		    $order_discount['discount']=$site['pay_discount'];
		    
			$data['proportion']=$site['pay_discount']/100;	
			$plus_name='Plus '.$site['pay_discount'].'% Off';
			$data['type']['0']['price']=$this->Currency_tag.$plus_price;
			$data['type']['0']['price_unit']+=$plus_price;
			$data['type']['0']['data'][]=['name'=>$plus_name,'price'=>$data['type']['0']['price']];
			
			$code_piont+=$plus_price;
		}
		 /*会员减免费用*/
		$data['reduce_plus']['price']=$this->Currency_tag.$plus_price;   
	
		
		/*清仓组合活动这里有问题，需要计算其他活动，只计算了组合*/
		
		if(!empty($cart['group_id'])){
		  
		   $group_num=count($cart['group_id']);
		  
            $group_ids_key=$group_ids_parm=[];
             foreach ($cart['group_id'] as $key=>$value){
                 
              $group_ids_key[]= $key;
             }
         
		   $activity_sql=Db::name("activity")->where(['id'=>['in',$group_ids_key],'status'=>'normal'])->select();	
		  
		   foreach ($activity_sql as $v){
		        
		       if(isset($cart['group_ids'][$v['id']]) && count($cart['group_ids'][$v['id']])>1){
		           $group_ids_parm[]=$v;
		       }
		   }
	
            $combo_money=0;
            $active_name='Combo Deal';
		    foreach ($group_ids_parm as $value){
         
           $combo_money+=$value['money'];
           $order_discount['activity_money'][$value['id']]=$value['money'];
		    }
		   
		  
		   $combo_money=changeStringPrice($combo_money,$change_price_tag);
		   $combo_money=sprintf("%01.2f",$combo_money);
		   $active_price=$this->Currency_tag.$combo_money;
		   /*加入活动优惠信息*/
	
		   $data['type']['0']['data'][]=['name'=>$active_name,'price'=>$active_price];
		   $data['type']['0']['price_unit']+= $combo_money;
		   $data['type']['0']['price']= $active_price;		      
		   $code_piont+=$combo_money;
		   $active_name=$active_price='';
	      
		}   
	
		/*百分比活动优惠*/
       	if(!empty($cart['activity'])){
         foreach ($cart['activity'] as $key=>$val){
             if($val['active_type'] !='3'){
                 
           $active_name=$val['active_name'];
	       $active_price_unit=$val['active_disconut']*$val['nums'];
	       $active_price_unit= sprintf("%01.2f",$active_price_unit);
		   $active_price=$this->Currency_tag.$active_price_unit;
		   /*加入活动优惠信息*/
		  
		   $data['type'][0]['data'][]=['name'=>$active_name,'price'=>$active_price];
		   $data['type'][0]['price_unit']+= $active_price_unit;
		   $data['type'][0]['price']= $active_price;		      
           
           $code_piont+=$active_price_unit;
           $active_name=$active_price='';
             }
          }
        }
        /*cart也有增加，comment有修改*/
    	/*常规活动优惠*/
       
       	  $active_price_unit=0;
       	 if($cart['data']){
       	     
       
         foreach ($cart['data'] as $key=>$val){
             if(!isset($cart['activity'][$val['shop_id']])){
             $active_name='Product Offers';  
	         $active_price_unit+=($val['price_unit']-$val['sale_price_unit'])*$val['num'];                 
             }
             if(isset($cart['activity'][$val['shop_id']]) && $cart['activity'][$val['shop_id']]['active_type']==3){
             $active_name='Product Offers';  
	         $active_price_unit+=($val['price_unit']-$val['sale_price_unit'])*$val['num'];             
             }
      
         }
         $active_price_unit= sprintf("%01.2f",$active_price_unit);
	     $active_price=$this->Currency_tag.$active_price_unit;
		   /*加入活动优惠信息*/
		  if($active_price_unit>0){

		   $data['type'][0]['data'][]=['name'=>$active_name,'price'=>$active_price];
		   $data['type'][0]['price_unit']+= $active_price_unit;
		   $data['type'][0]['price']= $active_price;		      
           
           $code_piont+=$active_price_unit;  
		  }
       	 }   	    
    	    
       //var_dump($code_piont);exit;
 
		
		/*获取优惠券状态*/
		$current_cart_price=$parm;
		$current_cart_price['current_cart_money']=$cart['money'];
		$coupon='';
		/*购物车价值大于0再查券*/
	    if($current_cart_price['current_cart_money']>0){
	      
	     $coupon=$data['current_coupon']=$this->useCouponcenter($current_cart_price);   
	    }
	
	
		$price_changge_empty_tag['empty_tag']='1';
		
		//$coupon['result_money']=$coupon['result_money'];
		if($coupon){
			/*判断优惠卷条件是否合格*/
			if($cart['money']>=$coupon['result_number']){
				/*判断优惠卷减免类型*/
				if($coupon['result_type']){
				    /*加入优惠信息*/
				    $order_discount['discount']+=$coupon['result_money'];
					$coupon_price=$cart['money']*($coupon['result_money']/100);
				}else{
					$coupon_price=$coupon['result_money'];
					 /*加入优惠信息*/
					$order_discount['money']+=$coupon['result_money'];
				}
			$coupon_price=sprintf("%01.2f",$coupon_price);
			
			$data['type']['0']['price']=$this->Currency_tag.($data['type']['0']['price_unit']+$coupon_price);
			$data['type']['0']['price_unit']+=$coupon_price;
			$coupon_name=$coupon['coupon_name'];
			$data['type']['0']['data'][]=['name'=>$coupon_name,'price'=>$this->Currency_tag.$coupon_price];	
			/*计算优惠券减免*/	
			$code_piont+=$coupon_price;	
	        $coupon_piont+=$coupon_price;	
	         $data['use_coupon']['is_use']=1;	
			}else{
			   $data['use_coupon']['is_use']=0;	  
			}
		
		}else{
		    
		   $data['use_coupon']['is_use']=0;
		}
		
		/*运费*/
		

		/*配件*/
		if(!empty($cart['cart_type']['2'])){
			
		$acc_sku_shop_id=$acc_shop_id=[];
		foreach($cart['cart_type']['2'] as $v){
			$acc_sku_shop_id[]=$v['sku_shop_id'];
			$acc_shop_id[]=$v['shop_id'];
		}	
		
		$accessories=Db::name("sku_shop")->field('sku_id,sale_price,sku_attr')->where(['id'=>['in',$acc_sku_shop_id],'shop_id'=>['in',$acc_shop_id]])->select();	
		$shipiing=[];
		
	
		foreach($accessories as $val){
		if($val['sale_price']>0){
		    $shipiing[$val['sku_id']]=['sku_attr'=>$val['sku_attr'],'price'=>changeStringPrice($val['sale_price']),'sale_price'=>$val['sale_price']];
		  
		}	
		}
		/*清除重复，只计算一次相同运费*/
		foreach($shipiing as $val){ 
		$data['type']['1']['data'][]=['name'=>$val['sku_attr'],'price'=>$val['price']];
		$data['type']['1']['price_unit']=$data['type']['1']['price_unit']+$val['sale_price'];
		}
		
		$data['type']['1']['price']=$data['type']['1']['price_unit'];
		
		}
		
	
		/*增值费用*/
		if($site['vat']>0){
			$vat_price=$cart['money']*($site['vat']/100);
			$vat_price=sprintf("%01.2f",$vat_price);
			$vat_discount=$site['vat']/100;
			$data['proportion']=$vat_discount-$data['proportion'];	
			$vat_name='VAT ('.$site['vat'].'%)';
			$data['type']['2']['price']=$this->Currency_tag.$vat_price;
			$data['type']['2']['price_unit']=$vat_price;
			$data['type']['2']['data'][]=['name'=>$vat_name,'price'=>$data['type']['2']['price']];
		}
		
		/**获取订单总价
		 *$order_tal 订单无优惠总和
		*/		
		
		$total+=$cart['old_total'];
		$order_tal+=$cart['old_total'];
		foreach($data['type'] as $v){
			if($v['mode']=='-'){
			$total-=$v['price_unit'];	
			}else{
			$total+=$v['price_unit'];
			$order_tal+=$v['price_unit'];
			}
			
		}	
        

		/*运费险*/
	
		if($site['insurance']>0){
         	if(!Session::has('use_insurance')){
         	    
    		  	Session::set('use_insurance',0);
    			 $insurance=0;
    		
    		}else{
    		    $use_insurance=Session::get('use_insurance');
    		    if($use_insurance==1){
    		     	$insurance=sprintf("%01.2f",$total*($site['insurance']/100));   
    		     	$total+=$insurance;		
	            	$order_tal+=$insurance;
    		    }else{
    		       $insurance=0;  
    		    }
    		
    		}		    
		  
		}else{
		    $insurance=0;
		}
	
        if($insurance>0){
          $data['is_insurance']=1;	  
        }else{
           $data['is_insurance']=0;	    
        }
		$data['insurance']=	$this->Currency_tag.$insurance;	
		$data['insurance_unit']=$insurance;	
		//var_dump(Session::get('use_piont'));exit;
		/*使用积分*/
		$use_piont=Session::get('use_piont');
		$point=	$this->use_point_num($cart['money']);
        $unit_money=sprintf("%01.2f",$point['unit_money']);
        
		if(!$use_piont){
			$this->assign('is_point',0); 
			$use_piont=0;
        
		}else{

			$this->assign('is_point',$use_piont); 
		
		}
	
	    	$data['use_point']['money']=$point['money'];
    		$data['use_point']['unit_money']=$unit_money;
    		$data['use_point']['point']=$point['point'];
    		$data['use_point']['is_use']=0;		

		//if(isset($inputs['method']) && count($inputs)>1 || isset($inputs['mw_amount']) || count($inputs)>1 && !empty($inputs['mw_amount'])){
		//if(isset($inputs['method']) && count($inputs)>1 || isset($inputs['mw_amount']) || count($inputs)>1 && !empty($inputs['mw_amount']) || isset($inputs['a']) && $inputs['a']=='updatainfo'){
		//var_dump($inputs['mw_amount']);exit;
		//var_dump($use_piont);exit;
	
		
		if($use_piont){
		  
		/*使用积分减免*/
		if($this->auth->point>0){
		
		$total=$total-$unit_money;
        /*加入优惠信息*/
		 $order_discount['money']+=$unit_money;
		/*计算优惠券积分减免*/
		$code_piont+=$unit_money;	
		$coupon_piont+=$unit_money;	
		$data['use_point']['is_use']=1;
		}
	
		}

//	var_dump(	$code_piont);
        /*定义所有优惠信息*/
        $data['order_discount']=$order_discount;
		/*优惠券积分减免*/
		$data['discount_total']='-'.$this->Currency_tag.$code_piont;
		$data['discount_total_unit']=$code_piont;
    	$data['discount_coupon_total']='-'.$this->Currency_tag.$coupon_piont;
		$data['discount_coupon_total_unit']=$coupon_piont;		
	
		$data['total']=$total;
		
		/*订单计算总价使用*/
		$data['order_total']=$order_tal;
		
		/*计算获得积分数*/
		$pay_point=changePoint($this->auth);	
		$data['point']=round($total*$pay_point*$this->unit['num']);
		//var_dump($data);exit;
		return $data;
	}
	/**
	 * 使用运费险
	 */	
	public function useInsurance(){	
	$inputs=input();

	/*获取购物车商品*/
	$cart=$this->cart();  
	$this->assign('cart',$cart); 

    if($inputs['method']==1){
    	Session::set('use_insurance',1);
    }else{
    	Session::set('use_insurance',0);
    }
	/*获取额外数据,计算结算单价格*/
	$param=$this->CalculateAdditionalPrice($cart);
	/*结算页购物车*/
	$datas['total']=$this->Currency_tag.$param['total'];	
	$this->assign('param',$param); 
	
	if($inputs['method']){
	$datas['insurance']=$param['insurance'];	
	}else{
	$datas['insurance']=$this->Currency_tag.'0.00';	
	//	var_dump($this->Currency_tag);exit;
	}

	$html['insurance']=$datas;
	$html['code']=1;	

	return $html;	
	
	}
    /**
     *购买会员
     */
    public function buy_plus()
    {
        
  		$inputs=input();
  
  		if($inputs['is_cancel']){
            Session::set('use_plus',1);
         $is_plus=1;
  		}else{
  		   	Session::set('use_plus',0); 
  		 $is_plus=0;
  		}
  		
  		 /*获取购物车商品*/
		$cart=$this->cart();  
		
		$this->assign('cart',$cart); 
  		/*获取额外数据,计算结算单价格*/
		$param=$this->CalculateAdditionalPrice($cart);
	
		/*结算页购物车*/
		$datas['total']=$this->Currency_tag.$param['total'];
		$this->assign('lists',$datas);		
		$this->assign('param',$param); 	
		$html['pay_info']=$this->view->fetch('cart/firecheckout/payinfo');			
		
		$this->assign('is_plus', $is_plus); 
		
		$this->assign('param',$param); 	
	
  		$html['plus_buy']=$this->view->fetch('cart/plus');	
 
  		$html['code']=1;
  		
		return $html;	
        
        
    }
	/**
	 * 更改使用积分状态
	 */	
	public function usePoint(){	
	    $inputs=input();
		 /*获取购物车商品*/
		$cart=$this->cart();  
		$this->assign('cart',$cart); 
       
		if($inputs['reward-remove']){
		  	Session::set('use_piont',0);
	    
		
		}else{
			Session::set('use_piont',1);
		
		}	
		/*获取额外数据,计算结算单价格*/
		$param=$this->CalculateAdditionalPrice($cart);
		 $this->assign('param',$param); 	
		/*结算页购物车*/
		$datas['total']=$this->Currency_tag.$param['total'];
		$this->assign('lists',$datas);		
		$this->assign('param',$param); 	
		$html['pay_info']=$this->view->fetch('cart/firecheckout/payinfo');	
		
		/*优惠券积分减免统计*/
		$html['discount_total']=$param['discount_coupon_total'];
		//var_dump($html);exit;
	    /*获取使用中的优惠券*/
	     
	    $Coupon=$this->useCouponcenter($inputs);
	   
		$this->assign('coupon',$Coupon); 
		$html['rewardpoints']=$this->view->fetch('cart/firecheckout/point');	
		$html['coupon']=$this->view->fetch('cart/firecheckout/coupon');		

		$html['code']=1;		
		return $html;		
	
	}
	
	/**
	 * 使用优惠券
	 *result_number   订单满
	 *result_type     满减类型:0=定额,1=百分比
     *result_money    订单减	
	 *coupon_code     优惠券模式:0=通用优惠卷,1=一人一码
	 *limit_status  日期限制:0=不限制,1=限制
	 *code_status   优惠券状态:0=正常，1=过期
	 *num           可以使用次数
	 *day           可用天数	
	 *use_status    优惠券状态:0=正常,1=作废,2=已使用,3=使用中	 
	 *get_type      优惠券获取来源:0=前端领取,1=积分兑换,2=抽奖,3=管理员赠送,4=邮箱发放
	 */	
	public function useCoupon(){
		$data=input();
		
		/*获取使用中的优惠券*/
		$sql=$this->useCouponcenter($data);

		/* 优惠券存在*/
		if($sql){
		
    		if(!empty($sql['order_id']) && $sql['order_id']>0 || $sql['code_status']==1){
    			/*优惠券已被使用*/
    			$html['code']=0;		
    			$html['msg']='The coupon has been used';	
    			$html['coupon']='';	
    			return $html;			    
    		    
    		}
    			
    		$cart=$this->cart();	
    		$total_moeny=$cart['money'];
    
    		/*订单总额大于等于优惠券可使用值,减免数小于支付数*/
    		if($total_moeny>=$sql['result_number'] && $total_moeny>0){
    		    /*超过实际支付金额不可用*/
    		    if($total_moeny<$sql['result_money']){
    		        $html['code']=0;		
    				$html['msg']='The current coupon reduction amount exceeds the actual payment amount, not available';	
    				$html['coupon']='';	
    				return $html;	   
    		        
    		    }
    		    
    			/*优惠券要求1人专用*/
    			if(isset($sql['coupon_code']) && $sql['coupon_code']==1){
    				/*判断是否为指定用户*/
    				if($sql['user_id']==$this->auth->id){
    					/*可以使用改变优惠券状态*/
    					
    					
    				}else{
    					/*不是指定用户不能使用返回错误值*/
    				$html['code']=0;		
    				$html['msg']='This coupon is a customized coupon and is not available for non designated users';	
    				$html['coupon']='';	
    				return $html;					
    				}
    				
    			}else{
    				/*没有要求，通用卷返回成功*/
    				
    				
    			}
    			
    		}else{
    			/*不达要求，不能使用*/
    			$html['code']=0;		
    		
    			$html['msg']='The coupon is available for more than $'.$sql['result_number'].'. Your order does not meet the requirements and is temporarily unavailable';	
    			$html['coupon']='';	
    			return $html;
    		}	
            
    		if($sql['limit_status']==0 ){
    			if($sql['day']>0){
    			$createtime=time();
    			$endtime=$createtime+$sql['day']*86400;	
    			}else{
    			$createtime=time();
    			$endtime=0;	
    			}
    		}else{
    			$createtime=time();	
    			$endtime=time()+$sql['day']*24*3600;	
    		}
    		
    		$updatetime=time();	
    		$parm=[];
    		if($this->auth->id){
    		 $get_Couponlog=Db::name('couponlog')->where(['id'=>$sql['couponlog_aid'],'user_id'=>$this->auth->id])->find();   
    		}else{
    		 $ip=getUserCartId();	
    		 $get_Couponlog=Db::name('couponlog')->where(['userip'=>$ip,'id'=>$sql['couponlog_aid']])->find();   
    		}
    	
    		if(empty($get_Couponlog)){
    		/*没有领取，领取优惠券*/
    
    		$parm=[
    		'couponcode_id'=>$sql['id'],
    		'get_type'=>0,
    		'createtime'=>$createtime,
    		'end_time'=>$endtime,
    		'updatetime'=>$updatetime,
    		'use_status'=>3
    		];
    		if($this->auth->id){
    		  $parm['user_id']=  $this->auth->id;
    		}else{
    		  $ip=getUserCartId();	
    		  $parm['userip']=  $ip;   
    		}
            Db::name('couponlog')->insert($parm);	
    		
    		}else{
    		/*更新优惠券信息*/	
    		$parm=[
    		'user_id'=>$this->auth->id,
    		'updatetime'=>$updatetime,
    		'use_status'=>3,
    		'id'=>$get_Couponlog['id']
    		];
    		Db::name('couponlog')->update($parm);
    		}
    		
    		return $this->updatainfo();	
		}else{
			/*优惠券不存在，不能使用*/
			$html['code']=0;		
		
			$html['msg']=$this->use_coupon_alert;	
			$html['coupon']='';	
			return $html;			
			
		}
	}
	/**
	 * 使用优惠券中间件处理 ，处理不可以使用优惠券的分类
	 * limit_type  类型:0=指定不可用,1=指定可用,2=全部禁用
	 */
	public function useCouponcenter($data=[]){
	  
	    $info=$this->usesingCoupon($data); 
	    $sql=Db::name('couponnkad')->where(['status'=>'0'])->select();   
	    $parm=[];
	   
	    foreach ($sql as $k=>$v){
	      $cats='';  
	      /*获取所有包含分类*/
	      $cats=$this->getAllChildCateIds($v['category_id']);
	      $parm[$v['limit_type']]['data'][$k]=['cat'=>$cats,'coupon_ids'=>$v['coupon_ids']];
	      $parm[$v['limit_type']]['cat'][$k]=$cats;
	    }
	   
        /*先查所有券全部禁用，在里面就输出id*/
        $coupon_category_shop['0']=$this->useCouponSearchAvailable($parm,2,0);
	   /*再查制定不可用的券，不在不可用里面继续，在的话输出id*/
	    $coupon_category_shop['1']=$this->useCouponSearchAvailable($parm,0,1);
	   /*查询自定可用，在里面，输出优惠券id*/
	    $coupon_category_shop['2']=$this->useCouponSearchAvailable($parm,1,2);
	    
	    /*相同键值去重*/
        $coupon_category_shops=arr_format($coupon_category_shop);
	    
	    /*优惠券在禁用里面输出空*/
	    if(!empty($coupon_category_shops)){
	        if(!empty($coupon_category_shop['0'])){
	          $this->use_coupon_alert='There are unavailable products in the shopping cart';  
	        }else if(!empty($coupon_category_shop['1'])){
	          $this->use_coupon_alert='There are unavailable products in the shopping cart';      
	            
	        }else if(!empty($coupon_category_shop['2'])){
	          $this->use_coupon_alert='There are unavailable products in the shopping cart';       
	            
	        }
	        
	        $info=[];
	    }
	    return $info;	
	}
	/*搜索不可用优惠券
	* $limit_type  类型:0=指定不可用,1=指定可用,2=全部禁用
	*/
	protected function useCouponSearchAvailable($parm,$limit_type,$type){
	   
	  if(!empty($parm[$limit_type]['cat'])){

	   $cats_all=implode(',',$parm[$limit_type]['cat']); 
	   $cats_all=explode(',',$cats_all);
	   $current_cat=[];
	    /*获取当前购物车商品的分类*/
	   $coupon_category_shop=[];
       $current_coupon=[];
      
      /*判断是否获取到购物车数据*/
       if(!isset($this->cart_data)){
            $this->cart();
       }
       
       if($this->cart_data){

	     foreach ($this->cart_data as $v){
	       $category_ids=explode(',',$v['category_id']);
	     
	       foreach ($category_ids as $k){
	               if(in_array($k,$cats_all)){
	        
	                      foreach ($parm[$limit_type]['data'] as $val){
	                          $current_cat=explode(',',$val['cat']);
	                          if(in_array($k,$current_cat)){
	                              switch($limit_type){
	                                  case '0':
	                                  /*指定不可用*/
	                                  $current_coupon=explode(',',$val['coupon_ids']);
	                                  if(in_array($this->Coupon_data['coupon_id'],$current_coupon)){
	                                     $coupon_category_shop[$v['shop_id']]=$v; 
	                                  }
	                                  break;
	                                  case '1':
	                                   /*指定可用,不存在输出*/
    	                               if(!in_array($this->Coupon_data['coupon_id'],$current_coupon)){
    	                                    $coupon_category_shop[$v['shop_id']]=$v; 
    	                                }      
	                                  break;
	                                  case '2':
	                                    
	                                       $coupon_category_shop[$v['shop_id']]=$v;    
	                                  break;
	                              }
	                             
	                                      
	                           }
	                      }
	                 
	                }
	           }
	    }
       }
	    return $coupon_category_shop;
	  }else{
	     return []; 
	  }
	  
	}

	/**
	 * 根据领取记录判断优惠券可用性
	 *$Coupon                优惠券信息
	 *$is_use_coupon         是否可用，默认1可用

	 */	
	 
	public function isCouponAvailable($Coupon,$is_use_coupon=1,$alert=1){

	        if(!isset($Coupon['couponlog_aid'])){
	            if(isset($Coupon['id'])){
	            $Coupon['couponlog_aid']=$Coupon['id'];
	            }
	            if(isset($Coupon['couponcode_id'])){
	            $Coupon['couponlog_aid']=$Coupon['couponcode_id'];
	            }
	            
	        }
	         	
	        $hint='';
             /*判断是否到期限制*/
             if($Coupon['limit_status']=='1'){
               /*时间大于现在时间不显示*/
                   if($Coupon['end_time']<=time()){
                     $is_use_coupon=0;
                     $hint= 'The coupon exceeds the validity period of use, not available';  
                   }
             }             
            /*获取使用记录*/
             $couponUsageRecordData=$this->couponUsageRecord(); 
             if(isset($couponUsageRecordData[$Coupon['couponlog_aid']])){
                /*判断是否可用，有没有达到数量限制*/
                if($couponUsageRecordData[$Coupon['couponlog_aid']]['num']==count($couponUsageRecordData[$Coupon['couponlog_aid']]['data'])){
                  $is_use_coupon=0;   
                  $hint= 'The number of coupons can be available '.$couponUsageRecordData[$Coupon['couponlog_aid']]['num'].' times, and it has been completed. It is not available';    
                }
               
               
             }	
             if($alert && $hint){
                $this->use_coupon_alert=$hint; 
             }
             return $is_use_coupon;
	    
	}	
	/** 
	 *  获取使用中的优惠券
	 * 	use_status    已获取的优惠券状态:0=正常,1=作废,2=已使用,3=使用中
	 *  limit_status  日期限制:0=不限制,1=限制
	 *  code_status   优惠券状态:0=正常，1=过期
	 * 	status        优惠券大类状态:0=正常，1=过期
	 *  num           可以使用次数
	 *  day           可用天数
	 *  end_time      结束时间
	 */
	  public function usesingCoupon($data=[]){
	      
		 $ip=getUserCartId();	
		 $exclude_id=[];
		 $is_use_coupon=1;
         /*输入已有优惠券*/
		 if(!empty($data) && isset($data['coupon']['code'])){
	
    		$code= $data['coupon']['code'];
    		
    		$Coupon=Db::name('couponcode')
    			->alias("a")
    			->field('c.id as couponlog_aid,b.id as coupon_id,b.coupon_code,b.result_number,b.result_type,b.result_money,c.user_id,c.use_status,a.limit_status,a.code_status,a.num,a.day,a.end_time,a.id,a.coupon_name')
    			->where(['a.coupon_name'=>$code,'a.code_status'=>'0','b.status'=>'0'])
    			->join("coupon b" ,"FIND_IN_SET(b.id,a.coupon_id)","LEFT")
    			->join("couponlog c" ,"FIND_IN_SET(c.couponcode_id,a.id)","LEFT")			
                ->find();
  	          if(!empty($Coupon)){
  	             
  	             $is_use_coupon=$this->isCouponAvailable($Coupon);     
  	          }
            

              if($is_use_coupon==0){
                 $Coupon=[];  
              }
                    		               
                //	var_dump($Coupon);exit;
            if(!empty($Coupon)){
             $price_change_empty_tag['empty_tag']='1';			
			 $price_change_empty_tag['change_name']=['result_number','result_money'];
			 
    	     $Coupon=copon_processing($Coupon,$price_change_empty_tag);    
    	
            }    
             
            
		 }else{ 
		    if(empty($this->auth->id)){
		           $ip=getUserCartId();	
		            $where=['a.userip'=>$ip,'a.use_status'=>['in','0,3'],'b.code_status'=>'0','c.status'=>'0']; 
		     }else{
		         $where=['a.user_id'=>$this->auth->id,'a.use_status'=>['in','0,3'],'b.code_status'=>'0','c.status'=>'0'];
		     }
		     /*用户未登录状态下提交了订单信息*/
		     if(isset($data['user_cart_ip']) && $data['user_cart_ip'] && isset($data['coupon_id']) && $data['coupon_id']){

		         $ip=getUserCartId();	
		         $where=['a.userip'=>$ip,'a.use_status'=>['in','0,3'],'b.code_status'=>'0','c.status'=>'0','a.id'=>$data['coupon_id']]; 
		     }
    		$Coupon=Db::name('couponlog')
    					->alias("a")
    					->field('c.id as coupon_id,c.result_number,c.result_type,c.result_money,b.coupon_name,a.id,b.code_status,c.name,b.id as couponcode_id,b.limit_status,b.num,b.end_time,c.coupon_code')
    					->where($where)
    					->order('a.use_status Desc,a.updatetime Desc')
    					->join("couponcode b" ,"FIND_IN_SET(b.id,a.couponcode_id)","LEFT")	
    					->join("coupon c" ,"FIND_IN_SET(c.id,b.coupon_id)","LEFT")						
    					->find();
             
			if(isset($data['current_cart_money']) && !empty($Coupon)){
			  $price_change_empty_tag['empty_tag']='1';			
			  $price_change_empty_tag['change_name']=['result_number','result_money'];
    		  $Coupon=changeStringPrice($Coupon,$price_change_empty_tag);

			        if($data['current_cart_money']<$Coupon['result_number']){
			          /*提供一个最大的公共券*/
			           $Coupon= $this->getCorrectCoupon($data,'max'); 
			       
			        }

            } 
             if(!empty($Coupon)){
             /*判断优惠券可用性*/
             $is_use_coupon=$this->isCouponAvailable($Coupon);    
             }   
              if($is_use_coupon==0){
                 $exclude_id= $Coupon['couponcode_id'];
                 $Coupon=[];  
              }
              
             
		}
		/*智能推荐开关*/
	    $couponAI=$this->site['couponAi_off'];
	    
		/*用户没有优惠券,但订单有金额传入，为用户推荐优惠券*/
		if(empty($Coupon) && isset($data['current_cart_money']) && $couponAI){
		  
		  $Coupon=$this->getCorrectCoupon($data,'max',$exclude_id); 
		  
		}
	
	    /*定义全局优惠券信息*/
	    $this->Coupon_data=$Coupon;
	   
	    
		return $Coupon;
	  }	 
	  
	/**
	 * 将优惠券进行对比，是否为最大可用面额
	 *$current_coupon_price  当前优惠券减免
	 *$max_coupon_price      最大可减免

	 */	
	 
	public function contrastCoupon($current_coupon_price,$max_coupon_price){
	    
	    if($current_coupon_price>$max_coupon_price || $current_coupon_price==$max_coupon_price){
	        return false;
	    }else{
	       return true; 
	    }
  
	}
	
	/**
	 * 获取所有用户可用固定值优惠券
	 *show_type        展示类型:0=定制发送,1=前台展示,2=收银台展示	
	 *status           优惠券状态:0=正常，1=隐藏
	 * result_type     满减类型:0=定额,1=百分比
	 *$cart_price      购物车总价
	 * add_result_type 固定类型条件 0满减类型:定额,1全部类型
	 * exclude         排除优惠券ID
	 */	
	 
	public function getAllCoupon($add_result_type=0,$exclude=[]){
	        if($add_result_type==0){
	        $where=['a.show_type'=>['in','1,2'],'a.status'=>'0','a.coupon_limit'=>'0','a.result_type'=>'0','a.coupon_code'=>'0','b.code_status'=>'0',''];  
	        
	        }else{
	         $where=['a.show_type'=>['in','1,2'],'a.status'=>'0','a.coupon_limit'=>'0','a.coupon_code'=>'0','b.code_status'=>'0'];   
	        }
		    
		    if(!empty($exclude)){
		       $where['b.id']=['not in',$exclude]; 
		    }
		      
        	$Coupon_sql=Db::name('coupon')
    					->alias("a")
    					->field('a.id as coupon_id,a.result_number,a.result_type,a.coupon_code,a.result_money,b.coupon_name,b.code_status,b.id as couponcode_id,b.limit_status,b.end_time,b.day,b.createtime,b.num')
    					->where($where)
    					->order('a.result_number Desc')
    					->join("couponcode b" ,"FIND_IN_SET(b.coupon_id,a.id)","LEFT")	
    					->select();	
    				
    	    /*获取用户优惠间使用记录*/ 
    	    $UsageRecord=$this->couponUsageRecord();
    	
    		$data=$now_data=[];
    		if($Coupon_sql){		
        		$price_change_empty_tag['empty_tag']='1';
        		$price_change_empty_tag['change_name']=['result_number','result_money'];
        		$Coupon_sql=changeStringPrice($Coupon_sql,$price_change_empty_tag);	
                
        		foreach ($Coupon_sql as $v){
        		    if(!empty($UsageRecord)){
        		      
        		        /*存在相同的优惠券*/
        		        if(isset($UsageRecord[$v['couponcode_id']])){
        		            
        		             $now_data=$UsageRecord[$v['couponcode_id']];   
        		             
        		            /*已使用的相同券存在限制*/
        		            if($now_data['num']>0){
        		               
        		                /*如果限制数量达到,跳过*/
        		               if($now_data['num']==count($now_data['data'])){
        		                   
        		               }else{
        		                   
        		                 $data=$this->couponOutput($v,$data); 		   
        		               } 
        		                
        		            }else{
        		                
        		               $data=$this->couponOutput($v,$data); 		  
        		            }
        		            
        		        }else{
        		           
        		            $data=$this->couponOutput($v,$data); 
        		        }
        		        
        		    }
        		    
                       
        		    
        		}	
    		}
    	

	       	return  $data; 
	}
	/**输出可用优惠券
	 */	
	 
	public function couponOutput($v, $data){
	 
        if($v['num']>0){
                
            if($v['limit_status']=='1'){
               /*时间小于现在时间显示*/
                   if($v['end_time']>=time()){
                    		               
                    $data[]=$v;
                    		               
                    }
               }else{
                  if($v['day']>0){
                    /*时间小于现在时间显示*/
                    if($v['createtime']+($v['day']*24*3600)>=time()){
                    	$data[]=$v;
                     }
                }else{
                   $data[]=$v;
                 } 
                    		        
             }
                    		 
         }  	    
	    
	   	return  $data;  
	    
	}
	/**
	 * 用户优惠间使用记录
	 * get_type    优惠券获取来源:0=前端领取,1=积分兑换,2=抽奖,3=管理员赠送,4=邮箱发放,5=大会员领取
	 * use_status  优惠券状态:0=正常,1=作废,2=已使用,3=使用中
	 */	
	 
	public function couponUsageRecord(){
       	if(empty($this->auth->id)){
		        $ip=getUserCartId();	
    		    $where_two=['a.userip'=>$ip];
    		}else{
    		    $where_two=['a.user_id'=>$this->auth->id];
    		}			
    		$Couponlog_sql=Db::name('couponlog')
    		              ->alias("a")
                          ->field('a.couponcode_id,b.num,b.limit_status')    		              
                          ->where($where_two)
    					->join("couponcode b" ,"FIND_IN_SET(b.id,a.couponcode_id)","LEFT")	
    					->select();	 
    			
    		/*获取已使用优惠券并输出数据*/	
    		$Couponlog_data=[];
    		foreach ($Couponlog_sql as $v){
    		    $Couponlog_data[$v['couponcode_id']]['data'][]=$v;
    		    $Couponlog_data[$v['couponcode_id']]['num']=$v['num'];
    		  
    		}	
    		
    		return $Couponlog_data;
	    
	}
	/**
	 * 使用新的优惠券加入记录
	 * get_type    优惠券获取来源:0=前端领取,1=积分兑换,2=抽奖,3=管理员赠送,4=邮箱发放,5=大会员领取
	 * use_status  优惠券状态:0=正常,1=作废,2=已使用,3=使用中
	 */	
	 
	public function useNewCoupon($id,$use_status=3){
	    
	   $updatetime=$createtime=time(); 
	   $endtime=0;
       $parm=[
		  'couponcode_id'=>$id,
		  'get_type'     =>0,
		  'createtime'   =>$createtime,
		  'end_time'     =>$endtime,
		  'updatetime'   =>$updatetime,
		  'use_status'   =>$use_status
		];
		if($this->auth->id){
		  $parm['user_id']=  $this->auth->id;
		}else{
		  $ip=getUserCartId();	
		  $parm['userip']=  $ip;   
		}
        $Couponlog_id=Db::name('couponlog')->insertGetId($parm);		
        return  $Couponlog_id;
	}
	/**
	 * 获取合适优惠券
	 *$show_type   展示类型:0=定制发送,1=前台展示,2=收银台展示	
	 *$status      优惠券状态:0=正常，1=隐藏
	 *$cart_price 购物车总价
	 *$money      优惠金额
     *$result_type     满减类型:0=定额,1=百分比
     *result_money    订单减
     *$type  获取类型 =  max=最大可用的券  min=最小可用的券
	 */	
	 
	public function getCorrectCoupon($cart_price,$type='max',$exclude=[]){
	      
        $Coupon_sql=$this->getAllCoupon(1,$exclude);
      
    	$Coupon=[];	
    	$return=[];
    	$money=[];
    		
	    foreach ($Coupon_sql as $k=>$v){

    	     /*获取本单可用*/
    	    if($v['result_number']<=$cart_price['current_cart_money']){
    	       $Coupon[$k]=$v;
    	        if($v['result_type']=='1'){
    	        $money[$k]=$cart_price['current_cart_money']*$v['result_money'];
    	      }else{
    	                    
    	        $money[$k]=$v['result_money'];  
    	      }
    	                
    	    }
	           
	   
	        if(!empty($money)){
	      
    	       if($type=='max'){
    	         
    	        $price = max($money); //获取最大的值 
    	       }else{
    	        $price = min($money); //获取最小的值    
    	       }
    	     
                $key = array_search($price,$money); //获取指定值的键名
                $return=$Coupon[$key];
	        }   
	     
	    }
	       	return $return;
	      
	}
	
	/**
	 * 显示收银台推荐优惠券
	 *show_type   展示类型:0=定制发送,1=前台展示,2=收银台展示	
	 *status     优惠券状态:0=正常，1=隐藏
	 */	
	 
	public function firecheckoutCoupon(){

		$coupon=Db::name('coupon')
			->alias("a")
			->where(['a.show_type'=>'2','a.status'=>'0','b.code_status'=>'0'])
			->join("couponcode b" ,"FIND_IN_SET(b.coupon_id,a.id)","LEFT")
            ->select();
	
		foreach($coupon as $k=>$v){
		    if($v['num']>1){

    		    if($v['limit_status']=='1'){
    		          /*时间小于现在时间显示*/
    		           if($v['end_time']>=time()){
    		               
    		              	/*优惠券转货币处理*/
    		            	$coupon[$k]=copon_processing($v); 
    		               
    		           }
    		    }else{
    		       if($v['day']>0){
    		           /*时间小于现在时间显示*/
    		           if($v['createtime']+($v['day']*24*3600)>=time()){
    		             
    		              	/*优惠券转货币处理*/
    		            	$coupon[$k]=copon_processing($v); 
    		               
    		           }
    		       }else{
    		           
    		           	/*优惠券转货币处理*/
    		        	$coupon[$k]=copon_processing($v);
    		       } 
    		        
    		    }
    		    	$coupon[$k]=copon_processing($v);
		    }

		}	
		$this->assign('is_bottom',1); 
		return $coupon;
	}	
	
 
	/** 
	 *  更新结账页数据
	 */
	  public function updatainfo($post_data=''){
		
		
		 /*获取购物车商品*/
		$cart=$this->cart();  
		$this->assign('cart',$cart); 
	      
  
		
		/*获取额外数据,计算结算单价格*/
		$param=$this->CalculateAdditionalPrice($cart);
		/*结算页购物车*/
		$datas['total']=$this->Currency_tag.$param['total'];
		/*商品价格总和*/
	
        $datas['old_total']=$this->Currency_tag.$cart['old_total'];	
		$this->assign('lists',$datas); 		
		$this->assign('param',$param); 	
		$html['pay_info']=$this->view->fetch('cart/firecheckout/payinfo');	
       // $html['checkout—payment-method']=$this->view->fetch('cart/firecheckout/payment-method');	
		/*获取用户礼物数据*/
		$gift=$this->getUserGift();
		$this->assign('gift',$gift); 	

		
		/*结算页购物车商品数统计*/
		$html['items_count']=$cart['num'];	
		/*商品价格总和*/
       // $html['items_old_total']=changeStringPrice($cart['old_total']);	
		
		$html['review']=$this->fetch('cart/firecheckout/cartinfo');

		if(IS_WAP){
		     	$referer = $this->request->server('HTTP_REFERER');
		     
		        /*为前端是否存在更新行为输出判断结果*/ 
		        if(strpos($referer, 'firecheckout') !== false){
		            $this->assign('update_money',1);    
		        }
	
	
		   $html['review_head']=$this->fetch('cart/firecheckout/cartinfo_head');    
		}
		
		    
		/*获取配件*/
		$shop=New Shop;
	    $collocation=$shop->collocation();
		$this->assign('collocation',$collocation); 
		$html['collocation']=$this->view->fetch('cart/firecheckout/collocation');		
	   
        
		 /*获取使用中的优惠券*/
	    $Coupon=$this->useCouponcenter($post_data);      
       
	   	
		$this->assign('coupon',$Coupon); 
		$html['discount_total']=$param['discount_coupon_total'];	
		$html['coupon']=$this->view->fetch('cart/firecheckout/coupon');		
        $html['msg']='Update Cart success';
		$html['code']=1;		
		return $html;		  
		  
	  }	 
	  
	  

    /**
     * 获取用户地址数据
    */
 	public function getAddressSql()
	{
	    /*地址*/
	   	$address=Db::name("address")->where(['user_id'=>$this->auth->id,'status'=>'normal'])->order('switch Desc')->select();	
	    $this->assign('address',$address); 
	    $country=getCountryName();   
	    $current_country='';
	    if(!empty($address)){
    	    foreach ($address as $k=>$v){
    	        if($v['switch']=='1'){
    	           switch($v['country']){
    	                case 'US':
    	                $current_country='ok';
    	                break;
    	                case 'AT':
    	                $current_country='ok';
    	                break;    	                
    	                case 'GB':
    	                $current_country='ok';
    	                break;        	
     	                case 'GER':
    	                $current_country='ok';
    	                break;     	                
    	            }
    	            
    	            break;
    	            
    	        }else{
    	           
    	            switch($v['country']){
    	                case 'US':
    	                $current_country='ok';
    	                break;
    	                case 'AT':
    	                $current_country='ok';
    	                break;    	                
    	                case 'GB':
    	                $current_country='ok';
    	                break;        	
     	                case 'GER':
    	                $current_country='ok';
    	                break;     	                
    	            }
    	              
    	           break;
    	            
    	        }
    	        
	         }
	    }
	  
	    $this->assign('country',$country);
	    $this->assign('address',$address); 
        $this->assign('current_country',$current_country); 
        $data['payment_method']=$this->view->fetch('cart/firecheckout/payment-method');
        $data['address']=$address;
        $data['info']=$this->view->fetch('user/address/info');
        if(IS_WAP){
         $data['info_head']=$this->view->fetch('user/address/info_head');
        }
	    return  $data;
	}
    /**
     * 获取用户地址薄
    */
 	public function getUserAddress()
	{
	   $data= $this->getAddressSql();
	    return $data['info'];
	}
	
	/**
	 * 检查用户是否有未购买的礼物
	 */
 	public function getUserGift()
	{	
  
	$gift=Db::name("giftlog")
		->alias("a")
		->where(['a.user_id'=>$this->auth->id,'a.gift_status'=>'0'] )
		->whereOr(['b.gift_type'=>'2'])
		->field("a.*,b.title,b.image,b.tag,b.price,b.nums")
		->join("gift b" ,"FIND_IN_SET(b.id,a.gift_id)","LEFT")
		->select();

	return $gift;
	}
	/* 购物车中心
	 */ 
    public function checkout()
    {
		/*自定义面包屑*/
		$this->diycrumbDiy('Shopping Cart');	
		
		//$this->auth
	//	if(!empty($this->auth->id)){
			
           
			$cart=$this->cart();
			if($cart['data']){
					
			/*获取额外数据计算*/
			$param=$this->CalculateAdditionalPrice($cart);

			$datas['total']=$this->Currency_tag.$param['total'];
			$gift=$this->getUserGift();
	    	$this->assign('gift',$gift); 	
			$this->assign('cart',$cart); 
			$this->assign('param',$param); 
			$this->assign('lists',$datas); 
			$this->assign('is_bottom',1); 
			return $this->view->fetch('cart/index');
			}else{
			 	$this->assign('is_bottom',1);    
			return $this->view->fetch('cart/empty');	
				
			}
	//	}else{
		//	return $this->view->fetch('cart/notlogin/index');
	//	}
	}	

/* 提交订单 */   
public function firecheckout(){    
    $post_datas=input();
    /*pc端未登录不需要打开*/
    if(isset($post_datas['event']) && $post_datas['event']=='open_address' && !IS_WAP && !isset($this->auth->id)){
        header("Location:/firecheckout/");
        die;    
    }
    if ($this->request->isPost()) {
        // 处理提交订单的逻辑
    } else {
        $cart=$this->cart(0);
        $param = []; // 定义 $param 变量
        if($cart['data']){
            /*获取额外数据计算*/
            $param=$this->CalculateAdditionalPrice($cart);
            $datas['total']=$this->Currency_tag.$param['total'];
            $datas['old_total']=$this->Currency_tag.$cart['old_total'];
            $firecheckoutCoupon=$this->firecheckoutCoupon();
            $by_stages=$this->Currency_tag.(sprintf("%01.2f",$param['total']/4));
            $this->assign('coupon_info',$firecheckoutCoupon);         
            $this->assign('cart',$cart); 
            $this->assign('param',$param); 
            $this->assign('lists',$datas); 
            $this->assign('stages_price',$by_stages); 
        }
        if($cart['num']==0){
            $this->error(__('The shopping cart is empty. Please add your favorite products to the shopping cart'));
        }       
        /*自定义面包屑*/
        $this->diycrumbDiy('Secure Checkout');    
        if(IS_WAP){
            $gift=$this->getUserGift();
            $this->assign('gift',$gift);      
        }
        $address=$this->getUserAddress();    
        $shop=New Shop;
        $collocation=$shop->collocation();
        if(isset($post_datas['event'])){
            if($post_datas['event']=='open_address'){
                $this->assign('open_address',1);     
            }
        }
        /*获取使用中的优惠券*/
        $Coupon = isset($param['current_coupon']) ? $param['current_coupon'] : ''; // 定义 $Coupon 变量
        $this->assign('coupon',$Coupon);     
        $this->assign('collocation',$collocation); 
        return $this->view->fetch('cart/firecheckout');
    }
}


 	/* 获取用户购物信息，准备提交
	 */   
 	public function get_firecheckout($data=[])
 	{	
    
    	$info['cart']=$this->cart(0);
    	$info['address']= $this->getAddressSql();
    	  /*获取额外数据,计算结算单价格*/
    	$param=$this->CalculateAdditionalPrice($info['cart'],$data);
    	$info['payinfo']=$param;

	    return $info;
 	}

}