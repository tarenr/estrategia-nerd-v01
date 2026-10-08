<?php
/**
 * -----------------------------------------------------------------------------
 * @file        app/Services/Instagram/TrackPickerService.php
 * @project     Estrategia Nerd
 * @purpose     Escolhe a trilha de cada Reel sem repetir enquanto houver faixa
 *              ativa sem uso compatível com tema, atmosfera e ritmo (IMP-032).
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
    private const EDGE_SECONDS = 5;
    private ?string $warning = null;

    /** @var array<int, list<int>> Escolhas feitas nesta execução: track_id => inícios. */
    private array $reserved = [];

    /**
     * @param list<int> $ignorePostIds Posts cuja trilha atual não conta como uso (redistribuição).
     */
    public function __construct(
        private readonly PDO $pdo,
        private readonly array $ignorePostIds = [],
        private readonly ?array $catalog = null
    ) {
    }

    /** @return list<string> */
    public static function poolsFor(string $categoria): array
    {
        return self::CATEGORY_POOLS[strtolower(trim($categoria))] ?? self::DEFAULT_POOLS;
    }

    /**
     * Compatibilidade temática precede diversidade. Não atravessa atmosferas para evitar repetição.
     * Depois de esgotar as compatíveis sem uso, reutiliza a menos usada, mantendo histórico e reservas.
     * @param array<string,mixed> $context Título/resumo/tags; ritmo opcional slow|medium|fast.
     *
     * @return array{track: array<string,mixed>, start: int, reused: bool}|null
     */
    public function pick(string $categoria, int $reelSeconds, array $context = []): ?array
    {
        $this->warning = null;
        $reelSeconds = max(1, $reelSeconds);
        $tracks = $this->activeTracks($reelSeconds);
        $profile = self::profileFor($categoria, $context);
        try {
            $catalog = $this->catalog ?? json_decode((string) file_get_contents(dirname(__DIR__, 3) . '/config/reel-music.json'), true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($catalog) || !isset($catalog['tracks']) || !is_array($catalog['tracks'])) {
                throw new \RuntimeException('Catálogo musical inválido');
            }
        } catch (\Throwable $e) {
            error_log('Seleção musical: catálogo indisponível.');
            $this->warning = 'Catálogo de classificação musical indisponível; nenhuma trilha foi escolhida.';
            return null;
        }
        $eligible = [];
        foreach ($tracks as $track) {
            foreach ($catalog['tracks'] as $entry) {
                if (!is_array($entry) || !is_string($entry['source'] ?? null) || !is_string($entry['sourceId'] ?? null)
                    || !is_string($entry['sha256'] ?? null) || !is_string($entry['rhythm'] ?? null)) {
                    continue;
                }
                if ($entry['source'] !== $track['origem'] || $entry['sourceId'] !== $track['origem_id']) {
                    continue;
                }
                $themes = is_array($entry['themes'] ?? null) ? $entry['themes'] : [];
                if (!in_array($profile['theme'], $themes, true) || !in_array($entry['rhythm'], $profile['rhythms'], true)) {
                    continue;
                }
                $file = dirname(__DIR__, 3) . '/public/' . ltrim($track['arquivo_path'], '/\\');
                $expected = $entry['sha256'];
                if (!is_file($file) || $expected === '' || hash_file('sha256', $file) !== $expected) {
                    continue;
                }
                $track['musical_theme'] = $profile['theme'];
                $track['musical_rhythm'] = (string) $entry['rhythm'];
                $eligible[] = $track;
                break;
            }
        }
        $tracks = $eligible;
        if ($tracks === []) {
            $this->warning = sprintf('Nenhuma trilha local compatível com o tema "%s" e ritmo "%s", com duração suficiente e integridade confirmada. Reel não gerado; ampliar ou revisar o catálogo.', $profile['theme'], implode('/', $profile['rhythms']));
            return null;
        }
        $usage = $this->usage();
        $uses = static fn (array $t): int => count($usage[(int) $t['id']]['starts'] ?? []);
        $poolUse = [];
        foreach ($tracks as $t) {
            $poolUse[$t['genero']] = ($poolUse[$t['genero']] ?? 0) + $uses($t);
        }

        $candidates = $tracks;
        $unused = array_values(array_filter($candidates, static fn (array $t): bool => $uses($t) === 0));

        $pool = $unused !== [] ? $unused : $candidates;
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

    public function warning(): ?string
    {
        return $this->warning;
    }

    /** @param array<string,mixed> $context @return array{theme:string,rhythms:list<string>} */
    public static function profileFor(string $categoria, array $context = []): array
    {
        $text = strip_tags(implode(' ', array_map(static fn ($key): string => is_scalar($context[$key] ?? null) ? (string) $context[$key] : '', ['titulo', 'resumo', 'tags'])));
        $text = strtolower((string) iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text));
        $theme = match (true) {
            preg_match('/\b(diablo|terror|horror|sombrio|sombria|demonios?|inferno|dark fantasy|dark souls|bloodborne|resident evil|silent hill|elden ring)\b/', $text) === 1 => 'dark',
            preg_match('/\b(rpg|fantasia|medieval|skyrim|baldur|dragons?|dragoes|magia|santuario)\b/', $text) === 1 => 'fantasy',
            preg_match('/\b(retro|nostalgia|nostalgico|nostalgica|8.?bits?|16.?bits?|pixel|chiptune|nes|snes|arcade|game boy)\b/', $text) === 1 => 'retro',
            preg_match('/\b(fps|shooter|acao|corrida|competitivo|counter.?strike|valorant|doom)\b/', $text) === 1 => 'action',
            strtolower(trim($categoria)) === 'hardware' => 'technology',
            strtolower(trim($categoria)) === 'dicas' => 'calm',
            strtolower(trim($categoria)) === 'games' => 'action',
            default => 'light',
        };
        $rhythms = match ($theme) {
            'dark', 'fantasy', 'calm' => ['slow', 'medium'],
            'action', 'retro' => ['medium', 'fast'],
            default => ['slow', 'medium', 'fast'],
        };
        if (in_array($context['ritmo'] ?? null, ['slow', 'medium', 'fast'], true)) {
            $rhythms = [(string) $context['ritmo']];
        }
        return ['theme' => $theme, 'rhythms' => $rhythms];
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
     * @return list<array{id: int, titulo: string, artista: string, genero: string, arquivo_path: string, duracao_s: int, origem:string, origem_id:string}>
     */
    private function activeTracks(int $reelSeconds): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, titulo, artista, genero, arquivo_path, duracao_s, origem, origem_id
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
                'origem'       => (string) $row['origem'],
                'origem_id'    => (string) $row['origem_id'],
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
