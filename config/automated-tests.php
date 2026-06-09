<?php
/**
 * -----------------------------------------------------------------------------
 * Arquivo: config/automated-tests.php
 * Projeto: Estrategia Nerd
 * Proposito: Centralizar configuracoes da suite automatizada operacional.
 * Uso: Carregado por scripts/tests.php e pelos services de testes.
 * Observacoes: Nao executa rotinas; apenas declara ambientes, limites e rotas.
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

$appUrl = rtrim((string) ($_ENV['APP_URL'] ?? ''), '/');

return [
    'result_root' => $_ENV['AUTOMATED_TEST_ROOT'] ?? (dirname(__DIR__) . '/storage/automated-tests'),
    'timeout_seconds' => max(3, (int) ($_ENV['AUTOMATED_TEST_TIMEOUT'] ?? 20)),
    'environments' => [
        'local' => [
            'label' => 'Local',
            'base_url' => (string) ($_ENV['AUTOMATED_TEST_LOCAL_URL'] ?? ($appUrl !== '' ? $appUrl : 'http://localhost/estrategia-nerd')),
        ],
        'stage' => [
            'label' => 'Stage',
            'base_url' => (string) ($_ENV['AUTOMATED_TEST_STAGE_URL'] ?? ($_ENV['SMOKE_STAGE_URL'] ?? 'https://estrategianerd.com.br/stage')),
        ],
    ],
    'admin' => [
        'user' => (string) ($_ENV['AUTOMATED_TEST_ADMIN_USER'] ?? ($_ENV['SMOKE_ADMIN_USER'] ?? ($_ENV['SMOKE_ADMIN_EMAIL'] ?? ''))),
        'password' => (string) ($_ENV['AUTOMATED_TEST_ADMIN_PASSWORD'] ?? ($_ENV['SMOKE_ADMIN_PASSWORD'] ?? '')),
    ],
    'public_paths' => [
        ['name' => 'Home publica', 'path' => '/', 'expected_statuses' => [200], 'fragments' => ['<title']],
        ['name' => 'Blog', 'path' => '/blog', 'expected_statuses' => [200], 'fragments' => ['blog']],
        ['name' => 'Sitemap XML', 'path' => '/sitemap.xml', 'expected_statuses' => [200], 'fragments' => ['<urlset', '<loc>']],
        ['name' => '404 publico', 'path' => '/__automated-test-url-inexistente', 'expected_statuses' => [404], 'fragments' => []],
    ],
    'admin_paths' => [
        'local' => [
            ['name' => 'Dashboard admin', 'path' => '/admin', 'fragments' => ['Dashboard']],
            ['name' => 'Posts', 'path' => '/admin/posts', 'fragments' => ['Posts']],
            ['name' => 'Categorias', 'path' => '/admin/categorias', 'fragments' => ['Categoria']],
            ['name' => 'Comentarios', 'path' => '/admin/comentarios', 'fragments' => ['Coment']],
            ['name' => 'Midia', 'path' => '/admin/midia', 'fragments' => ['Midia']],
            ['name' => 'Newsletter', 'path' => '/admin/newsletter', 'fragments' => ['Newsletter']],
            ['name' => 'Links', 'path' => '/admin/links', 'fragments' => ['Links']],
            ['name' => 'Central Operacional', 'path' => '/admin/central-operacional-v2', 'fragments' => ['Central Operacional']],
            ['name' => 'Health Check', 'path' => '/admin/health', 'fragments' => ['Health']],
            ['name' => 'Testes Automatizados', 'path' => '/admin/testes', 'fragments' => ['Testes Automatizados', 'Visao Geral']],
            ['name' => 'Observabilidade', 'path' => '/admin/central-operacional-v2/observabilidade', 'fragments' => ['Observabilidade']],
            ['name' => 'SEO Tecnico cache', 'path' => '/admin/central-operacional-v2/seo-tecnico/inspecao', 'fragments' => ['Google Search Console']],
        ],
        'stage' => [
            ['name' => 'Dashboard editorial', 'path' => '/admin', 'fragments' => ['Dashboard']],
            ['name' => 'Posts', 'path' => '/admin/posts', 'fragments' => ['Posts']],
            ['name' => 'Categorias', 'path' => '/admin/categorias', 'fragments' => ['Categoria']],
            ['name' => 'Comentarios', 'path' => '/admin/comentarios', 'fragments' => ['Coment']],
            ['name' => 'Midia', 'path' => '/admin/midia', 'fragments' => ['Midia']],
            ['name' => 'Newsletter', 'path' => '/admin/newsletter', 'fragments' => ['Newsletter']],
            ['name' => 'Links', 'path' => '/admin/links', 'fragments' => ['Links']],
        ],
    ],
    'stage_forbidden_fragments' => [
        'Criar Post',
        'Ofertas',
        'Sistema',
        'Operacional',
        'Central Tecnica',
        'Central Técnica',
        'Base de Conhecimento',
        'Health Check',
        'Testes',
        'Usuarios',
        'Usuários',
    ],
];
