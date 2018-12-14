<?php
namespace powerkernel\yiilaundry\controllers;
use yii\filters\AccessControl;
use yii\data\ActiveDataProvider;
use powerkernel\yiilaundry\models\Order;
use powerkernel\yiiproduct\models\Product;
use powerkernel\yiiproduct\models\Category;
use powerkernel\yiilaundry\models\LaundryDriver;
use powerkernel\yiiuser\models\User;
use powerkernel\yiilaundry\models\Setting;
use powerkernel\yiilaundry\models\Logger;


/**
 * Class AdminController
 */
class AdminController extends \powerkernel\yiicommon\controllers\ActiveController
{
    public $modelClass = 'powerkernel\yiilaundry\models\Order';

    /**
     * @inheritdoc
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
                    'actions' => ['dashboard','check-data','send-notification','update-driver-location','update','add-setting','get-setting','logger','flush-logger'],
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
                'dashboard' => ['POST'],
                'check-data' => ['POST'],
                'update' => ['POST'],
            ]
        );
    }
    public function actionGetSetting(){
        return Setting::find()->all();
    }
    public function actionAddSetting(){
        $data = \Yii::$app->getRequest()->getParsedBody();
        if(!empty($data['type'])){
            $model          =    Setting::find()->where(['type'=>$data['type']])->one();
            $model          =    !empty($model)?$model:new Setting;
            $model->type    =    $data['type'];
            $model->data    =    $data['data'];
            if($model->save()){                   
                return $model;
            }else{
                $model->validate();
                return[
                    'success'=>false,
                    'errors'=>$model->errors
                ];
            }
        }else{
            return[
                'success'=>false,
                'errors'=>'Please select type of setting.'
            ];
        }
    }
    public function actionDashboard(){
        $product = Product::find()->count();
        $category = Category::find()->count();
        $order   = Order::find()->count();
        $users   = \Yii::$app->runAction('v1/laundry/admin/check-data',["type"=>'member']);
        $driver  = \Yii::$app->runAction('v1/laundry/admin/check-data',["type"=>'driver']);
        $owner   = \Yii::$app->runAction('v1/laundry/admin/check-data',["type"=>'owner']);
        return array("product"=>$product,'category'=>$category,"order"=>$order,"User"=>count($users),'driver'=>count($driver),'owner'=>count($owner));
      
    }    
    public function actionCheckData($type = "member"){
        $user = User::find()->asArray()->all();
        $allusers = array();
        $users = array();
        if(!empty($user)){
            foreach($user as $i=>$u){
                $users[$i] = $u;
                $users[$i]['_id'] = (string)$u['_id'];
                $roles = $this->rolesArray(\Yii::$app->authManager->getRolesByUser((string)$u['_id']));
                $users[$i]['roles'] = $roles;
            }
        }
        if($type == 'member'){
            $allusers = $users;
        }else{
            if(!empty($users)){
                foreach($users as $u){
                    if(in_array($type,$u['roles'])){
                        $allusers[] = $u;
                    }
                }
            }
        }
        return $allusers;
    }

   

    public function rolesArray($array){
        $roles = array();
        if(!empty($array)){
            foreach($array as $key => $a){
                $roles[] = $key;
            }   
        }
        return $roles;
    }
    public function actionUpdateDriverLocation(){
        $data = \Yii::$app->getRequest()->getParsedBody();
        if(!empty($data['driver_id']) && !empty($data['lat']) && !empty($data['long'])){
            $LaundryDriver = LaundryDriver::find()->where(['user_id'=>$data['driver_id']])->one();
            if(!empty($LaundryDriver)){
                $d = [
                    "type"=> "Point",
                    "coordinates"=> [
                             $data['long'],
                             $data['lat']
                    ]
                ];
                $LaundryDriver->geo_location = $d;
                $LaundryDriver->save();
            }
        }
        return[
            'success'=>true
        ];
    }
    public function actionLogger(){
       
        $query = Logger::find()->orderBy([
            'created_at'=>SORT_DESC
        ]);;

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => [
                'pageSize' => 20,
            ],
        ]);
       return $dataProvider;
    }
    public function actionFlushLogger(){
            Logger::deleteAll();
            return[
                'success'=>true
            ];
    }
}
