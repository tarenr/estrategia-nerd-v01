ID: FEAT-youtube-agendamento-remoto-imediato-e-capas
Tipo: Melhoria e correção (módulo YouTube Studio)
Nome: Agendamento remoto imediato no YouTube e sincronização de capas

Objetivo:
Garantir que todo vídeo cadastrado como agendado no painel do YouTube seja enviado imediatamente para os servidores do YouTube com agendamento nativo privado (publishAt), recebendo de imediato o ID do YouTube, permalink para visualização na plataforma e status "Agendado no YouTube". Além disso, garantir que capas personalizadas (capa.jpg) sejam enviadas via API (setThumbnail) e que a URL de miniatura (thumbnail_url) retornada pela CDN do YouTube seja capturada e persistida automaticamente no banco durante a reconciliação periódica.

Contexto e problemas resolvidos:
1. Vídeos agendados ficavam retidos na fila local com remote_upload_due_at calculado para 24 horas antes da publicação (YouTubeVideoRules::remoteUploadDueAt), impedindo a visualização prévia do link externo e mantendo status "Agendado" em vez de "Agendado no YouTube".
2. Itens agendados exibiam um bloco preto sem miniatura na coluna "MÍDIA" da tabela de vídeos quando não possuíam thumbnail_path preenchido e porque o método reconcile() de YouTubePublishService não persistia as URLs de miniaturas retornadas pelo YouTube.
3. Foram adicionados métodos dedicados em YouTubeVideoRepository (updateThumbnailUrl e updateThumbnailPath) e integrado o disparo de setThumbnail com upload da capa personalizada do Reel.

Arquivos alterados:
- app/Services/YouTube/YouTubeVideoRules.php: adicionado immediateUploadDueAt() para retorno do timestamp imediato.
- app/Controllers/Admin/YouTubeController.php: store() passa a usar immediateUploadDueAt() em novos agendamentos.
- app/Services/YouTube/YouTubePublishService.php: reconcile() extrai e persiste automaticamente a melhor thumbnail_url (maxres, standard, high, medium, default) quando o registro estiver sem miniatura.
- app/Repositories/YouTubeVideoRepository.php: adicionados updateThumbnailUrl() e updateThumbnailPath().
- app/Views/site/local-docs-v2.php: documentação da Central Técnica atualizada com a nova rotina de envio imediato e capas.
- docs/features/FEAT-youtube-integration.md: fluxo operacional atualizado para despacho imediato com publishAt.
- docs/governanca/molde-serie-echo.md: diretriz de agendamento de Shorts atualizada.

Banco de dados:
- Mudanca de schema: nao (tabela youtube_videos ja continha os campos thumbnail_path e thumbnail_url)
- Mudanca de dados: sim (atualizacao pontual dos registros #224 e #225 com remote_upload_due_at imediato, caminhos de capa.jpg e URLs de miniatura do YouTube)
- Script necessario: nao

Dependencias:
- nenhuma nova dependencia

Impacto em producao: nenhum (módulo exclusivo de gestão e sincronização local)
Afeta rotas criticas: nao

Risco:
Baixo. Upload resumível com tolerância a falhas, reconciliação idempotente e validação prévia de arquivos.

Origem validada: local
Validado local: sim (scripts/verify-changes.php com 37 OK | 0 falhas; uploads dos itens #224 e #225 executados com sucesso e confirmados com status agendado_remoto, capas no YouTube e links funcionais)
Validado stage: nao

Apto para release: sim
