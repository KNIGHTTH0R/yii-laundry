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
use Yii;


/**
 * This is the model class for Category.
 *
 * @property \MongoDB\BSON\ObjectID $_id
 * @property string $user_id
 * @property string $rate
 * @property string $title
 * @property string $comment
 * @property string $driverid
 * @property string $status
 * @property string $created_by
 * @property string $updated_by
 * @property \MongoDB\BSON\UTCDateTime $created_at
 * @property \MongoDB\BSON\UTCDateTime $updated_at
 */

class Review extends \yii\mongodb\ActiveRecord
{
    

    public $rate_by;
    public static function collectionName()
    {
        return 'review_db';
    }
    public function attributes()
    {
        return [
            '_id',
            'rate_from_id',            
            'rate_to_id',
            'type',
            'order_id',
            'rate',
            'title',
            'comment',
            'created_by',
            'updated_by',
            'created_at',
            'updated_at',
        ];
    }
    public function fields()
    {
        $fields                 = parent::fields();
        $fields['rate_by']      = function ($model) {
            $User = User::find()->select(['profile_picture','name'])->where(['_id'=>$model->rate_from_id])->one();
            return $User;
        };
        return $fields;
    }
    public function rules()
    {
        return [
            [['rate','title','order_id','type'], 'required'],
            [['rate'], 'number'],
            [['title'], 'string', 'max' => 50],
            [['comment'], 'string', 'max' => 255],
            ['type', 'in', 'range' => ['owner','driver']],
            ['type', 'default', 'value' => "driver"],
            [['created_by'], 'exist', 'targetClass' => User::class, 'targetAttribute' => ['created_by' => '_id']],
            [['rate_from_id'], 'exist', 'targetClass' => User::class, 'targetAttribute' => ['rate_from_id' => '_id']],
            [['rate_to_id'], 'exist', 'targetClass' => User::class, 'targetAttribute' => ['rate_to_id' => '_id']],
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
