<?php

return [
    'demo_login_enabled' => env('DEMO_LOGIN_ENABLED', false),
    'reserved_usernames' => ['admin', 'api', 'auth', 'commands', 'cv', 'dashboard', 'demo', 'health', 'login', 'logout', 'onboarding', 'portfolios', 'projects', 'register', 'settings', 'storage', 'up', 'user'],
    'presets' => [
        'minimal' => ['label' => 'Minimal', 'description' => 'Quiet, clear and understated.'],
        'material' => ['label' => 'Material', 'description' => 'Soft cards and rounded surfaces.'],
        'editorial' => ['label' => 'Editorial', 'description' => 'Expressive type and open space.'],
    ],
    'accent_swatches' => ['Orange' => '#ea580c', 'Blue' => '#2563eb', 'Teal' => '#0f766e', 'Purple' => '#7c3aed', 'Rose' => '#be185d', 'Slate' => '#334155'],
    'cv_disk' => env('CV_FILESYSTEM_DISK', 'local'),
    'image_disk' => env('IMAGE_FILESYSTEM_DISK', 'images_local'),
];
