<?php
/**
 * @author Harry Tang <harry@powerkernel.com>
 * @link https://powerkernel.com
 * @copyright Copyright (c) 2018 Power Kernel
 */

namespace powerkernel\yiilaundry\models;

use powerkernel\yiicommon\behaviors\UTCDateTimeBehavior;
use powerkernel\yiiuser\models\User;
use powerkernel\yiilaundry\models\Order;
use powerkernel\yiilaundry\models\Bags;
use yii\validators\ExistValidator;

use Yii;

class OrderBags extends \yii\mongodb\ActiveRecord
{
    
    public static function collectionName()
    {
        return 'order_bags_db';
    }
    public function attributes()
    {
        return [
            '_id',
            'order_id',
            'qr_code',
            'status',
            'created_at',
            'updated_at',
        ];
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['order_id','qr_code'], 'required'],

            [['order_id'], 'exist', 'targetClass' => Order::class, 'targetAttribute' => ['order_id' => '_id']],
            ['status', 'default', 'value' => "PickedAtUser"],    
            ['status', 'in', 'range' => ['PickedAtUser','PickedAtStore']],
            // [['qr_code'], 'exist', 'targetClass' => Bags::class, 'targetAttribute' => ['qr_code' => 'bag_qr_code']],

        ];
    }
    
    public function behaviors()
    {
        return [
            UTCDateTimeBehavior::class,
        ];
    }
}
