<?php
/**
 * -----------------------------------------------------------------------------
 * @file        app/Controllers/Api/NerdOpsStatsController.php
 * @project     Estrategia Nerd
 * @purpose     Expor metricas de conteudo (somente leitura) para o NerdOPS
 * @description Reaproveita o DashboardService (ambiente fixo em producao),
 *              retornando um subconjunto dos dados (visitas, links mais
 *              clicados, posts). Protegido por token compartilhado (header),
 *              fora do middleware de sessao 'auth' - nao guarda login/senha.
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Repositories\CategoriaPostRepository;
use App\Repositories\ComentarioRepository;
use App\Repositories\EstatisticaRepository;
use App\Repositories\LinkClickRepository;
use App\Repositories\LinkRepository;
use App\Repositories\NewsletterRepository;
use App\Repositories\PostRepository;
use App\Services\Admin\DashboardService;
use App\Support\TargetEnvironmentDatabase;
use Throwable;

final class NerdOpsStatsController
{
    public function contentStats(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

        if (!$this->tokenValido()) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'erro' => 'nao autorizado'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        try {
            $pdo = TargetEnvironmentDatabase::pdo('production');

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
                'production',
            );

            $data = $service->getDashboardData(30);

            echo json_encode([
                'ok' => true,
                'views_hoje' => $data['views_hoje'],
                'views_semana' => $data['views_semana'],
                'total_views' => $data['total_views'],
                'top_links_clicks' => $data['top_links_clicks'],
                'top_posts_views' => $posts->topPostsByViews(5),
                'generated_at' => date('c'),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (Throwable $exception) {
            error_log('NerdOpsStatsController: falha ao consultar producao: ' . $exception->getMessage());
            http_response_code(503);
            echo json_encode(['ok' => false, 'erro' => 'banco indisponivel'], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }

    private function tokenValido(): bool
    {
        $esperado = (string) env('NERDOPS_STATS_TOKEN', '');
        $recebido = (string) ($_SERVER['HTTP_X_NERDOPS_TOKEN'] ?? '');

        if ($esperado === '' || $recebido === '') {
            return false;
        }

        return hash_equals($esperado, $recebido);
    }
}
