<?php
declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

use App\Support\TargetEnvironmentDatabase;
use App\Support\TargetEnvironmentUploads;

echo "=== INICIANDO DEPLOY DO POST ECHO PARA STAGE ===\n";

$localPdo = $GLOBALS['pdo'];

// 1. Atualizar post local para 'publicado'
echo "[1/4] Atualizando post local (#88) para 'publicado'...\n";
$stmt = $localPdo->prepare("
    UPDATE posts 
    SET status = 'publicado', 
        data_publicacao = COALESCE(data_publicacao, NOW()),
        data_atualizacao = NOW()
    WHERE id = 88
");
$stmt->execute();

$stmt = $localPdo->query("SELECT id, slug, status, data_publicacao, imagem_capa, imagem_thumb FROM posts WHERE id = 88");
$localPost = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Post local atualizado: " . json_encode($localPost, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";

// 2. Transferir os 10 arquivos para Stage (excluindo reels/)
echo "\n[2/4] Enviando arquivos de mídia para Stage via FTP...\n";
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
    $ok = TargetEnvironmentUploads::putFile('stage', $absPath, $relPath);
    if (!$ok) {
        throw new RuntimeException("Falha ao enviar via FTP: {$relPath}");
    }
    echo "OK!\n";
}

// 3. Sincronizar post no banco de dados de Stage
echo "\n[3/4] Conectando ao MySQL de Stage e sincronizando post...\n";
$stagePdo = TargetEnvironmentDatabase::pdo('stage');

// Obter dados completos do post local
$stmt = $localPdo->prepare("SELECT * FROM posts WHERE id = 88");
$stmt->execute();
$postData = $stmt->fetch(PDO::FETCH_ASSOC);

// Verificar se já existe no Stage pelo slug
$stmtCheck = $stagePdo->prepare("SELECT id FROM posts WHERE slug = :slug");
$stmtCheck->execute([':slug' => $postData['slug']]);
$stagePost = $stmtCheck->fetch(PDO::FETCH_ASSOC);

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

if ($stagePost) {
    echo "Post encontrado no Stage com ID {$stagePost['id']}. Atualizando...\n";
    $setClauses = [];
    $params = [':id' => $stagePost['id']];
    foreach ($fields as $col => $val) {
        $setClauses[] = "{$col} = :{$col}";
        $params[":{$col}"] = $val;
    }
    $setSql = implode(', ', $setClauses);
    $updateStmt = $stagePdo->prepare("UPDATE posts SET {$setSql} WHERE id = :id");
    $updateStmt->execute($params);
    $stageId = $stagePost['id'];
} else {
    echo "Post não existe no Stage. Inserindo novo registro...\n";
    $cols = array_keys($fields);
    $colsList = implode(', ', $cols);
    $placeholders = ':' . implode(', :', $cols);
    $params = [];
    foreach ($fields as $col => $val) {
        $params[":{$col}"] = $val;
    }
    $insertStmt = $stagePdo->prepare("INSERT INTO posts ({$colsList}) VALUES ({$placeholders})");
    $insertStmt->execute($params);
    $stageId = $stagePdo->lastInsertId();
}

echo "Post sincronizado com sucesso no Stage (ID: {$stageId})!\n";

// 4. Validação da sincronização
$stmtCheckStage = $stagePdo->prepare("SELECT id, slug, status, data_publicacao, imagem_capa FROM posts WHERE id = :id");
$stmtCheckStage->execute([':id' => $stageId]);
$stageRow = $stmtCheckStage->fetch(PDO::FETCH_ASSOC);
echo "Registro conferido no Stage: " . json_encode($stageRow, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";

echo "\n=== DEPLOY CONCLUÍDO COM SUCESSO ===\n";
