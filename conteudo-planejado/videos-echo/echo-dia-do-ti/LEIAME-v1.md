# Reel do Echo — Dia do Profissional de TI (19/10)

Vídeo vertical (1080x1920 @ 30 fps) com o Echo vestido na fantasia temática de TI (headset de suporte, óculos de programador, moletom e caneca de café), gravado na sala de servidores e sincronizado com música e legendas na área segura do Instagram Reels.

---

## 1. Arquivos Principais

- `echo-dia-do-ti.mp4`: Vídeo final montado (1080x1920, H.264 + AAC, 30 fps, ~20,5 s, loudness -15.7 LUFS).
- `capa.jpg`: Capa do Reel em alta resolução (1080x1920) com o Echo de TI e tipografia comemorativa.
- `cena.html`: Palco visual da cena em CSS 540x960 (renderizado a 1080x1920 com `deviceScaleFactor: 2`). Carrega o motor real do robô (`/robo-motor.js`), a sala de servidores vetorial com LEDs em `<canvas>`, a animação labial, o apagão no reinício e o fecho de encerramento.
- `gravar-ti.js`: Gravador em Node.js via Chrome Headless + CDP screencast. Captura os quadros em alta fidelidade com timeout de segurança de 120 s.
- `montar-ti.py`: Montador em Python e FFmpeg. Realiza o ducking da música (`pixabay_259777.mp3`) sob a voz, aplica normalização `loudnorm` (-15 LUFS), renderiza as legendas dinâmicas na área segura e gera o MP4 e a capa.
- `tempos.json`: Mapa temporal das 6 frases e momentos de animação (boca, apagão das luzes, comemoração e fecho).
- `audio/fala.mp3`: Faixa de voz consolidada (voz Cornelius, ElevenLabs, duração 15,07 s).
- `check/`: Quadros extraídos para validação visual (início, apagão, comemoração e fecho).

---

## 2. Como Trocar a Voz (ElevenLabs)

A pipeline foi construída para permitir a substituição completa da locução sem necessidade de editar nenhum arquivo de código.

### Passo a passo para substituição:
1. Grave o novo áudio na ElevenLabs com a voz Cornelius (ou outra de sua preferência) contendo o texto completo em arquivo único.
2. Salve o arquivo gerado em:
   ```text
   C:\Users\WINDOWS\Projects\output\echo-ti-video\audio\fala.mp3
   ```
   *(Substitua o arquivo existente mantendo o mesmo nome).*

3. **Caso os tempos das frases mudem:**
   - Apague o arquivo `tempos.json` (ou renomeie como backup).
   - Ao rodar `python montar-ti.py`, o script detectará que o `tempos.json` não existe e executará automaticamente o algoritmo inteligente de detecção de pausas via `ffmpeg silencedetect=noise=-35dB:d=0.1`.
   - O detector encontrará as pausas mais próximas da proporção de caracteres de cada uma das 6 frases e recriará o `tempos.json` automaticamente.
   - *Caso o áudio seja muito corrido e não haja pausas detectáveis em algum trecho*, o script recorrerá ao modo proporcional e exibirá um aviso no console.

4. **Regra de Pronúncia ("Echo" vs "Eco"):**
   - Ao gerar áudios na ElevenLabs em língua portuguesa, se o texto contiver o nome do robô, escreva **"Eco"** no prompt enviado à IA para que a síntese neural pronuncie corretamente com som aberto de "éco" e não como "étcho" ou "e-chô". No roteiro atual, o Echo fala "Estratégia Nerd" e não pronuncia seu próprio nome.

---

## 3. Comandos para Regravação e Remontagem

Abra o terminal na pasta `C:\Users\WINDOWS\Projects\output\echo-ti-video` e execute:

```powershell
# 1. Regravar a cena a partir dos novos tempos
node gravar-ti.js

# 2. Remontar o vídeo final com música, legendas e capa
python montar-ti.py
```

O arquivo `echo-dia-do-ti.mp4` e `capa.jpg` serão atualizados imediatamente.

---

## 4. Área Segura das Legendas (Instagram Reels)

Todas as caixas de texto respeitam rigidamente as zonas livres de sobreposição da interface do Reels:
- **Vertical:** Todo texto entre `Y = 170` e `Y = 1540`.
- **Horizontal:** `X ≥ 72` em toda a extensão, e `X ≤ 925` na faixa entre `Y = 970` e `Y = 1790` (para não colidir com os botões de curtir, comentar e compartilhar).
