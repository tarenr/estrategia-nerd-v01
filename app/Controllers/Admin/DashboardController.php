<?php
/**
 * -----------------------------------------------------------------------------
 * @file        app/Controllers/Admin/DashboardController.php
 * @project     Estrategia Nerd
 * @author      Taren Felipe Ribeiro
 * @version     1.0.0
 * @purpose     Controller do Dashboard Admin
 * @description Renderiza o dashboard e expoe endpoint JSON para atualizacao live.
 * @usage       GET /admin, GET /admin/api/dashboard
 * @notes       Range por data (start/end) com default 30 dias e clamp maximo 90 dias.
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Repositories\CategoriaPostRepository;
use App\Repositories\ComentarioRepository;
use App\Repositories\EstatisticaRepository;
use App\Repositories\LinkClickRepository;
use App\Repositories\LinkRepository;
use App\Repositories\NewsletterRepository;
use App\Repositories\PostRepository;
use App\Services\Admin\DashboardService;
use App\Support\TargetEnvironmentDatabase;
use App\Support\View;

final class DashboardController
{
    public function index(): void
    {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');

        $payload = $this->buildPayload();

        View::render('admin/dashboard', $payload);
    }

    public function data(): void
    {
        $payload = $this->buildPayload();

        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');

        echo json_encode(
            [
                'ok' => true,
                'data' => $payload,
                'generated_at' => date('c'),
            ],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        exit;
    }

    /**
     * @return array<string,mixed>
     */
    private function buildPayload(): array
    {
        $targetEnvironment = target_environment();
        $pdo = TargetEnvironmentDatabase::pdo($targetEnvironment);

        $posts = new PostRepository($pdo);
        $estatisticas = new EstatisticaRepository($pdo);
        $newsletter = new NewsletterRepository($pdo);
        $comentarios = new ComentarioRepository($pdo);
        $categorias = new CategoriaPostRepository($pdo);
        $links = new LinkRepository($pdo);
        $linkClicks = new LinkClickRepository($pdo);

        $service = new DashboardService(
            $pdo,
            $posts,
            $estatisticas,
            $newsletter,
            $comentarios,
            $categorias,
            $links,
            $linkClicks,
            $targetEnvironment,
        );

        [$start, $end, $days] = $this->resolveRangeFromRequest();

        $payload = $service->getDashboardData($start, $end);
        $payload['start'] = $payload['start'] ?? $start;
        $payload['end'] = $payload['end'] ?? $end;
        $payload['days'] = $payload['days'] ?? $days;
        $payload['target_environment'] = $payload['target_environment'] ?? $targetEnvironment;
        $payload['target_environment_label'] = $payload['target_environment_label'] ?? environment_label($targetEnvironment);
        $payload['is_remote_target'] = $payload['is_remote_target'] ?? ($targetEnvironment !== current_environment());

        return $payload;
    }

    /**
     * @return array{0:string,1:string,2:int}
     */
    private function resolveRangeFromRequest(): array
    {
        $today = date('Y-m-d');

        $startIn = $this->parseYmd($_GET['start'] ?? null);
        $endIn = $this->parseYmd($_GET['end'] ?? null);

        if ($startIn === null || $endIn === null) {
            $days = isset($_GET['days']) ? (int) $_GET['days'] : 30;
            $days = $this->clampInt($days, 1, 90);

            $end = $today;
            $start = date('Y-m-d', strtotime('-' . max(0, $days - 1) . ' days'));
            return [$start, $end, $days];
        }

        [$start, $end] = $this->orderRange($startIn, $endIn);
        [$start, $end] = $this->clampRange90($start, $end);

        $days = $this->diffDaysInclusive($start, $end);
        $days = $this->clampInt($days, 1, 90);

        return [$start, $end, $days];
    }

    private function parseYmd(?string $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';
        if ($value === '') {
            return null;
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            $date = new \DateTimeImmutable($value);
        } catch (\Throwable) {
            return null;
        }

        return $date->format('Y-m-d');
    }

    /**
     * @return array{0:string,1:string}
     */
    private function orderRange(string $start, string $end): array
    {
        try {
            $startDate = new \DateTimeImmutable($start);
            $endDate = new \DateTimeImmutable($end);
        } catch (\Throwable) {
            return [$start, $end];
        }

        if ($startDate > $endDate) {
            return [$endDate->format('Y-m-d'), $startDate->format('Y-m-d')];
        }

        return [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')];
    }

    /**
     * @return array{0:string,1:string}
     */
    private function clampRange90(string $start, string $end): array
    {
        try {
            $startDate = new \DateTimeImmutable($start);
            $endDate = new \DateTimeImmutable($end);
        } catch (\Throwable) {
            return [$start, $end];
        }

        if ($startDate > $endDate) {
            [$startDate, $endDate] = [$endDate, $startDate];
        }

        $days = (int) $startDate->diff($endDate)->days + 1;
        if ($days <= 90) {
            return [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')];
        }

        $startClamped = $endDate->modify('-89 days');
        return [$startClamped->format('Y-m-d'), $endDate->format('Y-m-d')];
    }

    private function diffDaysInclusive(string $start, string $end): int
    {
        try {
            $startDate = new \DateTimeImmutable($start);
            $endDate = new \DateTimeImmutable($end);
        } catch (\Throwable) {
            return 30;
        }

        if ($startDate > $endDate) {
            [$startDate, $endDate] = [$endDate, $startDate];
        }

        return (int) $startDate->diff($endDate)->days + 1;
    }

    private function clampInt(int $value, int $min, int $max): int
    {
        return max($min, min($max, $value));
    }
}
