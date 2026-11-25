<?php

return [
    'demo_login_enabled' => env('DEMO_LOGIN_ENABLED', false),
    'reserved_usernames' => ['admin', 'api', 'auth', 'commands', 'cv', 'dashboard', 'demo', 'health', 'login', 'logout', 'onboarding', 'portfolios', 'projects', 'register', 'settings', 'storage', 'up', 'user'],
    'demo_repository_roles' => [
        'https://github.com/camomilesson/website-to-conjugate-verbs' => 'Solo developer',
        'https://github.com/camomilesson/hic-sunt-dracones-map' => 'Solo developer',
    ],
    'cv_disk' => env('CV_FILESYSTEM_DISK', 'local'),
    'image_disk' => env('IMAGE_FILESYSTEM_DISK', 'images_local'),
];
