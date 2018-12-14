<?php
/**
 * @author Harry Tang <harry@powerkernel.com>
 * @link https://powerkernel.com
 * @copyright Copyright (c) 2018 Power Kernel
 */

namespace powerkernel\yiilaundry\models;
use yii\base\Model;
use powerkernel\yiicommon\behaviors\UTCDateTimeBehavior;
use powerkernel\yiiuser\models\User;
use powerkernel\yiiproduct\models\Product;
use Yii;


/**
 * This is the model class for Category.
 *
 * @property \MongoDB\BSON\ObjectID $_id
 * @property string $user_id
 * @property string $status
 * @property string $created_by
 * @property string $updated_by
 * @property \MongoDB\BSON\UTCDateTime $created_at
 * @property \MongoDB\BSON\UTCDateTime $updated_at
 */


/**
 * Class UserUpdateEmailForm
 * @package powerkernel\yiiuser\forms
 */
class CartItem extends Model
{

    public $product_id;
    public $product_qty;
 
    public function attributes()
    {
        return [
            'product_id',
            'product_qty',
        ];
    }
    public function rules()
    {
        return [
            [['product_id','product_qty'], 'required'],
            ['product_id', 'exist', 'targetClass' => Product::class, 'targetAttribute' => ['product_id' => '_id']],
            [['product_qty'],'integer']
        ];
    }
}
