<?php

return [
    'app_name' => '雏鹰计划学习系统',
    'db' => [
        'host' => '127.0.0.1',
        'port' => '3306',
        'database' => 'eagle_plan',
        'username' => 'root',
        'password' => 'root',
        'charset' => 'utf8mb4',
    ],
    'content_path' => dirname(__DIR__) . DIRECTORY_SEPARATOR . 'learning-content',
    'upload_path' => dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'uploads',
    'trial_days' => 3,
];
