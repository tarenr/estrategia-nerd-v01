<?php

declare(strict_types=1);

if (in_array('--payload', $argv, true)) {
    require dirname(__DIR__) . '/storage/review-navigation-20261011/deploy/app/Repositories/PostRepository.php';
    require dirname(__DIR__) . '/storage/review-navigation-20261011/deploy/app/Services/Site/PostService.php';
}
require dirname(__DIR__) . '/bootstrap.php';

use App\Repositories\ComentarioRepository;
use App\Repositories\EstatisticaRepository;
use App\Repositories\PostRepository;
use App\Services\Site\PostService;
use App\Support\TargetEnvironmentDatabase;

function navigationService(PDO $pdo): PostService
{
    return new PostService(new PostRepository($pdo), new ComentarioRepository($pdo), new EstatisticaRepository($pdo));
}
function navigationAssert(bool $condition, string $label): void
{
    if (!$condition) { throw new RuntimeException($label); }
    echo 'PASS: ' . $label . PHP_EOL;
}

// Local fixtures are changed only inside a transaction and always rolled back.
$pdo = $GLOBALS['pdo'];
$pdo->beginTransaction();
try {
    $source = $pdo->query("SELECT * FROM posts WHERE slug='amd-ryzen-5-7600x-desempenho-real-no-meu-setup' FOR UPDATE")->fetch(PDO::FETCH_ASSOC);
    $target = $pdo->query("SELECT * FROM posts WHERE slug='am4-ou-am5-em-2026' FOR UPDATE")->fetch(PDO::FETCH_ASSOC);
    if (!is_array($source) || !is_array($target)) { throw new RuntimeException('Artigos locais de teste indisponíveis.'); }
    $id = (int) $source['id'];
    $targetId = (int) $target['id'];
    $repo = new PostRepository($pdo);
    $service = navigationService($pdo);
    $chronological = $repo->nextPublicPost($source['data_publicacao'], $id);
    $chronologicalUrl = is_array($chronological) ? url('/post/' . $chronological['slug']) : null;
    $previous = $repo->previousPublicPost($source['data_publicacao'], $id);
    $previousUrl = is_array($previous) ? url('/post/' . $previous['slug']) : null;
    $set = $pdo->prepare('UPDATE posts SET tipo_post=?, proximo_post_id=? WHERE id=?');
    $set->execute(['review', $targetId, $id]);
    $view = $service->getViewModel($source['slug']);
    navigationAssert(($view['post_next']['url'] ?? null) === url('/post/' . $target['slug']), 'review usa destino publicado configurado');
    navigationAssert(($view['post_next']['direction'] ?? null) === 'proximo', 'direção do botão preservada');
    navigationAssert(($view['post_previous']['url'] ?? null) === $previousUrl, 'post anterior permanece cronológico');
    navigationAssert($repo->findPublishedById(999999999) === null, 'destino inexistente não é resolvido');
    foreach ([null, $id] as $candidate) {
        $set->execute(['review', $candidate, $id]);
        $view = $service->getViewModel($source['slug']);
        navigationAssert(($view['post_next']['url'] ?? null) === $chronologicalUrl, 'fallback para destino vazio, próprio ou inexistente: ' . (string) $candidate);
    }
    $set->execute(['review', $targetId, $id]);
    $pdo->prepare("UPDATE posts SET status='rascunho' WHERE id=?")->execute([$targetId]);
    $view = $service->getViewModel($source['slug']);
    navigationAssert(($view['post_next']['url'] ?? null) === $chronologicalUrl, 'destino não publicado não substitui navegação');
    $pdo->prepare('UPDATE posts SET status=? WHERE id=?')->execute([$target['status'], $targetId]);
    foreach (['guia', null] as $type) {
        $set->execute([$type, $targetId, $id]);
        $view = $service->getViewModel($source['slug']);
        navigationAssert(($view['post_next']['url'] ?? null) === $chronologicalUrl, 'outros tipos preservam navegação: ' . (string) $type);
    }
} finally {
    $pdo->rollBack();
}

// Real production views are inspected with counter writes rolled back as well.
$production = TargetEnvironmentDatabase::pdo('production');
$production->beginTransaction();
try {
    $repo = new PostRepository($production);
    $service = navigationService($production);
    foreach ([19 => 20, 43 => 32] as $id => $nextId) {
        $source = $repo->findAdminById($id);
        $target = $repo->findAdminById($nextId);
        if ($source === null || $target === null) { throw new RuntimeException('Artigos de produção indisponíveis.'); }
        $view = $service->getViewModel($source['slug']);
        navigationAssert(($view['post_next']['url'] ?? null) === url('/post/' . $target['slug']), 'produção #' . $id . ' aponta para #' . $nextId);
        navigationAssert(($view['post_next_step']['url'] ?? null) === ($view['post_next']['url'] ?? null), 'botão e próximo passo concordam #' . $id);
    }
} finally {
    $production->rollBack();
}
echo "PASS: transações de teste revertidas; nenhum vínculo alterado.\n";
