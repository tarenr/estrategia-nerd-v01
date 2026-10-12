<?php
/**
 * -----------------------------------------------------------------------------
 * @file        scripts/deploy-stage-blog-image-fix.php
 * @project     Estrategia Nerd
 * @purpose     Deploy restrito para Stage das correções de proporção de imagem:
 *              1) app/Services/Site/PostService.php
 *              2) assets/css/site.css
 *              Com backup imutável, validação SHA-256 e teste HTTP remoto.
 * @usage       C:\xampp\php\php.exe scripts/deploy-stage-blog-image-fix.php <acao> --target=stage
 *              Ações: --backup | --deploy | --verify | --rollback
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}

require __DIR__ . '/backup/EnvLoader.php';
$root = dirname(__DIR__);
Scripts\Backup\EnvLoader::load($root . '/.env');
$config = require $root . '/config/content-sync.php';

$action = (string) ($argv[1] ?? '');
if (!in_array($action, ['--backup', '--deploy', '--verify', '--rollback'], true)) {
    fwrite(STDERR, "Uso: php scripts/deploy-stage-blog-image-fix.php <--backup|--deploy|--verify|--rollback> --target=stage\n");
    exit(2);
}

$target = '';
foreach (array_slice($argv, 2) as $arg) {
    if (preg_match('/^--target=(stage|production)$/', $arg, $matches) === 1) {
        $target = $matches[1];
    }
}

if ($target !== 'stage') {
    fwrite(STDERR, "STOP: Apenas --target=stage é permitido para este deploy.\n");
    exit(2);
}

$profile = $config['profiles'][$target];
$codeProfile = (array) ($profile['code_deploy'] ?? []);
$uploadProfile = (array) ($profile['uploads'] ?? []);

$dir = $root . '/storage/deploy-stage-blog-image-fix/' . $target;
$beforeDir = $dir . '/before';
$manifestPath = $dir . '/manifest.json';

// Local files mapped to remote paths relative to base roots
$deployFiles = [
    'post_service' => [
        'label' => 'PostService.php',
        'local' => $root . '/app/Services/Site/PostService.php',
        'base'  => 'code',
        'rel'   => 'app/Services/Site/PostService.php',
    ],
    'site_css' => [
        'label' => 'site.css',
        'local' => $root . '/public/assets/css/site.css',
        'base'  => 'public',
        'rel'   => 'assets/css/site.css',
    ],
];

// Validate local files exist
foreach ($deployFiles as $key => $fileInfo) {
    if (!is_file($fileInfo['local'])) {
        fwrite(STDERR, "STOP: Arquivo local ausente: {$fileInfo['local']}\n");
        exit(1);
    }
}

function remoteGet(FTP\Connection $ftp, string $remotePath): string
{
    $stream = fopen('php://temp', 'w+b');
    if ($stream === false || !@ftp_fget($ftp, $stream, $remotePath, FTP_BINARY)) {
        throw new RuntimeException("Arquivo remoto indisponível ou inacessível: {$remotePath}");
    }
    rewind($stream);
    $bytes = (string) stream_get_contents($stream);
    fclose($stream);
    if ($bytes === '') {
        throw new RuntimeException("Arquivo remoto vazio: {$remotePath}");
    }
    return $bytes;
}

function remotePut(FTP\Connection $ftp, string $remotePath, string $content): void
{
    $stream = fopen('php://temp', 'w+b');
    fwrite($stream, $content);
    rewind($stream);
    $ok = @ftp_fput($ftp, $remotePath, $stream, FTP_BINARY);
    fclose($stream);
    if (!$ok) {
        throw new RuntimeException("Falha no upload FTP para: {$remotePath}");
    }
}

function httpGet(string $url): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_USERAGENT => 'EstrategiaNerd-DeployVerify/1.0',
    ]);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['code' => $status, 'body' => is_string($body) ? $body : ''];
}

$ftp = null;
try {
    $host = (string) ($codeProfile['host'] ?? '');
    $port = (int) ($codeProfile['port'] ?? 21);
    $user = (string) ($codeProfile['username'] ?? '');
    $pass = (string) ($codeProfile['password'] ?? '');
    $passive = (bool) ($codeProfile['passive'] ?? true);

    if ($host === '' || $user === '') {
        throw new RuntimeException("Configuração FTP de stage incompleta.");
    }

    $ftp = @ftp_connect($host, $port, 30);
    if ($ftp === false || !@ftp_login($ftp, $user, $pass)) {
        throw new RuntimeException("Não foi possível conectar/autenticar no FTP de stage.");
    }
    @ftp_pasv($ftp, $passive);

    // Determine root directories
    $rawCodeRoot = '/' . trim((string) $codeProfile['root'], '/');
    if (!str_ends_with($rawCodeRoot, '/_app_core') && @ftp_size($ftp, $rawCodeRoot . '/_app_core/bootstrap.php') >= 0) {
        $codeRoot = $rawCodeRoot . '/_app_core';
    } else {
        $codeRoot = $rawCodeRoot;
    }

    $publicRoot = dirname('/' . trim((string) $uploadProfile['root'], '/'));

    if (!str_ends_with($publicRoot, '/public_html/stage')) {
        throw new RuntimeException("Raiz pública de stage inválida: {$publicRoot}");
    }
    if (!str_contains($codeRoot, '/public_html/stage')) {
        throw new RuntimeException("Raiz de código de stage inválida: {$codeRoot}");
    }

    // Resolve remote absolute paths
    $remotePaths = [
        'post_service' => $codeRoot . '/' . $deployFiles['post_service']['rel'],
        'site_css'     => $publicRoot . '/' . $deployFiles['site_css']['rel'],
    ];

    $manifest = is_file($manifestPath)
        ? json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR)
        : ['target' => $target, 'createdAt' => gmdate('c'), 'files' => []];

    // --- AÇÃO: --backup ---
    if ($action === '--backup') {
        if (!is_dir($beforeDir)) {
            mkdir($beforeDir, 0700, true);
        }

        foreach ($deployFiles as $key => $fileInfo) {
            $remote = $remotePaths[$key];
            $bytes = remoteGet($ftp, $remote);
            $sha = hash('sha256', $bytes);

            $savePath = $beforeDir . '/' . basename($fileInfo['rel']);
            if (!is_file($savePath)) {
                file_put_contents($savePath, $bytes, LOCK_EX);
            }

            $manifest['files'][$key] = [
                'rel' => $fileInfo['rel'],
                'remote' => $remote,
                'beforeSha256' => $sha,
                'localSha256' => hash_file('sha256', $fileInfo['local']),
            ];

            echo "BACKUP OK [{$fileInfo['label']}]: {$remote} (SHA: " . substr($sha, 0, 12) . "...)\n";
        }

        $manifest['backedUpAt'] = gmdate('c');
        file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        echo "PASS: Backup imutável concluído com sucesso em {$beforeDir}.\n";
        exit(0);
    }

    // --- AÇÃO: --deploy ---
    if ($action === '--deploy') {
        if (!is_file($manifestPath)) {
            throw new RuntimeException("Manifesto ausente. Execute --backup primeiro.");
        }

        foreach ($deployFiles as $key => $fileInfo) {
            $remote = $remotePaths[$key];
            $local = $fileInfo['local'];
            $localContent = (string) file_get_contents($local);
            $localSha = hash('sha256', $localContent);

            $currentRemoteBytes = remoteGet($ftp, $remote);
            $currentRemoteSha = hash('sha256', $currentRemoteBytes);

            if ($currentRemoteSha === $localSha) {
                echo "SKIP [{$fileInfo['label']}]: Remoto já está com a versão atualizada.\n";
                continue;
            }

            $expectedBeforeSha = $manifest['files'][$key]['beforeSha256'] ?? null;
            if ($expectedBeforeSha !== null && $currentRemoteSha !== $expectedBeforeSha) {
                throw new RuntimeException("ALERTA: Remoto para {$fileInfo['label']} mudou desde o backup! Abortando por segurança.");
            }

            // Upload via arquivo temporário para garantir atomicidade
            $tmpRemote = $remote . '.tmp.' . bin2hex(random_bytes(4));
            remotePut($ftp, $tmpRemote, $localContent);

            // Verificar se o temporário está íntegro
            $uploadedSha = hash('sha256', remoteGet($ftp, $tmpRemote));
            if ($uploadedSha !== $localSha) {
                @ftp_delete($ftp, $tmpRemote);
                throw new RuntimeException("Upload do temporário falhou na verificação de hash: {$tmpRemote}");
            }

            // Substituição atômica via rename
            // Em alguns servidores FTP de hospedagem compartilhada, rename sobrescrevendo pode requerer delete do antigo se falhar
            if (!@ftp_rename($ftp, $tmpRemote, $remote)) {
                // Tenta remover temporário se o rename não funcionou
                @ftp_delete($ftp, $tmpRemote);
                // Se falhar o rename direto sobre arquivo existente, fazer upload direto seguro
                remotePut($ftp, $remote, $localContent);
            }

            // Verificação pós-deploy
            $finalSha = hash('sha256', remoteGet($ftp, $remote));
            if ($finalSha !== $localSha) {
                throw new RuntimeException("ERRO: Hash final remoto diverge do arquivo local para {$fileInfo['label']}!");
            }

            $manifest['files'][$key]['deployedSha256'] = $finalSha;
            $manifest['files'][$key]['deployedAt'] = gmdate('c');
            echo "DEPLOY OK [{$fileInfo['label']}]: {$remote} atualizado e verificado (SHA: " . substr($finalSha, 0, 12) . "...).\n";
        }

        file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        echo "PASS: Deploy em Stage finalizado com sucesso.\n";
        exit(0);
    }

    // --- AÇÃO: --rollback ---
    if ($action === '--rollback') {
        if (!is_file($manifestPath)) {
            throw new RuntimeException("Manifesto ausente. Não há dados para rollback.");
        }

        foreach ($deployFiles as $key => $fileInfo) {
            $remote = $remotePaths[$key];
            $backupFile = $beforeDir . '/' . basename($fileInfo['rel']);
            if (!is_file($backupFile)) {
                throw new RuntimeException("Arquivo de backup ausente: {$backupFile}");
            }

            $backupContent = (string) file_get_contents($backupFile);
            $backupSha = hash('sha256', $backupContent);

            remotePut($ftp, $remote, $backupContent);
            $verifiedSha = hash('sha256', remoteGet($ftp, $remote));
            if ($verifiedSha !== $backupSha) {
                throw new RuntimeException("Falha no rollback de {$fileInfo['label']}: hash não confere!");
            }

            echo "ROLLBACK OK [{$fileInfo['label']}]: Restaurado para versão original.\n";
        }

        echo "PASS: Rollback concluído com sucesso.\n";
        exit(0);
    }

    // --- AÇÃO: --verify ---
    if ($action === '--verify') {
        echo "=== Verificação Remota Stage ===\n";
        $errors = [];

        foreach ($deployFiles as $key => $fileInfo) {
            $remote = $remotePaths[$key];
            $localSha = hash_file('sha256', $fileInfo['local']);
            $remoteBytes = remoteGet($ftp, $remote);
            $remoteSha = hash('sha256', $remoteBytes);

            if ($remoteSha !== $localSha) {
                $errors[] = "Hash remoto de {$fileInfo['label']} diverge do local!";
                echo "FAIL [{$fileInfo['label']}]: Remoto != Local\n";
            } else {
                echo "PASS [{$fileInfo['label']}]: Hash remoto confere exatamente com o local.\n";
            }
        }

        // Teste HTTP ao CSS público de Stage
        $cssUrl = 'https://estrategianerd.com.br/stage/assets/css/site.css?v=' . time();
        $httpCss = httpGet($cssUrl);
        if ($httpCss['code'] !== 200) {
            $errors[] = "HTTP {$httpCss['code']} ao acessar {$cssUrl}";
            echo "FAIL [HTTP CSS]: Resposta {$httpCss['code']}\n";
        } else {
            if (str_contains($httpCss['body'], 'max-height: 36rem')) {
                echo "PASS [HTTP CSS]: Código HTTP 200 e regra 'max-height: 36rem' ativa em {$cssUrl}.\n";
            } else {
                $errors[] = "Regra 'max-height: 36rem' não encontrada no CSS servido por Stage (cache de CDN ou proxy?).";
                echo "WARN [HTTP CSS]: 200 recebido, mas string 'max-height: 36rem' ausente.\n";
            }
        }

        // Teste HTTP de um post em Stage
        $postUrl = 'https://estrategianerd.com.br/stage/post/o-mundo-de-diablo-entenda-santuario-ceu-e-inferno';
        $httpPost = httpGet($postUrl);
        if ($httpPost['code'] === 200) {
            echo "PASS [HTTP Post]: Artigo de teste respondeu HTTP 200 em {$postUrl}.\n";
            if (str_contains($httpPost['body'], 'max-height: 36rem') || str_contains($httpPost['body'], 'max-height:36rem')) {
                echo "PASS [HTTP Post Inline]: Regra 'max-height: 36rem' ativa no HTML do post.\n";
            }
        } else {
            $errors[] = "Artigo de teste em {$postUrl} respondeu HTTP {$httpPost['code']}";
            echo "FAIL [HTTP Post]: Artigo respondeu {$httpPost['code']}\n";
        }

        if ($errors !== []) {
            fwrite(STDERR, "\nVerificação concluída com pendências/erros:\n- " . implode("\n- ", $errors) . "\n");
            exit(1);
        }

        echo "\nPASS GERAL: Todos os arquivos e endpoints de Stage foram validados com 100% de sucesso.\n";
        exit(0);
    }
} catch (Throwable $e) {
    fwrite(STDERR, "ERRO: " . $e->getMessage() . PHP_EOL);
    exit(1);
} finally {
    if ($ftp instanceof FTP\Connection) {
        ftp_close($ftp);
    }
}
