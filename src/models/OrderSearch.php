<?php

namespace powerkernel\yiilaundry\models;

use Yii;
use powerkernel\yiilaundry\models\Order;
use powerkernel\yiilaundry\models\OrderDriver;
use powerkernel\yiicommon\behaviors\UTCDateTimeBehavior;
use yii\data\ActiveDataProvider;

/**
 * OrderSearch represents the model behind the search form of `common\models\PaypalIpn`.
 */
class OrderSearch extends Order
{
    /**
     * @inheritdoc
     * @return array
     */
    public function attributes()
    {
        return [
            '_id',
            'user_id',
            'order_number',
            'total_qty',
            'total_amount',
            'coupon_code',
            'coupon_discount',
            'owner_id',
            'pickup_address',
            'delivery_address',
            'pickup_date',
            'delivery_date',
            'payment_method',
            'order_status',
            'payment_status',
            'order_item',
            'created_by',
            'updated_by',
            'created_at',
            'updated_at',
            'items'
        ];
    }
    public function search($params)
    {
        $this->load($params);
        $query = Order::find();
        $order_status   = (isset($params['OrderSearch']['order_status'])) ? $params['OrderSearch']['order_status'] : "";
        if(!empty($params['OrderSearch']['order_number'])){
            $query->andWhere(['like', 'order_number',$params['OrderSearch']['order_number']]);
        }
        if(!empty($params['OrderSearch']['order_status'])){
            $query->andWhere(['=', 'order_status',$params['OrderSearch']['order_status']]);
        }   
        if(!empty($params['OrderSearch']['start_date'])){
            $query->andWhere([">=", "created_at", (int)strtotime($params['OrderSearch']['start_date'])]);
        }  
        if(!empty($params['OrderSearch']['end_date'])){
            $query->andWhere(["<=", "created_at", (int)strtotime($params['OrderSearch']['end_date'])+86400]);
        }  
        if(!empty($params['OrderSearch']['user_id'])){          
            $query->andWhere(["IN", "user_id", $params['OrderSearch']['user_id']]);
        }  
        if(!empty($params['OrderSearch']['driver_id'])){ 
            $Order = OrderDriver::find()->where(['driver_id'=>$params['OrderSearch']['driver_id'],'status'=>'Accepted'])->asArray()->all();
        
            $OrderIds =  \yii\helpers\ArrayHelper::map($Order,'order_id','order_id');   
           
            $query->andWhere(['IN', '_id',$OrderIds]);
        }   
        $query->orderBy([
            'created_at' => SORT_DESC,
        ]);
        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => [
                'pageSize' => 20,
            ],
        ]);
      
    
        return $dataProvider;
    }
}