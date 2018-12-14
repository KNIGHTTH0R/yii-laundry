<?php
namespace powerkernel\yiilaundry\controllers;
use yii\filters\AccessControl;
use powerkernel\yiilaundry\models\Cart;
use powerkernel\yiilaundry\models\CartItem;
use powerkernel\yiiproduct\models\Product;
use powerkernel\yiicoupon\models\Coupon;
use powerkernel\yiilaundry\models\Order;
use powerkernel\yiilaundry\models\OrderDriver;
use powerkernel\yiilaundry\models\LaundryDriver;
use powerkernel\yiilaundry\models\LaundryOwner;
use powerkernel\yiipush\models\Notification;
use powerkernel\yiilaundry\models\Setting;
use Yii;
/**
 * Class AdminController
 */
class CartController extends \powerkernel\yiicommon\controllers\ActiveController
{
    public $modelClass = '';
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors['access'] = [
            '__class' => AccessControl::class,
            'rules' => [
                [
                    'verbs' => ['OPTIONS'],
                    'allow' => true,
                ],
                [
                    'actions' => ['date-diff','update-cart','detail','update-order','apply-coupon','place-order','set-driver-and-owner'],
                    'roles' => ['@'],
                    'allow' => true,
                ],
            ],
        ];
        return $behaviors;
    }
  
    public function totalCartItem($CartId,$CartItem){
        $totalQty = 0;       
        if(!empty($CartItem)){
           foreach($CartItem  as $el){
                $Product            =  Product::find()->where(['_id'=>$el['product_id'],'status'=>'STATUS_ACTIVE'])->asArray()->one();
                if(!empty($Product)){
                     $totalQty = $totalQty+$el['product_qty'];
                }
           }
        }
        return $totalQty;
    }  
    public function totalCartAmount($CartId,$CartItem){
        $totalQty = 0;    $total  = 0;
        if(!empty($CartItem)){        
            foreach($CartItem as $ele){
                $Product            =  Product::find()->where(['_id'=>$ele['product_id'],'status'=>'STATUS_ACTIVE'])->asArray()->one();
                if(!empty($Product)){
                    $total = ($Product['price'] * $ele['product_qty'])+$total;
                }  
            }
        }
        return (float) $total;
    }
    public function actionDateDiff(){
        $s       = Setting::find()->where(['type'=>'pick_drop_diff'])->asArray()->one();
        $s       = $s['data'];
        return[
            'success'=>true,
                'data'=>[
                    'day'=>!empty($s['pick_drop_diff'])?ceil($s['pick_drop_diff']/86400):1
                ]
        ];
    }
    public function getOrderProducts($ProductId,$ProductQty){
            $Product            =   Product::find()->where(['_id'=>$ProductId])->asArray()->one();
            $o                  =   array(
                                        'name'=>$Product['name'],
                                        'price'=>$Product['price'],
                                        'qty'=>$ProductQty,
                                        'image'=>$Product['thumb_url'],
                                        'product_id' =>(string)$ProductId,
                                        'category_id'=>!empty($Product['category_id'])?(string)$Product['category_id']:""
                                    );
            return $o;
    }
    public function actionUpdateOrder($OrderId=""){
        $CartItem = new CartItem;          
        if($CartItem->load(\Yii::$app->getRequest()->getParsedBody(), '') && $CartItem->validate()){
             $New_CartItem   =  $this->getOrderProducts($CartItem->product_id,$CartItem->product_qty);
             $Order          =  Order::find()->where(['owner_id'=>(string)Yii::$app->user->id,'_id'=>(string)$OrderId])->one();
             if(!empty($Order)){
                if(!empty($Order->order_item)){
                    $Existing_OrderItem = $Order->order_item;      
                    $IsNew = true;                               
                    foreach($Existing_OrderItem as $k => $ele){
                        if($ele['product_id'] == $CartItem->product_id){
                            $IsNew = false;
                            $Existing_OrderItem[$k] = $New_CartItem;                            
                            if(empty($CartItem->product_qty)){
                                unset($Existing_OrderItem[$k]);
                            }
                        }                            
                    } 
                    if($IsNew){
                        array_push($Existing_OrderItem,$New_CartItem);
                    }
                    $Order->order_item = array_values($Existing_OrderItem);


                }else{
                    if(!empty($CartItem->product_qty)){                          
                        $Order->order_item =  array($New_CartItem); 
                    }else{
                        return [
                            'success' => false,
                            'errors'  => "Product Qty must be more than 0"
                        ];  
                    }  
                } 
                $total = 0;$totalQty = 0;
                foreach($Order->order_item as $k => $ele){
                    $total    = $total+($ele['price'] * $ele['qty']);
                    $totalQty = $totalQty + $ele['qty'];
                }
                if($total > 10000){
                    return [
                        'success' => false,
                        'errors' => 'You are allowed to place order upto amount of $ 10,000'
                    ]; 
                }
                $Discount            = !empty($Order->coupon_discount)?$Order->coupon_discount:0;
                $Order->total_amount = $total;
                $Order->total_qty    = $totalQty;
                $Order->final_amount = $total - $Discount;

                if($Order->save()){
                    return [
                        'success' => true,
                        'data'  => $Order
                    ]; 
                }else{
                    $Order->validate();
                    return [
                        'success' => false,
                        'errors'  =>$Order->errors
                    ]; 
                }
             }else{
                return [
                    'success' => false,
                    'errors' =>"Invalid OrderId Or You are not an Owner of this order."
                ]; 
             }
        }else {
            $CartItem->validate();
            return [
                'success' => false,
                'errors' =>$CartItem->errors
            ];
        }
    } 
    public function actionUpdateCart(){
        $CartItem = new CartItem;          
        if($CartItem->load(\Yii::$app->getRequest()->getParsedBody(), '') && $CartItem->validate()){

            $New_CartItem   =  array('product_id'=>(string)$CartItem->product_id,'product_qty'=>$CartItem->product_qty);

            $Cart           = Cart::find()->where(['user_id'=>(string)Yii::$app->user->id])->one();
            $Cart           = !empty($Cart)?$Cart:new Cart;
            $Cart->user_id  = (string)Yii::$app->user->id; 
            
            if(!empty($Cart->_id)){               
                if(!empty($Cart->cart_item)){
                    $Existing_CartItem = $Cart->cart_item;      
                    $IsNew = true;                               
                    foreach($Existing_CartItem as $k => $ele){
                        if($ele['product_id'] == $CartItem->product_id){
                            $IsNew = false;
                            $Existing_CartItem[$k] = $New_CartItem;
                            if(empty($CartItem->product_qty)){
                                unset($Existing_CartItem[$k]);
                            }
                        }                            
                    } 
                    if($IsNew){
                        array_push($Existing_CartItem,$New_CartItem);
                    }
                    $Cart->cart_item = $Existing_CartItem;

                }else{
                    if(!empty($New_CartItem['product_qty'])){
                        $Cart->cart_item =   array($New_CartItem);
                    }else{
                        return [
                            'success' => false,
                            'errors'  => "Product Qty must be more than 0"
                        ];  
                    }  
                }                
            }else{
                if(!empty($New_CartItem['product_qty'])){
                    $Cart->cart_item = array($New_CartItem);
                }else{
                    return [
                        'success' => false,
                        'errors'  => "Product Qty must be more than 0"
                    ];  
                }                 
            }
            $totalCheck = $this->totalCartAmount($Cart->_id,$Cart->cart_item);           
            if($totalCheck > 10000){
                return [
                    'success' => false,
                    'errors' => 'You are allowed to place order upto amount of $ 10,000'
                ]; 
            }
            if($Cart->save()){
                return [
                    'success' => true,
                    'data'    => $this->totalCartItem($Cart->_id,$Cart->cart_item)                     
                ]; 
            }else{
                return [
                    'success' => false,
                    'errors' =>$Cart->errors
                ]; 
            }
        }else {
            $CartItem->validate();
            return [
                'success' => false,
                'errors' =>$CartItem->errors
            ];
        }
    }
    public function actionDetail(){
        $Cart           = Cart::find()->where(['user_id'=>(string)Yii::$app->user->id])->one();
        if(!empty($Cart)){
            $CartItem    = !empty($Cart['cart_item'])?$Cart['cart_item']:array();
            if(!empty($CartItem)){   
                $i = 0;  $data = array();    $C = array(); 
                foreach($CartItem as $ele){
                    $Product            =  Product::find()->where(['_id'=>$ele['product_id'],'status'=>'STATUS_ACTIVE'])->asArray()->one();
                    if(!empty($Product)){
                        $C[$i]['product']         = $Product['name'];                        
                        $C[$i]['qty']             = $ele['product_qty'];
                        $C[$i]['total_price']     =  '$ '.number_format((float)$Product['price'] * $ele['product_qty'],2);                      
                        $i++;
                    }  
                    $data['items']        = $C;       
                    $data['total_amount'] = '$ '.number_format((float)$this->totalCartAmount($Cart->_id,$CartItem),2);   
                    $data['total_qty']    =  $this->totalCartItem($Cart->_id,$Cart->cart_item)  ;        
                }
                return [
                    'success' => true,
                    'data'    => $data
                ];  
            }else{
                return [
                    'success' => false,
                    'errors' =>"Your cart is empty.",
                ];  
            }
        }else{
            return [
                'success' => false,
                'errors' =>"Your cart is empty.",
            ];  
        }
    }
    private function couponDiscount($coounCode = '',$withSymbol=false){      
        $CouponExist = Coupon::find()->where(['coupon_code'=>$coounCode])->asArray()->one();
        if(!empty($CouponExist)){
            $Cart           = Cart::find()->where(['user_id'=>(string)Yii::$app->user->id])->one();
            if(!empty($Cart)){
                $TotalCartAmount =  $this->totalCartAmount($Cart->_id,!empty($Cart)?$Cart->cart_item:array());  
                if($TotalCartAmount <= 0){
                    return[
                        'success'=>false,
                        'errors'=>"You can't apply coupon on zero amount."
                    ];
                }
                $startdate       = $CouponExist['start_date'];
                $enddate         = $CouponExist['end_date'];
               
                $today           = time();
                $stated          = round(($startdate - $today));
               
                $end             = round(($today - $enddate));
                if($CouponExist['user_limit'] == 0){
                    return [
                        'success' => false,
                        'errors'=>"Coupon usage limit exceeded."
                    ];
                }
                if($stated > 0 ){
                    return [
                        'success' => false,
                        'errors'=>"The starting date of this coupon is in future."
                    ];
                }else{
                    if($end < 0){
                        $cart_total = $TotalCartAmount;
                        $final_total = 0;
                        if($CouponExist['coupon_type'] == 'per'){
                            $couponDiscount = $cart_total *  $CouponExist['value'] / 100;
                            $final_total = $cart_total - $couponDiscount;
                        }else if($CouponExist['coupon_type'] == 'fix'){
                            $couponDiscount = $CouponExist['value'];
                            if($couponDiscount >= $cart_total){
                                $couponDiscount = $cart_total - 1;
                            }
                            $final_total    = $cart_total - $couponDiscount;
                        }
                        $final_total = $final_total;
                        return [
                            'success' => true,
                            'data' => array("apply_code"=>$CouponExist['coupon_code'] ,
                                            "discount"=>!empty($withSymbol) ? '$ '.number_format((float)$couponDiscount,2):$couponDiscount,
                                            'final_amount'=>!empty($withSymbol) ? '$ '.number_format((float)$final_total,2):$final_total,
                                        ),
                                          
                        ];
                    }else{
                        return [
                            'success' => false,
                            'errors'=>"This coupon code is invalid or has expired."
                           
                        ];
                    }
                }    
                             
            }else{
                return [
                    'success' => false,
                    'errors'=>"Your cart is empty"
                ];
            }
        }else{
            return [
                'success' => false,
                'errors'=>"Invalid coupon code"
            ];
        }
    }
    public function actionApplyCoupon(){
        $REQ = \Yii::$app->getRequest()->getParsedBody();
        if($REQ['coupon_code']){
            return $this->couponDiscount($REQ['coupon_code'],true);            
        }else{
            return [
                'success' => false,
                'errors'=>"Coupon code is empty"
            ];
        }
    }
    public function actionPlaceOrder(){
        $Order = new Order;
        $Order->scenario = 'create';
        if($Order->load(\Yii::$app->getRequest()->getParsedBody(),'') && $Order->validate()){
           
                if(!empty($Order->payment_capture_url)){
                    $d                          = parse_url(urldecode($Order->payment_capture_url));
                    $Order->payment_capture_url = str_replace("url=", '', $d['query']);
                }

                $Order->order_number    = $Order->generateOrderNo();
                $Order->user_id         = (string)Yii::$app->user->id;
                $Cart                   = Cart::find()->where(['user_id'=>(string)Yii::$app->user->id])->one();              
                if(!empty($Cart)){   
                  
                    $Order->total_qty       = $this->totalCartItem($Cart->_id,!empty($Cart)?$Cart->cart_item:array());
                    $Order->total_amount    = $this->totalCartAmount($Cart->_id,!empty($Cart)?$Cart->cart_item:array());
                    $Order->final_amount    = $Order->total_amount;                    
                    $OrderItem = array();                   
                    if(!empty($Cart->cart_item)){
                        $i = 0;  $total = 0;
                        foreach($Cart->cart_item as $k=> $ele){
                            $Product            =  Product::find()->where(['_id'=>$ele['product_id'],'status'=>'STATUS_ACTIVE'])->asArray()->one();
                            if(!empty($Product)){
                                $total = ($Product['price'] * $ele['product_qty'])+$total;
                                $OrderItem[$i] = array(
                                                'name'=>$Product['name'],
                                                'price'=>$Product['price'],
                                                'qty'=>$ele['product_qty'],
                                                'image'=>$Product['thumb_url'],
                                                'product_id'=>(string)$Product['_id'],
                                                'category_id'=>(string)$Product['category_id']);
                                $i++;
                            }                             
                        }
                    }
                    $Order->order_item = $OrderItem ;
                    
                    if(!empty($Order->coupon_code)){
                     
                        $couponDiscount = $this->couponDiscount($Order->coupon_code);
                       
                        if($couponDiscount['success']){
                            $Order->coupon_code      =  $couponDiscount['data']['apply_code'];
                            $Order->coupon_discount  =  $couponDiscount['data']['discount'];   
                            $Order->final_amount     =  $couponDiscount['data']['final_amount'];    
                            $CouponExist = Coupon::find()->where(['coupon_code'=>$Order->coupon_code])->one();  
                            if($CouponExist){
                                $CouponExist->user_limit = $CouponExist->user_limit-1;
                                $CouponExist->save();
                            }   

                        }else{  
                            return $couponDiscount;
                        }                       
                    }
                }                
                if($Order->save()){
                    if(!empty($Cart)){ 
                        $Cart->delete();
                    }
                    return [
                        'success' => true,
                        'data' => $Order
                    ];
                }else{
                    $Order->validate();
                    return [
                        'success' => false,
                        'errors'=>$Order->errors
                    ];
                }          
        }else{
            
            return [
                'success' => false,
                'errors'=>$Order->errors
            ];
        }
    }
    public function setOwner($D){
        $LaundryOwner = LaundryOwner::find()->where($D)->andWhere(['OR',['online'=>'1'],['online'=>1]])->asArray()->one();
        if(!empty($LaundryOwner)){
                return $LaundryOwner;
        }else{
            return[
                'success'=>false,
                'errors'=>"Sorry we have not any store in your nearest location"
            ];
        }
    }
    public function getSleeptime(){
        
        $s       = Setting::find()->where(['type'=>'driver_setting'])->asArray()->one();
        $s       = $s['data'];
        return $s;
    }
    public function actionSetDriverAndOwner(){
        $DriverSetting = $this->getSleeptime();
        $searchKm      = !empty($DriverSetting['driver-available-km'])?$DriverSetting['driver-available-km']:10;
        
        $REQ = \Yii::$app->getRequest()->getParsedBody();
        $ToLocation = "";
        if(!empty($REQ['order_id'])){

            ///########################### Get Order Data #################################
            $Order = Order::find()->where(['_id'=>$REQ['order_id']])
            ->andWhere(['OR',['user_id'=>(string)Yii::$app->user->id],['owner_id'=>(string)Yii::$app->user->id]])
            ->one();
            
            if(!empty($Order)){
                    //########################### Searching Setup for Pickup Driver #################################
                    if($Order->order_status=="Pending"){
                            $Type         = "PICK_UP";
                            $lat          = $Order->pickup_address['lat'];
                            $lng          = $Order->pickup_address['lng'];
                            $FromLocation = $Order->pickup_address['name'];  

                            $D =[
                                'geo_location' => [
                                    '$near'=> [
                                        '$geometry'=> [
                                            "type"=>"Point",
                                            "coordinates"=> [(float)$lng,(float)$lat],
                                         ],  
                                        '$maxDistance'  =>1000*(float)$searchKm,                                                                          
                                    ]
                                ], 
                            ];  
                    }else if($Order->order_status=="Completed"){   
                            //########################### Searching Setup for Delivery Driver #################################
                            $Type = "DROP_UP"; 
                            $LaundryOwner = LaundryOwner::find()->where(['user_id'=>!empty($Order->owner_id)?(string)$Order->owner_id:""])->asArray()->one();
                            if(!empty($LaundryOwner)){
                                $lat          = $LaundryOwner['geo_location']['coordinates'][1];
                                $lng          = $LaundryOwner['geo_location']['coordinates'][0];
                                $FromLocation = $LaundryOwner['address'];  
                                $ToLocation   = $Order->delivery_address['name'];    
                                
                                $D =[
                                    'geo_location' => [
                                        '$near'=> [
                                            '$geometry'=> [
                                                "type"=>"Point",
                                                "coordinates"=> [(float)$lng,(float)$lat],
                                             ],                                                                   
                                        ]
                                    ], 
                                ];
                            }else{
                                return[
                                    'success'=>false,
                                    'errors'=>"Owner and order is not assigned"
                                ];
                            }
                    }else{
                        return[
                            'success'=>false,
                            'errors'=>"Order status must have Pending or Completed"
                        ]; 
                    }                       
                   
                    $LaundryDriver = LaundryDriver::find()->where($D)->andWhere(['OR',['online'=>'1'],['online'=>1]])->asArray()->all();  
                   

                    //################# Assigning Owner to Order ############################
                    if($Order->order_status=="Pending"){
                        $ForOwner =[
                            'geo_location' => [
                                '$near'=> [
                                    '$geometry'=> [
                                        "type"=>"Point",
                                        "coordinates"=> [(float)$lng,(float)$lat],
                                    ],                                                                     
                                ]
                            ], 
                        ];  
                        $LaundryOwner =  $this->setOwner($ForOwner);   

                        if(isset($LaundryOwner['success']) && $LaundryOwner['success'] == false){
                            return $LaundryOwner;
                        }  

                        $ToLocation      = $LaundryOwner['address'];
                        $Order->owner_id = $LaundryOwner['user_id'];
                        if($Order->save()){
                            $input = array(
                                'user_id'=>$Order->owner_id,
                                'title'  =>'You are assigned with new order',
                                'message'=>'You are assigned with new order #'.(string)$Order->order_number.'. Driver will send clothes at your store.',
                                'type'=>'Notification'
                            );                           
                            Yii::$app->push->send($input,array('order_number'=>(string)$Order->order_number,'order_id'=>(string)$Order->_id));      
                        }else{
                            return[
                                'success'=>false,
                                'errors'=>$Order->errors
                            ];                           
                        }
                       
                    }
                     //################# Searching driver for Order ############################
                   
                  
                    if(!empty($LaundryDriver)){
                        foreach($LaundryDriver as $Driver){
                            $OrderDriver = OrderDriver::find()->where(['order_id'=>(string)$REQ['order_id'],
                                'status'=>'Accepted',
                                'type'=>$Type
                            ])->one();       
                          
                            if(empty($OrderDriver)){
                                $OrderDriver = OrderDriver::find()->where([
                                    'order_id'=>$REQ['order_id'],
                                    'driver_id'=>$Driver['user_id'],
                                    'type'=>$Type
                                ])->one();
                                if(empty($OrderDriver)){
                                    $OrderDriver                 = new OrderDriver;
                                    $OrderDriver->order_id       = $REQ['order_id'];
                                    $OrderDriver->driver_id      = $Driver['user_id'];
                                    $OrderDriver->from_location  = $FromLocation;
                                    $OrderDriver->to_location    = $ToLocation;
                                    $OrderDriver->type           = $Type;
                                    if(!$OrderDriver->save()){
                                        continue;
                                    }
                                }
                                
                                //Send Notification/  
                                $input = array(
                                    'user_id'=> $Driver['user_id'],
                                    'title'  =>'There is a new order in your area.',
                                    'message'=>'There is a new order  #'.$Order['order_number'].' in your area please check and confirm.',
                                    'type'=>'Order'
                                );
                                Yii::$app->push->send($input,array('order_number'=>$Order['order_number'],'order_id'=>(string)$REQ['order_id']));
                                $sleep = !empty($DriverSetting['driver-count_down_sec'])?$DriverSetting['driver-count_down_sec']:60;
                                sleep($sleep);                                   
                                OrderDriver::updateAll(['status' => 'AutoRejected'], ['status'=>'Pending','order_id'=>(string)$REQ['order_id'],'type'=>$Type]); 
                                $Driver     =  OrderDriver::find()->where(['status'=>'AutoRejected','order_id'=>(string)$REQ['order_id']])->asArray()->all();
                                $DriverIds  =  yii\helpers\ArrayHelper::map($Driver,'driver_id','driver_id');
                                Notification::deleteAll(['AND',['IN','user_id',$DriverIds],['order_id'=>(string)$REQ['order_id']],['type'=>'Order'] ]);
                            }else{                                                     
                                return[
                                    'success'=>true,
                                    'message'=>'Congratulation! Your order has been accepted by driver.'
                                ];
                            }
                        }
                        $OrderDriver = OrderDriver::find()->where(['order_id'=>(string)$REQ['order_id'],
                            'status'=>'Accepted',
                            'type'=>$Type
                        ])->one();
                        if(empty($OrderDriver)){
                            if( $Order->order_status=="Pending" ||  $Order->order_status=="Completed"){                              
                                $Order->order_status = $Order->order_status=="Pending" ? "Cancelled":$Order->order_status;
                                $Order->remark      =  $Order->order_status=="Pending" ? "Pick up driver could not found":"Drop up driver could not found";
                                if(!$Order->save()){  
                                    $Order->validate();                              
                                    return[
                                        'success'=>false,
                                        'errors'=>$Order->errors
                                    ]; 
                                }
                            }
                            return[
                                'success'=>false,
                                'errors'=>"Sorry this service is not available in your area! We're working to bring our services to more areas as quickly as possible"
                            ];
                        }   
                        return[
                            'success'=>true,
                            'message'=>'Congratulation! Your order has been accepted by driver.'
                        ];                
                    }else{
                        if( $Order->order_status=="Pending" ||  $Order->order_status=="Completed"){
                            $Order->order_status = $Order->order_status=="Pending" ? "Cancelled":$Order->order_status;
                            $Order->remark      =  $Order->order_status=="Pending" ? "Pick up driver could not found":"Drop up driver could not found";
                            if(!$Order->save()){  
                                $Order->validate();                              
                                return[
                                    'success'=>false,
                                    'errors'=>$Order->errors
                                ]; 
                            }
                        }
                        return[
                            'success'=>false,
                            'errors'=>"Sorry this service is not available in your area! We're working to bring our services to more areas as quickly as possible"
                        ];
                    }

            }else{
                return [
                    'success' => false,
                    'errors' => "Invalid object with this order_id"
                ];
            }
        }else{
            return [
                'success' => false,
                'errors' => "driver_id or order_id or type is missing"
            ];
        }
    }
}
