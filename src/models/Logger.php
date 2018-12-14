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

class Logger extends \yii\mongodb\ActiveRecord
{
    
    public static function collectionName()
    {
        return 'logger';
    }
    public function attributes()
    {
        return [
            '_id',
            'request_url',
            'request_body',
            'user_id',
            'access_token',
            'order_id',
            'response',
            'type',
            'order_number',
            'created_at',
        ];
    }
}
