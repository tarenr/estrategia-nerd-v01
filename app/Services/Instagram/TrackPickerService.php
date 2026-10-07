<?php
/**
 * -----------------------------------------------------------------------------
 * @file        app/Services/Instagram/TrackPickerService.php
 * @project     Estrategia Nerd
 * @purpose     Escolhe a trilha de cada Reel sem repetir enquanto houver faixa
 *              ativa sem uso, respeitando o grupo musical da categoria (IMP-032)
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace App\Services\Instagram;

use PDO;

final class TrackPickerService
{
    /** Grupos musicais (valor de instagram_audio_tracks.genero) por categoria do blog. */
    private const CATEGORY_POOLS = [
        'hardware' => ['synthwave'],
        'games'    => ['epic', 'chiptune'],
        'dicas'    => ['lofi'],
    ];
    private const DEFAULT_POOLS = ['upbeat', 'lofi'];
    private const FALLBACK_ORDER = ['synthwave', 'upbeat', 'lofi', 'epic', 'chiptune'];
    private const EDGE_SECONDS = 5;

    /** @var array<int, list<int>> Escolhas feitas nesta execução: track_id => inícios. */
    private array $reserved = [];

    /**
     * @param list<int> $ignorePostIds Posts cuja trilha atual não conta como uso (redistribuição).
     */
    public function __construct(
        private readonly PDO $pdo,
        private readonly array $ignorePostIds = []
    ) {
    }

    /** @return list<string> */
    public static function poolsFor(string $categoria): array
    {
        return self::CATEGORY_POOLS[strtolower(trim($categoria))] ?? self::DEFAULT_POOLS;
    }

    /**
     * Faixa nunca usada do grupo da categoria; sem nenhuma, faixa nunca usada de outro grupo;
     * com tudo usado, a menos usada do grupo, em trecho diferente dos anteriores.
     *
     * @return array{track: array<string,mixed>, start: int, reused: bool}|null
     */
    public function pick(string $categoria, int $reelSeconds): ?array
    {
        $reelSeconds = max(1, $reelSeconds);
        $tracks = $this->activeTracks($reelSeconds);
        if ($tracks === []) {
            return null;
        }

        $usage = $this->usage();
        $uses = static fn (array $t): int => count($usage[(int) $t['id']]['starts'] ?? []);
        $poolUse = [];
        foreach ($tracks as $t) {
            $poolUse[$t['genero']] = ($poolUse[$t['genero']] ?? 0) + $uses($t);
        }

        $own = self::poolsFor($categoria);
        $candidates = array_values(array_filter($tracks, static fn (array $t): bool => in_array($t['genero'], $own, true)));
        $unused = array_values(array_filter($candidates, static fn (array $t): bool => $uses($t) === 0));
        if ($unused === []) {
            foreach (self::FALLBACK_ORDER as $pool) {
                if (in_array($pool, $own, true)) {
                    continue;
                }
                $unused = array_values(array_filter($tracks, static fn (array $t): bool => $t['genero'] === $pool && $uses($t) === 0));
                if ($unused !== []) {
                    break;
                }
            }
        }

        $pool = $unused !== [] ? $unused : ($candidates !== [] ? $candidates : $tracks);
        usort($pool, static function (array $a, array $b) use ($uses, $poolUse, $usage): int {
            return [$uses($a), $poolUse[$a['genero']] ?? 0, $usage[(int) $a['id']]['last'] ?? '', (int) $a['id']]
                <=> [$uses($b), $poolUse[$b['genero']] ?? 0, $usage[(int) $b['id']]['last'] ?? '', (int) $b['id']];
        });

        $chosen = $pool[0];
        $trackId = (int) $chosen['id'];
        $previous = $usage[$trackId]['starts'] ?? [];
        $start = $this->startFor((int) $chosen['duracao_s'], $reelSeconds, $previous);
        $this->reserved[$trackId][] = $start;

        return ['track' => $chosen, 'start' => $start, 'reused' => $previous !== []];
    }

    /**
     * @param list<int> $previous
     */
    private function startFor(int $trackSeconds, int $reelSeconds, array $previous): int
    {
        $last = $trackSeconds - $reelSeconds - self::EDGE_SECONDS;
        if ($last <= self::EDGE_SECONDS) {
            return intdiv(max(0, $trackSeconds - $reelSeconds), 2);
        }

        $start = self::EDGE_SECONDS;
        for ($i = 0; $i < 12; $i++) {
            $start = random_int(self::EDGE_SECONDS, $last);
            $overlaps = false;
            foreach ($previous as $p) {
                if (abs($start - $p) < $reelSeconds) {
                    $overlaps = true;
                    break;
                }
            }
            if (!$overlaps) {
                break;
            }
        }

        return $start;
    }

    /**
     * @return list<array{id: int, titulo: string, artista: string, genero: string, arquivo_path: string, duracao_s: int}>
     */
    private function activeTracks(int $reelSeconds): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, titulo, artista, genero, arquivo_path, duracao_s
               FROM instagram_audio_tracks
              WHERE ativo = 1 AND duracao_s > ?
              ORDER BY id'
        );
        $stmt->execute([$reelSeconds]);

        $tracks = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $tracks[] = [
                'id'           => (int) $row['id'],
                'titulo'       => (string) $row['titulo'],
                'artista'      => (string) $row['artista'],
                'genero'       => strtolower(trim((string) ($row['genero'] ?? ''))),
                'arquivo_path' => (string) $row['arquivo_path'],
                'duracao_s'    => (int) $row['duracao_s'],
            ];
        }

        return $tracks;
    }

    /**
     * Uso de cada faixa em qualquer Reel/post (inclusive rascunho), mais as escolhas desta execução.
     *
     * @return array<int, array{starts: list<int>, last: string}>
     */
    private function usage(): array
    {
        $sql = 'SELECT id, audio_track_id, audio_start_seconds,
                       COALESCE(agendado_para, publicado_em, criado_em) AS quando
                  FROM instagram_posts
                 WHERE audio_track_id IS NOT NULL';
        $ignore = array_values(array_filter(array_map('intval', $this->ignorePostIds), static fn (int $id): bool => $id > 0));
        if ($ignore !== []) {
            $sql .= ' AND id NOT IN (' . implode(',', $ignore) . ')';
        }

        $usage = [];
        $stmt = $this->pdo->query($sql);
        foreach ($stmt !== false ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [] as $row) {
            $id = (int) $row['audio_track_id'];
            $usage[$id]['starts'][] = (int) $row['audio_start_seconds'];
            $usage[$id]['last'] = max($usage[$id]['last'] ?? '', (string) ($row['quando'] ?? ''));
        }
        foreach ($this->reserved as $id => $starts) {
            foreach ($starts as $start) {
                $usage[$id]['starts'][] = $start;
            }
            $usage[$id]['last'] = '9999-12-31 23:59:59';
        }

        return $usage;
    }
}
