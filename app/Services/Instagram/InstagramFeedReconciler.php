<?php
/**
 * -----------------------------------------------------------------------------
 * @file        app/Services/Instagram/InstagramFeedReconciler.php
 * @project     Estrategia Nerd
 * @purpose     Detecta posts "publicado" que sumiram do Instagram (FEAT-010 v2.12.0)
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace App\Services\Instagram;

/**
 * Lógica pura (sem banco, sem rede): decide quais posts publicados deixaram de
 * existir na Meta. O chamador só grava se "aborted" vier nulo.
 */
final class InstagramFeedReconciler
{
    /** Post publicado há menos que isso pode ainda não constar na listagem da Meta. */
    public const MIN_AGE_MINUTES = 120;

    /** Lista da Meta com menos que esta fração dos posts rastreados é tratada como incompleta. */
    public const MIN_LIVE_RATIO = 0.5;

    /** Teto de cancelamentos numa só execução: fração dos rastreados, com piso. */
    public const MAX_ORPHAN_RATIO = 0.2;
    public const MAX_ORPHAN_FLOOR = 10;

    /**
     * @param list<array<string,mixed>> $published Linhas com status 'publicado' (id, ig_media_id, publicado_em).
     * @param list<string>              $liveIds   IDs de mídia devolvidos pela Meta.
     * @return array{orphans: list<int>, aborted: ?string}
     */
    public static function findOrphans(array $published, array $liveIds, ?int $now = null): array
    {
        $now ??= time();

        $live = [];
        foreach ($liveIds as $id) {
            $id = trim((string) $id);
            if ($id !== '') {
                $live[$id] = true;
            }
        }
        if ($live === []) {
            return ['orphans' => [], 'aborted' => 'Lista da Meta vazia; nada será cancelado.'];
        }

        $tracked = [];
        foreach ($published as $row) {
            if (trim((string) ($row['ig_media_id'] ?? '')) !== '') {
                $tracked[] = $row;
            }
        }
        if ($tracked === []) {
            return ['orphans' => [], 'aborted' => null];
        }

        $minLive = (int) ceil(count($tracked) * self::MIN_LIVE_RATIO);
        if (count($live) < $minLive) {
            return [
                'orphans' => [],
                'aborted' => 'Lista da Meta parece incompleta (' . count($live) . ' mídias para '
                    . count($tracked) . ' posts rastreados); nada será cancelado.',
            ];
        }

        $orphans = [];
        foreach ($tracked as $row) {
            if (isset($live[trim((string) $row['ig_media_id'])])) {
                continue;
            }
            $publishedAt = strtotime((string) ($row['publicado_em'] ?? ''));
            if ($publishedAt !== false && ($now - $publishedAt) < self::MIN_AGE_MINUTES * 60) {
                continue;
            }
            $orphans[] = (int) $row['id'];
        }

        $cap = max(self::MAX_ORPHAN_FLOOR, (int) floor(count($tracked) * self::MAX_ORPHAN_RATIO));
        if (count($orphans) > $cap) {
            return [
                'orphans' => [],
                'aborted' => count($orphans) . ' posts ausentes da Meta excedem o teto de ' . $cap
                    . ' por execução; nada será cancelado (revisar manualmente).',
            ];
        }

        return ['orphans' => $orphans, 'aborted' => null];
    }
}
