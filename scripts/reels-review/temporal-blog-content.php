<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require dirname(__DIR__) . '/backup/EnvLoader.php';
Scripts\Backup\EnvLoader::load(dirname(__DIR__, 2) . '/.env');
$root = dirname(__DIR__, 2);
$config = require $root . '/config/content-sync.php';
$d = $config['profiles']['local']['database'];
$pdo = new PDO(sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $d['host'], $d['port'], $d['database']), $d['username'], $d['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$stmt = $pdo->prepare('SELECT * FROM posts WHERE slug IN (?,?) ORDER BY slug');
$slugs = ['the-witcher-3-wild-hunt-remastered-chega-gr-atis-dia-29-de-setembro', 'windows-11-24h2-fim-do-suporte-em-13-de-outubro-de-2026'];
$stmt->execute($slugs); $rows = $stmt->fetchAll();
if (count($rows) !== 2) { throw new RuntimeException('Dois artigos obrigatorios'); }
$action = $argv[1] ?? '';
if ($action === '--inspect') {
    foreach ($rows as $row) { echo json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL; }
    exit;
}
if (!in_array($action, ['--apply', '--verify', '--rollback'], true)) { throw new RuntimeException('Use --inspect|--apply|--verify|--rollback (somente local)'); }
$dir = $root . '/storage/previews/reels-review/temporal-20261008';
if (!is_dir($dir) && !mkdir($dir, 0777, true)) { throw new RuntimeException('Diretorio de backup indisponivel'); }
$backupFile = $dir . '/backup.json';
/** @return array<string,string> */
function revisedEditorial(array $row): array {
    $fields = array_intersect_key($row, array_flip(['titulo','resumo','conteudo','seo_title','seo_description']));
    $content = $row['conteudo'];
    if (str_starts_with($row['slug'], 'the-witcher')) {
        $fields['titulo'] = '[[The Witcher 3: Wild Hunt Remastered]]: novidades e condições do upgrade gratuito';
        $fields['resumo'] = 'Lançado em 29/09/2026, The Witcher 3: Wild Hunt Remastered reúne melhorias visuais e de gameplay. Confira as plataformas e as condições do upgrade para quem já possui o jogo.';
        $fields['seo_title'] = 'The Witcher 3 Remastered: novidades e upgrade gratuito';
        $fields['seo_description'] = 'Confira o lançamento de The Witcher 3 Remastered, as plataformas e as condições do upgrade gratuito para quem já possui o jogo no mesmo ecossistema.';
        $changes = [
            'Agora a CD PROJEKT RED está entregando' => 'A CD PROJEKT RED lançou em 29 de setembro de 2026',
            'e o melhor de tudo é que ele é gratuito.' => 'com upgrade gratuito para quem já possui o jogo nas condições das plataformas elegíveis.',
            'Quando lança e em quais plataformas' => 'Lançamento e plataformas',
            'O Remastered chega no dia' => 'O Remastered foi lançado em',
            'a atualização também chega através' => 'o jogo também está disponível através',
            'O upgrade é totalmente gratuito' => 'Quem tem direito ao upgrade gratuito',
            'Quem já possui The Witcher 3: Wild Hunt em uma plataforma compatível recebe o Remastered sem custo nenhum.' => 'Quem já possui uma versão física ou digital de The Witcher 3: Wild Hunt pode atualizar gratuitamente dentro do mesmo ecossistema de plataforma. Isso não concede uma cópia gratuita em todas as lojas ou plataformas. As plataformas incluem GOG, Steam, Epic Games Store, Battle.net, PS5, Xbox Series X|S e Nintendo Switch 2; proprietários da versão de Nintendo Switch podem obter o upgrade no Switch 2.',
            'vão recebê-las de graça junto com o upgrade.' => 'recebem as expansões junto com o upgrade.',
            'A atualização promete combate' => 'A atualização trouxe melhorias no combate',
            'mas é algo pra ficar de olho conforme a data de lançamento se aproxima.' => 'os detalhes desse conteúdo devem ser consultados separadamente nos canais oficiais.',
            'The Witcher 3: Wild Hunt Remastered chega em 29/09/2026' => 'The Witcher 3: Wild Hunt Remastered foi lançado em 29/09/2026',
            'pra quem já tem o jogo, roda' => 'para proprietários elegíveis dentro do mesmo ecossistema de plataforma, roda',
        ];
        foreach ($changes as $from => $to) {
            if (substr_count($content, $from) !== 1) { throw new RuntimeException('Trecho Witcher mudou; revisar antes de aplicar'); }
            $content = str_replace($from, $to, $content);
        }
        $content .= '\n<p><b>Fontes oficiais:</b> <a href="https://www.thewitcher.com/es/en/news/52054/the-witcher-3-the-wild-hunt-remastered-is-available-now">CD PROJEKT RED — lançamento</a>; <a href="https://www.thewitcher.com/es/en/news/52041/see-whats-new-in-the-witcher-3-wild-hunt-remastered">notas e condições do upgrade</a>.</p>';
    } else {
        $fields['resumo'] = 'A data de fim do suporte do Windows 11 24H2 Home e Pro é 13/10/2026. Confira sua edição, os prazos e como atualizar pelo Windows Update.';
        $fields['seo_title'] = 'Windows 11 24H2: prazo de suporte e como atualizar';
        $fields['seo_description'] = 'O fim do suporte do Windows 11 24H2 Home e Pro tem data de 13/10/2026. Confira sua edição e como buscar uma versão com suporte pelo Windows Update.';
        $changes = [
            'encerra o suporte em' => 'tem como data de fim do suporte',
            'encerra esse ciclo em' => 'tem como data de encerramento desse ciclo',
            'O que está acontecendo' => 'Como funciona o prazo de suporte',
            'O que fazer antes de 13 de outubro de 2026' => 'Como atualizar para uma versão com suporte',
            'atualizar para a versão seguinte do Windows 11 (25H2), que já está disponível e recebe suporte por mais tempo.' => 'instalar uma versão com suporte oferecida ao seu dispositivo pelo Windows Update. A tabela oficial da Microsoft informa os prazos de cada versão e edição.',
            'Se a 25H2 aparecer disponível, instale' => 'Se uma atualização de versão com suporte aparecer disponível, confira os requisitos e instale',
            'Se o Windows Update não mostrar a 25H2' => 'Se o Windows Update não oferecer uma atualização de versão',
            'Atualização automática para a versão 25H2' => 'Atualização automática e compatibilidade',
            'a atualização para a versão 25H2 automaticamente' => 'uma atualização de versão automaticamente',
            'checar antes do prazo' => 'checar a versão instalada e a oferta de atualização',
            'verificar manualmente antes do prazo' => 'verificar manualmente a oferta de atualização',
            'perto do prazo' => 'e você ainda estiver em uma versão sem suporte',
            'rodar o Windows Update antes de 13 de outubro de 2026.' => 'rodar o Windows Update. Se ainda estiver no 24H2 Home/Pro após 13 de outubro de 2026, busque uma versão com suporte.',
            'Meu PC vai parar de funcionar em 13/10/2026?' => 'O fim do suporte impede o PC de funcionar?',
            'A atualização para a versão 25H2 é gratuita' => 'A atualização de versão oferecida pelo Windows Update é gratuita',
            'A 25H2 não apareceu no Windows Update. O que fazer?' => 'A atualização de versão não apareceu no Windows Update. O que fazer?',
        ];
        foreach ($changes as $from => $to) {
            if (substr_count($content, $from) < 1) { throw new RuntimeException('Trecho Windows mudou; revisar antes de aplicar'); }
            $content = str_replace($from, $to, $content);
        }
    }
    $fields['conteudo'] = $content;
    return $fields;
}
$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare('SELECT * FROM posts WHERE slug IN (?,?) ORDER BY slug FOR UPDATE');
    $stmt->execute($slugs); $rows = $stmt->fetchAll();
    if (!is_file($backupFile)) {
        if ($action !== '--apply') { throw new RuntimeException('Backup ausente'); }
        $items = [];
        foreach ($rows as $row) { $items[] = ['before' => $row, 'after' => revisedEditorial($row)]; }
        $handle = fopen($backupFile, 'x'); if (!$handle) { throw new RuntimeException('Backup existente'); }
        fwrite($handle, json_encode($items, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)); fclose($handle);
    }
    $items = json_decode((string) file_get_contents($backupFile), true, 512, JSON_THROW_ON_ERROR);
    foreach ($items as $item) {
        $matches = array_values(array_filter($rows, static fn(array $r): bool => $r['id'] === $item['before']['id'] && $r['slug'] === $item['before']['slug']));
        if (count($matches) !== 1) { throw new RuntimeException('Identidade mudou'); }
        $row = $matches[0];
        $desired = $item['after'];
        $desired['conteudo'] = str_replace('\\n<p><b>Fontes oficiais:', '<p><b>Fontes oficiais:', $desired['conteudo']);
        foreach ($item['after'] as $field => $value) { if ($row[$field] !== $item['before'][$field] && $row[$field] !== $value && $row[$field] !== $desired[$field]) { throw new RuntimeException('Edicao concorrente: ' . $field); } }
        if ($action === '--verify') {
            foreach ($desired as $field => $value) { if ($row[$field] !== $value) { throw new RuntimeException('Revisao nao aplicada'); } }
        } else {
            $values = $action === '--apply' ? $desired : array_intersect_key($item['before'], $desired);
            $update = $pdo->prepare('UPDATE posts SET titulo=?,resumo=?,conteudo=?,seo_title=?,seo_description=? WHERE id=? AND slug=?');
            $update->execute([$values['titulo'],$values['resumo'],$values['conteudo'],$values['seo_title'],$values['seo_description'],$row['id'],$row['slug']]);
            $stmt->execute($slugs); $after = array_values(array_filter($stmt->fetchAll(), static fn(array $r): bool => $r['id'] === $row['id']))[0];
            foreach ($row as $field => $value) { if (!array_key_exists($field, $values) && $field !== 'data_atualizacao' && $after[$field] !== $value) { throw new RuntimeException('Campo fora do escopo alterado'); } }
        }
        echo 'PASS local: artigo ' . $row['id'] . ', endereco preservado' . PHP_EOL;
    }
    $pdo->commit();
} catch (Throwable $e) { if ($pdo->inTransaction()) { $pdo->rollBack(); } throw $e; }
