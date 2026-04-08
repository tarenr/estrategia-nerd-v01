<?php

declare(strict_types=1);

namespace App\Services\Site;

use App\Repositories\LinkClickRepository;
use App\Repositories\LinkRepository;

final class LinkRedirectService
{
    public function __construct(
        private LinkRepository $links,
        private LinkClickRepository $clicks,
    ) {
    }

    public function resolve(string $slug, array $query, array $server): ?array
    {
        $link = $this->links->findPublicBySlug($slug);
        if ($link === null) {
            return null;
        }

        $targetUrl = trim((string) ($link['url'] ?? ''));
        if ($targetUrl === '') {
            return null;
        }

        if (str_starts_with($targetUrl, '/')) {
            $targetUrl = url($targetUrl);
        }

        $userAgent = trim((string) ($server['HTTP_USER_AGENT'] ?? ''));
        if ($this->isLikelyBot($userAgent)) {
            return ['ok' => true, 'target_url' => $targetUrl];
        }

        $origin = $this->sanitizeOrigin((string) ($query['origem'] ?? ''));
        $referer = trim((string) ($server['HTTP_REFERER'] ?? ''));
        $sessionId = session_id();
        if ($sessionId === '') {
            @session_start();
            $sessionId = session_id();
        }
        $sessionHash = $sessionId !== '' ? hash('sha256', $sessionId) : hash('sha256', ($server['REMOTE_ADDR'] ?? '') . '|' . $userAgent);

        $this->clicks->registerClick(
            (int) ($link['id'] ?? 0),
            $origin,
            $sessionHash,
            $referer,
            $userAgent
        );

        return ['ok' => true, 'target_url' => $targetUrl, 'link' => $link];
    }

    private function sanitizeOrigin(string $value): string
    {
        $value = trim(mb_strtolower($value));
        if ($value === '') {
            return 'desconhecida';
        }

        $value = preg_replace('/[^a-z0-9:_-]+/i', '-', $value) ?? 'desconhecida';
        $value = trim($value, '-');

        return $value !== '' ? mb_substr($value, 0, 60) : 'desconhecida';
    }

    private function isLikelyBot(string $userAgent): bool
    {
        $userAgent = mb_strtolower(trim($userAgent));
        if ($userAgent === '') {
            return false;
        }

        foreach (['bot', 'spider', 'crawl', 'slurp', 'preview', 'headless'] as $needle) {
            if (str_contains($userAgent, $needle)) {
                return true;
            }
        }

        return false;
    }
}