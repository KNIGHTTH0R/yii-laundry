<?php
/**
 * @author Harry Tang <harry@powerkernel.com>
 * @link https://powerkernel.com
 * @copyright Copyright (c) 2018 Power Kernel
 */


namespace powerkernel\yiilaundry\controllers;

use yii\filters\AccessControl;
use powerkernel\yiilaundry\models\Review;
use powerkernel\yiilaundry\models\Order;
use powerkernel\yiilaundry\models\OrderDriver;
use Yii;
use yii\data\ActiveDataProvider;

// use powerkernel\yiifaq\models\FAQ;

/**
 * Class ReviewController
 */
class ReviewController extends \powerkernel\yiicommon\controllers\ActiveController
{
    public $modelClass = 'powerkernel\yiilaundry\models\Review';
    
    /**
     * view group of  FAQs
     * @param string $group
     * @param string $lang
     * @return array
     */
    
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
                    'actions' => [ 'update', 'create', 'delete','view','index','review'],
                    'roles' => ['admin'],
                    'allow' => true,
                ],
                [
                    'actions' => [ 'addreview'],
                    'roles' => ['@'],
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
                'create' => ['POST'],
                'update' => ['POST'],
                'delete' => ['GET'],
                'view' => ['POST'],
            ]
        );
    }

    
    public function actionReview($type,$user_id){
        $data = \Yii::$app->getRequest()->getParsedBody();
        if(!empty($type) && !empty($user_id)){
            $query = Review::find()->where(['rate_to_id'=>$user_id,'type'=>$type])->orderBy([
                'created_at'=>SORT_ASC
            ]);    
    
            $dataProvider = new ActiveDataProvider([
                'query' => $query,
                'pagination' => [
                    'pageSize' => 9,
                ],
            ]);
           return $dataProvider;
        }else{
            return[
                'success'=>false,
                'errors'=>'Please select user.'
            ];
        }
    }
    public function actionAddreview(){
        $model = new Review();
        if($model->load(Yii::$app->getRequest()->getParsedBody(),'') && $model->validate()){
            $Order = Order::find()->where([
                '_id'=>(string)$model->order_id,
                'user_id'=>(string)Yii::$app->user->id,
                'order_status'=>'Delivered'])
            ->one();
            if(!empty($Order)){
               if($model->type == "driver"){
                    $OrderDriver = OrderDriver::find()->where(['order_id'=>(string)$model->order_id,'status'=>'Accepted'])->asArray()->all(); 
                    if(!empty($OrderDriver)){
                        foreach($OrderDriver as $driver){
                                $Rate = Review::find()->where(['order_id'=>(string)$model->order_id,'type'=>'driver','rate_from_id'=>(string)Yii::$app->user->id,
                                'rate_to_id'=>(string)$driver['driver_id']])->one();

                                $modelD             = !empty($Rate)?$Rate: new Review();
                                $modelD->load(Yii::$app->getRequest()->getParsedBody(),'');
                                $modelD->rate_from_id =  (string)Yii::$app->user->id;
                                $modelD->rate_to_id   =  (string)$driver['driver_id'];
                                $modelD->order_id     =  (string)$model->order_id;
                                
                                if($modelD->save()){
                                    $input = array(
                                        'user_id'=> $modelD->rate_to_id,
                                        'title'  =>'User has left the review.',
                                        'message'=>'User has left the review for order #'.(string)$Order->order_number,
                                        'type'=>'Notification'
                                    
                                    );
                                    Yii::$app->push->send($input,array('order_number'=>(string)$Order->order_number,'order_id'=>(string)$Order->_id));
                                }
                        }
                       // return $s;
                    }
               }else if($model->type == "owner"){
                    $Rate   = Review::find()->where(['order_id'=>(string)$model->order_id,'type'=>'owner'])->one();
                    $model  = !empty($Rate)?$Rate:$model;
                    $model->load(Yii::$app->getRequest()->getParsedBody(),'');
                    $modelD               =  $model;
                    $modelD->rate_from_id =  (string)Yii::$app->user->id;
                    $modelD->rate_to_id   =  (string)$Order->owner_id;
                    $modelD->save();

                    $input = array(
                        'user_id'=>(string)$Order->owner_id,
                        'title'  =>'User has left the review.',
                        'message'=>'User has left the review for order #'.(string)$Order->order_number,
                        'type'=>'Notification'
                    );
                    Yii::$app->push->send($input,array('order_number'=>(string)$Order->order_number,'order_id'=>(string)$Order->_id));
    
               }
                return [
                    'success' => true,
                    'data'=>'Thanks for rate us.'
                ];
            }else{
                return [
                    'success' => false,
                    'errors'=> 'Unable to rate this order'
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
}
