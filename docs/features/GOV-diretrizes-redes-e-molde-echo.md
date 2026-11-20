ID: GOV-diretrizes-redes-e-molde-echo
Tipo: Governança (documentação e regras de produção)
Nome: Diretrizes de redes sociais no projeto e molde do Echo para Reels + Shorts

Objetivo:
Trazer para dentro do projeto a estratégia de redes definida em 31/10/2026 e atualizar o molde da série do Echo com o que o vídeo do Castlevania ensinou, para nenhuma IA trabalhar sem essas regras de novo (Forge #1801).

Contexto:
A estratégia nova (pilares, público, formato único para Reels e Shorts) ficou só na pasta interna do Antigravity; Codex e Claude Code produziram o Castlevania sem ela, com retrabalho. O molde só tinha a área segura do Reels e o agendamento do Instagram.

Arquivos alterados:
- docs/governanca/diretrizes-redes-sociais.md (novo): posicionamento, público, pilares (Vida de TI suspensa), cadência "vídeo toda semana", Echo e voz, formato híbrido, perfis, direitos autorais, números sem fonte.
- docs/governanca/molde-serie-echo.md: área segura das duas redes (Y 250–1450, X 90–990, X ≤ 925 em Y 970–1450); chamada final só nos comentários; nada vai a público sem o usuário ver; voz atual (edge-tts Antonio); etapa de agendamento do YouTube; prévia quadro a quadro; coluna de exemplo do Castlevania; erros novos (gelatina, coordenadas copiadas, controle ao contrário, conferência de fachada, duas IAs na mesma pasta, legenda adiantando, pedaço de render de 3 s, captura em tempo real, publicação sem mostrar); modelos novos.
- app/Controllers/Admin/TechnicalCenterController.php: 3 cartões de Procedimentos/Padrões com a área segura das duas redes.
- conteudo-planejado/videos-echo/manifesto-estrategia-nerd/ferramentas/render_v12_janela.py: caminho do OneTake corrigido para Projects\media\onetake.
- Skill video-echo-serie (5 cópias: ~/.claude, ~/.codex, ~/.gemini, ~/.gemini/config e estrategia-nerd/.claude) e registro em infra/docs/codex-skills-context7.md.
- app/Views/site/local-docs-v2.php: aviso "Área Segura" da seção Instagram/Reels com a área segura das duas redes (antes: Y 170–1540, só Reels).

Banco de dados:
- Mudanca de schema: nao
- Mudanca de dados: nao
- Script necessario: nao

Dependencias:
- nenhuma

Impacto em producao: nenhum (documentação e cartões do admin local)
Afeta rotas criticas: nao

Risco:
Baixo. A skill muda o comportamento das três IAs na produção de vídeos do Echo.

Origem validada: local
Paridade local -> stage: nao validada
Paridade stage -> pacote: nao validada

Validado local: sim (verify-changes.php: 33 OK, 0 falhas; conteúdo dos cartões renderizado pelo controller com os textos novos)
Validado stage: nao

Apto para release: sim (sem mudança de comportamento do site)

Observacoes:
A página Documentação Técnica (app/Views/site/local-docs-v2.php, seção Instagram/Reels) ainda citava a área segura antiga do Reels (Y 170–1540); corrigida em plano próprio no mesmo dia (01/11/2026), dentro da #1801.
