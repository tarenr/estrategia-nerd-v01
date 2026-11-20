# Radar de Conteúdo, Pesquisa e Transcrições — YouTube (FEAT-017)

## Visão Geral

O **Radar de Conteúdo do YouTube** complementa o módulo oficial de publicação (`/admin/youtube`), funcionando como uma ferramenta de inteligência competitiva e inspiração para a produção de conteúdo do **Estratégia Nerd** e para o robô apresentador **Echo**.

Diferente do publicador (que utiliza a API Oficial OAuth com cota diária de 10.000 unidades), o Radar utiliza **APIs internas do YouTube (InnerTube)** e utilitários de extração (**`yt-dlp`** / **`youtube-transcript-api`**), permitindo varredura de tendências, busca de Shorts virais e extração de roteiros completos com **zero consumo de cota na Google Cloud** e sem risco de segurança para o canal oficial.

---

## Objetivos e Casos de Uso

1. **Monitoramento de Tendências e Virais**:
   - Varrer palavras-chave do nicho tech/nerd (ex.: *"setup geek"*, *"hardware custo benefício"*, *"easter eggs de games"*, *"curiosidades de tecnologia"*).
   - Identificar Shorts que estão recebendo alta tração e velocidade de visualizações nas últimas 24h e 7 dias.

2. **Extração Automática de Transcrições (Roteiros do Echo)**:
   - Extrair a transcrição completa (falas palavra por palavra com marcação de tempo) de qualquer Short ou vídeo longo de referência.
   - Alimentar os prompts de IA para que o robô Echo reescreva o conteúdo no tom característico do Estratégia Nerd (conciso, carismático e informativo).

3. **Análise de Miniaturas e Metadados**:
   - Baixar capas originais em alta definição (`maxresdefault`) e inspecionar tags ocultas utilizadas por vídeos bem ranqueados na busca.

---

## Arquitetura Técnica

```
  ┌────────────────────────────────────────────────────────┐
  │         Painel Administrativo (/admin/youtube)         │
  │                  Aba: Radar & Virais                   │
  └───────────────────────────┬────────────────────────────┘
                              │
                    Chamada PHP / Controller
                              │
            ┌─────────────────┴─────────────────┐
            ▼                                   ▼
    [ Servico Python / CLI ]           [ Servico InnerTube ]
        yt-dlp wrapper                      youtubei/v1
            │                                   │
            ├─ Extração de Transcrições         ├─ Busca sem cota
            ├─ Download de Capas em Alta        ├─ Shorts recomendados
            └─ Metadados e Tags                 └─ Tendências do nicho
```

### Componentes Planejados

1. **`app/Services/YouTube/YouTubeRadarService.php`**:
   - Serviço PHP responsável por orquestrar consultas ao script auxiliar Python ou CLI.
   - Cache local em SQLite/MySQL (`youtube_radar_cache`) com expiração de 6 horas para evitar requisições repetidas.

2. **`tools/youtube-radar/` (Script Auxiliar Python)**:
   - Utiliza as bibliotecas `innertube` e `youtube-transcript-api`.
   - Execução isolada em modo subprocesso (`python tools/youtube-radar/radar.py --action search --query "..."`).
   - Retorno estrito em formato JSON UTF-8.

3. **Interface no Painel (`/admin/youtube/radar`)**:
   - Campo de busca instantânea com filtros: *Mais Vistos*, *Mais Recentes*, *Somente Shorts*, *Somente Vídeos Longos*.
   - Botão **"Copiar Roteiro / Transcrição"** em cada card para acelerar a criação de novos episódios.
   - Botão **"Enviar para Criação do Echo"** integrando diretamente com o molde da série do Echo.

---

## Comparação de Camadas

| Funcionalidade | Módulo Publicador (`/admin/youtube`) | Módulo Radar (`/admin/youtube/radar`) |
| :--- | :--- | :--- |
| **Mecanismo** | YouTube Data API v3 (OAuth 2.0) | InnerTube API / `yt-dlp` |
| **Cota Google Cloud** | Consome quota oficial | **Zero cota consumida** |
| **Objetivo** | Upload, agendamento, métricas do canal | Espionagem, referências, transcrições |
| **Segurança** | Autenticação oficial com Google | Leitura pública anônima (sem credenciais) |

---

## Status da Implementação
- **Status**: Concluído e Validado (2026-11-20)
- **Tarefa no Forge**: `#1754 - [YouTube] - Radar de Pesquisa, Virais e Transcricoes (InnerTube / yt-dlp)`
- **Projeto**: Estratégia Nerd (ID: 4)
- **Rotas Ativas**:
  - `GET /admin/youtube/radar` (Interface principal do Radar)
  - `GET /admin/youtube/radar/transcript` (Endpoint JSON de transcrições)
- **Arquivos Entregues**:
  - `tools/youtube-radar/radar.py`: Engine Python com suporte à InnerTube API (`youtubei/v1/search`) e extração de legendas com `youtube-transcript-api 1.2.5`.
  - `app/Services/YouTube/YouTubeRadarService.php`: Orquestrador com cache local em `storage/cache/youtube-radar/` (TTL de 3h para busca, 24h para transcrições) e gestão limpa de ambiente do SO.
  - `app/Controllers/Admin/YouTubeController.php`: Métodos `radar()` e `radarTranscript()` adicionados.
  - `app/Views/admin/youtube/radar.php`: Interface dark/neon responsiva com filtros de formatos (Todos/Shorts/Vídeos), pills de termos em alta e modal de transcrição com cópia de roteiro para o Echo com 1 clique.
  - `app/Views/admin/youtube/index.php`: Botão de acesso "Radar de Conteúdo" integrado ao cabeçalho do YouTube Studio.
- **Validação Visual**:
  - Render real validado em desktop (1920x1080) e mobile (390x844).
  - Modal testado e validado com teletransporte para o `document.body` e cópia para a área de transferência.
