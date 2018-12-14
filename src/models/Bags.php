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

class Bags extends \yii\mongodb\ActiveRecord
{
    
    public static function collectionName()
    {
        return 'bags_db';
    }
    public function attributes()
    {
        return [
            '_id',
            'bag_name',
            'bag_qr_code',
            'status',
            'lot',
            'bag_qr_code_image_url',
            'created_at',
        ];
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['bag_name', 'bag_qr_code','lot'], 'required'],    
            [['bag_qr_code'],'unique'],
        ];
    }
    
}
