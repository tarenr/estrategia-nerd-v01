# Reels Konva — padrão editorial local com som

## Integração editorial v2

O plano aprovado em 07/10/2026 inclui tornar Konva o padrão dos próximos Reels editoriais do blog e substituir os 26 agendados. O modelo conserva o visual do piloto aprovado: quatro cenas, tipografia local, molduras, movimento de imagem, entrada de texto, partículas e progresso. As paletas identificam hardware, games, dicas e cultura nerd. Os textos usam título e trechos do resumo do próprio artigo, além de chamadas genéricas para leitura; não há geração paga de imagens nem de fatos.

`config/instagram.php` define `editorial_renderer=konva` e `node_path=node`. O sincronizador existente continua sendo chamado por `en-blog-publish-prod.php`; não exige `--motion` para o modelo novo. A duração calculada (24–30 segundos) é informada ao seletor de trilhas antes da escolha do recorte. A produção só fornece os artigos; os registros Instagram continuam no PDO local. Posts manuais conservam seu fluxo próprio.

`KonvaReelRenderer` monta o conteúdo e supervisiona o worker `scripts/reels-konva/editorial.mjs`. O worker valida as quatro cenas antes de exportar MP4 e JPG. Publicadores manual e agendado reutilizam o MP4 pronto. Se o cache de um Reel vinculado estiver ausente, `EditorialReelService` busca o artigo original em produção e regenera com a trilha e o início cadastrados. O novo cache e a referência da mídia são atualizados juntos, em transação, somente enquanto o post permanece na fase anterior ao envio. Falha de imagem, fonte, Node ou recorte impede o envio e aparece pelo tratamento de erro existente; não há fallback silencioso para um vídeo simples. As proteções de confirmação da Meta continuam sendo usadas.

O valor `legacy` permite diagnóstico/rollback explícito de configuração. Não muda arquivos já produzidos. Restaurar referências de um lote usa o journal descrito abaixo; não substituir vídeos antigos nem reaplicar snapshots após publicação iniciada.

O enquadramento editorial usa `imageFit=contain`: mantém a imagem inteira dentro da moldura, inclusive capas com texto incorporado. O zoom progride de 94% a 100% do espaço disponível, preservando bordas durante todo o movimento. A revisão do primeiro lote detectou cortes no modelo de preenchimento; esse lote foi preservado como evidência e não aplicado.

A capa JPEG gerada aos 1,6 s é obrigatória na publicação do Reel. Desde a proteção aprovada em 07/10/2026, o helper confere presença, tipo real e decodificação; falta ou corrupção bloqueia o envio e registra um aviso no post/log. A camada de containers também recusa Reels sem `cover_url`. Nunca voltar ao primeiro quadro vazio como fallback. Testar com `scripts/verify-reel-cover.php`, usando fixtures isoladas e HTTP simulado; não remover capas reais para provocar uma falha.

## Migração protegida dos 26 agendados

O comando `scripts/en-instagram-konva-batch.php` limita a aplicação aos IDs 190, 194–213 e 215–219. #214 publicado fica preservado. Não seleciona músicas, não gera legendas, não publica na Meta e não escreve artigos de produção.

1. `--inventory` grava um manifesto e snapshots completos dos posts/mídias em `storage/backups/reels-konva/batch-*/`. Confere vínculo de origem, fontes, hashes, áudio e duração do recorte original.
2. `--prepare --manifest=CAMINHO` gera novos MP4/JPG e folhas das quatro cenas em `public/uploads/reels/konva-batch/`. Retoma itens prontos sem recomprimir. Arquivos de tentativas anteriores permanecem preservados.
3. `--validate --manifest=CAMINHO` confere fontes e saídas e mostra o hash do manifesto. `node scripts/reels-konva/verify-editorial.mjs --manifest CAMINHO` decodifica integralmente todos os vídeos, mede áudio e verifica legibilidade e movimento; salva `validation.json` junto ao backup. Conferir também todas as folhas visuais.
4. Pausar a tarefa `EstrategiaNerd-InstagramPublicarAgendados` e confirmar que nenhum processo do publicador está em execução. Desabilitar a tarefa não interrompe uma instância já iniciada. Conferir calendário antes da pausa e antes de reativar.
5. `--apply --manifest=CAMINHO --approved-manifest=HASH --publisher-paused` exige o manifesto conferido. Os 26 estados completos são comparados sob lock. Journal com estados anteriores/posteriores é persistido com flush/fsync antes de uma única transação para todos os posts e mídias. A troca conserva IDs, trilhas, início de áudio, legenda, horários e vínculos. Só atualiza caminho de vídeo, dimensões/duração/cache e data de atualização.
6. `--verify --manifest=CAMINHO` confere o resultado inteiro e os registros/mídias externos ao lote. Reativar o publicador somente após conferência. Não alterar horários vencidos nem executar publicação manual como parte da migração.

Rollback: com o publicador pausado e sem execução ativa, usar `--rollback` com os mesmos argumentos de manifesto/hash. A recuperação exige igualdade com o estado posterior e hash intacto do MP4 anterior. Posts já iniciados/publicados ou editados recusam o rollback inteiro. Não exclui arquivos. Se houver interrupção depois do commit e antes de atualizar o journal, `--verify` confere os estados posteriores registrados no intent; não reenviar publicação nem reaplicar o lote. O CLI recusa uma segunda aplicação com journal existente.

### Erro encontrado no Windows e orientação para IAs

A origem encerra sessões MySQL ociosas após 20 segundos. Pipes PHP no Windows bloquearam `stream_get_contents`, apesar de `stream_set_blocking(false)`, impedindo a supervisão e a manutenção da conexão durante Node. A correção usa arquivos para stdout/stderr, laço de supervisão independente e consultas de manutenção a cada três segundos durante a renderização do lote. Não aumentar timeout do servidor nem usar uma segunda conexão local como origem. Falha de conexão cancela o processo próprio e impede a aplicação. Tentativas iniciais produziram arquivos de vídeo, mas não alteraram posts; não considerar existência de MP4 como evidência de migração concluída.

CLI sem bootstrap deve carregar `content_sync` e identificar explicitamente o ambiente atual como `local` antes de chamar `TargetEnvironmentDatabase::pdo('production')`. O ambiente padrão `production` pode fazer o helper devolver o PDO global local como se fosse a origem. A primeira consulta recusou o inventário por ausência de artigo, antes de gravar o backup ou alterar posts. Não improvisar conexão de origem com o banco local.

### Validação da integração

```powershell
C:\xampp\php\php.exe scripts/verify-konva-integration.php --manifest=CAMINHO --render-default
node scripts/reels-konva/verify-editorial.mjs --manifest CAMINHO
# Usar um MP4 atual de 24s; o piloto histórico v1 tem hashes de código históricos.
$env:EN_KONVA_PILOT='public/uploads/reels/konva-batch/LOTE/REEL-DE-24S.mp4'
node --test scripts/reels-konva/verify.mjs
C:\xampp\php\php.exe scripts/verify-instagram-publish-confirmation.php
C:\xampp\php\php.exe scripts/verify-changes.php
```

Os testes de integração usam SQLite efêmero e arquivos reais, sem carregar credenciais nem chamar a Meta: concorrência, falha no último UPDATE com rollback integral, preservação dos campos, recusa de reaplicação e rollback após início da publicação. `--render-default` exercita o mesmo gerador chamado pelo sincronizador, sem flag de animação. O lote é validado antes de alterar os registros. Uploads e backups locais não são enviados ao Git; o checkout no GitHub não transporta esses arquivos nem altera sozinho um servidor remoto.

### Resultado da implantação local — 07/10/2026

- Lote aplicado: `storage/backups/reels-konva/batch-20261007-145016-0065c4eb/manifest.json`, SHA-256 `a4d2b9bf7c97b8c10bdedb332f6f99bc81fc397193781147604c83b8ed54876c`.
- Journal aplicado: `application.json` na mesma pasta, SHA-256 `a6a1d04147fe1734a651bc58fd2e0756d28b3726f8bd50908b8686eed200a545`. Guardar junto a `all-records-before.json`, `validation.json` e registros do publicador; não compartilhar credenciais.
- 26/26 agendados substituídos atomicamente: 13 hardware, 7 games, 3 dicas e 3 cultura nerd. MP4 de 24–29 segundos; todas as cenas revisadas, decodificação integral, áudio audível, fontes legíveis e movimento confirmados.
- Música/início, legenda, horários, vínculo e IDs preservados. Conferência após o commit SQL confirmou também todos os posts e mídias externos ao lote. Os arquivos anteriores continuam disponíveis.
- Publicador pausado às 15h02 e reativado às 15h03, `Enabled=true`, `State=Ready`, último resultado 0; nenhuma execução ativa na troca e nenhum agendamento vencido. Próximo Reel: #194 em 07/10 às 19h30, horário original.
- Geração padrão exercitada sem `--motion`. Sincronizador em dry-run reconheceu #197 existente e não criou duplicata. Nenhuma chamada de publicação de teste à Meta.
- Testes: **34/34 integração**, **14/14 Konva**, **75/75 confirmação de publicação**, **17/17 suíte geral**, PHPStan nível 5 sem erros. Verificação completa dos **26/26 vídeos** registrada em `validation.json`.
- Renderização por vídeo: 15.702–22.336 ms; maior RSS Node registrado: 394,7 MiB (exclui FFmpeg). Sem novas dependências ou APIs pagas de geração/renderização.
- Código preparado na branch `main`; publicação efetiva dos Reels continua pelo agendamento normal do executor local. O push não representa deploy de Node em uma hospedagem remota nem postagem antecipada no Instagram.

## Histórico do piloto v1

## Objetivo e limite da entrega

Piloto aprovado em 07/10/2026 para avaliar uma composição visual mais elaborada dos Reels, usando bibliotecas e fontes gratuitas, imagens existentes e trilha local. Não usa API de geração de imagens nem serviços de renderização pagos. Esta etapa não integra o modelo ao sincronizador, não publica na Meta e não troca os 26 Reels agendados.

O artigo escolhido foi **A Lore Completa de Diablo: Entenda Toda a História do Universo**, ID 17, representado pelo Reel local #197 no inventário anterior. Foram usados o título/resumo desse inventário e quatro ilustrações já existentes no artigo; nenhum arquivo de origem foi editado. O uso de ilustrações existentes não equivale a comprovar sua licença de terceiros nem a produzir fotografias inéditas.

## Arquitetura

- `scripts/reels-konva/scene.mjs`: cena Konva 1080×1920, com composição em camadas e quatro capítulos. Tipografia local, campos de luz, molduras angulares, movimento proporcional da imagem, entrada de título, partículas e progresso. Renderização por tempo explícito de cada quadro, sem depender do relógio.
- `scripts/reels-konva/render.mjs`: exportação de quadros RGBA por pipe para um processo FFmpeg direto; H.264/yuv420p, 30 fps, AAC 192 kbps/48 kHz e faststart. O áudio recebe fades de entrada/saída.
- `scripts/reels-konva/pilot.json`: metadados, quatro cenas, imagens, trilha e recorte do piloto. O JSON não contém credenciais e não é um cadastro de posts.
- `scripts/en-instagram-konva-pilot.mjs`: comando isolado de prévias e MP4, sem carregar bootstrap, acessar banco ou modificar agenda.
- `scripts/reels-konva/verify.mjs`: testes isolados de saída real, falhas e recuperação.

Konva **10.7.1** e Skia Canvas **3.0.8** estão fixados no package.json/lockfile. Usar o adaptador oficial `konva/skia-backend`; não criar adaptações manuais nem instalar um segundo backend para contornar erro. Instalação e teste de PNG com texto acentuado funcionaram no Windows com Node **24.14.1** e FFmpeg **8.1**.

As fontes originais Bebas Neue e Inter ficam em `resources/reels-konva/fonts/`, acompanhadas dos respectivos textos OFL. São carregadas localmente; a composição não depende de fontes instaladas no Windows. Konva e Skia Canvas usam licença MIT. Preservar licenças ao redistribuir. Fontes e versões usadas são registradas por hash no manifesto.

Fontes oficiais: [Konva no Node](https://konvajs.org/docs/nodejs/nodejs-setup), [Skia Canvas](https://github.com/samizdatco/skia-canvas), [Bebas Neue](https://github.com/google/fonts/tree/main/ofl/bebasneue), [Inter](https://github.com/google/fonts/tree/main/ofl/inter).

## Executar

Instalação reprodutível após checkout: `npm ci`. O backend inclui um binário nativo; se não houver suporte para a máquina, interromper e diagnosticar. Uma alternativa de backend exige revisão do escopo.

Gerar uma prévia estática das quatro cenas:

```powershell
node scripts/en-instagram-konva-pilot.mjs --still --output storage/previews/reels-konva/nova-previa
```

Gerar as imagens e o vídeo completo com áudio:

```powershell
node scripts/en-instagram-konva-pilot.mjs --output storage/previews/reels-konva/novo-piloto
```

Especificação alternativa: `--spec arquivo.json`. Caminhos dos assets são resolvidos a partir da raiz do projeto. Aceita `--ffmpeg` e `--ffprobe` para executáveis locais. Uma saída existente é recusada para preservar o trabalho anterior. As imagens e trilhas do piloto são uploads locais ignorados pelo Git; precisam existir na máquina executora.

Saídas: `scene-1.png` a `scene-4.png`, `pilot.mp4` e `pilot.mp4.manifest.json`. Prévias ficam em storage e não são publicadas automaticamente. O manifesto vincula fontes, imagens, áudio, código do renderizador e MP4 por SHA-256, além de guardar configuração, versões, duração, tempo de execução e pico de RSS do processo Node.

## Falhas e preservação

- Imagem/fonte ausente, texto excessivo ou sem tamanho legível: falhar antes de produzir um MP4 final.
- Trilha menor que duração + início: recusar; não repetir, deslocar ou completar com silêncio automaticamente.
- Escrita de quadros aguarda a conclusão do frame anterior. stderr do FFmpeg é consumido continuamente, evitando bloqueio do pipe.
- Bloqueio exclusivo por arquivo impede dois renders para a mesma saída. A saída definitiva usa cópia exclusiva e só é exposta após inspeção e decodificação completa.
- Timeout padrão de 180 segundos e teto de RSS Node de 768 MiB. Fundos estáticos são cacheados; um único grafo de cena é reutilizado.
- SIGINT/SIGTERM e erros encerram o filho FFmpeg e removem apenas lock/MP4 temporário criados pela própria tentativa. Não há shell intermediário nem processo neto. Encerramento forçado do sistema pode impedir a limpeza; verificar o processo e preservar evidências antes de remover um lock remanescente.
- A biblioteca não cria imagens inéditas e não define a licença de imagens de outros autores. O ganho visual vem do modelo e do movimento.

O npm audit de produção encontrou um aviso baixo preexistente no esbuild 0.28.0 (servidor de desenvolvimento no Windows), sem aviso atribuído às duas bibliotecas novas. Não foi executado audit fix nem atualização incidental das dependências existentes.

## Resultado validado

Vídeo: `storage/previews/reels-konva/diablo-pilot-final/pilot.mp4`.

- 24 segundos; 1080×1920; 720 quadros a 30 fps; H.264/yuv420p + AAC; decodificação completa sem erros; áudio com volume presente.
- Quatro capítulos de seis segundos: apresentação, Anu/Tathamet, Guerra Eterna e Santuário/chamada para o blog.
- Trilha existente `public/uploads/audio/pixabay_399898.mp3`, começando em **9 segundos**, preservando o recorte inicial do Reel #197. Não alterou cadastro ou reserva de trilha da fila.
- Renderização final em **19.484 ms**; pico de RSS Node de aproximadamente **364 MiB**. Esse número não inclui a memória do processo FFmpeg.
- SHA-256 MP4: `0d3fcb4c9d0b37b0258ea95e61acd76268cbfefeddf64c2eafe5c8be977e445e`.

```powershell
node --test scripts/reels-konva/verify.mjs
C:\xampp\php\php.exe scripts/verify-changes.php
```

Os testes Konva tiveram **14 aprovações e zero falhas**: formato real, decode completo, volume do áudio, saída existente, áudio ausente/insuficiente, imagem/fonte ausente, limites de texto/duração, quadro reprodutível e animado, acentos/texto longo, bloqueio de concorrência, timeout, cancelamento sem processo filho remanescente, teto de memória e manifesto íntegro. A suíte PHP obrigatória teve **17 aprovações e zero falhas**, incluindo PHPStan.

Os testes pressupõem o piloto final acima. Para testar outro piloto do mesmo spec, definir `EN_KONVA_PILOT` com seu caminho. Fixtures dos testes ficam em pastas novas de storage, sem banco real.

Conferência em leitura ao concluir: 123 posts locais (1 rascunho, 26 agendados, 96 publicados); 26/26 vídeos anteriores com hashes preservados; publicador Windows ativo/Ready. Todos os arquivos alheios à tarefa foram preservados.

O usuário avaliou o piloto, confirmou que o resultado estava melhor que o modelo anterior e aprovou o plano de integração v2 descrito no início deste documento. Os números desta seção histórica pertencem ao piloto original.
