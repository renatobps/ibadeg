<?php

return [
    'seed_demo' => (bool) env('IBADEG_SEED_DEMO', false),

    'admin' => [
        'email' => env('IBADEG_ADMIN_EMAIL'),
        'name' => env('IBADEG_ADMIN_NAME', 'Coordenação IBADEG'),
        'password' => env('IBADEG_ADMIN_PASSWORD'),
    ],
];
