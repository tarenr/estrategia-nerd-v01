<?php
/**
 * -----------------------------------------------------------------------------
 * @file        app/Controllers/Admin/DashboardController.php
 * @project     Estrategia Nerd
 * @author      Taren Felipe Ribeiro
 * @version     1.0.0
 * @purpose     Controller do Dashboard Admin
 * @description Renderiza o dashboard e expõe endpoint JSON para atualização “live”.
 * @usage       GET /admin, GET /admin/api/dashboard
 * @notes       Range por data (start/end) com default 30 dias e clamp máximo 90 dias.
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Repositories\CategoriaPostRepository;
use App\Repositories\ComentarioRepository;
use App\Repositories\EstatisticaRepository;
use App\Repositories\NewsletterRepository;
use App\Repositories\PostRepository;
use App\Services\Admin\DashboardService;
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
        /** @var \PDO $pdo */
        $pdo = $GLOBALS['pdo'];

        $posts = new PostRepository($pdo);
        $estatisticas = new EstatisticaRepository($pdo);
        $newsletter = new NewsletterRepository($pdo);
        $comentarios = new ComentarioRepository($pdo);
        $categorias = new CategoriaPostRepository($pdo);

        $service = new DashboardService(
            $posts,
            $estatisticas,
            $newsletter,
            $comentarios,
            $categorias
        );

        // Resolve start/end (default 30) e clamp 90
        [$start, $end, $days] = $this->resolveRangeFromRequest();

        /**
         * Fonte da verdade do range e KPIs é o service (ele normaliza e clampa 90 dias).
         */
        $payload = $service->getDashboardData($start, $end);

        // Garante campos mínimos para a view/JS
        $payload['start'] = $payload['start'] ?? $start;
        $payload['end'] = $payload['end'] ?? $end;
        $payload['days'] = $payload['days'] ?? $days;

        return $payload;
    }

    /**
     * @return array{0:string,1:string,2:int} start,end,days
     */
    private function resolveRangeFromRequest(): array
    {
        $today = date('Y-m-d');

        $startIn = $this->parseYmd($_GET['start'] ?? null);
        $endIn = $this->parseYmd($_GET['end'] ?? null);

        // fallback: days (default 30)
        if ($startIn === null || $endIn === null) {
            $days = isset($_GET['days']) ? (int)$_GET['days'] : 30;
            $days = $this->clampInt($days, 1, 90);

            $end = $today;
            $start = date('Y-m-d', strtotime('-' . max(0, $days - 1) . ' days'));
            return [$start, $end, $days];
        }

        // ordena e clampa 90 dias
        [$start, $end] = $this->orderRange($startIn, $endIn);
        [$start, $end] = $this->clampRange90($start, $end);

        $days = $this->diffDaysInclusive($start, $end);
        $days = $this->clampInt($days, 1, 90);

        return [$start, $end, $days];
    }

    private function parseYmd(?string $s): ?string
    {
        $v = is_string($s) ? trim($s) : '';
        if ($v === '') return null;

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) return null;

        try {
            $dt = new \DateTimeImmutable($v);
        } catch (\Throwable) {
            return null;
        }

        return $dt->format('Y-m-d');
    }

    /**
     * @return array{0:string,1:string}
     */
    private function orderRange(string $start, string $end): array
    {
        try {
            $ds = new \DateTimeImmutable($start);
            $de = new \DateTimeImmutable($end);
        } catch (\Throwable) {
            return [$start, $end];
        }

        if ($ds > $de) {
            return [$de->format('Y-m-d'), $ds->format('Y-m-d')];
        }
        return [$ds->format('Y-m-d'), $de->format('Y-m-d')];
    }

    /**
     * Clamp máximo 90 dias inclusivo (end - 89d).
     * @return array{0:string,1:string}
     */
    private function clampRange90(string $start, string $end): array
    {
        try {
            $ds = new \DateTimeImmutable($start);
            $de = new \DateTimeImmutable($end);
        } catch (\Throwable) {
            return [$start, $end];
        }

        if ($ds > $de) [$ds, $de] = [$de, $ds];

        $days = (int)$ds->diff($de)->days + 1;
        if ($days <= 90) return [$ds->format('Y-m-d'), $de->format('Y-m-d')];

        $ds2 = $de->modify('-89 days');
        return [$ds2->format('Y-m-d'), $de->format('Y-m-d')];
    }

    private function diffDaysInclusive(string $start, string $end): int
    {
        try {
            $ds = new \DateTimeImmutable($start);
            $de = new \DateTimeImmutable($end);
        } catch (\Throwable) {
            return 30;
        }

        if ($ds > $de) [$ds, $de] = [$de, $ds];
        return (int)$ds->diff($de)->days + 1;
    }

    private function clampInt(int $v, int $min, int $max): int
    {
        return max($min, min($max, $v));
    }
}