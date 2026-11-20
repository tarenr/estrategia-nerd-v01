<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Services/YouTube/YouTubeVideoRules.php';
require_once dirname(__DIR__) . '/app/Services/YouTube/YouTubePublishState.php';
require_once dirname(__DIR__) . '/app/Services/YouTube/YouTubeSyncRules.php';

use App\Services\YouTube\YouTubePublishState;
use App\Services\YouTube\YouTubeVideoRules;
use App\Services\YouTube\YouTubeSyncRules;

$failures = [];
$checks = 0;

$check = static function (bool $condition, string $message) use (&$failures, &$checks): void {
    $checks++;
    if (!$condition) {
        $failures[] = $message;
    }
};

$valid = YouTubeVideoRules::validate([
    'title' => 'Echo explica a vida de TI',
    'description' => 'Um Short do Estratégia Nerd.',
    'privacy_status' => 'private',
    'made_for_kids' => false,
    'contains_synthetic_media' => true,
    'scheduled_at' => '2026-11-01 19:30:00',
]);
$check($valid === [], 'Metadados válidos não devem produzir erros.');

$invalid = YouTubeVideoRules::validate([
    'title' => str_repeat('a', 101),
    'description' => str_repeat('b', 5001),
    'privacy_status' => 'scheduled',
    'scheduled_at' => 'data-invalida',
]);
$check(isset($invalid['title']), 'Título acima de 100 caracteres deve ser rejeitado.');
$check(isset($invalid['description']), 'Descrição acima de 5000 caracteres deve ser rejeitada.');
$check(isset($invalid['privacy_status']), 'Privacidade desconhecida deve ser rejeitada.');
$check(isset($invalid['scheduled_at']), 'Data de agendamento inválida deve ser rejeitada.');

$check(
    YouTubeVideoRules::remoteUploadDueAt('2026-11-02 19:30:00', 24) === '2026-11-01 19:30:00',
    'Upload remoto deve ser antecipado pelo número de horas configurado.'
);

$check(YouTubeSyncRules::durationSeconds('PT1M30S') === 90, 'Duração ISO 8601 deve ser convertida em segundos.');
$check(YouTubeSyncRules::durationSeconds('PT1H2M3S') === 3723, 'Duração com horas deve ser convertida corretamente.');
$check(YouTubeSyncRules::contentKind(179) === 'short', 'Conteúdo de até 3 minutos deve ser classificado como Short.');
$check(YouTubeSyncRules::contentKind(181) === 'video', 'Conteúdo acima de 3 minutos deve ser classificado como vídeo.');
$check(YouTubeSyncRules::period('14d', null, null)['days'] === 14, 'Período de 14 dias deve ser aceito.');
$check(YouTubeSyncRules::period('invalid', null, null)['key'] === '7d', 'Período inválido deve voltar para 7 dias.');
$custom = YouTubeSyncRules::period('custom', '2026-10-01', '2026-10-20');
$check($custom['start'] === '2026-10-01' && $custom['end'] === '2026-10-20', 'Período personalizado válido deve ser preservado.');

$remote = YouTubeSyncRules::normalizeVideo([
    'id' => 'abc123',
    'snippet' => [
        'title' => 'Short de teste',
        'description' => 'Descrição',
        'publishedAt' => '2026-10-20T18:30:00Z',
        'thumbnails' => ['high' => ['url' => 'https://i.ytimg.com/test.jpg']],
    ],
    'status' => ['privacyStatus' => 'public', 'madeForKids' => false],
    'contentDetails' => ['duration' => 'PT59S'],
    'statistics' => ['viewCount' => '120', 'likeCount' => '9', 'commentCount' => '2'],
]);
$check($remote['youtube_video_id'] === 'abc123' && $remote['content_kind'] === 'short', 'Vídeo remoto deve ser normalizado com ID e tipo.');
$check($remote['views_count'] === 120 && $remote['likes_count'] === 9, 'Métricas remotas devem ser normalizadas como inteiros.');
$check($remote['idempotency_key'] === 'youtube:abc123', 'Importação deve usar chave idempotente estável.');

$check(YouTubePublishState::canClaim('agendado', 'idle'), 'Agendado em idle deve poder ser reivindicado.');
$check(!YouTubePublishState::canClaim('enviando', 'uploading'), 'Upload em andamento não pode ser reivindicado novamente.');
$check(!YouTubePublishState::canRetryAutomatically('resultado_incerto'), 'Resultado incerto nunca deve ser reenviado automaticamente.');
$check(YouTubePublishState::canRetryAutomatically('erro'), 'Falha comprovada deve permitir nova tentativa controlada.');
$check(
    YouTubePublishState::statusAfterProcessing('succeeded', 'private', '2026-11-02T22:30:00Z') === 'agendado_remoto',
    'Vídeo processado com publishAt futuro deve ficar agendado remotamente.'
);
$check(
    YouTubePublishState::statusAfterProcessing('failed', 'private', null) === 'erro',
    'Falha de processamento deve virar erro.'
);

$root = dirname(__DIR__);
$routes = (string) file_get_contents($root . '/config/routes.php');
$oauthSource = (string) file_get_contents($root . '/app/Services/YouTube/YouTubeOAuthService.php');
$publisherSource = (string) file_get_contents($root . '/scripts/en-youtube-publish-scheduled.php');
$migration = (string) file_get_contents($root . '/database/migrations/0008_create_youtube_tables.sql');
$syncMigration = (string) file_get_contents($root . '/database/migrations/0009_expand_youtube_dashboard.sql');
$check(str_contains($routes, "'/admin/youtube'"), 'A rota principal do YouTube deve existir.');
$check(str_contains($routes, "'/admin/youtube/callback'"), 'O callback OAuth deve existir.');
$check(str_contains($oauthSource, 'youtube.upload') && str_contains($oauthSource, 'youtube.readonly'), 'OAuth deve solicitar os dois escopos mínimos definidos.');
$check(!str_contains($oauthSource, 'youtube.force-ssl'), 'OAuth não deve solicitar o escopo amplo force-ssl nesta versão.');
$check(str_contains($publisherSource, 'LOCK_EX | LOCK_NB'), 'Publicador deve impedir execução concorrente.');
$check(str_contains($publisherSource, '--dry-run'), 'Publicador deve oferecer dry-run sem efeito remoto.');
$check(str_contains($migration, "'resultado_incerto'"), 'Schema deve representar resultado remoto incerto.');
$check(str_contains($syncMigration, 'content_kind'), 'Schema expandido deve distinguir vídeos e Shorts.');
$check(str_contains($oauthSource, 'yt-analytics.readonly'), 'OAuth deve solicitar o escopo somente leitura do YouTube Analytics.');

if ($failures !== []) {
    fwrite(STDERR, "FALHAS ({$checks} verificações):\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "YouTube: {$checks} verificações aprovadas.\n";
