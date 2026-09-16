<?php
/**
 * -----------------------------------------------------------------------------
 * en-blog-publish-prod.php
 * Publica um post ja existente no banco LOCAL como um novo post no banco de
 * PRODUCAO (fase 2 do IMP-021). Mapeia a categoria por slug (nao por ID -
 * os IDs podem divergir entre local e producao), envia todos os arquivos da
 * pasta de midia do post via FTP, e insere o registro em producao sempre
 * como "rascunho" (a menos que --status=publicado seja passado
 * explicitamente).
 *
 * Uso:
 *   php scripts/en-blog-publish-prod.php <post_id_local> [--status=rascunho|publicado|agendado]
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

$sessionPath = dirname(__DIR__) . '/storage/sessions';
if (!is_dir($sessionPath)) {
    mkdir($sessionPath, 0777, true);
}
ini_set('session.save_path', $sessionPath);

require dirname(__DIR__) . '/bootstrap.php';

use App\Repositories\PostRepository;
use App\Repositories\CategoriaPostRepository;
use App\Services\Admin\MidiaService;
use App\Support\EnvironmentGuard;
use App\Support\TargetEnvironmentDatabase;
use App\Support\TargetEnvironmentUploads;

EnvironmentGuard::requireLocal();

/**
 * @return never
 */
function en_blog_publish_fail(string $message): void
{
    fwrite(STDERR, 'ERRO: ' . $message . PHP_EOL);
    echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit(1);
}

$postId = (int) ($argv[1] ?? 0);
if ($postId <= 0) {
    en_blog_publish_fail('informe o id do post local como argumento 1.');
}

$forcedStatus = 'rascunho';
foreach ($argv as $arg) {
    if (is_string($arg) && str_starts_with($arg, '--status=')) {
        $candidate = substr($arg, strlen('--status='));
        if (in_array($candidate, ['rascunho', 'publicado', 'agendado'], true)) {
            $forcedStatus = $candidate;
        }
    }
}

/** @var PDO $localPdo */
$localPdo = $GLOBALS['pdo'];
$localPosts = new PostRepository($localPdo);
$localCategorias = new CategoriaPostRepository($localPdo);

$post = $localPosts->findAdminById($postId);
if ($post === null) {
    en_blog_publish_fail("post {$postId} nao encontrado no banco local.");
}

$localCategoriaId = (int) ($post['categoria_post_id'] ?? 0);
$localCategoria = null;
foreach ($localCategorias->listForSelect() as $categoria) {
    if ((int) ($categoria['id'] ?? 0) === $localCategoriaId) {
        $localCategoria = $categoria;
        break;
    }
}
if ($localCategoria === null) {
    en_blog_publish_fail("nao foi possivel resolver a categoria local (id {$localCategoriaId}) do post {$postId}.");
}
$categoriaSlug = trim((string) ($localCategoria['slug'] ?? ''));
if ($categoriaSlug === '') {
    en_blog_publish_fail('categoria local sem slug valido.');
}

$targetEnvironment = 'production';

try {
    $prodPdo = TargetEnvironmentDatabase::pdo($targetEnvironment);
} catch (Throwable $exception) {
    en_blog_publish_fail('falha ao conectar no banco de producao: ' . $exception->getMessage());
}

$prodCategorias = new CategoriaPostRepository($prodPdo);
$prodCategoriaId = null;
foreach ($prodCategorias->listForSelect() as $categoria) {
    if (trim((string) ($categoria['slug'] ?? '')) === $categoriaSlug) {
        $prodCategoriaId = (int) ($categoria['id'] ?? 0);
        break;
    }
}
if ($prodCategoriaId === null || $prodCategoriaId <= 0) {
    en_blog_publish_fail("categoria '{$categoriaSlug}' nao existe em producao. Crie-a la antes de publicar (ou rode via /admin/categorias com o alvo em producao).");
}

$prodPosts = new PostRepository($prodPdo);

$slug = trim((string) ($post['slug'] ?? ''));
if ($slug === '') {
    en_blog_publish_fail('post local sem slug valido.');
}
$slugFinal = $prodPosts->nextAvailableSlug($slug);

// Mapeia o "proximo passo" (post recomendado) do local pro ID equivalente em
// producao, pelo slug - os IDs numericos divergem entre os dois bancos.
$prodNextStepId = 0;
$localNextStepId = max(0, (int) ($post['proximo_post_id'] ?? 0));
if ($localNextStepId > 0) {
    $localNextStepPost = $localPosts->findAdminById($localNextStepId);
    $localNextStepSlug = trim((string) ($localNextStepPost['slug'] ?? ''));
    if ($localNextStepSlug !== '') {
        $prodNextStepPost = $prodPosts->findAnyBySlug($localNextStepSlug);
        if ($prodNextStepPost !== null) {
            $prodNextStepId = (int) ($prodNextStepPost['id'] ?? 0);
        }
    }
}

// Envia todos os arquivos da pasta de midia local do post pro FTP de producao.
$midia = new MidiaService($localPdo);
$mediaDirRelative = 'uploads/posts/' . $slug . '/images';
$mediaDirAbsolute = $midia->absolutePathForRelative($mediaDirRelative);
$sentFiles = [];
if (is_dir($mediaDirAbsolute)) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($mediaDirAbsolute, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if (!$file instanceof SplFileInfo || !$file->isFile()) {
            continue;
        }
        $relative = $mediaDirRelative . '/' . str_replace('\\', '/', substr($file->getPathname(), strlen($mediaDirAbsolute) + 1));
        try {
            TargetEnvironmentUploads::putFile($targetEnvironment, $file->getPathname(), $relative);
            $sentFiles[] = $relative;
        } catch (Throwable $exception) {
            en_blog_publish_fail('falha ao enviar midia via FTP (' . $relative . '): ' . $exception->getMessage());
        }
    }
}

$payload = [
    'titulo' => (string) ($post['titulo'] ?? ''),
    'slug' => $slugFinal,
    'resumo' => (string) ($post['resumo'] ?? ''),
    'conteudo' => (string) ($post['conteudo'] ?? ''),
    'categoria' => $categoriaSlug,
    'categoria_post_id' => $prodCategoriaId,
    'imagem_capa' => (string) ($post['imagem_capa'] ?? ''),
    'imagem_thumb' => (string) ($post['imagem_thumb'] ?? ''),
    'autor_id' => (int) ($post['autor_id'] ?? 1) ?: 1,
    'data_publicacao' => (string) ($post['data_publicacao'] ?? date('Y-m-d H:i:s')),
    'tempo_leitura' => max(1, (int) ($post['tempo_leitura'] ?? 5)),
    'seo_title' => (string) ($post['seo_title'] ?? ''),
    'seo_description' => (string) ($post['seo_description'] ?? ''),
    'seo_keywords' => (string) ($post['seo_keywords'] ?? ''),
    'tags' => (string) ($post['tags'] ?? ''),
    'status' => $forcedStatus,
    'destaque' => ((int) ($post['destaque'] ?? 0)) ? 1 : 0,
    'proximo_post_id' => $prodNextStepId,
    'tipo_post' => trim((string) ($post['tipo_post'] ?? '')),
];

$prodPostId = $prodPosts->insertAdmin($payload);

echo json_encode([
    'ok' => true,
    'local_id' => $postId,
    'production_id' => $prodPostId,
    'slug' => $slugFinal,
    'status' => $forcedStatus,
    'destaque' => $payload['destaque'],
    'tipo_post' => $payload['tipo_post'],
    'proximo_post_id' => $prodNextStepId,
    'media_sent' => $sentFiles,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
