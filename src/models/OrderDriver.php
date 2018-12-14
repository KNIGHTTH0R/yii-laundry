<?php
/**
 * @author Harry Tang <harry@powerkernel.com>
 * @link https://powerkernel.com
 * @copyright Copyright (c) 2018 Power Kernel
 */
namespace powerkernel\yiilaundry\models;
use powerkernel\yiicommon\behaviors\UTCDateTimeBehavior;
use powerkernel\yiiuser\models\User;
use Yii;
class OrderDriver extends \yii\mongodb\ActiveRecord
{

    public static function collectionName()
    {
        return 'order_driver';
    }
    public function attributes()
    {
        return [
            '_id',
            'order_id',
            'driver_id',
            'from_location',
            'to_location',
            'type',
            'status',
            'created_at',
            'updated_at',
        ];
    }
    public function rules()
    {
        return [
            [['order_id','driver_id','from_location','to_location','type'], 'required'],
            ['status', 'default', 'value' => 'Pending'],
            [['status'], 'in', 'range' => ['Pending','Accepted','Rejected','AutoRejected']],
            [['type'], 'in', 'range' => ['PICK_UP','DROP_UP']],
            [['driver_id'], 'exist', 'targetClass' => User::class, 'targetAttribute' => ['driver_id' => '_id']],
            [['order_id'], 'exist', 'targetClass' => Order::class, 'targetAttribute' => ['order_id' => '_id']],
        ];
    }

  

    public function behaviors()
    {
        return [
            UTCDateTimeBehavior::class,
        ];
    }
}
