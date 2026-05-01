<?php

declare(strict_types=1);

$appUrl = rtrim((string) ($_ENV['APP_URL'] ?? ''), '/');

return [
    'result_root' => $_ENV['SMOKE_TEST_ROOT'] ?? (dirname(__DIR__) . '/storage/smoke-tests'),
    'timeout_seconds' => max(3, (int) ($_ENV['SMOKE_TEST_TIMEOUT'] ?? 15)),
    'environments' => [
        'local' => [
            'label' => 'Local',
            'base_url' => (string) ($_ENV['SMOKE_LOCAL_URL'] ?? ($appUrl !== '' ? $appUrl : 'http://localhost/estrategia-nerd/public')),
        ],
        'stage' => [
            'label' => 'Stage',
            'base_url' => (string) ($_ENV['SMOKE_STAGE_URL'] ?? 'https://estrategianerd.com.br/stage'),
        ],
        'production' => [
            'label' => 'Producao',
            'base_url' => (string) ($_ENV['SMOKE_PRODUCTION_URL'] ?? 'https://estrategianerd.com.br'),
        ],
    ],
    'admin' => [
        'user' => (string) ($_ENV['SMOKE_ADMIN_USER'] ?? ($_ENV['SMOKE_ADMIN_EMAIL'] ?? '')),
        'password' => (string) ($_ENV['SMOKE_ADMIN_PASSWORD'] ?? ''),
    ],
];
