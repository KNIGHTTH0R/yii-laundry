<?php
/**
 * @author Harry Tang <harry@powerkernel.com>
 * @link https://powerkernel.com
 * @copyright Copyright (c) 2018 Power Kernel
 */


namespace powerkernel\yiifaq\controllers;


use powerkernel\yiifaq\models\FAQ;

/**
 * Class ViewController
 */
class ViewController extends \powerkernel\yiicommon\controllers\RestController
{

    /**
     * view group of  FAQs
     * @param string $group
     * @param string $lang
     * @return array
     */
    public function actionGroup($group, $lang)
    {
        $models = FAQ::find()
            ->select(['q', 'a'])
            ->where(
                [
                    'group' => $group,
                    'language' => $lang,
                    'status' => FAQ::STATUS_ACTIVE,
                ]
            )->all();

        if ($models) {
            return [
                'success' => true,
                'data' => $models
            ];
        }
        return [
            'success' => false,
            'data' => null
        ];
    }
}
