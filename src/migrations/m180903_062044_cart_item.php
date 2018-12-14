<?php

class m180903_062044_cart_item extends \yii\mongodb\Migration
{
    public function up()
    {
        $col = Yii::$app->mongodb->getCollection('cart_item_db');
        $col->init();
    }

    public function down()
    {
        echo "m180903_062044_cart_item cannot be reverted.\n";

        return false;
    }
}
