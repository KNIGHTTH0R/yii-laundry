<?php
/**
 * @author Harry Tang <harry@powerkernel.com>
 * @link https://powerkernel.com
 * @copyright Copyright (c) 2018 Power Kernel
 */


namespace powerkernel\yiilaundry\controllers;

use yii\filters\AccessControl;
use powerkernel\yiiproduct\models\Product;
use Da\QrCode\QrCode;
use powerkernel\yiilaundry\models\Bags;
use yii\data\ActiveDataProvider;


/**
 * Class BagsController
 */
class BagsController extends \powerkernel\yiicommon\controllers\ActiveController
{
    public $modelClass = 'powerkernel\yiilaundry\models\Bags';
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
                    'actions' => [ 'update', 'create', 'delete','view','addqrcode','index','lot'],
                    'roles' => ['admin'],
                    'allow' => true,
                ]
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
                'addqrcode' => ['POST'],
            ]
        );
    }
    public function actions()
    {
        $actions = parent::actions();
        $actions['index']['prepareDataProvider'] = [$this, 'prepareDataProvider'];
        
        return $actions;
    }
     public function prepareDataProvider(){
         
        $data = \Yii::$app->request->queryParams;
        if(!empty($data['Bags']['lot'])){
            $query        = Bags::find()->where(['like','lot',$data['Bags']['lot']]);
        }else{
            $query        = Bags::find();
        }
        
        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => [
                'pageSize' => 24,
            ],
        ]);
       return $dataProvider;
    }
    
    public function actionAddqrcode(){
        $data   = \Yii::$app->getRequest()->getParsedBody();
        $noBags = !empty($data['bag_name'])?$data['bag_name']:0;
        $lot    = !empty($data['lot'])?$data['lot']:"";
        $model  = new Bags();
        $model->bag_name        = $noBags;
        $model->bag_qr_code     = '3242';
        $model->lot = $lot;
        if(!$model->validate()){
            return[
                'success'=>false,
                'errors'=>$model->errors
            ];
        }

        $Bags = Bags::find()->orderBy(['created_at'=>SORT_DESC])->count();
        for($i=1;$i<=$noBags;$i++){
            $code = uniqid().time();
            $qrCode = (new QrCode($code))
                ->setSize(500)
                ->setMargin(10);
            $model = new Bags();
            $model->bag_name    = $Bags + $i;
            $model->bag_qr_code = $code;
            $model->lot = $lot;
            if($model->validate()){
                $imageUrl = $this->qrUpload($qrCode->writeDataUri());
                $model->bag_qr_code_image_url = $imageUrl;
                $model->save();
            }
        }
        return [
            'success'=>true
        ];      
       
    }

    public function qrUpload($value){
        $array_thumb = array( 
            "eager" => array(
                array("width" => 200, "height" => 200, "crop" => "fit")));
        $image = \Yii::$app->cloudinary->upload($value,$array_thumb );
        return $image['url'];
    }
}