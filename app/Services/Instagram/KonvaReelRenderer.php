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
        $curated=$meta['editorial_spec'] ?? null;
        if (is_array($curated) && is_int($curated['duration'] ?? null)) {
            $duration=$curated['duration'];
            if ($duration<16 || $duration>60) { throw new RuntimeException('Duração do roteiro fora dos limites editoriais.'); }
            return $duration;
        }
        $title=self::clean((string) ($meta['titulo'] ?? ''));
        // Legacy estimate for planning only; spec() still requires a curated script.
        return min(30,max(24,(int) ceil((count(preg_split('/\s+/u',$title) ?: []) / 2.5 + 1.1)*4)));
    }

    public static function clean(string $text): string
    {
        $text=(string) preg_replace('/\[\[(.*?)\]\]/u','$1',$text);
        return trim((string) preg_replace('/\s+/u',' ',html_entity_decode(strip_tags($text),ENT_QUOTES|ENT_HTML5,'UTF-8')));
    }

    /** @param array<string,mixed> $meta @return array<string,mixed> */
    public function spec(string $cover, string $audio, array $meta, int $start, ?int $seconds=null): array
    {
        // A summary is not a storyboard. Fail before rendering unless a curated
        // scene sequence was explicitly supplied by the editorial workflow.
        $curated = $meta['editorial_spec'] ?? null;
        if (!is_array($curated) || ($curated['editorial'] ?? '') !== 'manual'
            || !is_array($curated['scenes'] ?? null) || count($curated['scenes']) < 2) {
            throw new RuntimeException('Reel sem roteiro individual revisado. Prepare cenas, imagens, tempos e áudio antes de renderizar.');
        }
        return $curated;
    }

    public function asset(string $path): string
    {
        $base=realpath($this->root.'/public');
        $full=realpath(preg_match('~^(?:[A-Za-z]:[\\\\/]|/)~',$path)?$path:$this->root.'/public/'.ltrim($path,'/\\'));
        if($base===false || $full===false || !is_file($full) || !str_starts_with(strtolower(str_replace('\\','/',$full)),strtolower(str_replace('\\','/',$base)).'/'))throw new RuntimeException('Asset editorial ausente ou fora de public.');
        return $full;
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
