<?php

class m180903_060919_cart extends \yii\mongodb\Migration
{
    public function up()
    {
        $col = Yii::$app->mongodb->getCollection('cart_db');
    }

    public function down()
    {
        echo "m180903_060919_cart cannot be reverted.\n";

        return false;
    }
}
