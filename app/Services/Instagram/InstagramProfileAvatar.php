<?php
/**
 * -----------------------------------------------------------------------------
 * @file        app/Services/Instagram/InstagramProfileAvatar.php
 * @project     Estrategia Nerd
 * @purpose     Foto de perfil do Instagram guardada como arquivo local (FEAT-010 v2.12.2)
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace App\Services\Instagram;

/**
 * A URL da foto devolvida pela Meta e assinada e expira em ~14 dias; por isso a foto e
 * baixada para public/assets/instagram-feed/profile.jpg, como as capas dos posts.
 * Logica sem rede e sem banco.
 */
final class InstagramProfileAvatar
{
    public const REL_PATH = '/assets/instagram-feed/profile.jpg';
    public const MIN_BYTES = 1000;

    /** Valor aceito em "profile_picture" do feed quando a foto e local (com ou sem ?v=). */
    public const PUBLIC_PATTERN = '#^/assets/instagram-feed/profile\.jpg(?:\?v=[0-9a-f]{8})?$#';

    public static function file(string $publicRoot): string
    {
        return rtrim($publicRoot, '/\\') . self::REL_PATH;
    }

    /**
     * Caminho publico com cache-buster ("?v=" do conteudo) ou null se nao houver foto local valida.
     */
    public static function publicPath(string $publicRoot): ?string
    {
        $file = self::file($publicRoot);
        if (!is_file($file) || (int) filesize($file) < self::MIN_BYTES) {
            return null;
        }
        $hash = md5_file($file);

        return $hash === false ? null : self::REL_PATH . '?v=' . substr($hash, 0, 8);
    }

    /**
     * Grava a foto so se for um JPEG valido e com tamanho minimo; escrita atomica (temp + rename).
     */
    public static function save(string $bytes, string $targetFile): bool
    {
        if (strlen($bytes) < self::MIN_BYTES) {
            return false;
        }
        $info = @getimagesizefromstring($bytes);
        if ($info === false || $info[2] !== IMAGETYPE_JPEG || $info[0] < 1 || $info[1] < 1) {
            return false;
        }
        $dir = dirname($targetFile);
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            return false;
        }
        $tmp = $targetFile . '.tmp';
        if (file_put_contents($tmp, $bytes, LOCK_EX) !== strlen($bytes)) {
            @unlink($tmp);

            return false;
        }

        return rename($tmp, $targetFile);
    }

    /**
     * True quando o arquivo existe, tem tamanho minimo e foi gravado ha menos de $maxAgeSeconds.
     */
    public static function isFresh(string $targetFile, int $maxAgeSeconds, ?int $now = null): bool
    {
        if (!is_file($targetFile) || (int) filesize($targetFile) < self::MIN_BYTES) {
            return false;
        }
        $mtime = filemtime($targetFile);

        return $mtime !== false && (($now ?? time()) - $mtime) < $maxAgeSeconds;
    }

    /**
     * O perfil devolvido pela Meta so deve ser gravado no banco se vier completo
     * (o fallback do helper pode devolver campos vazios).
     *
     * @param array<string,mixed> $profile
     */
    public static function isCompleteProfile(array $profile): bool
    {
        return trim((string) ($profile['username'] ?? '')) !== ''
            && trim((string) ($profile['profile_picture_url'] ?? '')) !== '';
    }
}
