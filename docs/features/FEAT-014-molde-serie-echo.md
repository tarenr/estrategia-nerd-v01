ID: FEAT-014
Tipo: Feature
Nome: Molde da série de vídeos do Echo (documentação interna + skills)

Objetivo:
Registrar na documentação interna como produzir os vídeos da série do Echo (apresentador do @estrategia_nerd), separando o que é igual em todo vídeo, o que se decide em cada vídeo com o usuário e os erros já cometidos, com o nível mínimo de detalhe de cenário e animação. Transformar o fluxo em skills para todas as IAs usadas no projeto.

Contexto:
Criado em 20/10/2026 depois do Manifesto Estratégia Nerd (v12, 60 s, aprovado e agendado para 21/10/2026 16:00, post #23397) e do Reel do Dia do Profissional de TI. As duas produções tiveram retrabalho por final repetitivo, boca parada, cenários pobres, legenda pequena ou cobrindo texto, e relatórios de IA não conferidos. O usuário pediu que a documentação entre na documentação interna do sistema e que as escolhas de um vídeo (ex.: fonte da legenda) não virem padrão. Forge #1560–#1564.

Arquivos alterados:
- docs/governanca/molde-serie-echo.md (novo): molde da série; abre na Central Técnica em Mudanças → governanca
- docs/governanca/guia-direcao-audiovisual-ia.md (novo): guia de direção audiovisual (antes só em Downloads) + seção 15 com os erros registrados
- app/Views/site/local-docs-v2.php: bloco "Série de Vídeos do Echo (Molde)" na seção 11 (Instagram & Reels), com links para os dois documentos
- app/Controllers/Admin/TechnicalCenterController.php: cartões em Procedimentos ("Produzir um Vídeo da Série do Echo", "Agendar um Reel na Fila do Instagram"), Padrões ("o que é igual em todo vídeo", "nível de detalhe de cenário e animação") e FAQ (woff2 no ffmpeg, arquivo > 30 MB no celular, render travado/quadros perdidos)
- .claude/skills/video-echo-serie/SKILL.md e .claude/skills/agendar-reel-instagram/SKILL.md (novos, no repositório), com cópias para Claude Code, Codex, Gemini CLI e Antigravity no perfil do usuário

Banco de dados:
- Mudanca de schema: nao
- Mudanca de dados: nao
- Script necessario: nenhum

Dependencias:
- nenhuma nova

Impacto em producao: nenhum (Central Técnica e documentos só no ambiente local)
Afeta rotas criticas: nao

Risco:
Baixo. Somente texto em views/arrays de conteúdo do admin local; validado com scripts/verify-changes.php e renderização das páginas.

Origem validada: local
Paridade local -> stage: nao se aplica (admin e /local só no ambiente local)
Paridade stage -> pacote: nao se aplica

Validado local: sim (ver tarefa #1562 no Forge)
Validado stage: nao se aplica

Apto para release: nao se aplica (documentação local)

Complemento (20/10/2026, Forge #1565–#1567):
- Regra "Fim do vídeo: o que guardar e o que apagar" no molde (seção 11), na skill video-echo-serie (etapa 10), no cartão de Procedimentos e no FAQ.
- Pacotes de origem criados e testados: conteudo-planejado/videos-echo/manifesto-estrategia-nerd/ (31,3 MB; 20 instantes idênticos à original) e conteudo-planejado/videos-echo/echo-dia-do-ti/ (6,6 MB; páginas abrem sem erro, 6 instantes idênticos).
- Lixo apagado: 6,30 GB em 33 pastas (quadros PNG, pedaços de render, perfis de navegador). scratch/ e scripts/scratch/ passam a ser ignorados pelo git.

Observacoes:
O guia original continua em C:\Users\WINDOWS\Downloads\guia-direcao-audiovisual-estrategia-nerd.md. Cada vídeo novo da série acrescenta suas escolhas (como exemplo) e seus erros no molde; regra nova da série só com aprovação do usuário. Sem commit (não solicitado).
