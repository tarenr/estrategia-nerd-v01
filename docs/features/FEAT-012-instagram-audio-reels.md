# FEAT-012 — Trilha Sonora e Geração de Reels para Instagram

## 1. Visão Geral

A funcionalidade **FEAT-012** implementa suporte a trilhas sonoras musicais no módulo Instagram do **Estratégia Nerd**. 

Como a API Graph da Meta não permite anexar faixas de áudio a posts estáticos de fotos ou carrosséis normais, a solução une dois mundos:
1. **Biblioteca Local & Busca Online Audius (Open API, sem chave)**: navegação em faixas livres com preview instantâneo e download com 1 clique para o servidor local.
2. **Compilação Automática em Reels (1080×1920 MP4 via FFmpeg)**: qualquer post com imagem ou sequência de imagens que contenha uma trilha sonora selecionada é automaticamente transcodificado em vídeo vertical de alta qualidade (H.264 + AAC 48kHz) com corte de áudio no ponto exato escolhido pelo usuário e fade-out suave de 1.5s no encerramento.

---

## 2. Decisões Arquiteturais e de Negócio

### 2.1 Interface Discreta e Oculta por Padrão
- Por padrão, a interface de trilha sonora fica **colapsada/oculta** no formulário de criação e edição (`create.php` e `edit.php`).
- Um botão com identidade visual roxa/neon `[ 🎵 Adicionar Trilha Sonora (Reel com Música) ]` permite abrir a gaveta de configuração apenas quando o usuário desejar.
- Ao clicar em "Remover trilha", os dados são limpos e o painel volta a ser ocultado.

### 2.2 Duração Dinâmica Calculada por Imagens
- Em vez de enviar músicas completas de vários minutos, a duração do Reel é dimensionada de acordo com o volume de imagens:
  $$\text{Duração} = \min(30, \max(10, \text{total\_imagens} \times 4))$$
  - **1 imagem**: 10 segundos estáticos com a música de fundo.
  - **2 imagens**: 10 segundos (5s cada).
  - **4 imagens**: 16 segundos (4s cada).
  - **8 imagens**: 30 segundos (máximo permitido pela regra).
- O cálculo é reativo e atualiza em tempo real conforme o usuário adiciona ou remove fotos na dropzone.

### 2.3 Régua de Seleção e Corte da Música
- O usuário pode escolher qualquer ponto de início (`audio_start_seconds`) da música usando uma barra deslizante (slider) interativa.
- O slider possui limite máximo dinâmico: $\text{max\_start} = \max(0, \text{duração\_total} - \text{duração\_reel})$, garantindo que a música nunca termine antes do fim do vídeo.
- Um botão `[ ▶ Ouvir trecho do Reel (X s) ]` executa apenas o trecho exato que entrará no vídeo e pausa automaticamente após a duração prevista.

---

## 3. Integração com a API Audius

- **Provedor**: `https://discoveryprovider.audius.co` (rede descentralizada de música livre).
- **Autenticação**: Não requer chaves de API pagas ou tokens cadastrados.
- **Endpoints Utilizados**:
  - `GET /v1/tracks/search?query={q}&limit={limit}&app_name=EstrategiaNerd`: busca por termos ou tags rápidas (`synthwave`, `gaming`, `chiptune`, `lofi`, `cyberpunk`, `epic`).
  - `GET /v1/tracks/{track_id}/stream?app_name=EstrategiaNerd`: redireciona (HTTP 302) para o stream MP3 original dos nós de armazenamento IPFS/Audius.
- **Cache e Desduplicação**: Faixas baixadas ficam salvas em `public/uploads/audio/audius_{track_id}.mp3` e são indexadas na tabela `instagram_audio_tracks` com hash SHA-256 e duração real auditada via `ffprobe`.

---

## 4. Pipeline de Transcodificação FFmpeg

O serviço `App\Services\Instagram\AudioReelGeneratorService` orquestra a geração dos vídeos atendendo às especificações rígidas do Instagram Reels:
- **Resolução**: 1080×1920 (proporção 9:16 vertical). Imagens em proporção diferente são dimensionadas proporcionalmente sem cortes e preenchidas com padding preto centralizado.
- **Codec de Vídeo**: H.264 (`libx264`), perfil progressivo, 30 fps, pixel format `yuv420p`, CRF 22, preset fast.
- **Codec de Áudio**: AAC estéreo, taxa de amostragem 48.000 Hz, bitrate 128 kbps.
- **Fade de Áudio**: `-afade=t=out:st={duration - 1.5}:d=1.5` para uma finalização suave.
- **Otimização Web**: `-movflags +faststart` para posicionar o átomo `moov` no início do contêiner MP4.

### 4.1 Suporte a Vídeo de Entrada com Trilha Externa
Além de imagens estáticas, o `AudioReelGeneratorService::generateReel` aceita **1 arquivo de vídeo** (ex.: `.mp4`, `.mov`, `.webm`) como entrada para o Reel com trilha sonora:
- **Sem repetição desnecessária**: Remove o parâmetro `-loop 1` (exclusivo para imagens), evitando falhas do FFmpeg (`Option loop not found`).
- **Descarte de áudio original**: O áudio original embutido no vídeo é completamente descartado no transcode (`-map "[v]" -map "[a]"`), sendo substituído integralmente pela trilha sonora selecionada cortada em `startSeconds` com fade-out final de 1.5s.
- **Regra de duração**: Se `audio_duration_seconds` for maior que zero, utiliza exatamente essa duração; caso contrário, detecta a duração do vídeo de entrada via `ffprobe` e limita a no máximo 60 segundos.
- **Validação de entrada**: Não é permitida a mistura de vídeo com imagens nem o envio de mais de 1 vídeo para um post com trilha sonora. Nesses casos, o método rejeita a execução imediatamente com a mensagem `"Post com trilha aceita imagens ou 1 vídeo"`, sem onerar o FFmpeg.

---

## 5. Estrutura do Banco de Dados

### 5.1 Tabela `instagram_audio_tracks`
- `id`: Chave primária auto-incremental.
- `origem`: Enum (`local`, `audius`, `custom`).
- `origem_id`: ID externo (ex.: ID no Audius).
- `titulo`, `artista`, `genero`: Metadados da faixa.
- `arquivo_path`: Caminho relativo em disco (ex.: `uploads/audio/...`).
- `duracao_s`: Duração total em segundos.
- `file_hash`: Hash SHA-256 para evitar duplicidades.
- `ativo`: Booleano para controle de catálogo.

### 5.2 Novos Campos em `instagram_posts`
- `audio_track_id`: FK para `instagram_audio_tracks.id` (ON DELETE SET NULL).
- `audio_start_seconds`: Ponto de início do corte em segundos.
- `audio_duration_seconds`: Duração calculada do Reel.
- `video_rendered_path`: Caminho do arquivo MP4 gerado (ex.: `uploads/reels/...`).
- `render_status`: Status da renderização (`idle`, `rendering`, `ready`, `failed`).

---

## 6. Endpoints Adicionados

| Método | Rota | Descrição |
|---|---|---|
| `GET` | `/admin/instagram/api/audio/local` | Lista faixas ativas da biblioteca local |
| `GET` | `/admin/instagram/api/audio/search` | Busca faixas na API pública do Audius |
| `POST` | `/admin/instagram/api/audio/download` | Baixa faixa do Audius para a biblioteca local |
| `POST` | `/admin/instagram/api/audio/upload` | Upload manual de arquivo MP3 customizado |
