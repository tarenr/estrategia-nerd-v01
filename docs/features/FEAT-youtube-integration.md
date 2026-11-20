# Integração local com YouTube

O painel `/admin/youtube` gerencia o canal do YouTube no Estratégia Nerd com paridade operacional e visual ao painel do Instagram (`/admin/instagram`):
- Sincronização idempotente do acervo completo do canal (vídeos e Shorts com durações, estatísticas e miniaturas).
- 4 KPIs principais com cálculo de variações históricas: Inscritos, Visualizações, Vídeos/Shorts e Engajamento (curtidas).
- Cartão de perfil do canal com badges de status, data de fundação, contadores e última sincronização.
- Seletor de períodos (7d, 14d, 30d e intervalo personalizado).
- Gráfico diário de desempenho (evolução de visualizações e curtidas por dia).
- Card de distribuição de conteúdo: proporção e contagem entre Shorts (≤ 3 min) e Vídeos longos (> 3 min).
- Listagem completa com alternância entre tabela e grade (cards), busca textual, filtros por tipo, status e ordenação por métricas.
- Página de detalhes com player HTML5 para o arquivo local, inclusive enquanto o vídeo está privado ou agendado; vídeos públicos sem cópia local usam a incorporação do YouTube.
- Fila resiliente de agendamento local de novos vídeos MP4 com upload resumível em blocos.
- Cache local do avatar do canal em `public/assets/youtube/channel_avatar.jpg` e uso de `referrerpolicy="no-referrer"` nas capas e avatares para evitar bloqueios de hotlinking por CDN do Google em `localhost`.

A implementação é local-only: tabelas, arquivos, credenciais e o publicador não são enviados aos ambientes remotos.

## Configuração

Crie um cliente OAuth do tipo aplicação Web no Google Cloud, habilite a YouTube Data API v3 e configure no `.env` local:

```dotenv
YOUTUBE_CLIENT_ID=
YOUTUBE_CLIENT_SECRET=
YOUTUBE_REDIRECT_URI=http://localhost/estrategia-nerd/admin/youtube/callback
```

Cadastre exatamente a mesma URI no cliente OAuth. O sistema solicita os escopos `youtube.upload`, `youtube.readonly` e `yt-analytics.readonly`. O refresh token é persistido em `storage/app/youtube/connection.json`, diretório ignorado pelo Git; nunca o copie para documentação ou logs.

## Fluxo operacional

1. Aplique as migrations `0008_create_youtube_tables.sql` e `0009_expand_youtube_dashboard.sql`.
2. Abra `/admin/youtube` e conecte o canal.
3. Clique em **Sincronizar** para carregar os vídeos e Shorts existentes do canal.
4. Para agendar um novo vídeo: cadastre MP4, metadados, miniatura opcional e horário em `America/Sao_Paulo`.
5. O item entra na fila com despacho remoto imediato (`remote_upload_due_at = NOW()`).
6. O publicador inicia uma sessão resumível, envia o MP4 em blocos, define a capa personalizada via `thumbnails.set` (quando fornecida) e cria o vídeo como privado com `publishAt` (agendamento nativo do YouTube).
7. Na reconciliação periódica (`reconcile`), o sistema monitora o processamento do YouTube, captura e persiste a `thumbnail_url` oficial da CDN do YouTube e atualiza o status para `agendado_remoto` ("Agendado no YouTube"), liberando o permalink externo. A virada para público ocorre automaticamente pelo YouTube na data/hora marcada.

Execução manual segura do publicador:

```powershell
C:\xampp\php\php.exe scripts\en-youtube-publish-scheduled.php --dry-run
C:\xampp\php\php.exe scripts\en-youtube-publish-scheduled.php
```

O agendador usa lock global, reivindicação atômica e não reenvia automaticamente resultados incertos. Esses casos aparecem como `resultado_incerto` e exigem reconciliação antes de uma nova tentativa. Os logs ficam em `storage/logs/youtube` e removem URIs de sessão.

### Procedimento de Ativação Futura do Worker

A automação no Windows Task Scheduler (`EstrategiaNerd-YouTubePublicarAgendados`) é mantida desativada (`Disabled`) durante cargas e preparações editoriais. Para ativá-la com controle operacional antes do primeiro vencimento (`remote_upload_due_at`):

1. Execute um ciclo seco de verificação:
   ```powershell
   C:\xampp\php\php.exe scripts\en-youtube-publish-scheduled.php --dry-run
   ```
2. Habilite a tarefa no Task Scheduler quando a equipe decidir liberar os uploads automáticos:
   ```powershell
   Enable-ScheduledTask -TaskName "EstrategiaNerd-YouTubePublicarAgendados"
   ```

### Regras de Calendário e Idempotência Editorial

- **Calendário Unificado (`/admin/agendamento-posts`):** O método `YouTubeVideoRepository::listForCalendar` seleciona `published_at` para vídeos com status `publicado` e `scheduled_at` para vídeos agendados, garantindo que vídeos passados apareçam nos seus meses de publicação e vídeos futuros nas suas datas programadas, sem conflito de ordenação ou filtros.
- **Idempotência Estrita:** Vídeos editoriais utilizam chaves estáveis com o padrão `editorial:short:<slug>`, protegidas pela restrição única `uq_youtube_idempotency` na tabela `youtube_videos`.

## Verificação

```powershell
C:\xampp\php\php.exe scripts\verify-youtube-integration.php
C:\xampp\php\php.exe scripts\verify-youtube-detail-player.php
C:\xampp\php\php.exe scripts\verify-changes.php
```
