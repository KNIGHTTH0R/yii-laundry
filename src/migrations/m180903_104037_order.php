<?php

class m180903_104037_order extends \yii\mongodb\Migration
{
    public function up()
    {
        $col = Yii::$app->mongodb->getCollection('order_db');
        $col->init();
    }

    public function down()
    {
        echo "m180903_104037_order cannot be reverted.\n";

        return false;
    }
}
