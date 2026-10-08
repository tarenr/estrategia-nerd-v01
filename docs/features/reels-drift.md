# Piloto de Reels com Drift

Teste isolado aprovado em 07/10/2026, usando o artigo 17, **A Lore Completa de Diablo**. Não integra o publicador: Konva continua sendo o gerador editorial. Nenhum cadastro, agendamento, site ou publicação na Meta foi alterado.

## Ambiente e origem

- Windows, Node 24.14.1, Drift portátil **0.7.5**, NVIDIA RTX 3050, contexto OpenGL 3.3 confirmado no log.
- Download oficial: https://github.com/CutWire-Studios/Drift/releases/tag/v0.7.5
- ZIP `Drift-Portable-0.7.5-x64.zip`, SHA-256 conferido com o digest da API oficial: `a3c8c1d246f9b9abd5daa5c15d130e6c49d616b523b819d4e4700e0283126ebc`.
- Executável local: `C:/Users/WINDOWS/Projects/tools/drift/v0.7.5/Drift/drift.exe`. Binários externos não entram no Git. Drift tem licença GPL-3.0; preservar a licença caso haja redistribuição.
- O portátil pode usar configurações/complementos em `%APPDATA%/Drift`. Não houve instalação adicional de fontes, modelos ou runtimes.

O teste usa exclusivamente recursos locais incluídos no editor: MCP por stdio, imagens WebP originais, títulos nativos animados por palavra, zoom por keyframes, crossfade e áudio com fades. Sem marketplace, vozes pagas, geração paga de imagens ou modelos de IA. O texto usa **Segoe UI**, já disponível no Windows; não usa as fontes Bebas Neue/Inter carregadas pelo Konva. A família é gravada no projeto, mas a reprodução em outra máquina depende dessa fonte instalada.

## Reprodução

Na raiz do EN, com Node, FFmpeg e FFprobe no PATH:

```powershell
node scripts/reels-drift/pilot.mjs 'C:/Users/WINDOWS/Projects/tools/drift/v0.7.5/Drift/drift.exe' storage/previews/reels-drift/novo-piloto
node scripts/reels-drift/verify.mjs storage/previews/reels-drift/novo-piloto
```

Usar pasta inexistente. O comando lê `scripts/reels-konva/pilot.json`, importa quatro imagens e a trilha já existente, preservando os arquivos. O recorte é 9–33 s. São 24 segundos, 1080×1920, 30 fps, H.264/yuv420p e AAC estéreo/48 kHz. Ele monta o projeto por comandos, salva `diablo.drift` e exporta duas vezes sem cliques. Cada exportação tem limite de dez minutos; cada chamada MCP tem timeout de 60 segundos. A versão do servidor e os schemas devem ser reconferidos ao atualizar o Drift.

`mcp.mjs` implementa o cliente JSON-RPC; `pilot.mjs` monta e exporta; `verify.mjs` valida arquivos reais. Os resultados ficam em storage, ignorado pelo Git: projeto, MP4s, operações, inspeção, timings, quadros e validação. O arquivo `frame-1.6.jpg` serve de prévia de capa, não é registrado no Instagram. O cliente fecha stdin e encerra o subprocesso no final ou em erro. Não expõe servidor HTTP nem credenciais.

## Resultado observado

Evidência local: `storage/previews/reels-drift/diablo-pilot-final/`.

### Prévia no celular via cloudflared

A pedido do usuário, o piloto foi disponibilizado **somente no servidor local**, em `public/uploads/previews/drift-diablo-20261007/`: `index.html`, `diablo.mp4` e `capa.jpg`. A cópia MP4 mantém o SHA-256 original `60dde0417fce0f677686788694442f17d82b66e7f0691ca221e1fbde9d097321`.

Player: https://nerd.tfr-info.com.br/uploads/previews/drift-diablo-20261007/index.html

O HTML tem viewport móvel, controles nativos, `playsinline`, capa aos 1,6 s, carregamento inicial somente de metadados e link de download. Player, JPEG e MP4 retornaram HTTP 200 com os tipos corretos pelo túnel; a requisição `Range: bytes=0-1023` retornou HTTP 206 e os 1.024 bytes esperados, permitindo reprodução parcial. A disponibilidade depende do computador, Apache e cloudflared ligados. A pasta uploads já tem bypass de Cloudflare Access; o teste não mudou essa configuração. Não foi enviado ao stage. Os três arquivos são artefatos locais ignorados pelo Git.

| Medida | Drift, piloto final | Konva, piloto anterior do mesmo artigo |
|---|---|---|
| Tempo registrado | 5.351 e 5.307 ms | 19.484 ms |
| Memória | WorkingSet64 amostrado: 1.192.280.064 bytes no primeiro export | RSS máximo do Node: 381.624.320 bytes |
| Tamanho do MP4 | 37.129.620 bytes | 4.277.845 bytes |
| Composição | Quatro cenas, texto animado, zoom e crossfade | Quatro cenas, molduras, partículas e identidade editorial |

Esses tempos não são um benchmark controlado: o Konva é um resultado anterior, as composições/encoders diferem e o tempo Drift inclui polling e medição via PowerShell. A memória Drift é amostrada, não um pico exato; a memória Konva exclui o processo FFmpeg. Não extrapolar para outros computadores ou execução sem GPU.

As duas exportações foram decodificadas integralmente sem erros; têm 720 quadros, 24 s, AAC estéreo/48 kHz e áudio não silencioso. O quadro amostrado a 2 s é idêntico nas duas exportações. A diferença média na região de imagem entre 2 e 4 s foi 16,13 níveis de cinza, confirmando movimento. A inspeção confirma quatro imagens, textos no instante de cada cena e recorte de áudio. Quadros das quatro cenas e capa aos 1,6 s foram revisados visualmente: imagens inteiras, texto legível, sem tela branca na capa. A validação cobre 15 verificações, não equivale a aprovação estética do usuário.

Os três scripts passaram em `node --check`. A suíte obrigatória `C:/xampp/php/php.exe scripts/verify-changes.php` passou com **17 OK, zero falhas**, incluindo PHPStan nível 5. A primeira execução no sandbox falhou por restrições de acesso a sockets e sessões; a execução autorizada fora do sandbox passou sem alteração de código do EN para contornar essas restrições.

**Conclusão:** automação local e exportação foram comprovadas neste ambiente. O visual deste piloto ficou mais simples que o modelo Konva atual; não há evidência para substituir o gerador. Drift oferece recursos para desenvolver uma composição mais elaborada, mas isso exige outro trabalho de design e validação. A integração com os posts e um teste em lote não fazem parte deste piloto.

## Falhas encontradas e orientação para outras IAs

1. `place_clip` recusou imagens na track inicial `video`: `type_mismatch`. Imagens usam uma track compatível criada pelo editor. O piloto omite `track` nesse comando, usando a track correspondente ao asset. Não converter os arquivos nem instalar codecs por causa desse erro de tipo.
2. Textos colocados na mesma track foram empurrados para o próximo intervalo livre. Criar uma track de texto para cada elemento simultâneo e conferir `start`/`duration` na inspeção. Vídeo com codec válido não comprova a composição correta.
3. O log informou falha DNS ao consultar `drift-addons.cutwire.org` no sandbox. Os recursos incluídos funcionaram. Não afirmar que o serviço está indisponível globalmente e não instalar complementos ou mudar segurança de rede sem escopo aprovado.
4. Qt emitiu aviso sobre QOffscreenSurface fora da thread GUI. O contexto OpenGL e os exports funcionaram neste teste; manter o aviso documentado, não tratá-lo como garantia de estabilidade futura.
5. `save_project` responde antes de confirmar persistência. Conferir caminho, `dirty=false` e presença do arquivo. `apply` não é atômico; não reutilizar uma timeline parcialmente alterada após erro. Tentativas anteriores ficaram em pastas distintas e foram preservadas.
6. A exportação Drift resultou em arquivo cerca de 8,7 vezes maior que o piloto Konva. O ajuste de bitrate/qualidade para uso operacional ainda precisa ser avaliado antes de uma integração.

Documentação oficial de automação: https://github.com/CutWire-Studios/Drift/blob/main/docs/MCP.md
