<?php
/**
 * -----------------------------------------------------------------------------
 * en-blog-draft.php
 * Cria um post como RASCUNHO no banco LOCAL a partir de um JSON pronto (gerado
 * pela skill /post-blog). Usa PostRepository/CategoriaPostRepository (camada de
 * dados existente) — nao inventa SQL novo, nao mexe em producao.
 *
 * Uso:
 *   php scripts/en-blog-draft.php caminho/para/post.json
 *
 * Formato do JSON (ver .claude/skills/post-blog/SKILL.md):
 *   {
 *     "titulo": "...",
 *     "slug": "opcional-slug-sugerido",
 *     "resumo": "...",
 *     "conteudo": "<h2>...</h2>...",
 *     "categoria_slug": "hardware",
 *     "seo_title": "...", "seo_description": "...", "seo_keywords": "...",
 *     "tags": "...",
 *     "status": "rascunho",
 *     "destaque": 0,
 *     "proximo_post_id": 0,
 *     "tipo_post": "comparativo|review|ficha_tecnica|guia|noticia|lista",
 *     "data_publicacao": "2026-09-11 10:00:00",
 *     "autor_id": 1,
 *     "tempo_leitura": 6,
 *     "cover_image_path": "E:/AI/en-image/saidas/capa.png",
 *     "body_images": [{"path": "E:/AI/en-image/saidas/img1.png"}, ...]
 *   }
 *
 * As imagens do corpo sao referenciadas no HTML do "conteudo" pelos placeholders
 * __BODY_IMAGE_1__, __BODY_IMAGE_2__... (na ordem de "body_images"); o script
 * troca cada placeholder pelo caminho relativo final depois de copiar o arquivo.
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { exit(1); }


$sessionPath = dirname(__DIR__) . '/storage/sessions';
if (!is_dir($sessionPath)) {
    mkdir($sessionPath, 0777, true);
}
ini_set('session.save_path', $sessionPath);

require dirname(__DIR__) . '/bootstrap.php';

use App\Repositories\PostRepository;

/**
 * @return never
 */
function en_blog_draft_fail(string $message): void
{
    fwrite(STDERR, 'ERRO: ' . $message . PHP_EOL);
    echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit(1);
}

function en_blog_draft_slugify(string $value): string
{
    $value = trim(mb_strtolower($value));
    if ($value === '') {
        return '';
    }
    if (function_exists('iconv')) {
        $normalized = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if (is_string($normalized) && $normalized !== '') {
            $value = $normalized;
        }
    }
    $value = preg_replace('/[^a-z0-9]+/i', '-', $value) ?? '';
    return trim(mb_substr($value, 0, 190), '-');
}

$jsonPath = (string) ($argv[1] ?? '');
if ($jsonPath === '' || !is_file($jsonPath)) {
    en_blog_draft_fail('informe o caminho de um arquivo JSON valido como argumento 1.');
}

$raw = (string) file_get_contents($jsonPath);
$data = json_decode($raw, true);
if (!is_array($data)) {
    en_blog_draft_fail('JSON invalido em ' . $jsonPath);
}

$titulo = trim((string) ($data['titulo'] ?? ''));
$conteudo = trim((string) ($data['conteudo'] ?? ''));
$categoriaSlug = trim((string) ($data['categoria_slug'] ?? ''));
$status = trim((string) ($data['status'] ?? 'rascunho'));

if ($titulo === '') {
    en_blog_draft_fail('titulo obrigatorio.');
}
if ($conteudo === '') {
    en_blog_draft_fail('conteudo obrigatorio.');
}
if (!in_array($status, ['rascunho', 'publicado', 'agendado'], true)) {
    en_blog_draft_fail("status invalido: '{$status}' (use rascunho, publicado ou agendado).");
}
if ($categoriaSlug === '') {
    en_blog_draft_fail('categoria_slug obrigatorio.');
}

/** @var PDO $pdo */
$pdo = $GLOBALS['pdo'];
$posts = new PostRepository($pdo);

$catStmt = $pdo->prepare('SELECT id, slug FROM categoria_post WHERE slug = :slug LIMIT 1');
$catStmt->bindValue(':slug', $categoriaSlug, PDO::PARAM_STR);
$catStmt->execute();
$categoria = $catStmt->fetch(PDO::FETCH_ASSOC);
if ($categoria === false) {
    en_blog_draft_fail("categoria '{$categoriaSlug}' nao existe em categoria_post. Rode: SELECT id,nome,slug FROM categoria_post;");
}
$categoriaId = (int) $categoria['id'];
$categoriaSlugFinal = (string) $categoria['slug'];

$updateId = (int) ($data['post_id'] ?? 0);
$existingPost = $updateId > 0 ? $posts->findAdminById($updateId) : null;
if ($updateId > 0 && $existingPost === null) {
    en_blog_draft_fail("post_id {$updateId} nao encontrado - nao é possivel atualizar.");
}

if ($existingPost !== null) {
    // atualiza um rascunho existente: mantem o slug atual (a menos que peca um novo)
    $requestedSlug = trim((string) ($data['slug'] ?? ''));
    if ($requestedSlug !== '') {
        $slug = $posts->nextAvailableSlug(en_blog_draft_slugify($requestedSlug), $updateId);
    } else {
        $slug = (string) $existingPost['slug'];
    }
} else {
    $baseSlug = en_blog_draft_slugify((string) ($data['slug'] ?? $titulo));
    if ($baseSlug === '') {
        en_blog_draft_fail('nao foi possivel gerar slug a partir do titulo/slug informado.');
    }
    $slug = $posts->nextAvailableSlug($baseSlug);
}

$publicRoot = dirname(__DIR__) . '/public';
$mediaDir = $publicRoot . '/uploads/posts/' . $slug . '/images';
if (!is_dir($mediaDir) && !mkdir($mediaDir, 0775, true) && !is_dir($mediaDir)) {
    en_blog_draft_fail('nao foi possivel criar a pasta de midia: ' . $mediaDir);
}

$imagemCapa = '';
$coverPath = trim((string) ($data['cover_image_path'] ?? ''));
if ($coverPath !== '') {
    if (!is_file($coverPath)) {
        en_blog_draft_fail('cover_image_path nao encontrado: ' . $coverPath);
    }
    $ext = strtolower(pathinfo($coverPath, PATHINFO_EXTENSION)) ?: 'jpg';
    $destName = 'capa.' . $ext;
    if (!copy($coverPath, $mediaDir . '/' . $destName)) {
        en_blog_draft_fail('falha ao copiar a imagem de capa.');
    }
    $imagemCapa = 'uploads/posts/' . $slug . '/images/' . $destName;
}

$bodyImages = is_array($data['body_images'] ?? null) ? $data['body_images'] : [];
$counter = 1;
foreach ($bodyImages as $img) {
    if (!is_array($img)) {
        continue;
    }
    $srcPath = trim((string) ($img['path'] ?? ''));
    if ($srcPath === '' || !is_file($srcPath)) {
        $counter++;
        continue;
    }
    $ext = strtolower(pathinfo($srcPath, PATHINFO_EXTENSION)) ?: 'jpg';
    $destName = sprintf('img-%03d.%s', $counter, $ext);
    if (copy($srcPath, $mediaDir . '/' . $destName)) {
        $relative = 'uploads/posts/' . $slug . '/images/' . $destName;
        $conteudo = str_replace('__BODY_IMAGE_' . $counter . '__', $relative, $conteudo);
    }
    $counter++;
}

$dataPublicacao = trim((string) ($data['data_publicacao'] ?? ''));
if ($dataPublicacao === '') {
    $dataPublicacao = $existingPost !== null
        ? (string) ($existingPost['data_publicacao'] ?? date('Y-m-d H:i:s'))
        : date('Y-m-d H:i:s');
}

if ($imagemCapa === '' && $existingPost !== null) {
    // sem capa nova: preserva a que ja existia no post
    $imagemCapa = (string) ($existingPost['imagem_capa'] ?? '');
}

$payload = [
    'titulo' => $titulo,
    'slug' => $slug,
    'resumo' => trim((string) ($data['resumo'] ?? '')),
    'conteudo' => $conteudo,
    'categoria' => $categoriaSlugFinal,
    'categoria_post_id' => $categoriaId,
    'imagem_capa' => $imagemCapa,
    'imagem_thumb' => $imagemCapa,
    'autor_id' => (int) ($data['autor_id'] ?? ($existingPost['autor_id'] ?? 1)) ?: 1,
    'data_publicacao' => $dataPublicacao,
    'tempo_leitura' => max(1, (int) ($data['tempo_leitura'] ?? 5)),
    'seo_title' => trim((string) ($data['seo_title'] ?? '')),
    'seo_description' => trim((string) ($data['seo_description'] ?? '')),
    'seo_keywords' => trim((string) ($data['seo_keywords'] ?? '')),
    'tags' => trim((string) ($data['tags'] ?? '')),
    'status' => $status,
    'destaque' => ((int) ($data['destaque'] ?? 0)) ? 1 : 0,
    'proximo_post_id' => max(0, (int) ($data['proximo_post_id'] ?? 0)),
    'tipo_post' => trim((string) ($data['tipo_post'] ?? '')),
];

if ($existingPost !== null) {
    $posts->updateAdmin($updateId, $payload);
    $postId = $updateId;
} else {
    $postId = $posts->insertAdmin($payload);
}

$appUrl = rtrim((string) (getenv('APP_URL') ?: 'http://localhost/estrategia-nerd'), '/');

echo json_encode([
    'ok' => true,
    'id' => $postId,
    'slug' => $slug,
    'status' => $status,
    'mode' => $existingPost !== null ? 'updated' : 'created',
    'edit_url' => $appUrl . '/admin/editar-post?id=' . $postId,
    'preview_url' => $appUrl . '/post/' . $slug,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
