<?php

declare(strict_types=1);

return [
    'public_media_url' => (string) ($_ENV['INSTAGRAM_PUBLIC_MEDIA_URL'] ?? (function_exists('env') ? env('INSTAGRAM_PUBLIC_MEDIA_URL', 'https://nerd.tfr-info.com.br') : 'https://nerd.tfr-info.com.br')),
];
