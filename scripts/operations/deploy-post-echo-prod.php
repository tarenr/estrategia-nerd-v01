<?php
declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

use App\Support\TargetEnvironmentDatabase;
use App\Support\TargetEnvironmentUploads;

echo "=== INICIANDO DEPLOY DO POST ECHO PARA PRODUÇÃO ===\n";

$localPdo = $GLOBALS['pdo'];
$prodPdo = TargetEnvironmentDatabase::pdo('production');

// 1. Transferir os 10 arquivos para Produção (excluindo reels/)
echo "\n[1/3] Enviando arquivos de mídia para Produção via FTP...\n";
$baseLocalDir = __DIR__ . '/../../public/';
$slug = 'conheca-o-echo-assistente-estrategia-nerd';

$filesToUpload = [
    "uploads/posts/{$slug}/images/capa.webp",
    "uploads/posts/{$slug}/images/thumb.webp",
    "uploads/posts/{$slug}/images/img-001.webp",
    "uploads/posts/{$slug}/images/img-002.webp",
    "uploads/posts/{$slug}/images/img-003.webp",
    "uploads/posts/{$slug}/images/img-004.webp",
    "uploads/posts/{$slug}/images/img-005.webp",
    "uploads/posts/{$slug}/images/img-006.webp",
    "uploads/posts/{$slug}/images/img-007.webp",
    "uploads/posts/{$slug}/video/echo-detalhado.mp4",
];

foreach ($filesToUpload as $relPath) {
    $absPath = realpath($baseLocalDir . $relPath);
    if (!$absPath || !file_exists($absPath)) {
        throw new RuntimeException("Arquivo não encontrado: {$relPath}");
    }
    $sizeMb = round(filesize($absPath) / 1024 / 1024, 2);
    echo "Enviando {$relPath} ({$sizeMb} MB)... ";
    $ok = TargetEnvironmentUploads::putFile('production', $absPath, $relPath);
    if (!$ok) {
        throw new RuntimeException("Falha ao enviar via FTP para Produção: {$relPath}");
    }
    echo "OK!\n";
}

// 2. Sincronizar post no banco de dados de Produção
echo "\n[2/3] Sincronizando post no MySQL de Produção...\n";

// Obter dados completos do post local
$stmt = $localPdo->prepare("SELECT * FROM posts WHERE id = 88");
$stmt->execute();
$postData = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$postData) {
    throw new RuntimeException("Post local #88 não encontrado!");
}

// Verificar se já existe em Produção pelo slug
$stmtCheck = $prodPdo->prepare("SELECT id FROM posts WHERE slug = :slug");
$stmtCheck->execute([':slug' => $postData['slug']]);
$prodPost = $stmtCheck->fetch(PDO::FETCH_ASSOC);

// Lista de colunas a sincronizar
$syncColumns = [
    'titulo',
    'slug',
    'resumo',
    'conteudo',
    'categoria',
    'categoria_id',
    'categoria_post_id',
    'tipo_post',
    'imagem_capa',
    'imagem_thumb',
    'autor_id',
    'data_publicacao',
    'tempo_leitura',
    'seo_title',
    'seo_description',
    'seo_keywords',
    'tags',
    'status',
    'destaque',
];

$fields = [];
foreach ($syncColumns as $col) {
    if (array_key_exists($col, $postData)) {
        $fields[$col] = $postData[$col];
    }
}
$fields['status'] = 'publicado';
$fields['data_atualizacao'] = date('Y-m-d H:i:s');

if ($prodPost) {
    echo "Post encontrado em Produção com ID {$prodPost['id']}. Atualizando...\n";
    $setClauses = [];
    $params = [':id' => $prodPost['id']];
    foreach ($fields as $col => $val) {
        $setClauses[] = "{$col} = :{$col}";
        $params[":{$col}"] = $val;
    }
    $setSql = implode(', ', $setClauses);
    $updateStmt = $prodPdo->prepare("UPDATE posts SET {$setSql} WHERE id = :id");
    $updateStmt->execute($params);
    $prodId = $prodPost['id'];
} else {
    echo "Post não existe em Produção. Inserindo novo registro...\n";
    $cols = array_keys($fields);
    $colsList = implode(', ', $cols);
    $placeholders = ':' . implode(', :', $cols);
    $params = [];
    foreach ($fields as $col => $val) {
        $params[":{$col}"] = $val;
    }
    $insertStmt = $prodPdo->prepare("INSERT INTO posts ({$colsList}) VALUES ({$placeholders})");
    $insertStmt->execute($params);
    $prodId = $prodPdo->lastInsertId();
}

echo "Post sincronizado com sucesso em Produção (ID: {$prodId})!\n";

// 3. Validação do registro em Produção
$stmtCheckProd = $prodPdo->prepare("SELECT id, slug, status, data_publicacao, imagem_capa FROM posts WHERE id = :id");
$stmtCheckProd->execute([':id' => $prodId]);
$prodRow = $stmtCheckProd->fetch(PDO::FETCH_ASSOC);
echo "Registro conferido em Produção: " . json_encode($prodRow, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";

echo "\n=== DEPLOY EM PRODUÇÃO CONCLUÍDO COM SUCESSO ===\n";
