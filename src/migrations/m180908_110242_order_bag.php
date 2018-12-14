<?php

class m180908_110242_order_bag extends \yii\mongodb\Migration
{
    public function up()
    {
        $col = Yii::$app->mongodb->getCollection('order_bag');
    }

    public function down()
    {
        echo "m180908_110242_order_bag cannot be reverted.\n";

        return false;
    }
}
