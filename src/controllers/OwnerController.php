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
use powerkernel\yiilaundry\models\LaundryOwner;


class OwnerController extends \powerkernel\yiicommon\controllers\ActiveController
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
                    'actions' => [ 'my-orders'],               
                    'roles' => ['owner'],
                    'allow' => true,
                ],
                [     
                    'actions' => [ 'view','complete','is-online','order-detail','image-upload'],               
                    'roles' => ['owner'],
                    'allow' => true,
                ],
                [     
                    'actions' => [ 'my-orders','view'],               
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
                'complete' => ['POST'],
            ]
        );
    }
    public function actionIsOnline(){
        $data = Yii::$app->getRequest()->getParsedBody();
        if(!isset($data['online']) || $data['online'] == '' ){
            $data['online'] = 1;
        }      
        $LaundryOwner = LaundryOwner::find()->where(["user_id"=>(string)Yii::$app->user->id])->one();        
        if(empty($LaundryOwner)){
            return [
                'success' => false,
                'errors' => 'Store Not Found Or Invalid Token'
            ];
        }
        
        $LaundryOwner->online = $data['online'];
        if ($LaundryOwner->save()) {
            return [
                'success' => true,
                'data' => $data['online']
            ];
        } else {
            return [
                'success' => false,
                'errors' => $LaundryOwner->errors
            ];
        }
    }
    public function actionOrderDetail($id){
        $Order = new Order();
        return $Order->orderData($id);
    }
    public function actionComplete(){
        $model = new OrderHistory();
        if($model->load(Yii::$app->getRequest()->getParsedBody(),'') && $model->validate()){
            $Order = Order::find()->where(['_id'=>(string)$model->order_id,'owner_id'=>(string)Yii::$app->user->id])
            ->andWhere(['OR',['order_status'=>'Dropped'],['order_status'=>'Completed']])
            ->andWhere(['!=','payment_status','Success'])
            ->one();
            
            if(!empty($Order)){
                if($Order->final_amount <= 0){
                    return [
                        'success' => false,
                        'errors'=> 'Please update the order details. You have at least one item to place order.'
                    ];
                }
                $Order->order_status        = "Completed";
                $Order->changeStatus();    
                $PaymentRes = Yii::$app->runAction('v1/billing/paypal/make-payment',['OrderNo'=>$Order->order_number]);   
                if(isset($PaymentRes['success']) && $PaymentRes['success']==true){
                    $SearchDriverResponse = Yii::$app->runAction('v1/laundry/cart/set-driver-and-owner',['order_id'=>(string)$Order->_id]);   
                    return $SearchDriverResponse;
                }else{
                    $msg = "We are unable to charge the payment for order no #".(string)$Order->order_number.". Delivery driver will assign you once we collect the payment.Please keep the user's stuff with you.";                   
                    $input = array(
                        'user_id'=>(string)$Order->owner_id,
                        'title'  =>'We are unable to charge the payment for order #'.(string)$Order->order_number,
                        'message'=>$msg,
                        'type'=>'Info'
                    );
                    Yii::$app->push->send($input,array('order_number'=>(string)$Order->order_number,'order_id'=>(string)$Order->_id));
                    return[
                        'success'=>false,
                        'errors'=>$msg
                    ];
                }  
                
            }else{
                return [
                    'success' => false,
                    'errors'=> 'We have no such order to complete.'
                ];
            }
        }else{
            $model->validate();
            return [
                'success' => false,
                'errors' => $model->errors
            ];
        }
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
