<?php
/**
 * @author Harry Tang <harry@powerkernel.com>
 * @link https://powerkernel.com
 * @copyright Copyright (c) 2018 Power Kernel
 */

return [
    'rules' => [
        [
            '__class' => \yii\rest\UrlRule::class,
            'pluralize' => false,
            'controller' => [
                'v1/faq/view' => 'v1/faq/view',
               // 'v1/faq/admin'=>"v1/faq/admin"
            ],
            'tokens' => [
                '{id}' => '<id:\\w[\\w,]*>',
            ],
        ],
    ]
];