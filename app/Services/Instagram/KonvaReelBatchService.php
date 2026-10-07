<?php
declare(strict_types=1);
namespace App\Services\Instagram;

use PDO;
use RuntimeException;
use Throwable;

/** Explicit migration. No publisher or music selection is called. */
final class KonvaReelBatchService
{
    public const IDS = [190,194,195,196,197,198,199,200,201,202,203,204,205,206,207,208,209,210,211,212,213,215,216,217,218,219];
    public function __construct(private readonly PDO $local, private readonly MotionReelBatchService $guard, private readonly KonvaReelRenderer $renderer, private readonly EditorialMotionReelRenderer $media, private readonly string $root) {}

    /** @param array<string,mixed> $post */
    public static function eligible(array $post): void
    {
        MotionReelBatchService::eligibility($post);
        if(($post['status'] ?? '')!=='agendado' || !empty($post['creation_id']) || !empty($post['ig_media_id']) || !in_array($post['publish_phase'] ?? 'idle',['idle'],true))throw new RuntimeException('Publicação iniciada ou post fora dos agendados: preservado.');
    }

    /** @return array<string,string|false> */
    public function version(): array
    {
        $result=[];
        foreach(['app/Services/Instagram/KonvaReelRenderer.php','scripts/reels-konva/scene.mjs','scripts/reels-konva/render.mjs','scripts/reels-konva/editorial.mjs','resources/reels-konva/fonts/BebasNeue-Regular.ttf','resources/reels-konva/fonts/Inter.ttf'] as $path)$result[$path]=hash_file('sha256',$this->root.'/'.$path);
        return $result;
    }

    /** @return list<array<string,mixed>> */
    public function inventory(PDO $production): array
    {
        $items=array_values(array_filter($this->guard->inventory($production),static fn(array $i):bool=>in_array((int) $i['id'],self::IDS,true)));
        if(count($items)!==count(self::IDS))throw new RuntimeException('Os 26 agendados não correspondem ao inventário esperado.');
        foreach($items as &$item){
            if($item['state']!=='inventoried')throw new RuntimeException('Inventário recusado #'.$item['id'].': '.($item['reason'] ?? 'inelegível'));
            self::eligible($item['before']['post']);
            $audio=$this->media->probe($this->guard->asset((string) $item['track']['arquivo_path']));
            $seconds=KonvaReelRenderer::duration($item['article']);
            if((float) $audio['format']['duration']<$item['audio_start']+$seconds)throw new RuntimeException('Recorte original não comporta Konva #'.$item['id'].'; música e início preservados.');
            $item['duration']=$seconds;
            $item['spec']=$this->renderer->spec((string) $item['article']['imagem_capa'],(string) $item['track']['arquivo_path'],$item['article'],(int) $item['audio_start'],$seconds);
            $item['images']=[];
            foreach($item['spec']['scenes'] as $scene)$item['images'][$scene['image']]=hash_file('sha256',$this->root.'/'.$scene['image']);
        }
        unset($item);
        return $items;
    }

    /** @param array<string,mixed> $item */
    public function sources(PDO $production,array $item): void
    {
        $this->guard->assertArticle($production,$item);$this->guard->assertSources($item);
        foreach($item['images'] as $path=>$sha){if(hash_file('sha256',$this->root.'/'.$path)!==$sha)throw new RuntimeException('Imagem do artigo mudou.');}
        if(hash_file('sha256',$this->guard->asset((string) $item['before']['media'][0]['caminho']))!==$item['previous_media_sha256'])throw new RuntimeException('Vídeo anterior mudou.');
    }

    /** @param array<string,mixed> $item @return array<string,mixed> */
    public function prepare(PDO $production,array $item,string $run): array
    {
        $this->sources($production,$item);
        if($this->guard->snapshot((int) $item['id'])!==$item['before'])throw new RuntimeException('Post mudou depois do backup.');
        if($item['state']==='ready'){$this->validate($production,$item);return $item;}
        if(!preg_match('/^batch-[0-9]{8}-[0-9]{6}-[a-f0-9]{8}$/',$run))throw new RuntimeException('Lote inválido.');
        $relative='uploads/reels/konva-batch/'.$run.'/reel-'.$item['id'].'-'.bin2hex(random_bytes(4)).'.mp4';
        $result=$this->renderer->renderSpec($item['spec'],$relative,function()use($production):void{$production->query('SELECT 1');$this->local->query('SELECT 1');});
        $item['video']=$result['video_path'];$item['cover']=$result['canvas_path'];
        $item['video_sha256']=hash_file('sha256',$this->guard->asset($item['video']));
        $item['cover_sha256']=hash_file('sha256',$this->guard->asset($item['cover']));
        $sheet=imagecreatetruecolor(1080,480);
        if(!$sheet instanceof \GdImage)throw new RuntimeException('Falha na folha visual.');
        for($i=0;$i<4;$i++){
            $frame=$this->root.'/public/'.substr($relative,0,-4).'-scene-'.$i.'.png';
            $this->media->capture($this->guard->asset($relative),$i*$item['duration']/4+1.6,$frame);
            $image=imagecreatefrompng($frame);
            if(!$image instanceof \GdImage)throw new RuntimeException('Frame ausente.');
            imagecopyresampled($sheet,$image,$i*270,0,0,0,270,480,1080,1920);imagedestroy($image);
        }
        $item['contact_sheet']=substr($relative,0,-4).'-contact.png';
        if(!imagepng($sheet,$this->root.'/public/'.$item['contact_sheet']))throw new RuntimeException('Folha visual não salva.');
        imagedestroy($sheet);$item['state']='ready';$this->validate($production,$item);return $item;
    }

    /** @param array<string,mixed> $item */
    public function validate(PDO $production,array $item): void
    {
        if($item['state']!=='ready')throw new RuntimeException('Reel ainda não validável.');
        $this->sources($production,$item);
        $video=$this->guard->asset((string) $item['video']);
        if(hash_file('sha256',$video)!==$item['video_sha256'] || hash_file('sha256',$this->guard->asset((string) $item['cover']))!==$item['cover_sha256'])throw new RuntimeException('Saída mudou.');
        $this->media->assertVideo($video,(int) $item['duration']);
        $size=getimagesize($this->guard->asset((string) $item['cover']));
        if($size===false || $size[0]!==1080 || $size[1]!==1920)throw new RuntimeException('Capa inválida.');
    }

    /** @param array<string,mixed> $manifest @return array<string,mixed> */
    public function apply(PDO $production,array $manifest,string $journalPath): array
    {
        if($manifest['version']!==$this->version())throw new RuntimeException('Código/fontes mudaram.');
        if(array_column($manifest['items'],'id')!==self::IDS)throw new RuntimeException('Escopo do lote mudou.');
        foreach($manifest['items'] as $item)$this->validate($production,$item);
        $journal=['state'=>'intent','items'=>[]];
        foreach($manifest['items'] as $item){$item['apply_time']=date('Y-m-d H:i:s');$journal['items'][]=['id'=>$item['id'],'before'=>$item['before'],'after'=>$this->guard->expectedAfter($item),'previous_media_sha256'=>$item['previous_media_sha256']];}
        // Durable intent precedes the single database commit. Old files stay intact.
        self::saveJournal($journalPath,$journal);
        $this->local->beginTransaction();
        try{
            foreach($journal['items'] as $entry){$current=$this->guard->snapshot((int) $entry['id'],true);self::eligible($current['post']);if($current!==$entry['before'])throw new RuntimeException('Post mudou: troca inteira recusada.');}
            foreach($journal['items'] as $entry)$this->replace($entry['after']);
            foreach($journal['items'] as $entry){if($this->guard->snapshot((int) $entry['id'])!==$entry['after'])throw new RuntimeException('Estado posterior divergente.');}
            $this->local->commit();
        }catch(Throwable $e){$this->local->rollBack();throw $e;}
        $journal['state']='applied';self::saveJournal($journalPath,$journal);return $journal;
    }

    /** @param array<string,mixed> $snapshot */
    private function replace(array $snapshot): void
    {
        $post=$snapshot['post'];$media=$snapshot['media'][0];
        $this->local->prepare('UPDATE instagram_post_media SET caminho=?,url_publica=?,largura=?,altura=?,duracao_s=? WHERE id=? AND post_id=?')->execute([$media['caminho'],$media['url_publica'],$media['largura'],$media['altura'],$media['duracao_s'],$media['id'],$post['id']]);
        $this->local->prepare('UPDATE instagram_posts SET video_rendered_path=?,render_status=?,audio_duration_seconds=?,atualizado_em=? WHERE id=?')->execute([$post['video_rendered_path'],$post['render_status'],$post['audio_duration_seconds'],$post['atualizado_em'],$post['id']]);
    }

    /** @param array<string,mixed> $journal */
    public function rollback(array $journal): void
    {
        $this->local->beginTransaction();
        try{
            foreach($journal['items'] as $entry){$current=$this->guard->snapshot((int) $entry['id'],true);self::eligible($current['post']);if($current!==$entry['after'])throw new RuntimeException('Estado mudou: rollback recusado.');if(hash_file('sha256',$this->guard->asset((string) $entry['before']['media'][0]['caminho']))!==$entry['previous_media_sha256'])throw new RuntimeException('Vídeo anterior mudou: rollback recusado.');}
            foreach($journal['items'] as $entry)$this->replace($entry['before']);
            $this->local->commit();
        }catch(Throwable $e){$this->local->rollBack();throw $e;}
    }

    /** @param array<string,mixed> $journal */
    private static function saveJournal(string $path,array $journal): void
    {
        $temp=$path.'.tmp-'.bin2hex(random_bytes(4));$file=fopen($temp,'x');
        if($file===false)throw new RuntimeException('Journal não criado.');
        $json=json_encode($journal,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
        try{if(fwrite($file,$json)!==strlen($json) || !fflush($file) || !fsync($file))throw new RuntimeException('Journal não foi persistido.');}finally{fclose($file);}
        if(!rename($temp,$path))throw new RuntimeException('Journal não confirmado.');
    }
}
