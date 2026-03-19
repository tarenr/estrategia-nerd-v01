<?php
/**
 * -----------------------------------------------------------------------------
 * @file        app/Services/Admin/DashboardService.php
 * @project     Estrategia Nerd
 * @author      Taren Felipe Ribeiro
 * @version     1.0.1
 * @purpose     Orquestrar e calcular métricas do Dashboard Admin.
 * @description Consolida dados vindos dos repositórios (SQL) e calcula KPIs,
 *              deltas, comparativos e blocos do dashboard no padrão do repo.
 * @usage       (new DashboardService(...repos))->getDashboardData($days)
 * @notes       - Nunca “inventa” variação percentual quando não há base (prev=0).
 *              - Quando prev<=0, delta_pct retorna null e delta_has_base=false.
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace App\Services\Admin;

use App\Repositories\PostRepository;
use App\Repositories\EstatisticaRepository;
use App\Repositories\NewsletterRepository;
use App\Repositories\ComentarioRepository;
use App\Repositories\CategoriaPostRepository;

final class DashboardService
{
    public function __construct(
        private readonly PostRepository $posts,
        private readonly EstatisticaRepository $estatisticas,
        private readonly NewsletterRepository $newsletter,
        private readonly ComentarioRepository $comentarios,
        private readonly CategoriaPostRepository $categorias,
    ) {
    }

    /**
     * Retorna todos os dados necessários para render do dashboard e para o endpoint JSON.
     */
    public function getDashboardData(int $days = 30): array
    {
        $days = $days > 0 ? $days : 30;

        // Período atual
        $current = $this->collectPeriod($days);

        // Período anterior (mesma duração)
        $previous = $this->collectPreviousPeriod($days);

        // KPIs principais (com deltas seguros)
        $kpis = [
            'views' => $this->buildKpi(
                (float) ($current['totals']['views'] ?? 0),
                (float) ($previous['totals']['views'] ?? 0),
            ),
            'posts' => $this->buildKpi(
                (float) ($current['totals']['posts'] ?? 0),
                (float) ($previous['totals']['posts'] ?? 0),
            ),
            'subscriptions' => $this->buildKpi(
                (float) ($current['totals']['subscriptions'] ?? 0),
                (float) ($previous['totals']['subscriptions'] ?? 0),
            ),
        ];

        return [
            'meta' => [
                'days' => $days,
                'generated_at' => date('c'),
            ],
            'kpis' => $kpis,

            // Mantém o resto do payload como já era no projeto (top posts, hoje, atividade, gráfico, etc.)
            // Se você já tem esses blocos no seu projeto, substitua pelos seus métodos atuais.
            'today' => $current['today'] ?? [],
            'highlights' => $current['highlights'] ?? [],
            'activity' => $current['activity'] ?? [],
            'chart' => $current['chart'] ?? [],
        ];
    }

    /**
     * Monta uma estrutura de KPI com delta absoluto e percentual (quando há base).
     */
    private function buildKpi(float $current, float $previous): array
    {
        $delta = $this->delta($current, $previous);

        return [
            'current' => $current,
            'previous' => $previous,
            'delta_abs' => $delta['delta_abs'],
            'delta_pct' => $delta['delta_pct'],               // null quando não há base
            'delta_has_base' => $delta['delta_has_base'],     // false quando prev<=0
        ];
    }

    /**
     * Calcula delta absoluto e percentual de forma segura.
     *
     * Regra:
     * - prev <= 0  => delta_pct = null e delta_has_base=false (evita +100% fake / divisão por zero)
     * - prev > 0   => delta_pct calculado normalmente
     */
    private function delta(float $current, float $previous): array
    {
        $abs = $current - $previous;

        if ($previous <= 0.0) {
            return [
                'delta_abs' => $abs,
                'delta_pct' => null,
                'delta_has_base' => false,
            ];
        }

        return [
            'delta_abs' => $abs,
            'delta_pct' => ($abs / $previous) * 100.0,
            'delta_has_base' => true,
        ];
    }

    /**
     * Coleta dados do período atual (últimos $days).
     * Observação: este método assume que seus repositórios já existem e retornam números.
     * Ajuste os nomes dos métodos conforme seu projeto (mantendo o contrato final do array).
     */
    private function collectPeriod(int $days): array
    {
        // Totais do período
        $views = (int) $this->estatisticas->countViewsLastDays($days);
        $posts = (int) $this->posts->countPublishedLastDays($days);
        $subs  = (int) $this->newsletter->countSubscriptionsLastDays($days);

        // Hoje
        $today = [
            'posts' => (int) $this->posts->countPublishedToday(),
            'subscriptions' => (int) $this->newsletter->countSubscriptionsToday(),
            'comments' => (int) $this->comentarios->countCreatedToday(),
            'pending_comments' => (int) $this->comentarios->countPending(),
            'approval_rate' => (float) $this->comentarios->approvalRateToday(),
        ];

        // Blocos extras (placeholders compatíveis; troque pelos seus atuais, se já existirem)
        $highlights = [
            'top_posts' => $this->posts->topPostsByEngagementLastDays($days, 5),
            'popular_category' => $this->categorias->mostPopularLastDays($days),
        ];

        $activity = $this->estatisticas->activityTimelineLastDays($days);
        $chart = $this->estatisticas->viewsChartLastDays($days);

        return [
            'totals' => [
                'views' => $views,
                'posts' => $posts,
                'subscriptions' => $subs,
            ],
            'today' => $today,
            'highlights' => $highlights,
            'activity' => $activity,
            'chart' => $chart,
        ];
    }

    /**
     * Coleta dados do período anterior (days anteriores ao período atual).
     */
    private function collectPreviousPeriod(int $days): array
    {
        $views = (int) $this->estatisticas->countViewsPreviousWindow($days);
        $posts = (int) $this->posts->countPublishedPreviousWindow($days);
        $subs  = (int) $this->newsletter->countSubscriptionsPreviousWindow($days);

        return [
            'totals' => [
                'views' => $views,
                'posts' => $posts,
                'subscriptions' => $subs,
            ],
        ];
    }
}