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
 * @notes       GET /admin sem cache; default days=30 para mostrar histórico mais útil.
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
        // Evita o comportamento “só aparece depois do F5”
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');

        $data = $this->getDashboardPayloadFromRequest();
        $data['title'] = 'Dashboard — Admin';

        View::render('admin/dashboard', $data);
    }

    public function data(): void
    {
        $payload = $this->getDashboardPayloadFromRequest();

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
    private function getDashboardPayloadFromRequest(): array
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

        // ✅ Default do repo é mais “útil” com 30 dias (e evita gráfico vazio em 7d)
        $days = isset($_GET['days']) ? (int)$_GET['days'] : 30;

        return $service->getDashboardData($days);
    }
}