<?php

class m180903_103848_review extends \yii\mongodb\Migration
{
    public function up()
    {
        $col = Yii::$app->mongodb->getCollection('review_db');
    }

    public function down()
    {
        echo "m180903_103848_review cannot be reverted.\n";

        return false;
    }
}
