<?php
namespace powerkernel\yiilaundry\models;
use powerkernel\yiicommon\behaviors\UTCDateTimeBehavior;
use powerkernel\yiiuser\models\User;
use Yii;
class Setting extends \yii\mongodb\ActiveRecord
{
    
    public static function collectionName()
    {
        return 'setting';
    }
    public function attributes()
    {
        return [
            '_id',
            'type',
            'data',
        ];
    }
    public function rules()
    {
        return [
            [['type'], 'required'],    
        ];
    }
    
}
