<?php
declare(strict_types=1);
namespace App\Services\Instagram;

use App\Support\TargetEnvironmentDatabase;
use PDO;
use RuntimeException;

/** Regeneration for linked blog Reels only. Article production connection is read-only. */
final class EditorialReelService
{
    /** @param array<string,mixed> $post */
    public static function isLinked(array $post): bool
    {
        $articleId=(int) ($post['post_blog_id'] ?? 0);
        return $articleId>0 && ($post['tipo'] ?? '')==='reels' && ($post['origin'] ?? '')==='local'
            && ($post['idempotency_key'] ?? '')===BlogCrosspostService::idempotencyKey('production',$articleId);
    }

    /** @param array<string,mixed> $post @param array<string,mixed> $track @return array{video_path:string,canvas_path:string,duration:int} */
    public static function regenerate(array $post,array $track): array
    {
        if(!self::isLinked($post))throw new RuntimeException('Post não é um Reel editorial vinculado.');
        $production=TargetEnvironmentDatabase::pdo('production');
        $s=$production->prepare('SELECT id,titulo,resumo,categoria,imagem_capa FROM posts WHERE id=?');
        $s->execute([(int) $post['post_blog_id']]);
        $article=$s->fetch(PDO::FETCH_ASSOC);
        if(!is_array($article))throw new RuntimeException('Artigo de origem indisponível; Reel não enviado.');
        @set_time_limit(360);
        return AudioReelGeneratorService::fromGlobals()->generateEditorialReel((string) $article['imagem_capa'],(string) $track['arquivo_path'],$article,(int) ($post['audio_start_seconds'] ?? 0));
    }
}
