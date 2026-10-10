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
        $s=$production->prepare('SELECT id,titulo,slug,resumo,conteudo,categoria,imagem_capa FROM posts WHERE id=?');
        $s->execute([(int) $post['post_blog_id']]);
        $article=$s->fetch(PDO::FETCH_ASSOC);
        if(!is_array($article))throw new RuntimeException('Artigo de origem indisponível; Reel não enviado.');
        $catalogPath=base_path('resources/reels-review/corrections-20261010.json');
        $catalog=is_file($catalogPath)?json_decode((string) file_get_contents($catalogPath),true):null;
        if(!is_array($catalog) || ($catalog['publicationApproval'] ?? false)!==true) {
            throw new RuntimeException('Roteiros de correção aguardam aprovação visual. Nenhum Reel novo foi enviado.');
        }
        $curated=null;
        foreach ($catalog['items'] ?? [] as $item) {
            if ((int) ($item['id'] ?? 0)===(int) ($post['id'] ?? 0)
                && ($item['originEnvironment'] ?? '')==='production'
                && ($item['articleSlug'] ?? '')===$article['slug']
                && ($item['articleSha256'] ?? '')===hash('sha256',(string) $article['conteudo'])
                && (int) ($item['trackId'] ?? 0)===(int) ($track['id'] ?? 0)) {
                if ((int) ($item['spec']['audioStart'] ?? -1)!==(int) ($post['audio_start_seconds'] ?? 0)) {
                    throw new RuntimeException('Início da trilha alterado; revise o roteiro antes de renderizar.');
                }
                $curated=$item['spec'] ?? null;
                break;
            }
        }
        if(!is_array($curated))throw new RuntimeException('Roteiro individual ausente ou artigo/trilha alterados. Revise antes de renderizar; Reel não enviado.');
        $article['editorial_spec']=$curated;
        @set_time_limit(360);
        return AudioReelGeneratorService::fromGlobals()->generateEditorialReel((string) $article['imagem_capa'],(string) $track['arquivo_path'],$article,(int) ($post['audio_start_seconds'] ?? 0));
    }
}
