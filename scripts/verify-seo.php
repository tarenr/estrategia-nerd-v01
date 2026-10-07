<?php
declare(strict_types=1);

// Integração isolada: SQLite em memória, sem bootstrap/.env, HTTP ou banco real.
use App\Repositories\ComentarioRepository;
use App\Repositories\EstatisticaRepository;
use App\Repositories\PostRepository;
use App\Services\Site\PostService;
use App\Support\View;

if (PHP_SAPI !== 'cli') { exit(1); }
$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';
require $root . '/app/Support/Helpers.php';
$_ENV['APP_URL'] = 'https://seo.example.test';
$_SERVER['HTTP_HOST'] = 'seo.example.test';
$_SERVER['REQUEST_URI'] = '/post/artigo-teste';
$_SESSION = ['_csrf_token' => 'fixture-seo'];
date_default_timezone_set('America/Sao_Paulo');
$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$GLOBALS['pdo'] = $pdo;
$pdo->exec("CREATE TABLE configuracoes (chave TEXT, valor TEXT);
INSERT INTO configuracoes VALUES ('nome_site','Estratégia Nerd'),('logo_url','/assets/brand/logo-main.png');
CREATE TABLE categoria_post (id INTEGER, nome TEXT, slug TEXT, cor TEXT);
INSERT INTO categoria_post VALUES (1,'Hardware','hardware','#00d4ff');
CREATE TABLE usuarios (id INTEGER, nome TEXT, usuario TEXT, avatar_tipo TEXT, avatar_icone TEXT, avatar_cor TEXT, avatar_imagem TEXT, avatar_focal_x REAL, avatar_focal_y REAL);
INSERT INTO usuarios (id,nome) VALUES (1,'Autora de Teste');
CREATE TABLE comentarios (id INTEGER, post_id INTEGER, nome TEXT, email TEXT, comentario TEXT, status TEXT, parent_id INTEGER, admin_user_id INTEGER, data TEXT);
CREATE TABLE posts (id INTEGER, titulo TEXT, slug TEXT, resumo TEXT, conteudo TEXT, imagem_capa TEXT, imagem_thumb TEXT, data_publicacao TEXT, views INTEGER, curtidas INTEGER, tempo_leitura INTEGER, comentarios_count INTEGER, destaque INTEGER, seo_title TEXT, seo_description TEXT, tags TEXT, autor_id INTEGER, categoria_post_id INTEGER, status TEXT);");
$insert = $pdo->prepare('INSERT INTO posts VALUES (0,:titulo,\'artigo-teste\',\'Resumo do artigo\',\'<p>Conteúdo de teste.</p>\',\'/uploads/capa.jpg\',\'/uploads/thumb.jpg\',\'2026-10-07 10:30:00\',0,0,5,0,0,\'Título SEO\',\'Descrição SEO\',\'hardware, review\',1,1,\'publicado\')');
$insert->execute(['titulo' => 'Conheça [[este hardware]]']);
$repo = new PostRepository($pdo);
// A coluna opcional de recomendação não é parte destas fixtures SQLite.
(new ReflectionProperty($repo, 'supportsNextStepColumn'))->setValue($repo, false);
$service = new PostService($repo, new ComentarioRepository($pdo), new EstatisticaRepository($pdo));
$passed = 0;
$failed = 0;
$check = static function (string $name, bool $ok) use (&$passed, &$failed): void {
    $ok ? $passed++ : $failed++;
    echo ($ok ? 'PASS ' : 'FAIL ') . $name . "\n";
};
$render = static function () use ($service): array {
    $model = $service->getViewModel('artigo-teste');
    if (!is_array($model)) { throw new RuntimeException('Fixture não encontrada.'); }
    ob_start();
    View::render('site/post', $model);
    $html = (string) ob_get_clean();
    $dom = new DOMDocument();
    @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
    $xpath = new DOMXPath($dom);
    $schemas = [];
    foreach ($xpath->query('//script[@type="application/ld+json"]') as $node) {
        $schemas[] = json_decode($node->textContent, true, 512, JSON_THROW_ON_ERROR);
    }
    return [$model, $xpath, $schemas, $html];
};
[$model, $xpath, $schemas, $previewHtml] = $render();
if (in_array('--preview', $argv, true)) {
    $directory = $root . '/storage/previews/seo/seo-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3));
    mkdir($directory, 0755, true);
    file_put_contents($directory . '/article.html', $previewHtml);
    echo 'PREVIEW ' . $directory . '/article.html' . "\n";
}
$check('Article e BreadcrumbList emitidos uma única vez', count($schemas) === 2 && $schemas[0]['@type'] === 'Article' && $schemas[1]['@type'] === 'BreadcrumbList');
$check('Autoria real e pública consistente', $schemas[0]['author'] === ['@type' => 'Person', 'name' => 'Autora de Teste'] && $xpath->query('//*[contains(@class,"post-meta-line")]')->item(0)->textContent !== '' && str_contains($xpath->query('//*[contains(@class,"post-meta-line")]')->item(0)->textContent, 'Autora de Teste'));
$check('Data publicada ISO com fuso configurado', $schemas[0]['datePublished'] === '2026-10-07T10:30:00-03:00');
$check('Data editorial de atualização não inventada', !isset($schemas[0]['dateModified']));
$check('Headline conserva título editorial sem marcadores', $schemas[0]['headline'] === 'Conheça este hardware');
$check('Breadcrumb inclui categoria e artigo', count($schemas[1]['itemListElement']) === 4 && $schemas[1]['itemListElement'][2]['item'] === 'https://seo.example.test/blog/hardware');
$crumbNodes = $xpath->query('//nav[@aria-label="Caminho de navegação"]//li');
foreach ($schemas[1]['itemListElement'] as $i => $crumb) {
    $node = $crumbNodes->item($i);
    $check('Breadcrumb visual e estruturado coerentes ' . ($i + 1), $node !== null && trim($node->textContent) === $crumb['name'] && $crumb['position'] === $i + 1 && ($i === 3 || $node->getElementsByTagName('a')->item(0)->getAttribute('href') === $crumb['item']));
}
$check('Página atual e H1 únicos', $xpath->query('//nav//*[@aria-current="page"]')->length === 1 && $xpath->query('//h1')->length === 1);
$expected = ['og:title' => 'Título SEO', 'og:description' => 'Descrição SEO', 'og:url' => 'https://seo.example.test/post/artigo-teste', 'og:image' => 'https://seo.example.test/uploads/capa.jpg', 'twitter:title' => 'Título SEO', 'twitter:description' => 'Descrição SEO', 'twitter:image' => 'https://seo.example.test/uploads/capa.jpg'];
foreach ($expected as $name => $value) {
    $nodes = $xpath->query('//meta[@property="' . $name . '" or @name="' . $name . '"]');
    $check('Metadado único e correto ' . $name, $nodes->length === 1 && $nodes->item(0)->getAttribute('content') === $value);
}
$check('Canonical único e igual à URL compartilhada', $xpath->query('//link[@rel="canonical"]')->length === 1 && $xpath->query('//link[@rel="canonical"]')->item(0)->getAttribute('href') === $expected['og:url']);
$pdo->exec("UPDATE categoria_post SET slug='setup nerd'; UPDATE posts SET imagem_capa='https://images.example.test/capa.jpg'");
[$model, , $schemas] = $render();
$check('Categoria com espaços usa rota codificada', $schemas[1]['itemListElement'][2]['item'] === 'https://seo.example.test/blog/setup%20nerd');
$check('Imagem externa HTTP mantém URL absoluta', $model['meta_image'] === 'https://images.example.test/capa.jpg' && $schemas[0]['image'][0] === $model['meta_image']);
$pdo->exec("UPDATE usuarios SET nome='Estratégia Nerd'");
[, , $schemas] = $render();
$check('Perfil editorial da marca usa Organization', $schemas[0]['author'] === ['@type' => 'Organization', 'name' => 'Estratégia Nerd']);
$pdo->exec("UPDATE posts SET seo_title='',seo_description='',autor_id=999,categoria_post_id=NULL,imagem_capa=''");
[$model, $xpath, $schemas] = $render();
$check('Título ausente usa título editorial sem marcadores', $model['title'] === 'Conheça este hardware | Estratégia Nerd');
$check('Descrição ausente usa resumo', $model['meta_description'] === 'Resumo do artigo');
$check('Capa ausente usa thumbnail absoluta', $model['meta_image'] === 'https://seo.example.test/uploads/thumb.jpg');
$check('Autor inexistente usa organização, sem perfil inventado', $schemas[0]['author'] === ['@type' => 'Organization', 'name' => 'Estratégia Nerd']);
$check('Categoria ausente não inventa link', count($schemas[1]['itemListElement']) === 3 && $xpath->query('//nav[@aria-label="Caminho de navegação"]//li')->length === 3);
foreach (['', '0000-00-00 00:00:00', '0000-01-01 10:00:00', '2026-02-30 10:00:00', 'amanhã', '2026-10-07 25:00:00', "2026-10-07 10:00:00\0extra"] as $date) {
    $pdo->prepare('UPDATE posts SET data_publicacao=:date')->execute(['date' => $date]);
    [$model, , $schemas] = $render();
    $check('Data inválida/ausente omitida: ' . ($date !== '' ? str_replace("\0", '[NUL]', $date) : '(vazia)'), !isset($schemas[0]['datePublished']) && !isset($schemas[0]['dateModified']) && $model['post']['data'] === '');
}
$pdo->exec("UPDATE posts SET seo_title='<b></b>',seo_description='<i></i>'");
[$model] = $render();
$check('SEO sem texto após limpeza usa alternativas', $model['title'] === 'Conheça este hardware | Estratégia Nerd' && $model['meta_description'] === 'Resumo do artigo');
$pdo->exec("UPDATE posts SET imagem_thumb='',resumo=''");
[$model, $xpath] = $render();
$check('Sem imagem usa logo absoluto no compartilhamento', $xpath->query('//meta[@property="og:image"]')->item(0)->getAttribute('content') === 'https://seo.example.test/assets/brand/logo-main.png');
$check('Descrição padrão disponível sem resumo', trim($model['meta_description']) !== '');
$pdo->prepare('UPDATE posts SET titulo=:title,seo_title=:title')->execute(['title' => 'Teste </script><script>alert(1)</script> & "aspas"']);
$pdo->prepare('UPDATE usuarios SET nome=:name')->execute(['name' => 'Autora </script><script>alert(1)</script>']);
$pdo->exec('UPDATE posts SET autor_id=1');
[, $xpath, $schemas, $html] = $render();
$check('Conteúdo não encerra script JSON-LD', count($schemas) === 2 && !str_contains($html, '<script>alert(1)</script>') && str_contains($html, '\\u003C'));
$pdo->exec('DROP TABLE usuarios');
$pdo->exec('UPDATE posts SET autor_id=1');
$row = $repo->findPublicBySlug('artigo-teste');
$check('Tabela de autoria indisponível preserva leitura do artigo', is_array($row) && $row['autor_nome'] === '');
echo "\nSEO: {$passed} OK, {$failed} falhas. Sem HTTP ou alteração de dados reais.\n";
exit($failed === 0 ? 0 : 1);
