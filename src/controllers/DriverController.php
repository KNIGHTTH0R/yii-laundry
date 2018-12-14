<?php
namespace powerkernel\yiilaundry\controllers;
use Yii;
use yii\filters\AccessControl;
use yii\data\ActiveDataProvider;
use powerkernel\yiilaundry\models\Order;
use yii\mongodb\ActiveQuery;
use yii\mongodb\Collection;

use powerkernel\yiilaundry\models\Bags;
use powerkernel\yiilaundry\models\OrderBags;
use powerkernel\yiilaundry\models\OrderDriver;
use powerkernel\yiilaundry\models\OrderHistory;
use powerkernel\yiilaundry\models\LaundryDriver;
use powerkernel\yiipush\models\Notification;
use powerkernel\yiiauth\models\AuthAssignment;

/**
 * Class DriverController
 */
class DriverController extends \powerkernel\yiicommon\controllers\ActiveController
{
    public $modelClass = '';
    public function actions()
    {
        $actions = parent::actions();
        unset($actions['create']);
        unset($actions['delete']);
        unset($actions['view']);
        return $actions;
    }
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
                    'actions' => ['image-upload','edit-profile','get-user','get-driver-info','my-orders','qr-scan','accept-or-reject','change-order-status','is-online','remove-qrcode','view','change-status','driver-license-image'],               
                    'roles' => ['driver'],
                    'allow' => true,
                ],
                [     
                    'actions' => ['remove-qrcode','get-drivers','driver-license-image'],               
                    'roles' => ['admin'],
                    'allow' => true,
                ],
            ],
        ];
        return $behaviors;
    }
    
    protected function verbs()
    {
        $parents = parent::verbs();
        return array_merge(
            $parents,
            [
                'my-orders' => ['POST'],
                'qr-scan' => ['POST'],
                'accept-or-reject' => ['POST'],
                'change-order-status' => ['POST'],
                'is-online' => ['POST'],
                'remove-qrcode' => ['POST'],
                'driver-license-image' => ['POST'],
            ]
        );
    }  
    public function actionAcceptOrReject(){
        $data = \Yii::$app->getRequest()->getParsedBody();
        if(!empty($data['status']) && !empty($data['order_id'])){   
            $driver = OrderDriver::find()->where(['driver_id'=>(string)\Yii::$app->user->id,'order_id'=>isset($data['order_id']) ? (string)$data['order_id'] : "","status"=>"Pending"])->one();
            if(!empty($driver )){
                $driver->status = $data['status'];
                if($driver->save()){
                    $Notification = Notification::find()->where(['order_id'=>(string)$data['order_id'],'user_id'=>(string)\Yii::$app->user->id])->one();
                    !empty($Notification)?$Notification->delete():"";
                    if($data['status']=="Accepted"){

                        //Send Notification/ 
                        $Order = Order::find()->where(['_id'=>(string)$data['order_id']])->asArray()->one();
                        if( $Order['order_status']=="Completed"){
                            $input = array(
                                'user_id'=>$Order['owner_id'],
                                'title'  =>'Your order has been accepted by the driver',
                                'message'=>'Your order #'.$Order['order_number'].' has been accepted by the driver, He will come soon at your store for picking up.',
                                'type'=>'Notification'
                            );
                            Yii::$app->push->send($input,array('order_number'=>$Order['order_number'],'order_id'=>(string)$data['order_id']));     
                             
                            $input = array(
                                'user_id'=>$Order['user_id'],
                                'title'  =>'Delivery driver has been assigned to your order #'.$Order['order_number'],
                                'message'=>'Your order #'.$Order['order_number'].' has been assigned with delivery driver.',
                                'type'=>'Notification'
                            );
                            Yii::$app->push->send($input,array('order_number'=>$Order['order_number'],'order_id'=>(string)$data['order_id']));      
                        }else{
                            $input = array(
                                'user_id'=>$Order['user_id'],
                                'title'  =>'Your order has been accepted by the driver',
                                'message'=>'Your order #'.$Order['order_number'].' has been accepted by the driver, He will come soon for picking up your clothes.',
                                'type'=>'Order'
                            );
                            Yii::$app->push->send($input,array('order_number'=>$Order['order_number'],'order_id'=>(string)$data['order_id'])); 
                        }
                             
                                          
                    }
                    return $driver;
                }else{
                    return [
                        "success" => false,
                        "errors"  => $driver->errors
                    ];
                }
                
            }else{
                return [
                    "success" => false,
                    "errors"  => "Your time is over now, You can not proceed with this order."
                ];
            }
        }else{
            return [
                "success" => false,
                "errors"  => "status and order_id is required"
            ];
        }
        
    }
    public function actionQrScan(){         
        $data = \Yii::$app->getRequest()->getParsedBody();     
        $bags = Bags::find()->where(['bag_qr_code'=>!empty($data['qr_code'])?(string)$data['qr_code']:""])->one();
        if(empty($bags)){
            return [
                "success" => false,
                "errors"  => "Bag Not Found."
            ];
        }

        $Order         = Order::find()->where(['_id'=>$data['order_id']])->one();            
        if(empty($Order)){
            return [
                "success" => false,
                "errors"  => "Order is not exist with this Order."
            ];  
        }

        if($Order->order_status == "Completed"){
            $OrderBags     = OrderBags::find()->where(['qr_code'=>(string)$data['qr_code'],'status'=>'PickedAtStore'])->one();  
        }else{
            $OrderBags     = OrderBags::find()->where(['qr_code'=>(string)$data['qr_code'],'status'=>'PickedAtUser'])->one();  
        }
        
        if(!empty($OrderBags)){
            return [
                "success" => false,
                "errors"  => "Sorry, This bag is already assigned with order."
            ];  
        }

        $bagWithQrCode = OrderBags::find()->where([
            'order_id'=>!empty($data['order_id'])?(string)$data['order_id']:"",
            'qr_code'=>(string)$data['qr_code'],
        ])->one();

        if($Order->order_status=="Completed"){
            if(empty($bagWithQrCode)){
                return [
                    "success" => false,
                    "errors"  => "Bag is not assigned with this Order."
                ];   
            }
            if(!empty($bagWithQrCode) && $bagWithQrCode->status == "PickedAtStore"){                
                return [
                    "success" => false,
                    "errors"  => "Bag is already scanned for delivery."
                ]; 
            }
            
        }else if($Order->order_status!="Completed" && !empty($bagWithQrCode) && $bagWithQrCode->status == "PickedAtUser" ){
            return [
                "success" => false,
                "errors"  => "Bag is already scanned for picking up."
            ];                                  
        }   

        $bagWithQrCode           =    !empty($bagWithQrCode)?$bagWithQrCode:new OrderBags();
        $bagWithQrCode->order_id =    !empty($data['order_id'])?(string)$data['order_id']:"";
        $bagWithQrCode->qr_code  =    (string)$data['qr_code'];
        $bagWithQrCode->status   =    $Order->order_status =="Completed"?'PickedAtStore':'PickedAtUser';   
                    
        if($bagWithQrCode->save()){
            if($Order->order_status=="Completed"){
                $availableBagsCount = OrderBags::find()->where([
                    'order_id'=>!empty($data['order_id'])?(string)$data['order_id']:"",
                    'status'=>'PickedAtUser',
                ])->count();
                return[
                    'success'=>true,
                    'data'=>[
                        'moreBags'=>$availableBagsCount
                    ]
                ];
            }else{
                return[
                    'success'=>true,
                ];
            }

        }else{
            $bagWithQrCode->validate();
            return[
                'success'=>false,
                'errors'=>$bagWithQrCode->validate()
            ];
        }
        return $bagWithQrCode;
        
    }
    public function actionRemoveQrcode(){
        $data = \Yii::$app->getRequest()->getParsedBody();
        if(empty($data['qr_code']) || empty($data['order_id'])){
            return array(
                'success'=>false,
                'errors' => 'Qr Code and Order id is Required.'
            );
        }
        
        $Order = Order::find()->where(['OR',
                        ['order_status'=>"Pick Up"],
                        ['order_status'=>"Picked Up"]
                        ])->andWhere(['_id'=>(string)$data['order_id']])->one();
        if(empty($Order)){
            return array(
                'success'=>false,
                'errors' => 'Order id is invalid or you can not able to remove bags'
            );
        }
        $bagWithQrCode = OrderBags::find()->where(['order_id'=>(string)$data['order_id'],'qr_code'=>$data['qr_code'],'status'=>'PickedAtUser'])->one();
        if(!empty($bagWithQrCode)){
            $bagWithQrCode->delete();
            $bags = OrderBags::find()->where(['order_id'=>(string)$data['order_id']])->count();
            return array(
                'success'=>true,
                'data'=>['bags'=>$bags]
            );
        }else{
            return array(
                'success'=>false,
                'errors' => 'Bag is not found.'
            );
           
        }
       
    }
    public function actionGetDrivers(){
        return \Yii::$app->runAction('v1/laundry/admin/check-data',["type"=>'driver']);
    }
    public function actionChangeOrderStatus(){   
        $model = new OrderHistory();
        if($model->load(Yii::$app->getRequest()->getParsedBody(),'') && $model->validate()){    
            $Order = Order::find()->where(['_id'=>(string)$model->order_id])->one();
            if(!empty($Order)){
                $OrderDriver = OrderDriver::find()->where(['order_id'=>(string)$model->order_id,'driver_id'=>(string)Yii::$app->user->id,'status'=>'Accepted'])->one();
                if(!empty($OrderDriver)){
                    $Order->order_status  = $model->status;
                    return $Order->changeStatus();
                }else{
                    return [
                        'success' => false,
                        'errors'=> 'Order id is not allocated with this driver'
                    ]; 
                }
            }else{
                return [
                    'success' => false,
                    'errors'=> 'Order id invalid or Data is not available'
                ];
            }
        }else{           
            return [
                'success' => false,
                'errors' => $model->errors
            ];
        }   
    }

    public function actionIsOnline(){
        $data = Yii::$app->getRequest()->getParsedBody();
        if(!isset($data['online']) || $data['online'] == '' ){
            $data['online'] = 1;
        }
        $LaundryDriver = LaundryDriver::find()->where(["user_id"=>(string)Yii::$app->user->id])->one();        
        if(empty($LaundryDriver)){
            return [
                'success' => false,
                'errors' => 'Driver Not Found Or Invalid Token'
            ];
        }
        
        $LaundryDriver->online = $data['online'];
        if ($LaundryDriver->save()) {
            return [
                'success' => true,
                'data' => $data['online']
            ];
        } else {
            return [
                'success' => false,
                'errors' => $LaundryDriver->errors
            ];
        }
    }

    public function actionDriverLicenseImage(){
        $data = \Yii::$app->getRequest()->getParsedBody();
        if(empty($data['image'])){
            return [
                'success' => false,
                'errors' => "Image is required."
            ];
        }
        $array_thumb = array( 
            "eager" => array(
                array("width" => 100, "height" => 100, "crop" => "fit"),
                array("width" => 200, "height" => 200, "crop" => "fit"),
                array("width" => 500, "height" => 500, "crop" => "fit")
            ));
        $image = \Yii::$app->cloudinary->upload($data['image'],$array_thumb);       
        return $image;
    }
    
    public function actionImageUpload(){
        $data = \Yii::$app->getRequest()->getParsedBody();
        $array_thumb = array( 
            "eager" => array(
                array("width" => 200, "height" => 200, "crop" => "fit")));
        $image = \Yii::$app->cloudinary->upload($data['image'],$array_thumb);
       
        return $image;
    }
    
}