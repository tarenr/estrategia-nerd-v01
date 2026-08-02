<?php

declare(strict_types=1);

return [
    'base_url' => rtrim((string) ($_ENV['HOSTINGER_API_BASE_URL'] ?? 'https://developers.hostinger.com'), '/'),
    'token' => (string) ($_ENV['HOSTINGER_API_TOKEN'] ?? ''),
    'domain' => 'estrategianerd.com.br',
    'timeout' => (int) ($_ENV['HOSTINGER_API_TIMEOUT'] ?? 12),
];
