import {readFileSync,writeFileSync,mkdirSync,existsSync,copyFileSync,constants} from 'node:fs';
import {resolve,dirname} from 'node:path';
import {fileURLToPath} from 'node:url';
import {createHash} from 'node:crypto';
import {spawnSync} from 'node:child_process';
import {Canvas} from 'skia-canvas';
import {createScene,validateSpec} from '../reels-konva/scene.mjs';
import {renderVideo,inspectOutput} from '../reels-konva/render.mjs';
const root=resolve(dirname(fileURLToPath(import.meta.url)),'../..');
const run='correcao-instagram-20261010';
const evidence=resolve(root,'storage',run),out=resolve(root,'public/uploads/reels',run);
const catalogPath=resolve(root,'resources/reels-review/corrections-20261010.json');
const json=p=>JSON.parse(readFileSync(p,'utf8'));
const hash=p=>createHash('sha256').update(readFileSync(p)).digest('hex');
const save=c=>writeFileSync(catalogPath,JSON.stringify(c,null,2)+'\n');
const args=process.argv.slice(2),action=args[0],selected=args.includes('--id')?Number(args[args.indexOf('--id')+1]):null;
const command=(tool,argv)=>{const p=spawnSync(tool,argv,{encoding:'utf8',timeout:120000,windowsHide:true});if(p.error||p.status!==0)throw new Error(tool+': '+p.stderr.slice(-800));return p;};
const quadrants=[[0,0,.5,.5],[.5,0,.5,.5],[0,.5,.5,.5],[.5,.5,.5,.5]];
const still={mode:'contain',motion:'still'};
const scene=(eyebrow,title,body,image,framing=still)=>({eyebrow,title,body,image,framing});
if(action==='--prepare'){
 if(existsSync(catalogPath))throw new Error('Catálogo existente: preserve a revisão anterior.');
 const source=json(resolve(evidence,'before.json'));
 const previous=json(resolve(root,'storage/previews/reels-review/revisao-reels-20261007/manifest.json'));
 const boards=json(resolve(root,'resources/reels-review/storyboards.json')).items;
 const reusable=new Set([195,197,205,215,216,217,218,219,224,225,226,227]);
 const linked={1:205,224:191,225:193,226:192,227:194};
 const captions={
  1:'🎮 Qual RPG merece entrar na sua próxima aventura?\n\nChrono Trigger, The Witcher 3 e Baldur’s Gate 3 mostram três formas de viver boas histórias: viagem no tempo, escolhas em mundo aberto e estratégia por turnos.\n\nQual desses você recomenda para alguém que está começando?',
  190:'💻 Seu PC precisa de 16 GB ou 32 GB?\n\nObserve os jogos e programas que você usa juntos. Mais capacidade pode dar margem para multitarefa, mas compatibilidade, configuração e orçamento também entram na decisão.\n\nO que costuma ficar aberto enquanto você joga?',
  196:'🎮 As ferramentas mudaram. A estratégia continua contando.\n\nHoje, o controle está no mouse, no teclado e nas respostas do seu setup. O áudio também ajuda a perceber o que acontece ao redor.\n\nEquipamento pode apoiar sua experiência; prática, conforto e escolhas continuam fazendo parte do jogo. Qual ferramenta mais mudou sua forma de jogar?',
  198:'💻 O que sustenta o Estratégia Nerd por trás da tela?\n\nO conjunto reúne Ryzen 5 7600X, MSI B850 TOMAHAWK MAX WIFI e 32 GB de DDR5. O objetivo é atender a rotina de trabalho e jogos com escolhas que façam sentido juntas.\n\nVocê prioriza qual parte ao planejar seu setup?',
  199:'💻 Ryzen 5 7600X: onde ele entra no seu planejamento?\n\nSão seis núcleos e doze threads na plataforma AM5. Antes da compra, pense no seu uso, na refrigeração e no custo do conjunto.\n\nVocê monta o PC pensando mais em jogos ou trabalho?',
  200:'🔧 Placa-mãe é decisão de conjunto.\n\nNa MSI MAG B650 TOMAHAWK WIFI, confira compatibilidade da CPU e BIOS, memória DDR5 e conexões necessárias para sua rotina. O artigo específico é sobre a B650; a B850 do setup é outro modelo.\n\nQue conexão não pode faltar na sua placa?',
  201:'🎮 RTX 3050 6 GB: ajuste a compra à sua expectativa.\n\nÉ uma GPU de entrada. O resultado depende do jogo, da resolução e dos ajustes; recursos como ray tracing também têm limites. Compare o modelo exato e os preços disponíveis.\n\nVocê prefere qualidade visual ou mais fluidez?',
  202:'💻 Memória para o que você faz ao mesmo tempo.\n\nO kit Corsair Vengeance DDR5 de 32 GB e 6000 MT/s precisa ser compatível com a plataforma. Capacidade, perfil de memória e estabilidade importam tanto quanto a frequência anunciada.\n\nQuantos programas ficam abertos no seu PC?',
  203:'💾 Kingston NV3 de 1 TB: confira o modelo antes de comprar.\n\nEste conteúdo mostra o NV3, não o NV2. Para instalar, verifique o formato M.2, a interface aceita pelo slot e as condições do seu uso. Velocidade anunciada não é resultado garantido em toda tarefa.\n\nSeu armazenamento ainda dá conta da rotina?',
  204:'🔧 Antes de montar o PC, revise o conjunto.\n\nCompatibilidade, qualidade da fonte e equilíbrio entre componentes evitam escolhas feitas só pelo nome da peça. Planejar antes ajuda a não descobrir uma incompatibilidade depois.\n\nQual erro você gostaria de ter evitado no primeiro setup?',
  206:'🎮 RTX 3050 ou RX 6600? Comece pelo modelo exato.\n\nA RTX 3050 tem versões diferentes. Compare testes do mesmo jogo, resolução e ajustes, além dos recursos e do preço final.\n\nQual pesa mais para você: custo, desempenho ou recursos?',
  207:'🖥️ O PC liga e a imagem não aparece?\n\nComece pelo monitor, entrada selecionada, cabo e saída correta. Se precisar verificar componentes internos, desligue o equipamento e siga um diagnóstico seguro.\n\nVocê já resolveu esse problema sem trocar nenhuma peça?',
  208:'⚔️ Geralt voltou ao Caminho.\n\nThe Witcher 3: Wild Hunt Remastered foi lançado em 29 de setembro de 2026. O upgrade gratuito depende de já possuir o jogo e das condições do mesmo ecossistema de plataforma; não significa jogo base grátis para todos.\n\nVocê vai revisitar qual região primeiro?',
  209:'🖥️ Confira a versão do Windows antes de ignorar o aviso.\n\nA tabela de atualizações da Microsoft informa 13/10/2026 para Windows 11 24H2 Home e Pro. Outras edições têm prazos próprios. Abra winver e planeje uma versão com suporte pelo Windows Update.\n\nVocê já conferiu a edição e a versão do seu PC?',
  210:'🎮 Uma comparação de GPU começa antes do gráfico de FPS.\n\nRTX 5060 e RX 9060 XT precisam ser comparadas por versão, memória, preço e testes nas mesmas condições. Considere também os jogos e recursos que você usa.\n\nO que decide a sua compra em 1080p?',
  211:'💻 AM4 ou AM5: o ponto de partida muda a resposta.\n\nReaproveitar componentes AM4 pode reduzir o custo. Uma plataforma AM5 exige considerar placa-mãe, CPU e DDR5 no orçamento completo.\n\nVocê está atualizando um PC ou começando do zero?',
  212:'⚡ A fonte merece mais atenção que um número de watts.\n\nAvalie modelo, proteções, conectores, demanda do conjunto e garantia. Certificação de eficiência não resume a qualidade de uma fonte. Nunca abra a fonte para conferir componentes.\n\nQual critério você olha primeiro?',
  213:'💾 Não espere o alerta para lembrar do backup.\n\nSMART ajuda a acompanhar o armazenamento, mas um status bom não garante que uma falha não acontecerá. Preserve cópias importantes e investigue mudanças de comportamento.\n\nSeu backup está atualizado?',
  214:'🌡️ Temperatura alta precisa de contexto.\n\nConfira o sensor, a carga e o limite do modelo antes de decidir que existe um defeito. Queda de desempenho, desligamentos e mudanças de temperatura merecem investigação.\n\nVocê costuma acompanhar CPU e GPU durante o jogo?',
  225:'⚔️ Os Altos Céus não têm uma única visão sobre Santuário.\n\nImperius, Tyrael, Auriel, Itherael e Malthael representam aspectos e escolhas diferentes. Tyrael se aproxima da humanidade; a trajetória de Malthael mostra outro caminho.\n\nQual decisão dos arcanjos mais marcou você em Diablo?',
  229:'🔦 Desliguei o terror. O corredor não recebeu o aviso.\n\nUm casaco no escuro já vira chefe opcional. E o gato escolhe exatamente essa hora para aparecer. 😅\n\nQual jogo deixou sua casa mais assustadora depois?',
  230:'👻 Medo também tem estilos.\n\nSilent Hill 2 trabalha a incerteza; Signalis transforma recursos em decisões; Alan Wake 2 aproxima a ameaça de um cotidiano estranho. Três recortes do artigo, sem misturar outra seleção.\n\nQual desses mecanismos mais pega você?',
  231:'🎧 Um ruído pode mudar a cena inteira.\n\nEste Reel usa exemplos sonoros ilustrativos para mostrar antecipação, silêncio e direção. São demonstrações produzidas para a explicação, não áudio original dos jogos.\n\nOuça com fones em volume confortável: de qual lado veio o último som?',
  232:'🎮 O squad tem cinco pessoas e seis horários incompatíveis.\n\nVocê está pronto. Um está no trabalho, outro dormiu. Quando finalmente dá certo para você… a sala já ficou vazia. 😅\n\nQuem do seu grupo sempre chega depois?',
  233:'⏳ Meia hora livre: escolha também seu ponto de parada.\n\nBalatro, Slay the Spire, Vampire Survivors, Celeste e Hades oferecem sessões diferentes. Rodada, trecho e run completa não são a mesma coisa. Confira modo, progresso e salvamento.\n\nQual você abre quando tem pouco tempo?',
  234:'⏰ “É a última partida.”\n\nPerdeu? Precisa recuperar. Ganhou? Agora embalou. O problema é que o amanhecer não entrou nessa negociação. 😅\n\nQual foi a sua desculpa mais recente para jogar mais uma?'
 };
 const items=[];
 for(const post of source.posts.filter(p=>p.status!=='cancelado')){
  const id=Number(post.id),oldId=linked[id]??id,old=previous.items.find(i=>i.id===oldId);
  const recordArticle=source.productionArticles.find(a=>Number(a.id)===Number(post.post_blog_id));
  const article=id>=229?source.localArticles.find(a=>Number(a.id)===Number(post.post_blog_id)):id===1?source.productionArticles.find(a=>Number(a.id)===25):recordArticle;
  let spec=old?.spec?structuredClone(old.spec):null;
  if(id>=229){const manifest=json(resolve(root,'public',post.video_rendered_path+'.manifest.json'));spec=structuredClone(manifest.spec);}
  if(id===214){const image='public/'+recordArticle.imagem_capa;spec={...structuredClone(previous.items.find(i=>i.id===213).spec),articleId:35,articleTitle:recordArticle.titulo,layout:'guide',tone:'calm',scenes:[scene('Identifique o dado','Qual sensor você está vendo?','Temperatura do chip e hotspot são leituras diferentes.',image),scene('Observe a carga','O PC está em repouso ou jogando?','Compare situações equivalentes antes de tirar conclusões.',image),scene('Confira o modelo','O limite depende do componente','Consulte a especificação da sua CPU ou GPU.',image),scene('Sinais de problema','Não olhe só o número','Queda de desempenho e desligamentos merecem investigação.',image),scene('Próxima verificação','Revise a refrigeração','Confira ventilação e limpeza com o equipamento desligado.',image)]};}
  if(!spec)throw new Error('Roteiro de origem ausente #'+id);
  spec.articleTitle=(id===197?'A Lore Completa de Diablo: Entenda Toda a História do Universo':article?.titulo??spec.articleTitle).replaceAll('[[','').replaceAll(']]','');
  if(id===1)spec.articleId=28;
  spec.category=article?.categoria??'editorial';
  spec.presentation=[190,206,210,211].includes(id)?'comparison':[204,207,209,212,213,214].includes(id)?'steps':id>=198&&id<=203?'product':[1,205,215,217,218,219,233].includes(id)?'list':id===231?'sound':id===232?'chat':id===229||id===234?'comedy':'story';
  spec.scenes=spec.scenes.map(s=>({...s,framing:{...(s.framing??{}),motion:'still'}}));
  if(id===196){const image='public/uploads/reels/'+run+'/images/reel-196-img-001.png';spec.layout='chronicle';spec.scenes=[scene('Os campos mudaram','A batalha chegou à tela','Hoje, os desafios pedem foco, estratégia e controle.',image,{...still,region:quadrants[0]}),scene('Controle','Cada movimento importa','Conforto e precisão ajudam a executar suas escolhas.',image,{...still,region:quadrants[1]}),scene('Resposta','O conjunto acompanha você','O jogo e o equipamento influenciam a resposta aos comandos.',image,{...still,region:quadrants[2]}),scene('Percepção','Ouvir também é jogar','Ferramentas apoiam a habilidade. Não substituem a prática.',image,{...still,region:quadrants[3]})];}
  if(id===200)spec.scenes=spec.scenes.map(s=>({...s,image:'resources/reels-review/assets/msi-b650.png',framing:still}));
  if(id===203)spec.scenes=spec.scenes.map(s=>({...s,image:'resources/reels-review/assets/kingston-nv3.jpg',framing:still}));
  if(id===198)spec.scenes.push(scene('A escolha do conjunto','As peças precisam conversar','A B850 do setup e a B650 de outra análise são modelos distintos.',spec.scenes[0].image));
  if(id===204)spec.scenes.splice(1,0,scene('Compatibilidade','Confira antes de montar','Socket, memória, espaço e conexões precisam ser compatíveis.',spec.scenes[0].image));
  if(id===207)spec.scenes.push(scene('Se o problema continua','Desligue antes de mexer','Siga os próximos testes com segurança; não troque peças por tentativa.',spec.scenes[0].image));
  if(id===213)spec.scenes.push(scene('Se houver sinais de falha','Priorize seus arquivos','Preserve cópias antes de procedimentos que possam agravar a perda.',spec.scenes[0].image));
  if(id===229||id===232||id===234){const image='public/uploads/reels/'+run+'/images/reel-'+id+'-img-001.png';const content=id===229?[['Depois do jogo','O terror acabou. Será?','Você desligou o jogo, mas continua em estado de alerta.'],['O corredor','A casa ganhou dificuldade','Agora você atravessa o corredor como se tivesse perdido o save.'],['A sombra','Tem alguma coisa ali','O coração acelera. A lanterna aponta para a porta.'],['O alívio','Era só o casaco','E o gato apareceu para garantir o susto.']]:id===232?[['O convite','Hoje o squad fecha','Você manda a mensagem e já prepara o controle.'],['A primeira resposta','Ainda estou no trabalho','Um horário livre não significa que todo mundo pode.'],['O silêncio','Esse já dormiu','O grupo segue combinando. Um participante saiu da realidade.'],['A chegada','Cheguei! Cadê vocês?','Quando você consegue entrar, os outros já desligaram.']]:[['A promessa','É a última partida','O relógio ainda permite acreditar nessa frase.'],['Depois da derrota','Não posso parar assim','Agora você precisa recuperar antes de encerrar.'],['Depois da vitória','Agora eu embalo','Uma vitória vira argumento para jogar de novo.'],['O amanhecer','A manhã não negocia','O sol apareceu. Seu horário de dormir desapareceu.']];spec.scenes=content.map((c,i)=>scene(...c,image,{...still,region:quadrants[i]}));}
  if(id===230){const p=source.localArticles.find(a=>Number(a.id)===77),folder='public/'+dirname(p.imagem_capa).replaceAll('\\','/');spec.scenes=[scene('Silent Hill 2','A dúvida assusta','A névoa e o rádio fazem você antecipar o que ainda não vê.',folder+'/img-001.webp'),scene('Signalis','Cada item é uma escolha','Gerenciar recursos também faz parte da tensão.',folder+'/img-002.webp'),scene('Alan Wake 2','O cotidiano fica estranho','A investigação aproxima o medo de lugares reconhecíveis.',folder+'/img-003.webp')];spec.presentation='comparison';}
  if(id===231){spec.scenes=spec.scenes.map((s,i)=>({...s,eyebrow:['Exemplo ilustrativo','Antecipação','O silêncio','Direção'][i],title:['Ouça antes de ver','O alerta se aproxima','Agora, nada','Esquerda ou direita?'][i],body:['Um ruído pode mudar a leitura de uma cena.','O sinal repetido faz você esperar alguma coisa.','A pausa deixa o próximo ruído em destaque.','Ouça com fones em volume confortável.'][i]}));spec.audio='public/uploads/reels/'+run+'/audio/reel-231-demo.wav';spec.audioStart=0;spec.duration=28;spec.beatStarts=[0,7,14,21];}
  if(id===233){const folder='public/uploads/posts/jogos-para-quem-so-tem-30-minutos-por-dia/images';const cards=[['Balatro','Uma rodada, não uma promessa','Pare entre etapas; a sequência completa pode levar mais tempo.',folder+'/img-001.webp'],['Slay the Spire','Uma decisão por vez','Confira o salvamento da versão para continuar depois.','public/uploads/reels/'+run+'/images/reel-233-img-002.jpg'],['Vampire Survivors','Confira o modo','A duração muda por modo e estágio; escolha seu ponto de parada.','public/uploads/posts/12-jogos-leves-pc-fraco-ou-modesto/images/vampire-survivors.jpg'],['Celeste','Um trecho da montanha','Você pode avançar uma etapa sem terminar o capítulo.',folder+'/img-002.webp'],['Hades','Sair também exige planejar','Confira como preservar o progresso antes de encerrar.',folder+'/img-003.webp']];spec.scenes=cards.map(c=>scene(c[0],c[1],c[2],c[3]));}
  if(!reusable.has(id)&&id!==231){const count=spec.scenes.length;spec.duration=count===3?21:count===5?35:id===196?32:id===229?24:id===232?28:id===234?28:28;spec.beatStarts=spec.scenes.map((_,i)=>Math.floor(i*spec.duration/count));}
  const original=post.video_rendered_path?'public/'+post.video_rendered_path:null;
  const track=source.tracks.find(t=>'public/'+t.arquivo_path===spec.audio);
  const tag=id>=198&&id<=213||id===190||id===196||id===214?'#Hardware #Tecnologia #SetupGamer #Geek':id===217?'#Cinema #Filmes #CulturaGeek #Geek':id===218?'#Anime #Animes #CulturaGeek #Geek':id===219?'#Nostalgia #Desenhos #CulturaGeek #Geek':'#Games #Gamer #CulturaGeek #Geek';
  const raw=captions[id]??post.legenda.replace(/#[\p{L}\p{N}_]+/gu,'').trim();
  const caption=raw+'\n\n#EstratégiaNerd '+tag;
  const title=id===208?'The Witcher 3 Remastered: novidades e condições do upgrade':spec.articleTitle;
  const entry={id,title,status:post.status,scheduled:post.agendado_para,originEnvironment:id>=229||id===1?'local':'production',articleId:post.post_blog_id,articleSlug:article?.slug??null,articleSha256:article?createHash('sha256').update(article.conteudo).digest('hex'):null,type:spec.presentation,note:old?.note??'Roteiro individual revisado conforme a pauta.',sources:boards.find(b=>b.id===oldId)?.sources??[],caption,original,originalSha256:original?hash(resolve(root,original)):null,reuse:reusable.has(id),trackId:track?.id??post.audio_track_id,spec,state:'prepared',video:'uploads/reels/'+run+'/reel-'+id+'-v1.mp4',poster:'uploads/reels/'+run+'/reel-'+id+'-v1.jpg'};
  validateSpec(spec);items.push(entry);
 }
 if(items.length!==37)throw new Error('Exatamente 37 registros não cancelados esperados');
 mkdirSync(out,{recursive:true});save({version:1,approvedOn:'2026-10-10',cancelled:[191,192,193,194],publicationApproval:false,items});console.log('PREPARED: 37 fichas individuais; 4 cancelados preservados.');
}
if(action==='--frame-revision'){
 const catalog=json(catalogPath);
 for(const item of catalog.items.filter(i=>!i.reuse)){
  if(item.revision===2)continue;
  if(item.state!=='ready')throw new Error('Finalize a versão anterior antes da revisão.');
  item.previousVersions=[{video:item.video,poster:item.poster,sha256:item.sha256}];
  item.spec.scenes=item.spec.scenes.map((s,index)=>{
   let region=s.framing?.region;
   if(item.id===229||item.id===234){const divide=item.id===229?549/1254:584/1254;region=[index%2?.5:0,index>=2?divide:0,.5,index>=2?1-divide:divide];}
   return {...s,framing:{...still,...(region?{region}:{} )}};
  });
  item.video=item.video.replace('-v1.mp4','-v2.mp4');item.poster=item.poster.replace('-v1.jpg','-v2.jpg');item.state='prepared';item.revision=2;
 }
 save(catalog);console.log('Enquadramentos corrigidos; versões anteriores preservadas.');
}
if(action==='--avatar-revision'){
 const catalog=json(catalogPath),approved=[196,229,232,234];
 const pending=catalog.items.filter(i=>approved.includes(i.id)&&i.revision!==3);
 if(pending.length && pending.length!==4)throw new Error('Revisão parcial: verifique o catálogo antes de aplicar.');
 for(const item of pending){
  if(item.state!=='ready'||item.revision!==2||item.spec.scenes.length!==4)throw new Error('Estado anterior divergente #'+item.id);
  if(hash(resolve(root,'public',item.video))!==item.sha256)throw new Error('Versão anterior divergente #'+item.id);
  const image='public/uploads/reels/'+run+'/images/reel-'+item.id+'-avatar-v3.png';
  if(!existsSync(resolve(root,image)))throw new Error('Arte do avatar ausente #'+item.id);
 }
 for(const item of pending){
  item.previousVersions.push({video:item.video,poster:item.poster,sha256:item.sha256,spec:structuredClone(item.spec)});
  const image='public/uploads/reels/'+run+'/images/reel-'+item.id+'-avatar-v3.png';
  item.spec.scenes=item.spec.scenes.map((s,index)=>({...s,image,framing:{...still,region:quadrants[index]}}));
  item.video=item.video.replace('-v2.mp4','-v3.mp4');item.poster=item.poster.replace('-v2.jpg','-v3.jpg');
  item.state='prepared';item.revision=3;item.avatarReference=true;
 }
 save(catalog);console.log('Quatro Reels preparados com avatar; versões anteriores e demais 33 preservados.');
}
if(action==='--render'){
 const catalog=json(catalogPath);
 const ordered=[...catalog.items].sort((a,b)=>(a.id===196?-1:b.id===196?1:0));
 for(const item of ordered.filter(i=>selected===null||i.id===selected)){
  const target=resolve(root,'public',item.video),poster=resolve(root,'public',item.poster);
  if(item.state==='ready'){if(hash(target)!==item.sha256)throw new Error('Vídeo pronto divergiu');continue;}
  console.log('RENDER #'+item.id+' '+item.type+' '+item.spec.scenes.length+' cenas');
  const stage=await createScene(item.spec,root);try{for(const start of item.spec.beatStarts)stage.render(start+2.5);}finally{stage.destroy();}
  if(item.reuse){copyFileSync(resolve(root,item.original),target,constants.COPYFILE_EXCL);command('ffmpeg',['-v','error','-n','-ss','2.6','-i',target,'-frames:v','1',poster]);}
  else{
   await renderVideo(item.spec,root,target,{timeoutMs:600000,maxRssBytes:1536*1024**2});
   const stage=await createScene(item.spec,root);try{
    stage.render(2.6);writeFileSync(poster,await stage.layer.getCanvas()._canvas.toBuffer('jpg',{quality:.9}),{flag:'wx'});
    const sheet=new Canvas(item.spec.scenes.length*270,480),ctx=sheet.getContext('2d');
    for(let i=0;i<item.spec.scenes.length;i++){stage.render(item.spec.beatStarts[i]+2.6);ctx.drawImage(stage.layer.getCanvas()._canvas,i*270,0,270,480);}
    writeFileSync(target.replace('.mp4','-contact.jpg'),await sheet.toBuffer('jpg',{quality:.9}),{flag:'wx'});
   }finally{stage.destroy();}
  }
  item.sha256=hash(target);item.state='ready';save(catalog);console.log('READY #'+item.id);
 }
}
if(action==='--verify'){
 const catalog=json(catalogPath),results=[];
 for(const item of catalog.items){
  if(item.state!=='ready')throw new Error('Pendente #'+item.id);
  const path=resolve(root,'public',item.video);if(hash(path)!==item.sha256)throw new Error('Hash divergente');
  const duration=item.reuse?Number(command('ffprobe',['-v','error','-show_entries','format=duration','-of','csv=p=0',path]).stdout):item.spec.duration;
  inspectOutput(path,duration);command('ffmpeg',['-v','error','-i',path,'-f','null','-']);
  const level=command('ffmpeg',['-hide_banner','-i',path,'-vn','-af','volumedetect','-f','null','-']).stderr.match(/max_volume: (-?[\d.]+) dB/);
  if(!level||Number(level[1])<=-35)throw new Error('Áudio silencioso #'+item.id);
  if(item.original&&hash(resolve(root,item.original))!==item.originalSha256)throw new Error('Original alterado');
  if(!item.caption.includes('#EstratégiaNerd')||(item.caption.match(/#[\p{L}\p{N}_]+/gu)||[]).length!==5)throw new Error('Hashtags inválidas');
  results.push({id:item.id,decode:true,audioPeakDb:Number(level[1]),duration,sha256:item.sha256,originalPreserved:true,publicationApproved:false});console.log('PASS #'+item.id);
 }
 writeFileSync(resolve(evidence,'validation.json'),JSON.stringify({items:results,technicalOnly:true},null,2));
}
if(!['--prepare','--frame-revision','--avatar-revision','--render','--verify'].includes(action))throw new Error('Use --prepare|--frame-revision|--avatar-revision|--render|--verify [--id N]');
