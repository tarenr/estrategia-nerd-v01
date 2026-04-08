<?php
/**
 * -----------------------------------------------------------------------------
 * @file        app/Services/Admin/DashboardService.php
 * @project     Estrategia Nerd
 * @author      Taren Felipe Ribeiro
 * @version     1.0.0
 * @purpose     Service do Dashboard Admin
 * @description Orquestra metricas do dashboard e calcula indicadores, serie e comparativos.
 * @usage       getDashboardData(30) OU getDashboardData('YYYY-MM-DD','YYYY-MM-DD')
 * @notes       Range por data (start/end) com default 30 dias e clamp maximo 90 dias.
 *              Delta_abs e sempre real; delta_percent so existe se previous > 0.
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace App\Services\Admin;

use App\Repositories\CategoriaPostRepository;
use App\Repositories\ComentarioRepository;
use App\Repositories\EstatisticaRepository;
use App\Repositories\LinkClickRepository;
use App\Repositories\LinkRepository;
use App\Repositories\NewsletterRepository;
use App\Repositories\PostRepository;
use DateTimeImmutable;
use PDO;
use Throwable;

final class DashboardService
{
    public function __construct(
        private PostRepository $posts,
        private EstatisticaRepository $estatisticas,
        private NewsletterRepository $newsletter,
        private ComentarioRepository $comentarios,
        private CategoriaPostRepository $categorias,
        private LinkRepository $links,
        private LinkClickRepository $linkClicks,
    ) {
    }

    /**
     * @return array<string,mixed>
     */
    public function getDashboardData(int|string $daysOrStart = 30, ?string $end = null): array
    {
        if (is_int($daysOrStart)) {
            $days = $this->clampInt($daysOrStart, 1, 90);
            $rangeEnd = (new DateTimeImmutable('today'))->format('Y-m-d');
            $rangeStart = (new DateTimeImmutable('today'))
                ->modify('-' . max(0, $days - 1) . ' days')
                ->format('Y-m-d');

            return $this->getDashboardDataByRange($rangeStart, $rangeEnd);
        }

        $start = $this->parseYmd($daysOrStart);
        $endParsed = $this->parseYmd($end);

        if ($start === null || $endParsed === null) {
            $rangeEnd = (new DateTimeImmutable('today'))->format('Y-m-d');
            $rangeStart = (new DateTimeImmutable('today'))->modify('-29 days')->format('Y-m-d');
            return $this->getDashboardDataByRange($rangeStart, $rangeEnd);
        }

        return $this->getDashboardDataByRange($start, $endParsed);
    }

    /**
     * @return array<string,mixed>
     */
    private function getDashboardDataByRange(string $start, string $end): array
    {
        [$start, $end] = $this->orderRange($start, $end);
        [$start, $end] = $this->clampRange90($start, $end);
        $days = $this->diffDaysInclusive($start, $end);

        $totalPosts = (int) $this->posts->countAll();
        $postsByStatus = (array) $this->posts->countByStatus();

        $totalViews = (int) $this->posts->sumViews();
        $viewsToday = (int) $this->estatisticas->viewsToday();
        $viewsWeek = (int) $this->estatisticas->viewsLastDays(7);

        $likesTotal = (int) $this->posts->sumLikes();
        $totalComentarios = (int) $this->posts->sumComentariosCount();
        $engagementRate = $this->safePercent($likesTotal + $totalComentarios, $totalViews);

        $totalInscritos = (int) $this->newsletter->countAll();
        $inscritosNovos30 = (int) $this->newsletter->countLastDays(30);
        $inscritosHoje = (int) $this->newsletter->countToday();

        $comentariosHoje = (int) $this->comentarios->countToday();
        $comentariosPendentes = (int) $this->comentarios->countPending();

        $approvalBase = (array) $this->comentarios->approvalBaseCounts();
        $taxaAprovacao = $this->safePercent(
            (int) ($approvalBase['aprovados'] ?? 0),
            (int) ($approvalBase['aprovados'] ?? 0) + (int) ($approvalBase['reprovados'] ?? 0)
        );

        $topViews = $this->posts->topByViews();
        $topLikes = $this->posts->topByLikes();
        $topComments = $this->posts->topByComments();

        $categoriaPopular = $this->categorias->mostPopular();
        $postsHoje = (int) $this->posts->countToday();

        $series = $this->seriesByRange($start, $end);
        $seriesWithMa7 = $this->addMovingAverage($series, 7, 'views', 'views_ma7');

        $currentViews = $this->sumByRange('views', $start, $end);
        $currentInscricoes = $this->sumByRange('inscricoes', $start, $end);
        $currentPostsNovos = $this->sumByRange('posts_novos', $start, $end);

        $prevEnd = (new DateTimeImmutable($start))->modify('-1 day')->format('Y-m-d');
        $prevStart = (new DateTimeImmutable($start))->modify('-' . $days . ' days')->format('Y-m-d');

        $previousViews = $this->sumByRange('views', $prevStart, $prevEnd);
        $previousInscricoes = $this->sumByRange('inscricoes', $prevStart, $prevEnd);
        $previousPostsNovos = $this->sumByRange('posts_novos', $prevStart, $prevEnd);

        $linksAtivos = (int) $this->links->countPublicActive();
        $linksRevisao = (int) $this->links->countReview();
        $linkClicksResumo = $this->linkClicks->summaryByRange($start, $end);
        $linkClicksHoje = (int) $this->linkClicks->countToday();
        $topLinksClicks = $this->linkClicks->topLinksByRange($start, $end, 5);
        $linkSectionClicks = $this->linkClicks->sectionBreakdownByRange($start, $end);

        return [
            'days' => $days,
            'start' => $start,
            'end' => $end,

            'total_posts' => $totalPosts,
            'posts_publicados' => (int) ($postsByStatus['publicados'] ?? 0),
            'posts_rascunho' => (int) ($postsByStatus['rascunhos'] ?? 0),
            'posts_agendados' => (int) ($postsByStatus['agendados'] ?? 0),

            'total_views' => $totalViews,
            'views_hoje' => $viewsToday,
            'views_semana' => $viewsWeek,

            'likes_total' => $likesTotal,
            'total_comentarios' => $totalComentarios,
            'engagement_rate' => $engagementRate,

            'total_inscritos' => $totalInscritos,
            'inscritos_novos_30dias' => $inscritosNovos30,

            'top_post_views' => $topViews,
            'top_post_likes' => $topLikes,
            'top_post_comments' => $topComments,

            'categoria_popular' => $categoriaPopular,

            'posts_hoje' => $postsHoje,
            'inscritos_hoje' => $inscritosHoje,
            'comentarios_hoje' => $comentariosHoje,
            'comentarios_pendentes' => $comentariosPendentes,
            'taxa_aprovacao_comentarios' => $taxaAprovacao,

            'links_ativos' => $linksAtivos,
            'links_revisao' => $linksRevisao,
            'link_clicks_periodo' => (int) ($linkClicksResumo['total'] ?? 0),
            'link_clicks_unicos' => (int) ($linkClicksResumo['unique_sessions'] ?? 0),
            'link_clicks_hoje' => $linkClicksHoje,
            'top_links_clicks' => $topLinksClicks,
            'link_section_clicks' => $linkSectionClicks,

            'chart' => [
                'series' => $seriesWithMa7,
                'current' => [
                    'views' => $currentViews,
                    'posts_novos' => $currentPostsNovos,
                    'inscricoes' => $currentInscricoes,
                ],
                'previous' => [
                    'views' => $previousViews,
                    'posts_novos' => $previousPostsNovos,
                    'inscricoes' => $previousInscricoes,
                ],
                'delta_abs' => [
                    'views' => $currentViews - $previousViews,
                    'posts_novos' => $currentPostsNovos - $previousPostsNovos,
                    'inscricoes' => $currentInscricoes - $previousInscricoes,
                ],
                'delta_percent' => [
                    'views' => $this->deltaPercentOrNull($currentViews, $previousViews),
                    'posts_novos' => $this->deltaPercentOrNull($currentPostsNovos, $previousPostsNovos),
                    'inscricoes' => $this->deltaPercentOrNull($currentInscricoes, $previousInscricoes),
                ],
            ],

            'posts_recentes' => $this->posts->latestWithCategoria(5),
        ];
    }

    /**
     * @param array<int, array<string,mixed>> $series
     * @return array<int, array<string,mixed>>
     */
    private function addMovingAverage(array $series, int $window, string $valueKey, string $targetKey): array
    {
        $window = max(1, $window);

        $sum = 0;
        $queue = [];

        foreach ($series as $index => $row) {
            $value = (int) ($row[$valueKey] ?? 0);
            $queue[] = $value;
            $sum += $value;

            if (count($queue) > $window) {
                $sum -= (int) array_shift($queue);
            }

            $series[$index][$targetKey] = (int) round($sum / count($queue));
        }

        return $series;
    }

    private function safePercent(int $num, int $den): float
    {
        if ($den <= 0) {
            return 0.0;
        }

        return round(($num / $den) * 100, 2);
    }

    private function deltaPercentOrNull(int $current, int $previous): ?float
    {
        if ($previous <= 0) {
            return $current <= 0 ? 0.0 : null;
        }

        return round((($current - $previous) / $previous) * 100, 2);
    }

    /**
     * @return array<int, array{data:string,views:int,posts_novos:int,inscricoes:int}>
     */
    private function seriesByRange(string $start, string $end): array
    {
        $pdo = $this->pdo();

        $sql = "
            SELECT
                DATE(data) AS data,
                COALESCE(SUM(views), 0) AS views,
                COALESCE(SUM(posts_novos), 0) AS posts_novos,
                COALESCE(SUM(inscricoes), 0) AS inscricoes
            FROM estatisticas
            WHERE DATE(data) BETWEEN :start AND :end
            GROUP BY DATE(data)
            ORDER BY DATE(data) ASC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue('start', $start);
        $stmt->bindValue('end', $end);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $map = [];
        foreach ($rows as $row) {
            $key = (string) ($row['data'] ?? '');
            if ($key === '') {
                continue;
            }

            $map[$key] = [
                'data' => $key,
                'views' => (int) ($row['views'] ?? 0),
                'posts_novos' => (int) ($row['posts_novos'] ?? 0),
                'inscricoes' => (int) ($row['inscricoes'] ?? 0),
            ];
        }

        $out = [];
        try {
            $startDate = new DateTimeImmutable($start);
            $endDate = new DateTimeImmutable($end);
        } catch (Throwable) {
            return array_values($map);
        }

        if ($startDate > $endDate) {
            [$startDate, $endDate] = [$endDate, $startDate];
        }

        $cursor = $startDate;
        while ($cursor <= $endDate) {
            $key = $cursor->format('Y-m-d');
            $out[] = $map[$key] ?? ['data' => $key, 'views' => 0, 'posts_novos' => 0, 'inscricoes' => 0];
            $cursor = $cursor->modify('+1 day');
        }

        return $out;
    }

    private function sumByRange(string $column, string $start, string $end): int
    {
        if (!in_array($column, ['views', 'posts_novos', 'inscricoes'], true)) {
            return 0;
        }

        $pdo = $this->pdo();
        $sql = "
            SELECT COALESCE(SUM($column), 0) AS total
            FROM estatisticas
            WHERE DATE(data) BETWEEN :start AND :end
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue('start', $start);
        $stmt->bindValue('end', $end);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        return (int) ($row['total'] ?? 0);
    }

    private function pdo(): PDO
    {
        $pdo = $GLOBALS['pdo'] ?? null;
        if (!$pdo instanceof PDO) {
            throw new \RuntimeException('PDO nao disponivel em $GLOBALS["pdo"]');
        }

        return $pdo;
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

        [$year, $month, $day] = array_map('intval', explode('-', $value));
        if (!checkdate($month, $day, $year)) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    /**
     * @return array{0:string,1:string}
     */
    private function orderRange(string $start, string $end): array
    {
        try {
            $startDate = new DateTimeImmutable($start);
            $endDate = new DateTimeImmutable($end);
        } catch (Throwable) {
            return [$start, $end];
        }

        return $startDate > $endDate
            ? [$endDate->format('Y-m-d'), $startDate->format('Y-m-d')]
            : [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')];
    }

    /**
     * @return array{0:string,1:string}
     */
    private function clampRange90(string $start, string $end): array
    {
        try {
            $startDate = new DateTimeImmutable($start);
            $endDate = new DateTimeImmutable($end);
        } catch (Throwable) {
            return [$start, $end];
        }

        if ($startDate > $endDate) {
            [$startDate, $endDate] = [$endDate, $startDate];
        }

        $days = (int) $startDate->diff($endDate)->days + 1;
        if ($days <= 90) {
            return [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')];
        }

        return [$endDate->modify('-89 days')->format('Y-m-d'), $endDate->format('Y-m-d')];
    }

    private function diffDaysInclusive(string $start, string $end): int
    {
        try {
            $startDate = new DateTimeImmutable($start);
            $endDate = new DateTimeImmutable($end);
        } catch (Throwable) {
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