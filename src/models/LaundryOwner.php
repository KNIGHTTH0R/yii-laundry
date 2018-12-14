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


/**
 * This is the model class for Page.
 *
 * @property \MongoDB\BSON\ObjectID $_id
 * @property string $user_id
 * @property string $address
 * @property \MongoDB\BSON\UTCDateTime $created_at
 * @property \MongoDB\BSON\UTCDateTime $updated_at
 */
class LaundryOwner extends \yii\mongodb\ActiveRecord
{

  
    
    public static function collectionName()
    {
        return 'store_owner_info';
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
            'online',
            'dob',
            'geo_location',
            'store_name',
            'total_order',
            'bank_details',
            'routing_number',
            'account_number'
        ];
    }
    public function rules()
    {
        return [            
            [['user_id','address','geo_location','store_name','bank_details','routing_number','account_number','dob'], 'required'],
            ['online', 'default', 'value' => '1'],
            ['online', 'in', 'range' => ['0','1']],
            [['user_id'], 'exist', 'targetClass' => User::class, 'targetAttribute' => ['user_id' => '_id']],

        ];
    }

}
