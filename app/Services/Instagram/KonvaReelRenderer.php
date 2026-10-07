<?php
declare(strict_types=1);
namespace App\Services\Instagram;

use RuntimeException;

/** Local-only PHP/Node bridge; no database, credentials or external generation APIs. */
final class KonvaReelRenderer
{
    public function __construct(private readonly string $root, private readonly string $ffmpeg='ffmpeg', private readonly string $ffprobe='ffprobe', private readonly string $node='node') {}

    /** @param array<string,mixed> $meta */
    public static function duration(array $meta): int
    {
        $title=self::clean((string) ($meta['titulo'] ?? ''));
        // Four uniform scenes; opening title must have enough reading time.
        return min(30,max(24,(int) ceil((count(preg_split('/\s+/u',$title) ?: []) / 2.5 + 1.1)*4)));
    }

    public static function clean(string $text): string
    {
        $text=(string) preg_replace('/\[\[(.*?)\]\]/u','$1',$text);
        return trim((string) preg_replace('/\s+/u',' ',html_entity_decode(strip_tags($text),ENT_QUOTES|ENT_HTML5,'UTF-8')));
    }

    private static function excerpt(string $text, int $words=15, int $chars=180): string
    {
        $items=preg_split('/\s+/u',self::clean($text)) ?: [];
        $value=implode(' ',array_slice($items,0,$words));
        while(mb_strlen($value)>$chars && count($items)>1){array_pop($items);$value=implode(' ',array_slice($items,0,$words));}
        return rtrim($value," .,;:") . (count(preg_split('/\s+/u',$text) ?: [])>count(preg_split('/\s+/u',$value) ?: [])?'…':'');
    }

    /** @param array<string,mixed> $meta @return array<string,mixed> */
    public function spec(string $cover, string $audio, array $meta, int $start, ?int $seconds=null): array
    {
        $title=self::clean((string) ($meta['titulo'] ?? ''));
        if($title==='')throw new RuntimeException('Artigo sem título para Reel editorial.');
        $summary=self::clean((string) ($meta['resumo'] ?? ''));
        if($summary==='')throw new RuntimeException('Artigo sem resumo: revise antes de gerar o Reel.');
        $category=mb_strtolower(trim((string) ($meta['categoria'] ?? '')));
        if(!in_array($category,['hardware','games','dicas'],true))$category='editorial';
        $images=[$this->asset($cover)];
        $more=glob(dirname($images[0]).'/img*') ?: [];
        sort($more,SORT_NATURAL);
        foreach($more as $path){if(is_file($path) && in_array(strtolower(pathinfo($path,PATHINFO_EXTENSION)),['png','jpg','jpeg','webp'],true))$images[]=$this->asset($this->relative($path));}
        $chunks=preg_split('/(?<=[.!?])\s+/u',$summary) ?: [$summary];
        if(count($chunks)<2){$words=preg_split('/\s+/u',$summary) ?: []; $middle=(int) ceil(count($words)/2);$chunks=[implode(' ',array_slice($words,0,$middle)),implode(' ',array_slice($words,$middle))];}
        $point1=self::excerpt($chunks[0]);
        $point2=self::excerpt(implode(' ',array_slice($chunks,1)));
        if($point2==='')$point2=self::excerpt($summary);
        $shortTitle=mb_strlen($title)<=110?$title:self::excerpt($title,30,107);
        $bodies=['Confira os pontos do artigo e continue a leitura no blog.',$point1,$point2,'Leia o artigo completo no Estratégia Nerd. Acesse pelo link na bio.'];
        $titles=[$shortTitle,'O QUE VOCÊ VAI ENCONTRAR','CONTINUE EXPLORANDO','LEIA O ARTIGO COMPLETO'];
        $labels=['EM FOCO','PRIMEIRO PONTO','OUTRO PONTO DO ARTIGO','CONTINUE NO BLOG'];
        $scenes=[];
        for($i=0;$i<4;$i++)$scenes[]=['eyebrow'=>$labels[$i],'title'=>$titles[$i],'body'=>$bodies[$i],
            'image'=>'public/'.$this->relative($images[min($i,count($images)-1)]),'imageFit'=>'contain','caption'=>'IMAGEM DO ARTIGO',
            'chapter'=>sprintf('%02d / %s',$i+1,$i===3?'LINK NA BIO':'ESTRATÉGIA NERD')];
        return ['articleId'=>(int) ($meta['id'] ?? 0),'articleTitle'=>$title,'category'=>$category,'duration'=>$seconds ?? self::duration($meta),
            'audio'=>'public/'.$this->relative($this->asset($audio)),'audioStart'=>$start,'scenes'=>$scenes];
    }

    public function asset(string $path): string
    {
        $base=realpath($this->root.'/public');
        $full=realpath(preg_match('~^(?:[A-Za-z]:[\\\\/]|/)~',$path)?$path:$this->root.'/public/'.ltrim($path,'/\\'));
        if($base===false || $full===false || !is_file($full) || !str_starts_with(strtolower(str_replace('\\','/',$full)),strtolower(str_replace('\\','/',$base)).'/'))throw new RuntimeException('Asset editorial ausente ou fora de public.');
        return $full;
    }

    private function relative(string $path): string
    {
        $base=realpath($this->root.'/public');
        if($base===false)throw new RuntimeException('Raiz pública ausente.');
        return substr(str_replace('\\','/',$path),strlen(str_replace('\\','/',$base))+1);
    }

    /** @param array<string,mixed> $spec @return array{video_path:string,canvas_path:string,duration:int} */
    public function renderSpec(array $spec, string $relativeOutput, ?callable $heartbeat = null): array
    {
        if(str_contains($relativeOutput,'..') || str_contains($relativeOutput,'\\') || !str_starts_with($relativeOutput,'uploads/reels/') || !str_ends_with($relativeOutput,'.mp4'))throw new RuntimeException('Saída editorial deve ficar em uploads/reels.');
        $runtime=$this->root.'/storage/previews/reels-konva/runtime';
        if(!is_dir($runtime) && !mkdir($runtime,0755,true) && !is_dir($runtime))throw new RuntimeException('Não foi possível preparar a configuração de renderização.');
        $input=$runtime.'/spec-'.bin2hex(random_bytes(8)).'.json';
        if(file_put_contents($input,json_encode($spec,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),LOCK_EX)===false)throw new RuntimeException('Não foi possível salvar o conteúdo do Reel.');
        $output=$this->root.'/public/'.$relativeOutput;
        $command=[$this->node,$this->root.'/scripts/reels-konva/editorial.mjs','--spec',$input,'--output',$output,'--ffmpeg',$this->ffmpeg,'--ffprobe',$this->ffprobe];
        $pipes=[];
        // Windows pipes can block stream_get_contents despite stream_set_blocking(false).
        // File descriptors keep the supervision/timeout loop running while Node works.
        $process=proc_open($command,[0=>['pipe','r'],1=>['file',$input.'.stdout','w'],2=>['file',$input.'.stderr','w']],$pipes,$this->root,null,['bypass_shell'=>true]);
        if(!is_resource($process))throw new RuntimeException('Não foi possível iniciar o renderizador Node local.');
        fclose($pipes[0]);
        $started=microtime(true);$lastHeartbeat=$started;$stdout='';$stderr='';$code=-1;$timedOut=false;$heartbeatError=null;
        do{
            $status=proc_get_status($process);
            if(!$status['running']){$code=$status['exitcode'];break;}
            if($heartbeat!==null && microtime(true)-$lastHeartbeat>3){
                try{$heartbeat();}catch(\Throwable $e){$heartbeatError=$e;}
                $lastHeartbeat=microtime(true);
            }
            if(microtime(true)-$started>330 || $heartbeatError!==null){
                $timedOut=true;
                if(PHP_OS_FAMILY==='Windows'){
                    $kill=proc_open(['taskkill','/PID',(string) $status['pid'],'/T','/F'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$killPipes,null,null,['bypass_shell'=>true]);
                    if(is_resource($kill)){foreach($killPipes as $pipe)fclose($pipe);proc_close($kill);}
                }else{proc_terminate($process);}
                break;
            }
            usleep(20000);
        }while(true);
        $closed=proc_close($process);if($code<0)$code=$closed;
        $stdout=(string) file_get_contents($input.'.stdout');$stderr=substr((string) file_get_contents($input.'.stderr'),-3000);
        if($heartbeatError!==null)throw new RuntimeException('Conexão interrompida durante a renderização; lote não aplicado.',0,$heartbeatError);
        if($timedOut || $code!==0)throw new RuntimeException($timedOut?'Renderização excedeu o limite; nada publicado.':'Falha no Reel Konva: '.trim($stderr));
        $response=json_decode(trim($stdout),true,512,JSON_THROW_ON_ERROR);
        if(!is_array($response) || !is_file($output) || !is_file(substr($output,0,-4).'.jpg') || abs((float) ($response['duration'] ?? 0)-(int) $spec['duration'])>0.12)throw new RuntimeException('Renderizador não confirmou vídeo/capa válidos.');
        return ['video_path'=>$relativeOutput,'canvas_path'=>substr($relativeOutput,0,-4).'.jpg','duration'=>(int) round((float) $response['duration'])];
    }

    /** @param array<string,mixed> $meta @return array{video_path:string,canvas_path:string,duration:int} */
    public function render(string $cover,string $audio,array $meta,int $start=0,?int $seconds=null,?string $output=null): array
    {
        return $this->renderSpec($this->spec($cover,$audio,$meta,$start,$seconds),$output ?? 'uploads/reels/konva/reel-'.date('Ymd-His').'-'.bin2hex(random_bytes(5)).'.mp4');
    }
}
