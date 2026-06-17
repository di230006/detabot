<?php

return [
    'db_driver' => 'mysql',
    'mysql' => [
        'host' => '127.0.0.1',
        'database' => 'detabot',
        'username' => 'root',
        'password' => '',
    ],
    'mail' => [
        // Use "log" for localhost testing or "smtp" to send through Gmail.
        'driver' => 'log',
        'from_email' => 'yourclinic@gmail.com',
        'from_name' => 'Detabot',
        'smtp' => [
            'host' => 'smtp.gmail.com',
            'port' => 465,
            'encryption' => 'ssl',
            'username' => 'yourclinic@gmail.com',
            'password' => 'your-google-app-password',
        ],
    ],
];
