<?php

class m180908_064555_bags extends \yii\mongodb\Migration
{
    public function up()
    {
        $col = Yii::$app->mongodb->getCollection('bags');
    }

    public function down()
    {
        echo "m180908_064555_bags cannot be reverted.\n";

        return false;
    }
}
