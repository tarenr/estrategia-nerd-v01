<?php
declare(strict_types=1);

/**
 * Correção local, limitada aos posts 77–80 / Instagram 229–234.
 * --prepare: mídias/specs; --render: engine oficial; --apply: transação local;
 * --verify: integridade; --gallery: prévias geradas da mesma fonte.
 * Nenhuma etapa publica, sincroniza outro ambiente ou altera agendamentos.
 */
if (PHP_SAPI !== 'cli') { exit(1); }
$root = dirname(__DIR__);
chdir($root);
require $root . '/vendor/autoload.php';
$posts = require __DIR__ . '/restructure-posts.php';
$backup = $root . '/storage/correcao-leva02-20261010-codex';
$reelDir = 'uploads/reels/leva02-bloco1';
$cardDir = 'uploads/carrosseis/leva02-bloco1';
$mode = $argv[1] ?? '--help';
$reelIds = [229, 230, 231, 232, 233, 234];
$revisionBackup = $root . '/storage/correcao-leva02-reels-20261010';
$audioIds = [229=>21,230=>25,231=>22,232=>34,233=>28,234=>15];
function reelName(int $id): string { return 'reel-'.$id.(in_array($id,[229,231],true)?'-revisado':'-final'); }
$links = [229 => 77, 230 => 77, 231 => 78, 232 => null, 233 => 79, 234 => null];
$captions = [
229 => "Você desligou o jogo. O corredor de casa continuou assustador. 😅🔦\n\nSilent Hill 2, Signalis e Alan Wake 2 constroem tensão de jeitos diferentes: névoa, recursos limitados e uma realidade que não parece confiável. E sim, também têm sustos.\n\nQual jogo te fez conferir o que estava atrás de você?\n\n#EstratégiaNerd #PerrengueNerd #JogosDeTerror #SilentHill2 #Signalis #AlanWake2",
230 => "O mesmo corredor pode assustar de jeitos diferentes. 👻\n\nQuatro abordagens para entender o terror nos games. Elas se misturam: um jogo pode trabalhar a sobrevivência e o psicológico na mesma cena.\n\n📌 Salve para escolher sua próxima experiência de terror.\n\n#EstratégiaNerd #JogosDeTerror #SurvivalHorror #TerrorPsicológico #Games",
231 => "Você ainda não viu o inimigo. Mas já ouviu alguma coisa. 🎧\n\nO chiado do rádio, o detector e os ruídos do cenário oferecem pistas antes da imagem. O silêncio muda o contraste; o áudio espacial pode ajudar a perceber direções.\n\nSem porcentagem mágica: o efeito depende do jogo, da cena e da reprodução.\n\nQual som de jogo ficou na sua memória?\n\n#EstratégiaNerd #DesignDeSom #JogosDeTerror #SilentHill2 #AlienIsolation #DeadSpace",
232 => "O squad tem cinco pessoas e seis horários incompatíveis. 🎮🗓️\n\n“Hoje dá?”\n“Depois do trabalho.”\n“Antes da aula.”\n“Eu só vi a mensagem agora.”\n\nCena fictícia, perrengue conhecido: o grupo existe. A partida virou reunião de agenda. 😅\n\nMarque quem sempre aparece quando todo mundo já saiu.\n\n#EstratégiaNerd #PerrengueNerd #HumorGamer #Squad #RotinaGamer",
233 => "Meia hora livre não precisa virar uma promessa de terminar a run. ⏳🎮\n\nBalatro e Slay the Spire ajudam quem quer pensar por etapas; Celeste permite praticar um trecho. Em Hades e Vampire Survivors, confira o ponto de parada e as condições da tentativa.\n\nO tempo também inclui menus e decisões. Salvar, sair e suspender o aparelho não são a mesma coisa.\n\n📌 Salve estas ideias para uma noite corrida.\n\n#EstratégiaNerd #DicasDeGames #SessõesCurtas #Balatro #Celeste #Hades",
234 => "“É a última partida.” O relógio ouviu e pediu uma segunda opinião. ⏰😅\n\nPrimeiro você queria fechar a noite com uma vitória. Depois decidiu que precisava recuperar a derrota. Quando percebeu, o horário também tinha entrado na disputa.\n\nQual jogo te faz negociar esse “só mais uma”?\n\n#EstratégiaNerd #PerrengueNerd #SóMaisUmaPartida #HumorGamer #RotinaGamer",
];
$path = static fn(int $id, int $n): string => 'public/uploads/posts/' . $posts[$id]['slug'] . '/images/img-' . sprintf('%03d', $n) . '.webp';
$beat = static fn(string $eyebrow, string $title, string $body, string $image): array => [
    'eyebrow'=>$eyebrow, 'title'=>$title, 'body'=>$body, 'image'=>$image,
    'framing'=>['mode'=>'contain','motion'=>'still'],
];
$specs = [
229 => ['articleId'=>77, 'articleTitle'=>$posts[77]['titulo'], 'tone'=>'tech', 'layout'=>'showcase', 'collection'=>'PERRENGUE NERD · TERROR',
'audio'=>'public/uploads/audio/pixabay_10376.mp3','audioStart'=>5,
'footer'=>'Qual jogo te fez olhar para trás?', 'closingLabel'=>'CONTA NOS COMENTÁRIOS',
'scenes'=>[
$beat('Depois da jogatina','O CORREDOR VIROU FASE','Você desligou o jogo. Mas continuou conferindo cada sombra.',$path(77,1)),
$beat('Silent Hill 2','VOCÊ OUVE. NÃO ENXERGA.','A névoa limita a visão. A estática avisa que há algo por perto.',$path(77,1)),
$beat('Signalis','O QUE VOCÊ VAI LEVAR?','Uma ferramenta ou mais munição? Preparar a volta também cria tensão.',$path(77,2)),
$beat('Alan Wake 2','A REALIDADE NÃO AJUDA','Investigação e horror: nem tudo funciona como você esperava.',$path(77,3)),
]],
231 => ['articleId'=>78,'articleTitle'=>$posts[78]['titulo'],'tone'=>'tech','layout'=>'guide','collection'=>'UTILIDADE · SOM NO TERROR',
'audio'=>'public/uploads/audio/pixabay_136824.mp3','audioStart'=>10,
'footer'=>'Qual som de jogo ficou na sua memória?', 'closingLabel'=>'CONTA NOS COMENTÁRIOS',
'scenes'=>[
$beat('Antes do contato visual','TEM ALGO AÍ','Um ruído fora da câmera pode mudar a leitura de uma sala.',$path(78,1)),
$beat('Pistas do ambiente','ESCUTAR TAMBÉM É JOGAR','O detector e os passos ajudam você a levantar hipóteses.',$path(78,2)),
$beat('Contraste','QUANDO O SOM SOME','Retirar uma camada de áudio pode destacar o próximo ruído.',$path(78,3)),
$beat('Áudio espacial','DE ONDE VEIO?','Direção é uma pista. O efeito depende do jogo e da reprodução.',$path(78,3)),
]],
232 => ['articleId'=>0,'articleTitle'=>'O squad existe. O horário, não.','tone'=>'tech','layout'=>'chronicle','collection'=>'PERRENGUE NERD · CENA FICTÍCIA',
'audio'=>'public/uploads/audio/pixabay_9503.mp3','audioStart'=>15,
'footer'=>'Marque quem chega quando todo mundo saiu.', 'closingLabel'=>'O GRUPO RECONHECE ESSA CENA?',
'scenes'=>[
$beat('O convite','HOJE O SQUAD FECHA','Cinco pessoas no grupo. Uma partida parecia um plano simples.','public/'.$reelDir.'/squad-imagegen.png'),
$beat('As respostas','EU POSSO DEPOIS','Um sai do trabalho. Outro entra na aula. Os horários não se encontram.','public/'.$reelDir.'/squad-imagegen.png'),
$beat('A negociação','E AMANHÃ, ENTÃO?','A partida virou uma conversa sobre agenda. O grupo segue otimista.','public/'.$reelDir.'/squad-imagegen.png'),
$beat('No dia seguinte','CHEGUEI. CADÊ VOCÊS?','A mensagem aparece quando todo mundo já desligou.','public/'.$reelDir.'/squad-imagegen.png'),
]],
234 => ['articleId'=>0,'articleTitle'=>'Só mais uma partida','tone'=>'tech','layout'=>'chronicle','collection'=>'PERRENGUE NERD · SÓ MAIS UMA',
'audio'=>'public/uploads/audio/pixabay_519731.mp3','audioStart'=>8,
'footer'=>'Qual jogo te faz negociar com o relógio?', 'closingLabel'=>'CONTA NOS COMENTÁRIOS',
'scenes'=>[
$beat('A promessa','É A ÚLTIMA PARTIDA','Você falou com confiança. O relógio acreditou por alguns segundos.','public/'.$reelDir.'/relogio-imagegen.png'),
$beat('Depois da derrota','NÃO POSSO PARAR ASSIM','Agora a meta é recuperar. A última partida ganhou uma continuação.','public/'.$reelDir.'/relogio-imagegen.png'),
$beat('Depois da vitória','AGORA EU EMBALO','A vitória chegou. E trouxe um novo argumento para ficar.','public/'.$reelDir.'/relogio-imagegen.png'),
$beat('O despertador','A MANHÃ NÃO NEGOCIA','O jogo ainda oferece revanche. O seu horário já pediu pausa.','public/'.$reelDir.'/relogio-imagegen.png'),
]],
];
$specs[230] = ['articleId'=>77,'articleTitle'=>$posts[77]['titulo'],'tone'=>'tech','layout'=>'guide','collection'=>'UTILIDADE · TIPOS DE TERROR','audio'=>'public/uploads/audio/pixabay_248213.mp3','audioStart'=>6,'duration'=>28,'beatStarts'=>[0,7,14,21],'footer'=>'Salve para escolher seu próximo jogo.','closingLabel'=>'QUATRO JEITOS DE SENTIR MEDO','scenes'=>[
$beat('Silent Hill 2','A DÚVIDA ASSUSTA','O terror psicológico trabalha a incerteza sobre pessoas e lugares.',$path(77,1)),
$beat('Signalis','CADA ITEM É UMA ESCOLHA','Na sobrevivência, preparar o inventário também participa da tensão.',$path(77,2)),
$beat('Dead Space','AÇÃO NÃO ELIMINA O MEDO','O combate divide espaço com ruídos, isolamento e vulnerabilidade.',$path(78,3)),
$beat('Alan Wake 2','O COTIDIANO ESTRANHA','Uma realidade pouco confiável pode deixar cada encontro desconfortável.',$path(77,3)),
]];
$specs[233] = ['articleId'=>79,'articleTitle'=>$posts[79]['titulo'],'tone'=>'tech','layout'=>'guide','collection'=>'UTILIDADE · SESSÕES CURTAS','audio'=>'public/uploads/audio/pixabay_151007.mp3','audioStart'=>5,'duration'=>30,'beatStarts'=>[0,8,15,22],'footer'=>'Salve estas ideias para uma noite corrida.','closingLabel'=>'SESSÃO CURTA NÃO É RUN COMPLETA','scenes'=>[
$beat('Balatro e Slay the Spire','CARTAS POR ETAPAS','Uma sequência de decisões pode ser a meta. Confira como continuar depois.',$path(79,1)),
$beat('Vampire Survivors','AÇÃO COM UM LIMITE','Modo, estágio e menus mudam a duração. Escolha seu ponto de parada.','public/uploads/posts/real_gameplay/vampire_gameplay.jpg'),
$beat('Celeste','UM TRECHO DE CADA VEZ','Praticar uma tela já é uma sessão. Não precisa fechar o capítulo.',$path(79,2)),
$beat('Hades','CONFIRA ANTES DE SAIR','Salvar o progresso e abandonar a tentativa são ações diferentes.',$path(79,3)),
]];
foreach ($specs as &$spec) { $spec += ['editorial'=>'manual','duration'=>24,'beatStarts'=>[0,6,12,18]]; } unset($spec);
$cards = [
230 => [
['QUE TIPO DE TERROR TE PEGA?','Quatro abordagens que podem se misturar no mesmo jogo.','ARRASTE PARA CONHECER',$path(77,1)],
['O PSICOLÓGICO','Silent Hill 2: dúvidas sobre personagens e lugares acompanham a exploração.','INCERTEZA E INTERPRETAÇÃO',$path(77,1)],
['A SOBREVIVÊNCIA','Signalis: recursos e escolhas do inventário participam da tensão.','PREPARAR TAMBÉM É JOGAR',$path(77,2)],
['A AÇÃO SOB PRESSÃO','Dead Space: enfrentar ameaças não elimina o desconforto do ambiente.','COMBATE E VULNERABILIDADE',$path(78,3)],
['O ESTRANHAMENTO','Alan Wake 2: o cotidiano ganha regras que deixam de parecer estáveis.','SALVE PARA ESCOLHER SEU PRÓXIMO JOGO',$path(77,3)],
],
233 => [
['MEIA HORA LIVRE?','Escolha uma meta pequena e um ponto seguro para encerrar.','SESSÃO CURTA NÃO É RUN COMPLETA',$path(79,1)],
['CARTAS POR ETAPAS','Balatro e Slay the Spire: algumas decisões já podem ser a sessão de hoje.','CONFIRA COMO CONTINUAR DEPOIS',$path(79,1)],
['AÇÃO COM UM LIMITE','Vampire Survivors: estágio, modo e menus mudam o tempo da sessão.','NÃO PROMETA TERMINAR EM 30 MINUTOS','public/uploads/posts/real_gameplay/vampire_gameplay.jpg'],
['UM TRECHO DE CADA VEZ','Celeste: praticar uma tela é uma meta possível, mesmo sem fechar o capítulo.','AJUSTE A EXPERIÊNCIA AO SEU RITMO',$path(79,2)],
['ANTES DE SAIR','Hades: confira o menu. Sair com progresso salvo e abandonar a tentativa são ações diferentes.','SALVE ESTAS IDEIAS PARA UMA NOITE CORRIDA',$path(79,3)],
],
];
function writeJson(string $file, array $data): void {
    $parent=dirname($file); if(!is_dir($parent)&&!mkdir($parent,0755,true))throw new RuntimeException('Falha ao criar pasta.');
    if(file_put_contents($file,json_encode($data,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR))===false)throw new RuntimeException('Falha ao gravar JSON.');
}
function convertImage(string $from,string $to): void {
    if(!is_file($from))throw new RuntimeException('Fonte ausente: '.$from);
    $info=getimagesize($from); if(!$info)throw new RuntimeException('Fonte inválida: '.$from);
    $src=match($info['mime']){'image/jpeg'=>imagecreatefromjpeg($from),'image/png'=>imagecreatefrompng($from),'image/webp'=>imagecreatefromwebp($from),default=>false};
    if(!$src)throw new RuntimeException('Formato sem suporte.');
    $w=imagesx($src);$h=imagesy($src);$cw=min($w,$h*16/9);$ch=$cw*9/16;
    $dst=imagecreatetruecolor(1200,675);
    imagecopyresampled($dst,$src,0,0,(int)(($w-$cw)/2),(int)(($h-$ch)/2),1200,675,(int)$cw,(int)$ch);
    if(!imagewebp($dst,$to,90))throw new RuntimeException('Falha ao converter.');
    imagedestroy($src);imagedestroy($dst);
}
function localPdo(): PDO {
    $config=require dirname(__DIR__).'/config/database.php';
    if(!in_array($config['host'],['127.0.0.1','localhost'],true)||$config['database']!=='estrategia-nerd')throw new RuntimeException('Execução permitida somente no banco local.');
    return new PDO('mysql:host='.$config['host'].';port='.$config['port'].';dbname='.$config['database'].';charset=utf8mb4',$config['username'],$config['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
}
function worker(array $args): void {
    $pipes=[];$process=proc_open($args,[0=>['pipe','r'],1=>STDOUT,2=>STDERR],$pipes,dirname(__DIR__));
    if(!is_resource($process))throw new RuntimeException('Falha ao iniciar worker.');
    fclose($pipes[0]);if(proc_close($process)!==0)throw new RuntimeException('Worker falhou.');
}
function ffBinary(string $name): string {
    $configured=getenv(strtoupper($name).'_BIN');if(is_string($configured)&&is_file($configured))return $configured;
    $matches=glob((string)getenv('LOCALAPPDATA').'/Microsoft/WinGet/Packages/Gyan.FFmpeg_*/ffmpeg-*-full_build/bin/'.$name.'.exe') ?: [];
    if(!$matches)throw new RuntimeException('Informe '.strtoupper($name).'_BIN com caminho do executável existente.');
    return $matches[0];
}
function validateReel(int $id,string $reelDir,array $specs): array {
    $file='public/'.$reelDir.'/'.reelName($id).'.mp4';
    $manifestFile=$file.'.manifest.json';
    if(!is_file($file)||!is_file($manifestFile))throw new RuntimeException('Reel sem arquivo/manifesto: '.$id);
    $manifest=json_decode((string)file_get_contents($manifestFile),true,512,JSON_THROW_ON_ERROR);
    if($manifest['videoSha256']!==hash_file('sha256',$file)||$manifest['spec']!==$specs[$id])throw new RuntimeException('Reel diverge do roteiro: '.$id);
    foreach($manifest['sources'] as $src)if($src['sha256']!==hash_file('sha256',$src['path']))throw new RuntimeException('Imagem mudou depois da renderização.');
    if($manifest['manualRenderer']['sha256']!==hash_file('sha256','scripts/reels-review/scene.mjs'))throw new RuntimeException('Renderer mudou depois da renderização.');
    return $manifest;
}
try {
    if(!is_dir($backup)||!is_file($backup.'/posts.json')||!is_file($backup.'/instagram_posts.json'))throw new RuntimeException('Backup prévio obrigatório.');
    if($mode==='--backup-reels'){
        $pdo=localPdo();
        foreach(['posts'=>'id IN(77,78,79,80)','instagram_posts'=>'id IN(229,230,231,232,233,234)','instagram_post_media'=>'post_id IN(229,230,231,232,233,234)'] as $table=>$where){
            $file=$revisionBackup.'/'.$table.'.json';
            if(!is_file($file))writeJson($file,$pdo->query('SELECT * FROM '.$table.' WHERE '.$where.' ORDER BY id')->fetchAll());
        }
        echo "Backup de dados anterior à conversão preservado.\n";
    }elseif($mode==='--prepare'){
        if(!is_dir($backup.'/specs'))mkdir($backup.'/specs',0755,true);
        foreach($specs as $id=>$spec)writeJson($backup.'/specs/reel-'.$id.'.json',$spec);
        $manifest=[];
        foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($backup.'/files',FilesystemIterator::SKIP_DOTS)) as $file){
            if($file->isFile())$manifest[]=['path'=>substr($file->getPathname(),strlen($backup.'/files/')),'bytes'=>$file->getSize(),'sha256'=>hash_file('sha256',$file->getPathname())];
        }
        writeJson($backup.'/files-manifest.json',$manifest);
        echo "Preparados: 2 ilustrações ImageGen e 6 roteiros. Backup original: ".count($manifest)." arquivos.\n";
    }elseif($mode==='--render'){
        foreach($reelIds as $id){
            $out=$root.'/public/'.$reelDir.'/'.reelName($id).'.mp4';
            if(is_file($out)){validateReel($id,$reelDir,$specs);echo "Reel $id já corresponde ao roteiro.\n";continue;}
            echo "Renderizando Reel $id...\n";
            worker(['node',$root.'/scripts/reels-konva/editorial.mjs','--spec',$backup.'/specs/reel-'.$id.'.json','--output',$out,'--ffmpeg',ffBinary('ffmpeg'),'--ffprobe',ffBinary('ffprobe')]);
            validateReel($id,$reelDir,$specs);
        }
    }elseif($mode==='--inspect'){
        $pdo=localPdo();
        foreach($pdo->query('SHOW COLUMNS FROM instagram_audio_tracks') as $column)echo $column['Field']."\n";
        foreach($pdo->query('SELECT * FROM instagram_audio_tracks WHERE id IN(15,21,22,34)') as $audio){
            $safe=array_filter($audio,static fn($key)=>!preg_match('/token|secret|key|password/i',(string)$key),ARRAY_FILTER_USE_KEY);
            echo json_encode($safe,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)."\n";
        }
    }elseif($mode==='--apply'){
        foreach($reelIds as $id)validateReel($id,$reelDir,$specs);
        $pdo=localPdo();$postRepo=new App\Repositories\PostRepository($pdo);$igRepo=new App\Repositories\InstagramPostRepository($pdo);
        $pdo->beginTransaction();
        try{
            // Bloqueia apenas o lote. Não modifica status, datas nem campos da publicação.
            $current=$pdo->query('SELECT * FROM instagram_posts WHERE id IN(229,230,231,232,233,234) ORDER BY id FOR UPDATE')->fetchAll();
            if(count($current)!==6)throw new RuntimeException('Lote do Instagram incompleto.');
            foreach($current as $row)if($row['ig_media_id']!==null||$row['creation_id']!==null||!in_array($row['status'],['agendado','rascunho','erro'],true))throw new RuntimeException('Lote já entrou em publicação; nenhuma atualização realizada.');
            $currentPosts=$pdo->query('SELECT * FROM posts WHERE id IN(77,78,79,80) ORDER BY id FOR UPDATE')->fetchAll();
            if(count($currentPosts)!==4)throw new RuntimeException('Lote de artigos incompleto.');
            $appliedBefore=is_file($backup.'/applied.json');
            if(!$appliedBefore){
                $baselinePosts=json_decode((string)file_get_contents($backup.'/posts.json'),true,512,JSON_THROW_ON_ERROR);
                $baselineIg=json_decode((string)file_get_contents($backup.'/instagram_posts.json'),true,512,JSON_THROW_ON_ERROR);
                if($currentPosts!=$baselinePosts||$current!=$baselineIg)throw new RuntimeException('Dados mudaram desde o backup. Revisar antes de aplicar.');
            }
            foreach($current as $row){
                $id=(int)$row['id'];$media=$igRepo->findMediaByPostId($id);
                $isReel=in_array($id,$reelIds,true);
                $expected=$isReel?1:5;
                if(in_array($id,[230,233],true) && count($media)===5){
                    $saved=json_decode((string)file_get_contents($revisionBackup.'/instagram_post_media.json'),true,512,JSON_THROW_ON_ERROR);
                    $saved=array_values(array_filter($saved,static fn($m)=>(int)$m['post_id']===$id));
                    if($saved!=$media)throw new RuntimeException('Mídias mudaram desde o backup.');
                    $igRepo->deleteMediaByPostId($id);$media=[];
                }
                if($media && count($media)!==$expected)throw new RuntimeException('Cadastro mudou inesperadamente: '.$id);
                for($n=1;$n<=$expected;$n++){
                    $rel=$isReel?$reelDir.'/'.reelName($id).'.mp4':$cardDir.'/carrossel-'.$id.'-card-'.sprintf('%03d',$n).'.jpg';
                    if(!is_file('public/'.$rel))throw new RuntimeException('Mídia ausente: '.$rel);
                    $entry=['ordem'=>$n,'tipo_arquivo'=>$isReel?'video':'imagem','caminho'=>$rel,'url_publica'=>null,'largura'=>1080,'altura'=>$isReel?1920:1080,'duracao_s'=>$isReel?$specs[$id]['duration']:null];
                    if(!$media){$igRepo->addMedia($id,$entry);}else{
                        $old=$media[$n-1];if($old['tipo_arquivo']!==$entry['tipo_arquivo']||(int)$old['ordem']!==$n)throw new RuntimeException('Tipo/ordem inesperados.');
                        $igRepo->updateMediaFile((int)$old['id'],$rel,null,1080,$entry['altura']);
                    }
                }
                preg_match_all('/#[\p{L}\p{N}_]+/u',$captions[$id],$tags);
                $changes=['tipo'=>'reels','audio_track_id'=>$audioIds[$id],'legenda'=>$captions[$id],'hashtags_count'=>count($tags[0]),'post_blog_id'=>$links[$id],'render_status'=>$isReel?'ready':'idle','video_rendered_path'=>$isReel?$reelDir.'/'.reelName($id).'.mp4':null];
                if($isReel){$changes['audio_start_seconds']=$specs[$id]['audioStart'];$changes['audio_duration_seconds']=$specs[$id]['duration'];}
                $igRepo->update($id,array_replace($row,$changes));
            }
            $pdo->commit();
        }catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();throw $error;}
        writeJson($backup.'/applied.json',['applied_at'=>date(DATE_ATOM),'blog_ids'=>array_keys($posts),'instagram_ids'=>array_keys($captions)]);
        echo "Transação concluída no banco LOCAL: 6 Reels com música e 6 mídias. Artigos mantidos. Agendamentos preservados.\n";
    }elseif($mode==='--gallery'){
        $style='body{margin:0;background:#0b0f19;color:#e5e7eb;font:18px/1.65 system-ui,sans-serif}main{max-width:1000px;margin:0 auto;padding:32px 20px}a{color:#62dfe9}h1,h2{line-height:1.22;color:#fff}h2{margin-top:2em}figure{margin:28px 0}img{max-width:100%;height:auto;border-radius:12px}figcaption{font-size:14px;color:#aab9ce;margin-top:8px}video{display:block;width:min(100%,380px);margin:auto;background:#111;border-radius:12px}.cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:16px}.panel{margin:38px 0;padding:20px;border:1px solid #334155;border-radius:16px}.caption{white-space:pre-wrap}.meta{color:#aab9ce;font-size:14px}.content-block{padding:22px;border:1px solid #475569;border-radius:12px}.content-block-label{font-weight:700;color:#62dfe9}';
        $esc=static fn(string $value):string=>htmlspecialchars($value,ENT_QUOTES,'UTF-8');
        $page=static fn(string $title,string $body):string=>'<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.$esc($title).'</title><style>'.$style.'</style></head><body><main>'.$body.'</main></body></html>';
        foreach(['leva02-bloco1','leva02-bloco1-v2','leva02-bloco1-v3'] as $gallery){
            $dir='public/uploads/previews/'.$gallery;
            $index='<h1>Estratégia Nerd · Leva 02 · Bloco 1</h1><p>Prévia revisada dos artigos e peças do Instagram. Os vídeos abaixo são os arquivos corrigidos.</p>';
            foreach($posts as $id=>$post){
                $html=str_replace('src="uploads/','src="../../',$post['conteudo']);
                $html=str_replace('href="/post/'.$posts[78]['slug'].'"','href="post-78.html"',$html);
                $body='<p><a href="index.html">← Voltar à galeria</a></p><p class="meta">'.($post['categoria_post_id']===3?'Games':'Dicas').' · '.($id===77?'Seleção comentada':($id===78?'Explicativo':($id===79?'Recomendações':'Guia prático'))).'</p><h1>'.$esc($post['titulo']).'</h1><p>'.$esc($post['resumo']).'</p><img src="../../'.substr($post['imagem_capa'],8).'" alt="'.$esc($post['titulo']).'"><article class="article-content">'.$html.'</article>';
                if(file_put_contents($dir.'/post-'.$id.'.html',$page($post['titulo'],$body))===false)throw new RuntimeException('Falha na prévia.');
                $index.='<section class="panel"><h2><a href="post-'.$id.'.html">'.$esc($post['titulo']).'</a></h2><p>'.$esc($post['resumo']).'</p></section>';
            }
            foreach($captions as $id=>$caption){
                $index.='<section class="panel"><h2>Instagram #'.$id.'</h2>';
                if(in_array($id,$reelIds,true)){
                    $base='../../'.substr($reelDir,8).'/'.reelName($id);
                    $index.='<video controls preload="metadata" poster="'.$base.'.jpg"><source src="'.$base.'.mp4" type="video/mp4"></video>';
                }else{
                    $index.='<div class="cards">';
                    for($n=1;$n<=5;$n++)$index.='<figure><img src="../../'.substr($cardDir,8).'/carrossel-'.$id.'-card-'.sprintf('%03d',$n).'.jpg" alt="Carrossel '.$id.', card '.$n.'"><figcaption>Card '.$n.' de 5</figcaption></figure>';
                    $index.='</div>';
                }
                $index.='<p class="caption">'.$esc($caption).'</p></section>';
            }
            if(file_put_contents($dir.'/index.html',$page('Leva 02 · Bloco 1 · Galeria revisada',$index))===false)throw new RuntimeException('Falha na galeria.');
        }
        echo "Três galerias reconciliadas: 15 HTMLs gerados de um único payload.\n";
    }elseif($mode==='--verify'){
        $pdo=localPdo();$repo=new App\Repositories\InstagramPostRepository($pdo);$blog=new App\Repositories\PostRepository($pdo);$checks=0;
        require_once $root.'/app/Support/Helpers.php';
        $_ENV['APP_URL']='http://localhost/estrategia-nerd/public';
        $service=new App\Services\Site\PostService($blog,new App\Repositories\ComentarioRepository($pdo),new App\Repositories\EstatisticaRepository($pdo));
        // Exercita a preparação real sem incrementar views/estatísticas.
        $prepareContent=new ReflectionMethod($service,'prepareContent');
        $assert=static function(bool $ok,string $message)use(&$checks):void{if(!$ok)throw new RuntimeException($message);$checks++;};
        $baselinePosts=json_decode((string)file_get_contents($backup.'/posts.json'),true,512,JSON_THROW_ON_ERROR);
        $baselineIg=json_decode((string)file_get_contents($backup.'/instagram_posts.json'),true,512,JSON_THROW_ON_ERROR);
        foreach($posts as $id=>$post){
            $actual=$blog->findAdminById($id);$assert(is_array($actual),'Artigo ausente.');
            $category=$pdo->query('SELECT p.categoria_id,p.categoria_post_id,c.nome FROM posts p JOIN categoria_post c ON c.id=p.categoria_post_id WHERE p.id='.(int)$id)->fetch();
            $assert(is_array($category) && (int)$category['categoria_id']===$post['categoria_post_id'] && (int)$category['categoria_post_id']===$post['categoria_post_id'],'Categorias sem correspondência.');
            foreach($post as $key=>$value)$assert((string)$actual[$key]===(string)$value,'Artigo '.$id.' diverge em '.$key);
            foreach(['status','data_publicacao','autor_id','slug'] as $key)$assert($actual[$key]===$baselinePosts[$id-77][$key],'Campo preservado mudou: '.$key);
            foreach(['capa.webp','img-001.webp','img-002.webp','img-003.webp'] as $name){
                $file='public/uploads/posts/'.$post['slug'].'/images/'.$name;$info=getimagesize($file);
                $assert($info && $info[0]===1200 && $info[1]===675 && $info['mime']==='image/webp','Imagem fora do padrão: '.$file);
            }
            $dom=new DOMDocument();libxml_use_internal_errors(true);$dom->loadHTML('<?xml encoding="UTF-8">'.$actual['conteudo']);libxml_clear_errors();
            foreach($dom->getElementsByTagName('img') as $img)$assert(is_file('public/'.$img->getAttribute('src')) && trim($img->getAttribute('alt'))!=='','Imagem/alt ausente.');
            $prepared=$prepareContent->invoke($service,$actual['conteudo']);
            $rendered=new DOMDocument();$rendered->loadHTML('<?xml encoding="UTF-8">'.$prepared['html']);libxml_clear_errors();
            $assert($rendered->getElementsByTagName('img')->length===3,'Renderer oficial removeu uma imagem.');
            $assert(count($prepared['toc'])===$dom->getElementsByTagName('h2')->length,'Sumário oficial incompleto.');
            foreach($rendered->getElementsByTagName('img') as $img)$assert(str_starts_with($img->getAttribute('src'),'http://localhost/estrategia-nerd/public/uploads/posts/'.$post['slug'].'/images/'),'Renderer produziu caminho incorreto.');
            $assert(!preg_match('/80%|naturally|adrenaline|19\s*Hz|menos de 5 horas|my-6|bg-gray/iu',$actual['conteudo']),'Resíduo editorial antigo.');
            foreach(['leva02-bloco1','leva02-bloco1-v2','leva02-bloco1-v3'] as $gallery){
                $html=(string)file_get_contents('public/uploads/previews/'.$gallery.'/post-'.$id.'.html');
                $assert(str_contains($html,htmlspecialchars($post['titulo'],ENT_QUOTES,'UTF-8')) && str_contains($html,strip_tags(substr($post['conteudo'],0,strpos($post['conteudo'],'</p>')+4))),'Prévia desatualizada.');
            }
            preg_match_all('/[\p{L}\p{N}]+/u',strip_tags($post['conteudo']),$words);echo "Artigo $id: ".count($words[0])." palavras · ".$post['tipo_post']." · ".$post['categoria']."\n";
        }
        foreach($captions as $id=>$caption){
            $actual=$repo->findById($id);$assert(is_array($actual),'Instagram ausente.');
            $assert($actual['legenda']===$caption && $actual['post_blog_id']==$links[$id],'Legenda/vínculo divergente.');
            $before=$baselineIg[$id-229];
            foreach(['account_id','status','agendado_para','publicado_em','ig_media_id','creation_id','publish_phase','origin'] as $key)$assert($actual[$key]===$before[$key],'Campo preservado mudou: '.$key);
            $media=$repo->findMediaByPostId($id);$isReel=in_array($id,$reelIds,true);
            $assert($actual['tipo']==='reels' && (int)$actual['audio_track_id']===$audioIds[$id],'Tipo/trilha incorretos.');
            $assert(count($media)===($isReel?1:5),'Contagem de mídias incorreta.');
            $assert(App\Repositories\InstagramPostRepository::mediaRuleError($actual['tipo'],array_column($media,'tipo_arquivo'))===null,'Regra oficial de mídia rejeitada.');
            foreach($media as $i=>$entry){
                $assert((int)$entry['ordem']===$i+1 && is_file('public/'.$entry['caminho']),'Ordem/arquivo de mídia.');
                if(!$isReel){$info=getimagesize('public/'.$entry['caminho']);$assert($info && $info[0]===1080 && $info[1]===1080 && $info['mime']==='image/jpeg','Card fora do padrão.');}
            }
            if($isReel){
                $manifest=validateReel($id,$reelDir,$specs);
                $audio=$repo->findAudioTrack((int)$actual['audio_track_id']);
                $assert(is_array($audio) && 'public/'.$audio['arquivo_path']===$specs[$id]['audio'] && (int)$audio['ativo']===1,'Trilha do cadastro não corresponde ao vídeo.');
                $assert($manifest['audio']['sha256']===hash_file('sha256',$specs[$id]['audio']),'Arquivo de áudio mudou depois da renderização.');
                $assert($actual['render_status']==='ready' && $actual['video_rendered_path']===$media[0]['caminho'],'Cache de vídeo divergente.');
                $assert((int)$actual['audio_duration_seconds']===$specs[$id]['duration'] && (int)$actual['audio_start_seconds']===$manifest['audio']['start'],'Recorte de áudio divergente.');
            }
            preg_match_all('/#[\p{L}\p{N}_]+/u',$caption,$tags);$assert((int)$actual['hashtags_count']===count($tags[0]) && count($tags[0])>=4 && count($tags[0])<=6,'Hashtags fora do padrão.');
        }
        $assert(count(array_unique(array_column($specs,'audio')))===6,'Trilhas repetidas no lote.');
        $finalMedia=$pdo->query('SELECT * FROM instagram_post_media WHERE post_id IN(229,230,231,232,233,234) ORDER BY post_id,ordem')->fetchAll();
        writeJson($backup.'/media-after.json',$finalMedia);
        echo "$checks verificações de integridade OK.\n";
    }else{
        echo "Uso: php scripts/execute-full-plan-corrections.php --prepare|--render|--inspect|--apply|--gallery|--verify\n";
    }
}catch(Throwable $error){fwrite(STDERR,'ERRO: '.$error->getMessage()."\n");exit(1);}
