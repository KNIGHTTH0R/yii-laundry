<?php
/**
 * @author Harry Tang <harry@powerkernel.com>
 * @link https://powerkernel.com
 * @copyright Copyright (c) 2018 Power Kernel
 */

namespace powerkernel\yiilaundry\models;

use powerkernel\yiicommon\behaviors\UTCDateTimeBehavior;
use powerkernel\yiicommon\Core;
use powerkernel\yiiuser\models\User;
use Yii;
use yii\helpers\Markdown;

class LaundryDriver extends \yii\mongodb\ActiveRecord
{
    
    public static function collectionName()
    {
        return 'store_driver_info';
    }

    /**
     * @inheritdoc
     * @return array
     */
    public function attributes()
    {
        return [
            '_id',
            'user_id',
            'address',            
            'geo_location',
            'dob',
            'online',
            'driver_licence_image',
            'car_registration',
            'bank_details',
            'routing_number',
            'account_number',
        ];
    }
    public function rules()
    {
        return [            
            [['user_id','address','driver_licence_image','car_registration','bank_details','routing_number','account_number','dob','geo_location'], 'required'],
            ['online', 'default', 'value' => '1'],
            ['driver_licence_image', 'url'],
            ['online', 'in', 'range' => ['0','1']],
            [['user_id'], 'exist', 'targetClass' => User::class, 'targetAttribute' => ['user_id' => '_id']],
        ];
    }
}
