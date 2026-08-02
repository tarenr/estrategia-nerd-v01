<?php

declare(strict_types=1);

namespace App\Services\Site;

final class HostingerApiService
{
    /** @var array<string, mixed> */
    private array $config;

    /** @var array<string, array<string, mixed>> */
    private array $cache = [];

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboard(): array
    {
        $domain = $this->domain();

        if ($this->token() === '') {
            return [
                'domain' => $domain,
                'connection' => [
                    'ok' => false,
                    'message' => 'Configure HOSTINGER_API_TOKEN no .env local para ativar a leitura.',
                    'errors' => [],
                ],
                'cards' => [[
                    'label' => 'Dominio monitorado',
                    'value' => $domain,
                    'hint' => 'Aguardando token',
                    'icon' => 'fa-solid fa-globe',
                    'tone' => 'warning',
                ]],
                'domain_details' => [],
                'dns_groups' => [],
                'hosting' => [],
                'databases' => [],
                'subscriptions' => [],
                'snapshots' => [],
                'forwarding' => [],
                'alerts' => [[
                    'label' => 'Token ausente',
                    'text' => 'A tela esta pronta, mas a API nao sera consultada sem HOSTINGER_API_TOKEN.',
                    'tone' => 'warning',
                ]],
            ];
        }

        $domainDetails = $this->safeGet('/api/domains/v1/portfolio/' . rawurlencode($domain));
        $dnsZone = $this->safeGet('/api/dns/v1/zones/' . rawurlencode($domain));
        $websites = $this->safeGet('/api/hosting/v1/websites');
        $orders = $this->safeGet('/api/hosting/v1/orders');
        $subscriptions = $this->safeGet('/api/billing/v1/subscriptions');
        $snapshots = $this->safeGet('/api/dns/v1/snapshots/' . rawurlencode($domain));
        $forwarding = $this->safeGet('/api/domains/v1/forwarding/' . rawurlencode($domain));

        $hostingRaw = $this->findHosting($websites, $domain);
        $databasesRaw = [];
        if (($hostingRaw['username'] ?? '') !== '') {
            $databasesRaw = $this->safeGet('/api/hosting/v1/accounts/' . rawurlencode((string) $hostingRaw['username']) . '/databases');
        }

        $normalizedSubscriptions = $this->normalizeSubscriptions($subscriptions);
        $normalizedDomain = $this->normalizeDomainDetails($domainDetails);
        $dnsGroups = $this->normalizeDns($dnsZone);
        $hosting = $this->normalizeHosting($hostingRaw, $orders, $normalizedSubscriptions);
        $databases = $this->normalizeDatabases($databasesRaw, $domain);
        $normalizedForwarding = $this->normalizeForwarding($forwarding);

        return [
            'domain' => $domain,
            'connection' => [
                'ok' => $this->hasAnySuccessfulCall(),
                'message' => $this->hasAnySuccessfulCall()
                    ? 'API consultada em modo somente leitura.'
                    : 'Nao foi possivel consultar a API da Hostinger.',
                'errors' => $this->errors(),
            ],
            'cards' => $this->cards($normalizedDomain, $dnsGroups, $hosting, $databases),
            'domain_details' => $normalizedDomain,
            'dns_groups' => $dnsGroups,
            'hosting' => $hosting,
            'databases' => $databases,
            'subscriptions' => $normalizedSubscriptions,
            'snapshots' => $this->normalizeSnapshots($snapshots),
            'forwarding' => $normalizedForwarding,
            'alerts' => $this->buildAlerts($normalizedDomain, $dnsGroups, $hosting, $normalizedSubscriptions, $normalizedForwarding),
        ];
    }

    private function domain(): string
    {
        $domain = strtolower(trim((string) ($this->config['domain'] ?? 'estrategianerd.com.br')));

        return $domain !== '' ? $domain : 'estrategianerd.com.br';
    }

    private function token(): string
    {
        return trim((string) ($this->config['token'] ?? ''));
    }

    private function baseUrl(): string
    {
        $baseUrl = rtrim((string) ($this->config['base_url'] ?? 'https://developers.hostinger.com'), '/');

        return $baseUrl !== '' ? $baseUrl : 'https://developers.hostinger.com';
    }

    /**
     * @return mixed
     */
    private function safeGet(string $path): mixed
    {
        try {
            $data = $this->get($path);
            $this->cache[$path] = ['ok' => true, 'error' => null];

            return $data;
        } catch (\Throwable $exception) {
            $this->cache[$path] = ['ok' => false, 'error' => $exception->getMessage()];

            return null;
        }
    }

    /**
     * @return mixed
     */
    private function get(string $path): mixed
    {
        if (!function_exists('curl_init')) {
            throw new \RuntimeException('Extensao cURL indisponivel no PHP.');
        }

        $handle = curl_init($this->baseUrl() . $path);
        if ($handle === false) {
            throw new \RuntimeException('Nao foi possivel inicializar cURL.');
        }

        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => max(3, (int) ($this->config['timeout'] ?? 12)),
            CURLOPT_PROXY => '',
            CURLOPT_NOPROXY => '*',
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->token(),
                'Content-Type: application/json',
                'Accept: application/json',
            ],
        ]);

        $body = curl_exec($handle);
        $curlError = curl_error($handle);
        $statusCode = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);

        if ($body === false) {
            throw new \RuntimeException($curlError !== '' ? $curlError : 'Falha desconhecida na requisicao.');
        }

        $decoded = json_decode((string) $body, true);
        if ($statusCode >= 400) {
            $message = is_array($decoded) && isset($decoded['error'])
                ? (string) $decoded['error']
                : ('HTTP ' . $statusCode);
            throw new \RuntimeException($message);
        }

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Resposta da Hostinger nao esta em JSON valido.');
        }

        return $decoded;
    }

    private function hasAnySuccessfulCall(): bool
    {
        foreach ($this->cache as $entry) {
            if (($entry['ok'] ?? false) === true) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function errors(): array
    {
        $errors = [];
        foreach ($this->cache as $path => $entry) {
            if (($entry['ok'] ?? false) !== true) {
                $errors[] = [
                    'path' => $path,
                    'error' => (string) ($entry['error'] ?? 'Erro desconhecido'),
                ];
            }
        }

        return $errors;
    }

    /**
     * @param mixed $websites
     * @return array<string, mixed>
     */
    private function findHosting(mixed $websites, string $domain): array
    {
        $items = is_array($websites) && is_array($websites['data'] ?? null) ? $websites['data'] : [];
        foreach ($items as $item) {
            if (is_array($item) && strtolower((string) ($item['domain'] ?? '')) === $domain) {
                return $item;
            }
        }

        return [];
    }

    /**
     * @param mixed $domainDetails
     * @return array<string, string>
     */
    private function normalizeDomainDetails(mixed $domainDetails): array
    {
        if (!is_array($domainDetails)) {
            return [];
        }

        $nameServers = is_array($domainDetails['name_servers'] ?? null) ? $domainDetails['name_servers'] : [];

        return [
            'domain' => (string) ($domainDetails['domain'] ?? $this->domain()),
            'status' => (string) ($domainDetails['status'] ?? '-'),
            'created_at' => $this->formatDate($domainDetails['created_at'] ?? null),
            'registered_at' => $this->formatDate($domainDetails['registered_at'] ?? null),
            'updated_at' => $this->formatDate($domainDetails['updated_at'] ?? null),
            'expires_at' => $this->formatDate($domainDetails['expires_at'] ?? null),
            'lock_expires_at' => $this->formatDate($domainDetails['60_days_lock_expires_at'] ?? null),
            'privacy' => (($domainDetails['is_privacy_protected'] ?? false) === true) ? 'Ativa' : 'Inativa',
            'domain_lock' => (($domainDetails['is_locked'] ?? false) === true) ? 'Ativo' : 'Inativo',
            'nameservers' => implode(', ', array_filter(array_map('strval', array_values($nameServers)))),
        ];
    }

    /**
     * @param mixed $dnsZone
     * @return array<string, array<int, array<string, string>>>
     */
    private function normalizeDns(mixed $dnsZone): array
    {
        $groups = [];
        $items = is_array($dnsZone) ? $dnsZone : [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $type = strtoupper((string) ($item['type'] ?? 'OUTRO'));
            $records = is_array($item['records'] ?? null) ? $item['records'] : [];
            $content = [];
            foreach ($records as $record) {
                if (is_array($record)) {
                    $content[] = (string) ($record['content'] ?? '');
                }
            }

            $groups[$type][] = [
                'name' => (string) ($item['name'] ?? '-'),
                'ttl' => (string) ($item['ttl'] ?? '-'),
                'content' => implode("\n", array_filter($content)),
            ];
        }

        ksort($groups);

        return $groups;
    }

    /**
     * @param array<string, mixed> $hosting
     * @param mixed $orders
     * @param array<int, array<string, string>> $subscriptions
     * @return array<string, string>
     */
    private function normalizeHosting(array $hosting, mixed $orders, array $subscriptions): array
    {
        if ($hosting === []) {
            return [];
        }

        $order = $this->findOrder($orders, (string) ($hosting['order_id'] ?? ''));
        $subscription = $this->findSubscription($subscriptions, (string) ($order['subscription_id'] ?? ''));

        return [
            'domain' => (string) ($hosting['domain'] ?? '-'),
            'enabled' => (($hosting['is_enabled'] ?? false) === true) ? 'Ativo' : 'Inativo',
            'vhost_type' => (string) ($hosting['vhost_type'] ?? '-'),
            'username' => (string) ($hosting['username'] ?? '-'),
            'order_id' => (string) ($hosting['order_id'] ?? '-'),
            'plan' => (string) ($order['plan_name'] ?? '-'),
            'subscription' => (string) ($subscription['name'] ?? '-'),
            'root_directory' => (string) ($hosting['root_directory'] ?? '-'),
            'created_at' => $this->formatDate($hosting['created_at'] ?? null),
        ];
    }

    /**
     * @param mixed $orders
     * @return array<string, string>
     */
    private function findOrder(mixed $orders, string $orderId): array
    {
        $items = is_array($orders) && is_array($orders['data'] ?? null) ? $orders['data'] : [];
        foreach ($items as $item) {
            if (!is_array($item) || (string) ($item['id'] ?? '') !== $orderId) {
                continue;
            }

            $plan = is_array($item['plan'] ?? null) ? $item['plan'] : [];

            return [
                'subscription_id' => (string) ($item['subscription_id'] ?? ''),
                'plan_name' => (string) ($plan['name'] ?? ''),
            ];
        }

        return [];
    }

    /**
     * @param array<int, array<string, string>> $subscriptions
     * @return array<string, string>
     */
    private function findSubscription(array $subscriptions, string $subscriptionId): array
    {
        foreach ($subscriptions as $subscription) {
            if ((string) ($subscription['id'] ?? '') === $subscriptionId) {
                return $subscription;
            }
        }

        return [];
    }

    /**
     * @param mixed $databases
     * @return array<int, array<string, string>>
     */
    private function normalizeDatabases(mixed $databases, string $domain): array
    {
        $items = is_array($databases) && is_array($databases['data'] ?? null) ? $databases['data'] : [];
        $normalized = [];

        foreach ($items as $item) {
            if (!is_array($item) || strtolower((string) ($item['domain'] ?? '')) !== $domain) {
                continue;
            }

            $normalized[] = [
                'name' => (string) ($item['name'] ?? '-'),
                'user' => (string) ($item['user'] ?? '-'),
                'disk_usage' => (string) ($item['disk_usage_mb'] ?? '0') . ' MB',
                'max_size' => (string) ($item['max_size_mb'] ?? '0') . ' MB',
                'updated_at' => $this->formatDate($item['updated_at'] ?? null),
            ];
        }

        return $normalized;
    }

    /**
     * @param mixed $subscriptions
     * @return array<int, array<string, string>>
     */
    private function normalizeSubscriptions(mixed $subscriptions): array
    {
        $items = is_array($subscriptions) ? $subscriptions : [];
        $normalized = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $normalized[] = [
                'id' => (string) ($item['id'] ?? ''),
                'name' => (string) ($item['name'] ?? '-'),
                'status' => (string) ($item['status'] ?? '-'),
                'expires_at' => $this->formatDate($item['expires_at'] ?? null),
                'renewal_price' => $this->formatMoney($item['renewal_price'] ?? null, (string) ($item['currency_code'] ?? 'BRL')),
                'auto_renewed' => (($item['is_auto_renewed'] ?? false) === true) ? 'Ativa' : 'Inativa',
            ];
        }

        return $normalized;
    }

    /**
     * @param mixed $snapshots
     * @return array<int, array<string, string>>
     */
    private function normalizeSnapshots(mixed $snapshots): array
    {
        $items = is_array($snapshots) ? $snapshots : [];
        $normalized = [];

        foreach (array_slice($items, 0, 8) as $item) {
            if (!is_array($item)) {
                continue;
            }

            $normalized[] = [
                'id' => (string) ($item['id'] ?? '-'),
                'reason' => (string) ($item['reason'] ?? '-'),
                'created_at' => $this->formatDate($item['created_at'] ?? null),
            ];
        }

        return $normalized;
    }

    /**
     * @param mixed $forwarding
     * @return array<string, string>
     */
    private function normalizeForwarding(mixed $forwarding): array
    {
        if (!is_array($forwarding)) {
            return [];
        }

        return [
            'redirect_type' => (string) ($forwarding['redirect_type'] ?? ''),
            'redirect_url' => (string) ($forwarding['redirect_url'] ?? ''),
        ];
    }

    /**
     * @param array<string, string> $domainDetails
     * @param array<string, array<int, array<string, string>>> $dnsGroups
     * @param array<string, string> $hosting
     * @param array<int, array<string, string>> $databases
     * @return array<int, array<string, string>>
     */
    private function cards(array $domainDetails, array $dnsGroups, array $hosting, array $databases): array
    {
        return [
            [
                'label' => 'Dominio monitorado',
                'value' => (string) ($domainDetails['domain'] ?? $this->domain()),
                'hint' => (string) ($domainDetails['status'] ?? 'Sem leitura'),
                'icon' => 'fa-solid fa-globe',
                'tone' => ($domainDetails['status'] ?? '') === 'Active' ? 'success' : 'warning',
            ],
            [
                'label' => 'Expiracao',
                'value' => (string) ($domainDetails['expires_at'] ?? '-'),
                'hint' => 'Data informada pela Hostinger',
                'icon' => 'fa-solid fa-calendar-days',
                'tone' => 'info',
            ],
            [
                'label' => 'DNS',
                'value' => (string) array_sum(array_map('count', $dnsGroups)),
                'hint' => 'Registros ativos na zona',
                'icon' => 'fa-solid fa-network-wired',
                'tone' => $dnsGroups === [] ? 'warning' : 'success',
            ],
            [
                'label' => 'Hospedagem',
                'value' => (string) ($hosting['enabled'] ?? '-'),
                'hint' => (string) ($hosting['plan'] ?? 'Plano nao localizado'),
                'icon' => 'fa-solid fa-server',
                'tone' => ($hosting['enabled'] ?? '') === 'Ativo' ? 'success' : 'warning',
            ],
            [
                'label' => 'Bancos',
                'value' => (string) count($databases),
                'hint' => 'Vinculados ao dominio',
                'icon' => 'fa-solid fa-database',
                'tone' => $databases === [] ? 'warning' : 'info',
            ],
        ];
    }

    /**
     * @param array<string, string> $domainDetails
     * @param array<string, array<int, array<string, string>>> $dnsGroups
     * @param array<string, string> $hosting
     * @param array<int, array<string, string>> $subscriptions
     * @param array<string, string> $forwarding
     * @return array<int, array<string, string>>
     */
    private function buildAlerts(array $domainDetails, array $dnsGroups, array $hosting, array $subscriptions, array $forwarding): array
    {
        $alerts = [];

        if (($domainDetails['domain_lock'] ?? '') === 'Inativo') {
            $alerts[] = [
                'label' => 'Domain lock inativo',
                'text' => 'A API informa que o lock do dominio nao esta ativo ou nao esta disponivel.',
                'tone' => 'warning',
            ];
        }

        $autoRenewalAlert = $this->autoRenewalAlert($subscriptions);
        if ($autoRenewalAlert !== []) {
            $alerts[] = $autoRenewalAlert;
        }

        foreach (($dnsGroups['TXT'] ?? []) as $record) {
            if (($record['name'] ?? '') === '_dmarc' && str_contains(strtolower((string) ($record['content'] ?? '')), 'p=none')) {
                $alerts[] = [
                    'label' => 'DMARC em monitoramento',
                    'text' => 'O registro DMARC esta com p=none. Ele monitora, mas ainda nao aplica rejeicao.',
                    'tone' => 'info',
                ];
                break;
            }
        }

        if ($hosting === []) {
            $alerts[] = [
                'label' => 'Hospedagem nao localizada',
                'text' => 'Nao encontrei website ativo para o dominio monitorado na listagem de hosting.',
                'tone' => 'warning',
            ];
        }

        if (($forwarding['redirect_url'] ?? '') !== '') {
            $alerts[] = [
                'label' => 'Forwarding ativo',
                'text' => 'Existe redirecionamento configurado para ' . (string) $forwarding['redirect_url'] . '.',
                'tone' => 'info',
            ];
        }

        return $alerts !== [] ? $alerts : [[
            'label' => 'Sem alertas criticos',
            'text' => 'A leitura basica nao encontrou alertas operacionais importantes.',
            'tone' => 'success',
        ]];
    }

    /**
     * @param array<int, array<string, string>> $subscriptions
     * @return array<string, string>
     */
    private function autoRenewalAlert(array $subscriptions): array
    {
        $inactive = [];

        foreach ($subscriptions as $subscription) {
            if (($subscription['auto_renewed'] ?? '') !== 'Inativa') {
                continue;
            }

            $name = trim((string) ($subscription['name'] ?? 'Assinatura'));
            $name = $name !== '' ? $name : 'Assinatura';
            $inactive[$name] = ($inactive[$name] ?? 0) + 1;
        }

        if ($inactive === []) {
            return [];
        }

        ksort($inactive);

        $items = [];
        foreach ($inactive as $name => $count) {
            $items[] = $count > 1 ? ($count . 'x ' . $name) : $name;
        }

        $total = array_sum($inactive);

        return [
            'label' => 'Auto-renovacao informativa',
            'text' => 'Renovacao automatica inativa em ' . $total . ' ' . ($total === 1 ? 'assinatura' : 'assinaturas') . ': ' . implode('; ', $items) . '. Use como controle financeiro, nao como alerta critico.',
            'tone' => 'info',
        ];
    }

    private function formatDate(mixed $value): string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return '-';
        }

        try {
            $date = new \DateTimeImmutable($raw);
            $date = $date->setTimezone(new \DateTimeZone('America/Sao_Paulo'));

            return $date->format('d/m/Y H:i');
        } catch (\Throwable) {
            return $raw;
        }
    }

    private function formatMoney(mixed $value, string $currency): string
    {
        if (!is_numeric($value)) {
            return '-';
        }

        return trim($currency . ' ' . number_format(((int) $value) / 100, 2, ',', '.'));
    }
}
