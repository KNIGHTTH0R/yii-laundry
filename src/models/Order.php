<?php
/**
 * @author Harry Tang <harry@powerkernel.com>
 * @link https://powerkernel.com
 * @copyright Copyright (c) 2018 Power Kernel
 */

namespace powerkernel\yiilaundry\models;

use powerkernel\yiicommon\behaviors\DefaultDateTimeBehavior;
use powerkernel\yiiuser\models\User;
use powerkernel\yiilaundry\models\OrderHistory;
use powerkernel\yiilaundry\models\OrderBags;
use powerkernel\yiilaundry\models\OrderDriver;
use powerkernel\yiilaundry\models\LaundryOwner;

use powerkernel\yiilaundry\models\Setting;
use Yii;

class Order extends \yii\mongodb\ActiveRecord
{
    public $drivers;
    public $status_for_user;
    public $status_for_owner;
    public $location_for_driver;
    public $order_detail_image;
    public $order_list_image;
    public $order_date;
    public $user_detail;
    public static function collectionName()
    {
        return 'order_db';
    }
    public function attributes()
    {
        return [
            '_id',
            'user_id',
            'order_number',
            'total_qty',
            'total_amount',
            'final_amount',
            'coupon_code',
            'coupon_discount',
            'owner_id',
            'pickup_address',
            'delivery_address',
            'pickup_date',
            'delivery_date',            
            'order_status',
            'payment_status',
            'payment_method',
            'payment_card_id',
            'payment_capture_url',
            'payment_response',
            'order_item',
            'created_by',
            'updated_by',
            'created_at',
            'updated_at',
            'remark',
            'items',
            'order_note'
        ];
    }
    public function fields()
    {
        $fields = parent::fields();
        $fields['total_amount']=function ($model) {
            if($model->final_amount>0){
                 return '$ '.number_format((float)$model->total_amount,2);
             }
            return '$ 0';
        };
        $fields['final_amount']=function ($model) {
         
            if(isset($model->final_amount) && $model->final_amount > 0){
                 return '$ '.number_format((float)$model->final_amount,2);
            }
            return '$ 0';
        };
        $fields['user_detail']=function ($model) {
            return $this->userData((string)$model->user_id);
        };
        $fields['total_qty']=function ($model) {
            return !empty($model->total_qty)?$model->total_qty:0;
        };
        $fields['order_item']=function ($model) {
            return !empty($model->order_item)?$model->order_item:array();
        };
        $fields['order_date']=function ($model) {
            return $model->created_at;
        };
        $fields['pickup_date']=function ($model) {
            return strtotime($model->pickup_date);
        };
        $fields['delivery_date']=function ($model) {
            return strtotime($model->delivery_date);
        };
        $fields['drivers']=function ($model) {
            $pickup_driver   = $this->orderDriver($model->_id,"PICK_UP");    
            $delivery_driver = $this->orderDriver($model->_id,"DROP_UP");  
            if(!empty($pickup_driver)){
                $d = "Unknown";
                if(!empty( $pickup_driver['user_name'])){
                    $d = $pickup_driver['user_name'];
                }
                if(!empty( $delivery_driver['user_name'])){
                    $d = $d.'/'.$delivery_driver['user_name'];
                }
               return $d;
            }else{
                if($this->order_status =="Pending"){
                    return 'searching driver...';
                }
                return '';
            }
        };
        $fields['status_for_owner'] = function ($model) {
            return $this->getOrderStatus($model->order_status,"owner");
        };
        $fields['status_for_user'] = function ($model) {
            return $this->getOrderStatus($model->order_status,"user");
        };
        $fields['location_for_driver'] = function ($model) {
            return $this->orderLocation($model->_id);
        };
        $fields['order_bags'] = function ($model) {
            return $this->getOrderBags($model->order_status,(string)$model->_id);
        }; 
        $fields['order_list_image'] = function ($model) {           
            return\Yii::$app->params['organization']['order_list_image'];
        }; 
        $fields['order_detail_image'] = function ($model) {           
            return\Yii::$app->params['organization']['order_detail_image'];
        }; 
        $fields['order_note'] = function ($model) {  
            return !empty($model->order_note) ? $model->order_note:"";
        };               
        return $fields;
    }
    public function orderLocation($Order_Id){             
        $Order = Order::find()->where(['_id'=>$Order_Id])->asArray()->one();        
        $OrderDriver = OrderDriver::find()->where(['status'=>'Accepted','order_id'=>(string)$Order_Id,'driver_id'=>(string)Yii::$app->user->id])
                     ->asArray()->all();
    
        if(!empty($OrderDriver[1])){
            $ar['pickup_location'] = !empty($OrderDriver[1]['from_location'])?$OrderDriver[1]['from_location']:"";
            $ar['dropup_location'] = !empty($OrderDriver[1]['to_location'])?$OrderDriver[1]['to_location']:"";
            
        }else{
            $ar['pickup_location'] = !empty($OrderDriver[0]['from_location'])?$OrderDriver[0]['from_location']:"";
            $ar['dropup_location'] = !empty($OrderDriver[0]['to_location'])?$OrderDriver[0]['to_location']:"";
        }
       
        return $ar;
    }
    public function getOrderStatus($Status,$Role){ 
        $FinalStatus  = $Status;
        if($Role == "owner"){
            if($Status == 'Pending'){
                $FinalStatus = "Placed";
            }
        }else if($Role == "user"){           
            if($Status == 'Pick Up'){
                $FinalStatus = "Driver on way";
            }
            if($Status == 'Pick Up Hold'){
                $FinalStatus = "Please Search Driver";
            }
            if($Status == 'Drop Up Hold'){
                $FinalStatus = "Please Search Driver";
            }
            if($Status == 'Picked Up'){
                $FinalStatus = "Driver Picked Up";
            }
            if($Status == 'Dropped'){
                $FinalStatus = "At Cleaning house";
            }
            if($Status == 'Completed'){
                $FinalStatus = "Cleaned & Ready";
            }
            if($Status == 'Picked Up Back'){
                $FinalStatus = "Ready For Delivery";
            }
        }
        return $FinalStatus;
    }
    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['pickup_address', 'delivery_address','pickup_date', 'delivery_date','payment_method'], 'required'],

            [['total_amount'], 'number'],
            [['total_qty'], 'integer'],

            [['coupon_discount'], 'number'],
            [['coupon_code'], 'string', 'max' => 20],   
            [['remark','order_note'], 'string'],            

            ['order_status', 'default', 'value' => "Pending"],
            ['order_status', 'in', 'range' => ['Pending','Pick Up','Picked Up','Dropped','Completed','Picked Up Back','Delivered','Cancelled']],

            ['payment_method', 'in', 'range' => ['PayPal','CreditCard']],

            ['payment_status', 'default', 'value' => "Pending"],            
            ['payment_status', 'in', 'range' => ['Pending','Failed','Success']],
            ['payment_capture_url','url'],   

            [
                'payment_card_id', 'required', 'when' => function ($model) {
                     return $model->payment_method == 'CreditCard';
                },'message'=>'Please select your credit card'
            ],

            
            [
                'payment_capture_url', 'required', 'when' => function ($model) {
                     return $model->payment_method == 'PayPal';
                },'message'=>'Please authorized your PayPal account.'
            ],

            ['pickup_date','validate_pickupdate','on' => 'create'],
            ['delivery_date','validate_deliverydate','on' => 'create'],

            ['pickup_address','validate_pick_address'],
            ['delivery_address','validate_drop_address'],

            

            [['created_by'], 'exist', 'targetClass' => User::class, 'targetAttribute' => ['created_by' => '_id']],
            [['updated_by'], 'exist', 'targetClass' => User::class, 'targetAttribute' => ['updated_by' => '_id']],
        ];
    }
    public function validate_pick_address($attribute, $params){
        $array = $this->pickup_address;
        if(!isset($array['name']) || !isset($array['lat']) || !isset($array['lng'])){
            $this->addError($attribute, Yii::t('order', 'Pickup address is invalid or lat long are not fetching'));
        }
    }

    public function validate_drop_address($attribute, $params){
        $array = $this->delivery_address;
        if(!isset($array['name']) || !isset($array['lat']) || !isset($array['lng'])){
            $this->addError($attribute, Yii::t('order', 'Delivery address is invalid or lat long are not fetching'));
        }
    }

    public function validate_pickupdate($attribute, $params){   

        $time   =  $this->pickup_date;
        if($time < time()){
            $this->addError($attribute, Yii::t('order', 'Pickup date and time should be greater than now.'));
        }        
    }
    public function validate_deliverydate($attribute, $params){
        $s              = Setting::find()->where(['type'=>'pick_drop_diff'])->asArray()->one();
        $s              = $s['data'];
        $diff           = $s['pick_drop_diff'];
        $diff_text      = $s['pick_drop_diff_txt'];
        $delivery_time  =  $this->delivery_date;
        $pickup_time    =  $this->pickup_date;
        $t              =  $pickup_time + $diff;
        if($t <= $delivery_time){
            
        }else{
            $this->addError($attribute, Yii::t('order', 'Delivery date and time should be greater than '.$diff_text.' of pick-up date at least.'));
        }    
    }
    public function userData($user_id){
        $User = User::find()->where(['_id'=>(string)$user_id])->asArray()->one();
        if(!empty($User)){
            $UserData['profile_pic'] = !empty($User['profile_picture'])?$User['profile_picture']:\Yii::$app->cloudinary->placehoder_image;
            $UserData['user_name']   = !empty($User['name'])?$User['name']:"";
            $UserData['phone']       = !empty($User['phone'])?$User['phone']:"";     
            $UserData['user_id']     = (string)$user_id; 
        }else{
            $UserData = array();
        }
        return $UserData;
    }
    public function changeStatus(){
        if($this->save()){
            if($this->order_status == "Pick Up"){
                $input = array(
                    'user_id'=>(string)$this->user_id,
                    'title'  =>'Driver is on his way to pick up your scheduled order with '.Yii::$app->name,
                    'message'=>'Driver is on his way to pick up the order #'.(string)$this->order_number.'. driver will reach your pick-up location on scheduled time. ',
                    'type'=>'Track'
                );
                Yii::$app->push->send($input,array('order_number'=>(string)$this->order_number,'order_id'=>(string)$this->_id)); 


                $input = array(
                    'user_id'=>(string)$this->owner_id,
                    'title'  =>'Driver is on his way to pick up order',
                    'message'=>'Driver is on his way to pick up order #'.(string)$this->order_number.' and will be delivered on your shop soon.',
                    'type'=>'Info',
                );
                Yii::$app->push->send($input,array('order_number'=>(string)$this->order_number,'order_id'=>(string)$this->_id)); 

            }else if($this->order_status == "Picked Up"){
                $input = array(
                    'user_id'=>(string)$this->user_id,
                    'title'  =>'Your order is picked up by the driver and on its way to the cleaning house',
                    'message'=>'Your order #'.(string)$this->order_number.' is picked up by the driver and on its way to the cleaning house. ',
                    'type'=>'Info'
                );
                Yii::$app->push->send($input,array('order_number'=>(string)$this->order_number,'order_id'=>(string)$this->_id)); 

                $input = array(
                    'user_id'=>(string)$this->owner_id,
                    'title'  =>'Your new order is on its way to your shop',
                    'message'=>'Your new order #'.(string)$this->order_number.' is on its way to your shop',
                    'type'=>'Track',
                );
                Yii::$app->push->send($input,array('order_number'=>(string)$this->order_number,'order_id'=>(string)$this->_id)); 

            }else if($this->order_status == "Dropped"){
              
                $input = array(
                    'user_id'=>(string)$this->user_id,
                    'title'  =>'Your order is dropped at the cleaning house and will be ready soon',
                    'message'=>'Your order #'.(string)$this->order_number.' is dropped at the cleaning house and will be ready soon.',
                    'type'=>'Info'
                );
                Yii::$app->push->send($input,array('order_number'=>(string)$this->order_number,'order_id'=>(string)$this->_id)); 

                $input = array(
                    'user_id'=>(string)$this->owner_id,
                    'title'  =>'Order is dropped at your store',
                    'message'=>'Your new order #'.(string)$this->order_number.' is dropped at your store',
                    'type'=>'Info',
                );
                Yii::$app->push->send($input,array('order_number'=>(string)$this->order_number,'order_id'=>(string)$this->_id)); 

            }else if($this->order_status == "Picked Up Back"){

                $input = array(
                    'user_id'=>(string)$this->user_id,
                    'title'  =>'Your Order is on its way to Delivery.',
                    'message'=>'Your order #'.(string)$this->order_number.' is on its way to Delivery.',
                    'type'=>'Track'
                );
                Yii::$app->push->send($input,array('order_number'=>(string)$this->order_number,'order_id'=>(string)$this->_id));

            }else if($this->order_status == "Delivered"){
                $input = array(
                    'user_id'=>(string)$this->user_id,
                    'title'  =>'Your order is delivered.',
                    'message'=>'Your order #'.(string)$this->order_number.' is delivered.',
                    'type'=>'Info'
                );
                Yii::$app->push->send($input,array('order_number'=>(string)$this->order_number,'order_id'=>(string)$this->_id));
                OrderBags::deleteAll(['order_id'=>(string)$this->_id]);
            }else if($this->order_status == "Cancelled"){
                $OrderDriver = OrderDriver::find()->where(['order_id'=>(string)$this->_id,'status'=>'Accepted'])->asArray()->all(); 
                if($OrderDriver){
                    foreach($OrderDriver as $ele){
                        $input = array(
                            'user_id'=>(string)$ele['driver_id'],
                            'title'  =>'Your order has been cancelled.',
                            'message'=>'Your order #'.(string)$this->order_number.' has been cancelled.',
                            'type'=>'Info'
                        );
                        Yii::$app->push->send($input,array('order_number'=>(string)$this->order_number,'order_id'=>(string)$this->_id));
                    }
                }
                if($this->owner_id){
                    $input = array(
                        'user_id'=>(string)$this->owner_id,
                        'title'  =>'Your order has been cancelled.',
                        'message'=>'Your order #'.(string)$this->order_number.' has been cancelled.',
                        'type'=>'Info',
                    );
                    Yii::$app->push->send($input,array('order_number'=>(string)$this->order_number,'order_id'=>(string)$this->_id));
                }
                
            }

            $OrderHistory           = new OrderHistory;
            $OrderHistory->order_id = (string) $this->_id;
            $OrderHistory->status   = $this->order_status;
            $OrderHistory->save();                
            return [
                'success' => true,
                'data'=> ['status'=>$this->order_status]
            ]; 
        }else{
            return [
                'success' => false,
                'errors'=> $this->errors
            ]; 
        }
    }
    public function orderDriver($order_id,$type){
        $OrderDriver = OrderDriver::find()->where(['order_id'=>(string)$order_id,'type'=>$type,'status'=>'Accepted'])->one();      
        $user = $this->userData(!empty($OrderDriver['driver_id'])?$OrderDriver['driver_id']:"");
        return $user;
    }
    public function orderOwner($owner_id){
        $LaundryOwner    = LaundryOwner::find()->where(['user_id'=>(string)$owner_id])->one();
        $user            = $this->userData(!empty($LaundryOwner['user_id'])?$LaundryOwner['user_id']:"");
        $user['address'] = $LaundryOwner['address'];
        return $user;
    }
    
    public function orderData($OrderId){
        $Order = Order::find()->where(['_id'=>$OrderId])->asArray()->one();
        if(!empty($Order['user_id'])){   
       
            $Order['location_for_driver']  = $this->orderLocation($OrderId);
            $Order['user_detail']     = $this->userData((string)$Order['user_id']);
            $Order['pickup_driver']   = $this->orderDriver((string)$OrderId,"PICK_UP");      
            $Order['delivery_driver'] = $this->orderDriver((string)$OrderId,"DROP_UP"); 
            
            $Order['isDeliveryDriver'] = isset($Order['delivery_driver']['user_id']) && $Order['delivery_driver']['user_id'] ==\Yii::$app->user->id ? true : false;
            $Order['isPickupDriver']   = isset($Order['pickup_driver']['user_id']) && $Order['pickup_driver']['user_id'] ==\Yii::$app->user->id ? true : false;

            $Order['owner']           = !empty($Order['owner_id'])?$this->orderOwner((string)$Order['owner_id']):array();  
            $Order['total_amount']    = isset($Order['total_amount'])?$Order['total_amount'] :0;
            $Order['final_total']     = '$ '.number_format((float)$Order['total_amount'],2);
            if(isset($Order['final_amount'])){
                $Order['final_amount']     = '$ '.number_format((float)$Order['final_amount'],2);
            }else{
                $Order['final_amount']     = '$ 0';
            }
            if(!empty($Order['coupon_discount'])){
                
                 $Order['final_total']     = '$ '.number_format((float)$Order['total_amount']-(float)$Order['coupon_discount'],2);
                 $Order['coupon_discount'] = '$ '.number_format((float)$Order['coupon_discount'],2);
            }
            
            $Order['total_amount']    = '$ '.number_format((float)$Order['total_amount'],2);
            $Order['order_place']     = $Order['created_at'];
            
            $Order['order_picked']    = strtotime($Order['pickup_date']);
            $Order['order_delivered'] = strtotime($Order['delivery_date']);
            $Order['order_history']   = $this->history($OrderId);

            if(!empty($Order['order_item'])){
                $order_item = array();
                foreach($Order['order_item'] as $k => $item){
                    $qty                      =  !empty($item['qty'])?$item['qty']:0;
                    $order_item[$k]          = $item;
                    $order_item[$k]['qty']   = $qty;
                    $order_item[$k]['price'] = '$ '.number_format((float)$item['price'],2);
                    $order_item[$k]['total_price'] = '$ '.number_format((float)$item['price'] * $qty,2);
                }
                $Order['order_item'] = $order_item;
            }
            $Order['user_order_status']   = $this->getOrderStatus($Order['order_status'],"user");
            $Order['owner_order_status']  = $this->getOrderStatus($Order['order_status'],"owner"); 
            $Order['order_bags']          = $this->getOrderBags($Order['order_status'],$OrderId) ; 
            $Order['order_detail_image']  = \Yii::$app->params['organization']['order_detail_image'];    
            $Order['order_id']            =    (string)$Order['_id'];
            $Order['order_note']            = !empty($Order['order_note'])?$Order['order_note']:"";

            return [
                'success' => true,
                'data'=> $Order
            ]; 
        }else{
            return [
                'success' => false,
                'errors'=> 'Order id invalid or Data is not available'
            ];
        }
        return $Order;
        
    }
    public function getOrderBags($OrderStatus,$OrderId){
        return OrderBags::find()->select(['qr_code'])            
        ->where(['order_id'=>(string)$OrderId,'status'=>'PickedAtUser'])->asArray()->all();
        return array();
    }
    public function history($OrderId){
        $OrderHistory = OrderHistory::find()->where(['order_id'=>(string)$OrderId])->asArray()->all();
        $history = array();
        if(!empty($OrderHistory)){
            foreach($OrderHistory as $ele){
                    $time = $ele['created_at'];
                    $D = date("M d, Y",$time).' at '.date('H:i A',$time);
                    $d = $ele['status'];
                    if($ele['status'] == "Pick Up"){
                        $driver = $this->orderDriver($ele['order_id'],"PICK_UP");                    
                        if(!empty($driver['user_name'])){
                            $d = $driver['user_name']." is going to pick up On ";
                        }else{
                            $d = "Driver is going to pick up On ";
                        }
                    }else if($ele['status'] == "Picked Up"){
                            $driver = $this->orderDriver($ele['order_id'],"PICK_UP");                    
                            if(!empty($driver['user_name'])){
                                $d = "Picked by ".$driver['user_name']." On ";
                            }else{
                                $d = "Picked On ";
                            }
                    }else  if($ele['status'] == "Dropped"){
                            $d = "Arrived at clearing house On ";  
                    }else  if($ele['status'] == "Completed"){
                            $d = "Cleaning Completed On ";                        
                    }else  if($ele['status'] == "Picked Up Back"){
                            $driver = $this->orderDriver($ele['order_id'],"DROP_UP");      
                            if(!empty($driver['user_name'])){
                                $d = "Picked by ".$driver['user_name']." for delivery back to customer On ";
                            }else{
                                $d = "Picked up back for delivery back to customer On ";
                            }
                    }else  if($ele['status'] == "Delivered"){
                            $driver = $this->orderDriver($ele['order_id'],"DROP_UP");      
                            if(!empty($driver['user_name'])){
                                $d = "Delivered by ".$driver['user_name']." to customer On ";
                            }else{
                                $d = "Delivered to customer On ";
                            }
                    }else if($ele['status'] == "Cancelled"){
                        $d = "Order has been cancelled On ";
                    }     
                    $group['txt']  = $d;
                    $group['time'] = $time;            
                    array_push($history,$group);
            }
        }
        return array_values($history);
    }
    public function behaviors()
    {
        return [
            DefaultDateTimeBehavior::class,
        ];
    }
    public function generateOrderNo()
    {
        do{
            $orderNo = substr(number_format(time() * rand(),0,'',''),0,6); 
            $Order = Order::find()->where(['order_number'=>$orderNo])->asArray()->one();
        }while($Order);
        return $orderNo;
    }
   
    
    public function beforeSave($insert)
    {
        if ($insert) {
            $this->created_by = (string)Yii::$app->user->id;

        }

        $this->updated_by = (string)Yii::$app->user->id;
        return parent::beforeSave($insert); // TODO: Change the autogenerated stub

    }
}
