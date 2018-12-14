<?php
namespace powerkernel\yiilaundry\controllers;
use yii\filters\AccessControl;
use powerkernel\yiilaundry\models\Cart;
use powerkernel\yiilaundry\models\CartItem;
use powerkernel\yiiproduct\models\Product;
use powerkernel\yiicoupon\models\Coupon;
use powerkernel\yiilaundry\models\Order;
use powerkernel\yiilaundry\models\OrderDriver;

use powerkernel\yiiuser\models\User;
use powerkernel\yiiuser\models\UserAddress;
use yii\data\ActiveDataProvider;
use yii\helpers\ArrayHelper;
use Yii;
/**
 * Class OrderController
 */
class OrderController extends \powerkernel\yiicommon\controllers\ActiveController
{
    public $modelClass = 'powerkernel\yiilaundry\models\Order';
    public function actions()
    {
        $actions = parent::actions();    
        // customize the data provider preparation with the "prepareDataProvider()" method
        $actions['index']['prepareDataProvider'] = [$this, 'prepareDataProvider'];
        unset($actions['create']);
        unset($actions['delete']);
        unset($actions['view']);  
        return $actions;
    }
    public function prepareDataProvider()
    {
       
        $searchModel = new \powerkernel\yiilaundry\models\OrderSearch();  
        return $searchModel->search(\Yii::$app->request->queryParams);
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
                    'actions' => [ 'update','delete','view-order','index','dashboard'],               
                    'roles' => ['admin'],
                    'allow' => true,
                ],
                [     
                    'actions' => ['user-order','view-order','user-cancel-order','order-track'],               
                    'roles' => ['@'],
                    'allow' => true,
                ],
                [     
                    'actions' => [ 'driver-order','view-order'],               
                    'roles' => ['driver','admin'],
                    'allow' => true,
                ],
                [     
                    'actions' => [ 'owner-order','view-order'],               
                    'roles' => ['owner','admin'],
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
                'index' => ['GET'],
                'update' => ['POST'],
                'delete' => ['GET'],
                'view' => ['POST'],
            ]
        );
    }
     //##### For member ##############
     public function actionUserOrder($type=""){  
        $query = Order::find()->where(['user_id'=>(string)\Yii::$app->user->id]);
        if(!empty($type)){
            if($type=="current"){
                $query->andWhere(['AND',["!=",'order_status','Delivered'],["!=",'order_status','Cancelled']]);
            }
            if($type=="past"){
                $query->andWhere(['OR',["=",'order_status','Delivered'],["=",'order_status','Cancelled']]);
            }
        }
        $query->orderBy([
            'created_at'=>SORT_DESC
        ]);;
        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => [
                'pageSize' => 10,
            ],
        ]);
       return $dataProvider;
    }
    public function actionUserCancelOrder($id){
        $Order = Order::find()->where(['_id'=>(string)$id,'user_id'=>(string)\Yii::$app->user->id])->one();
        if(!empty($Order)){
            if($Order->order_status == "Pending"){
                $Order->order_status = "Cancelled";
                return $Order->changeStatus();
            }else{
                return [
                    'success' => false,
                    'errors'=> "Sorry you are not able to cancel order."
                ]; 
            }
        }else{
            return [
                'success' => false,
                'errors'=> "Data is not available with this order"
            ]; 
        }
    }
    //############ Driver's Order #################
    public function actionDriverOrder($driverId ="",$type=""){
        $driverId = $driverId==""?\Yii::$app->user->id:$driverId;
        $Order = OrderDriver::find()->select(['order_id'])->where(['driver_id'=>(string)$driverId,'status'=>'Accepted'])
        ->asArray()->all();
        $OId = [];
        if(!empty($Order)){
            foreach($Order as $Oid){
              array_push($OId,(string)$Oid['order_id']);
            }
        }
        $query = Order::find()->where([
            'AND',
            ['IN','_id',$OId],
        ]);

        if(!empty($type)){
            if($type=="current"){
                $query->andWhere(['AND',["!=",'order_status','Delivered'],["!=",'order_status','Cancelled']]);
            }
            if($type=="past"){
                $query->andWhere(['OR',["=",'order_status','Delivered'],["=",'order_status','Cancelled']]);
            }
        }

        $query->orderBy([
            'created_at'=>SORT_DESC
        ]);;

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
        ]);
        
        return $dataProvider;
    }
    public function actionOwnerOrder($ownerId ="",$type=""){  
        $ownerId = $ownerId==""?\Yii::$app->user->id:$ownerId;
        $query  = Order::find()->where(['owner_id'=>(string)$ownerId]);
        
        if(!empty($type)){
            if($type=="current"){
                $query->andWhere(['AND',["!=",'order_status','Delivered'],["!=",'order_status','Cancelled']]);
            }
            if($type=="past"){
                $query->andWhere(['OR',["=",'order_status','Delivered'],["=",'order_status','Cancelled']]);
            }
        }

        $query->orderBy([
            'created_at'=>SORT_DESC
        ]);;

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
        ]);       
        return $dataProvider;
    }
    public function actionViewOrder($id){
        $Order = new Order();
        return $Order->orderData($id);
    } 
    public function actionOrderTrack(){

        $data = \Yii::$app->getRequest()->getParsedBody();
        $order_id = !empty($data['order_id'])?$data['order_id']:"";

        $order = Order::find()->where(['_id'=>(string)$order_id])->one();
        if(empty($order->order_status)){
            return[
                'success'=>false,
                'errors'=>'Invalid Order Id'
            ];
        }
        if($order->order_status == "Picked Up Back"){
            $OrderDriver = OrderDriver::find()->where(['status'=>'Accepted','order_id'=>(string)$order_id,'type'=>'DROP_UP'])
                ->asArray()->one(); 
        }else{
            $OrderDriver = OrderDriver::find()->where(['status'=>'Accepted','order_id'=>(string)$order_id,'type'=>'PICK_UP'])
                ->asArray()->one(); 
        }
       
        $pickup_location = !empty($OrderDriver['from_location'])?$OrderDriver['from_location']:"";
        $pickup_location = UserAddress::find()->select(['address'])->where(['address.name'=>$pickup_location])->asArray()->one();
        
       
        $dropup_location = !empty($OrderDriver['to_location'])?$OrderDriver['to_location']:"";
       
        $dropup_location = UserAddress::find()->select(['address'])->where(['address.name'=>$dropup_location])->asArray()->one();
        return[
            'success'=>true,
            'data'=>[
                'pickup'=>!empty($pickup_location['address'])?$pickup_location['address']:"",
                'dropup'=>!empty($dropup_location['address'])?$dropup_location['address']:"",
            ]
        ];
    } 

}
