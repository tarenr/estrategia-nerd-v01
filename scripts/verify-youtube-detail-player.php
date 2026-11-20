<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/Support/Helpers.php';

/** @param array<string,mixed> $data */
function renderYouTubeDetail(array $data): string
{
    $video = $data;
    ob_start();
    require __DIR__ . '/../app/Views/admin/youtube/show.php';
    return (string) ob_get_clean();
}

function assertContains(string $needle, string $html, string $message): void
{
    if (!str_contains($html, $needle)) {
        fwrite(STDERR, "FALHA: {$message}\n");
        exit(1);
    }
}

$base = [
    'id' => 223,
    'title' => 'Teste',
    'content_kind' => 'video',
    'duration_seconds' => 40,
    'views_count' => 0,
    'likes_count' => 0,
    'comments_count' => 0,
    'status' => 'agendado_remoto',
    'publish_phase' => 'confirmed',
    'privacy_status' => 'private',
];

$local = renderYouTubeDetail(array_replace($base, [
    'video_path' => 'uploads/reels/quadros-yugioh-setup/reel.mp4',
    'thumbnail_path' => 'uploads/reels/quadros-yugioh-setup/capa.jpg',
]));
assertContains('<video', $local, 'o arquivo local deve ser exibido em um player HTML5');
assertContains('controls', $local, 'o player local deve oferecer controles');
assertContains('preload="metadata"', $local, 'o player não deve baixar o vídeo inteiro ao abrir a página');
assertContains('uploads/reels/quadros-yugioh-setup/reel.mp4', $local, 'o player deve usar o caminho local registrado');

$remote = renderYouTubeDetail(array_replace($base, [
    'status' => 'publicado',
    'privacy_status' => 'public',
    'video_path' => null,
    'youtube_video_id' => 'gMJGHSTjLWc',
]));
assertContains('<iframe', $remote, 'um vídeo público sem arquivo local deve usar o player do YouTube');
assertContains('youtube-nocookie.com/embed/gMJGHSTjLWc', $remote, 'o iframe deve usar o ID remoto');

$unavailable = renderYouTubeDetail(array_replace($base, [
    'video_path' => null,
    'youtube_video_id' => 'gMJGHSTjLWc',
]));
assertContains('Reprodução indisponível', $unavailable, 'vídeo privado sem arquivo local deve explicar a indisponibilidade');

echo "OK: player local, fallback público e estado indisponível verificados.\n";
