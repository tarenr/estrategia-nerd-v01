# Reels Konva — piloto local com som

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

## Próxima etapa

A aprovação técnica deste piloto não é aprovação visual do usuário. Depois da avaliação do MP4 com som, planejar a integração ao fluxo existente, os modelos das demais categorias e eventual substituição protegida dos vídeos. Nenhuma dessas mudanças está ativada nesta etapa.
