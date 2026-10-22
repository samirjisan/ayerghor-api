<?php

return [
    'super_admin' => [
        'mobile' => env('SUPER_ADMIN_MOBILE'),
        'password' => env('SUPER_ADMIN_PASSWORD'),
        'name' => env('SUPER_ADMIN_NAME', 'Super Admin'),
        'email' => env('SUPER_ADMIN_EMAIL'),
    ],
];
