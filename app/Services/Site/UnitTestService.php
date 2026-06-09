<?php
/**
 * -----------------------------------------------------------------------------
 * Arquivo: app/Services/Site/UnitTestService.php
 * Projeto: Estrategia Nerd
 * Proposito: Executar testes unitarios leves de contratos puros do sistema.
 * Uso: Chamado por AutomatedTestService no nivel unit.
 * Observacoes: Nao executa HTTP, banco, FTP, Dropbox, deploy, restore ou backup.
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace App\Services\Site;

final class UnitTestService
{
    /**
     * @param array<string,mixed> $config
     */
    public function __construct(private array $config)
    {
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function run(): array
    {
        $operations = new OperationalTestService($this->config);
        $tests = [];

        $tests[] = $this->case('levels', 'Normaliza nivel safe', fn (): bool => $operations->normalizeLevel(' SAFE ') === OperationalTestService::LEVEL_SAFE);
        $tests[] = $this->case('levels', 'Normaliza nivel routine', fn (): bool => $operations->normalizeLevel('Routine') === OperationalTestService::LEVEL_ROUTINE);
        $tests[] = $this->case('levels', 'Normaliza nivel unit', fn (): bool => $operations->normalizeLevel('UNIT') === OperationalTestService::LEVEL_UNIT);
        $tests[] = $this->throws('levels', 'Bloqueia nivel invalido', fn (): string => $operations->normalizeLevel('production'));

        $tests[] = $this->case('environment', 'Normaliza ambiente local', fn (): bool => $operations->normalizeEnvironment(' LOCAL ') === OperationalTestService::ENV_LOCAL);
        $tests[] = $this->case('environment', 'Normaliza ambiente stage', fn (): bool => $operations->normalizeEnvironment('Stage') === OperationalTestService::ENV_STAGE);
        $tests[] = $this->throws('environment', 'Bloqueia producao na suite operacional', fn (): string => $operations->normalizeEnvironment('production'));

        $tests[] = $this->case('config', 'Resolve URL base local sem barra final', function () use ($operations): bool {
            return !str_ends_with($operations->baseUrl(OperationalTestService::ENV_LOCAL), '/');
        });
        $tests[] = $this->case('config', 'Credenciais admin detectadas', fn (): bool => is_bool($operations->hasAdminCredentials()));

        $tests[] = $this->case('html', 'Extrai CSRF com name antes de value', fn (): bool => $operations->csrfFromHtml('<input name="_csrf_token" value="abc123">') === 'abc123');
        $tests[] = $this->case('html', 'Extrai CSRF com value antes de name', fn (): bool => $operations->csrfFromHtml('<input value="xyz789" name="_csrf_token">') === 'xyz789');
        $tests[] = $this->case('html', 'Retorna CSRF vazio quando ausente', fn (): bool => $operations->csrfFromHtml('<form></form>') === '');

        $tests[] = $this->case('html', 'Fragmentos obrigatorios sao case-insensitive', fn (): bool => $operations->missingFragments('Dashboard Admin', ['dashboard', 'ADMIN']) === []);
        $tests[] = $this->case('html', 'Fragmentos ausentes sao listados', fn (): bool => $operations->missingFragments('Dashboard', ['Dashboard', 'Newsletter']) === ['Newsletter']);
        $tests[] = $this->case('html', 'Fragmento vazio e ignorado', fn (): bool => $operations->missingFragments('Dashboard', ['']) === []);

        $tests[] = $this->case('errors', 'Detecta fatal error', fn (): bool => $operations->firstErrorSignature('<b>Fatal error</b>: erro') === 'Fatal error');
        $tests[] = $this->case('errors', 'Detecta warning PHP', fn (): bool => $operations->firstErrorSignature('<b>Warning</b>: aviso') === 'PHP Warning');
        $tests[] = $this->case('errors', 'HTML limpo nao gera assinatura', fn (): bool => $operations->firstErrorSignature('<main>OK</main>') === '');

        $tests[] = $this->case('url', 'Mantem URL absoluta', fn (): bool => $operations->absoluteUrl('https://example.com/a.css', 'http://localhost/base') === 'https://example.com/a.css');
        $tests[] = $this->case('url', 'Resolve URL protocol-relative', fn (): bool => $operations->absoluteUrl('//cdn.example.com/a.css', 'http://localhost/base') === 'https://cdn.example.com/a.css');
        $tests[] = $this->case('url', 'Resolve URL relativa', fn (): bool => $operations->absoluteUrl('/assets/admin.css', 'http://localhost/base/') === 'http://localhost/base/assets/admin.css');

        $assets = $operations->assetUrlsFromHtml('<link href="/a.css"><script src="/b.js"></script><img src="/a.css">', 'http://localhost/site');
        $tests[] = $this->case('assets', 'Extrai assets unicos do HTML', fn (): bool => $assets === ['http://localhost/site/a.css', 'http://localhost/site/b.js']);
        $tests[] = $this->case('assets', 'Extrai primeiro asset do HTML', fn (): bool => $operations->firstAssetUrl('<link href="/a.css"><script src="/b.js"></script>', 'http://localhost/site') === 'http://localhost/site/a.css');

        $block = $operations->securityBlock('deploy', OperationalTestService::RULE_DEPLOY_DISABLED, 'Bloqueado em teste.', OperationalTestService::ENV_LOCAL, OperationalTestService::LEVEL_UNIT);
        $tests[] = $this->case('security', 'Bloqueio possui contrato auditavel', fn (): bool => ($block['status'] ?? '') === OperationalTestService::STATUS_BLOCKED
            && ($block['rule'] ?? '') === OperationalTestService::RULE_DEPLOY_DISABLED
            && ($block['environment'] ?? '') === OperationalTestService::ENV_LOCAL
            && ($block['level'] ?? '') === OperationalTestService::LEVEL_UNIT
            && trim((string) ($block['timestamp'] ?? '')) !== '');

        $skipped = $operations->skipped('unit', 'Caso ignorado', 'Motivo');
        $tests[] = $this->case('result', 'Resultado skip padronizado', fn (): bool => ($skipped['status'] ?? '') === OperationalTestService::STATUS_SKIP && ($skipped['message'] ?? '') === 'Motivo');

        $failed = $operations->failed('unit', 'Caso falhou', 'Mensagem', 500, 12, '/teste');
        $tests[] = $this->case('result', 'Resultado fail padronizado', fn (): bool => ($failed['status'] ?? '') === OperationalTestService::STATUS_FAIL
            && ($failed['http_status'] ?? null) === 500
            && ($failed['duration_ms'] ?? null) === 12
            && ($failed['url'] ?? '') === '/teste');

        return $tests;
    }

    /**
     * @return array<string,mixed>
     */
    private function case(string $group, string $name, callable $assertion): array
    {
        $started = microtime(true);

        try {
            $passed = (bool) $assertion();
            return $this->result($group, $name, $passed, $passed ? 'Contrato validado.' : 'Contrato retornou valor inesperado.', $started);
        } catch (\Throwable $exception) {
            return $this->result($group, $name, false, $exception->getMessage(), $started);
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function throws(string $group, string $name, callable $callback): array
    {
        $started = microtime(true);

        try {
            $callback();
            return $this->result($group, $name, false, 'Excecao esperada nao foi lancada.', $started);
        } catch (\Throwable) {
            return $this->result($group, $name, true, 'Excecao esperada lancada.', $started);
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function result(string $group, string $name, bool $passed, string $message, float $started): array
    {
        return [
            'group' => $group,
            'name' => $name,
            'status' => $passed ? OperationalTestService::STATUS_OK : OperationalTestService::STATUS_FAIL,
            'http_status' => null,
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            'url' => '',
            'message' => $message,
        ];
    }
}
