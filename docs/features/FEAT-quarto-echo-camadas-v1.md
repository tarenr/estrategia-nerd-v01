ID: FEAT-quarto-echo-camadas-v1
Tipo: Feature / Governança (cenário oficial e regras de produção da série do Echo)
Nome: Quarto vivo em camadas v1 como padrão oficial da série de vídeos do Echo

Objetivo:
Consolidar o quarto raster vivo em camadas 1080×1920 (`quarto_setup_raster.js` + `producao-camadas-v1/`) como cenário padrão oficial de todos os vídeos da série do Echo (Forge #1915), com cadeira ampliada 1,28×, iluminação determinística por `tempo`, Echo ampliado sentado sobre a almofada da cadeira (`k = 1.50`) e encerramento fluido a 60 fps em que ele pousa o controle 3D sobre o assento e sai voando para o meio do quarto (`k = 1.85`) com a Propulsão Matrix.

Contexto:
O quarto do Echo reproduz o setup real do operador. Na primeira versão em camadas, elementos luminosos estáticos permaneciam gravados nas camadas base (`00-ambiente.png` e `20-mesa-equipamentos.png`) e a camada frontal da cadeira (`31-cadeira-frente.png`) cobria o Echo sentado. O operador solicitou também ampliar a cadeira, ampliar o Echo, implementar o voo fluido de encerramento para o centro do quarto e documentar o resultado como padrão para todos os vídeos do Estratégia Nerd.

Arquivos alterados:
- conteudo-planejado/videos-echo/_kit-quarto/quarto_setup_raster.js: motor de composição das camadas 1080×1920, iluminação determinística por `tempo` (6 painéis hexagonais LED 3D, 3 barras neon, painel pixelado 6×6 em perspectiva e gabinete RGB com 4 ventoinhas e memórias RAM) e callbacks `desenharEcho`, `desenharSobreCadeira`, `desenharSobreMesa` e `desenharTela`.
- conteudo-planejado/videos-echo/_kit-quarto/producao-camadas-v1/: gerador (`gerar-assets.mjs` com limpeza Coons da parede, base escura das ventoinhas/visor pixel e ampliação 1,28× da cadeira), coordenadas e contrato temporal (`camadas.mjs`), demonstração 60 fps (`demo.html`, `demo.mjs`, `render.cjs`, `render-preview.cjs`), suíte de 16 testes (`quarto-raster.test.mjs`) e README.
- conteudo-planejado/videos-echo/_kit-quarto/README.md e pecas.json: registro do quarto vivo em camadas v1 como padrão oficial da série a partir de 06/11/2026.
- docs/governanca/molde-serie-echo.md e docs/governanca/diretrizes-redes-sociais.md: regras do quarto padrão, coreografia de encerramento a 60 fps e novos aprendizados técnicos na seção 8.2.
- app/Views/site/local-docs-v2.php e app/Controllers/Admin/TechnicalCenterController.php: atualização da Documentação Técnica (`/local/docs`) e dos cartões de Procedimentos e Padrões na Central Técnica.
- Skill `video-echo-serie` (5 cópias sincronizadas: `estrategia-nerd/.claude/skills`, `~/.claude/skills`, `~/.codex/skills`, `~/.gemini/skills` e `~/.gemini/config/skills`).

Banco de dados:
- Mudanca de schema: nao
- Mudanca de dados: nao
- Script necessario: nao

Dependencias:
- nenhuma

Impacto em producao: nenhum (ativos de produção de vídeo, documentação interna e cartões do admin local)
Afeta rotas criticas: nao

Risco:
Baixo. Nenhum vídeo anterior já publicado foi alterado; o novo padrão aplica-se às próximas produções da série.

Origem validada: local
Paridade local -> stage: nao validada
Paridade stage -> pacote: nao validada

Validado local: sim (16/16 testes em `quarto-raster.test.mjs`, render 60 fps validado com `ffprobe`/`ffmpeg` e `scripts/verify-changes.php` com 0 falhas)
Validado stage: nao

Apto para release: sim (sem impacto nas rotas públicas do portal)

Observacoes:
Prévia pública 1080×1920 a 60 fps (8 s, 480 quadros) validada pelo operador em `public/uploads/previews/quarto-echo-vivo-20261106/previa-quarto-vivo.mp4`.
