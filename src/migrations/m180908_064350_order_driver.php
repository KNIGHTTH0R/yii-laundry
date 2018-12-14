<?php

class m180908_064350_order_driver extends \yii\mongodb\Migration
{
    public function up()
    {
        $col = Yii::$app->mongodb->getCollection('order_driver');
    }

    public function down()
    {
        echo "m180908_064350_order_driver cannot be reverted.\n";

        return false;
    }
}
