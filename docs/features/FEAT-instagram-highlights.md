# FEAT — Instagram Stories e Destaques (@estrategia_nerd)

## 1. Visão Geral e Objetivo

Esta funcionalidade implementa uma **segunda integração do Instagram** para o Estratégia Nerd, focada exclusivamente na publicação sequencial e gerenciamento de **Stories e Destaques** da conta `@estrategia_nerd`.

A integração existente baseada na **Meta Graph API v21.0** permanece 100% intacta, operando paralelamente para feed e Reels. Esta segunda integração utiliza a biblioteca `instagrapi` encapsulada em um motor local durável em Python, projetado para contornar limitações da Graph API oficial para Stories e Destaques sem comprometer a estabilidade do sistema.

---

## 2. Decisões Arquiteturais

### 2.1. Execução Local Estrita (Local-Only)
- O módulo é protegido por `EnvironmentGuard::requireLocal()`.
- Nenhuma operação remota de escrita é permitida em servidores de Stage ou Produção sem autorização prévia e ambiente homologado.
- Requer autenticação de administrador (`Auth::require()`) e validação de tokens CSRF (`Csrf::validate()`).

### 2.2. Motor Desacoplado em Python (`tools/instagram-highlights/`)
- **Isolamento de Runtime:** O motor opera em ambiente virtual dedicado em `storage/instagram-highlights/venv/` (Python 3.13, instagrapi 3.0.24).
- **Comunicação Segura:** O PHP interage exclusivamente via CLI (`tools/instagram-highlights/cli.py`) através de `proc_open` com argumentos estáticos (`bypass_shell`), dados JSON via `stdin` e respostas sanitizadas via `stdout`.
- **Desconexão de Sessão PHP:** O Service fecha a sessão do PHP (`session_write_close()`) antes de despachar o subprocesso para não bloquear navegação em outras abas durante operações demoradas.

### 2.3. Política de Mutação Única (Single-Attempt) e Prevenção de Duplicatas
- **Inspeção do Instagrapi:** A biblioteca padrão repete requisições após `ClientRequestTimeout` e `ClientIncompleteReadError`. Em mutações (upload de story ou criação de destaque), retentativas cegas causam publicações duplicadas no Instagram.
- **Adaptador Seguro (`adapter.py`):** Subclasseia `Client` desativando retentativas automáticas em requisições de mutação.
- **Journal Atômico:** Toda etapa grava o estado `pending` antes de disparar o efeito externo.
- **Tratamento de Timeout / Rede:** Se o resultado for incerto, o status transiciona para `unknown`. NUNCA há retentativa automática. O sistema exige reconciliação manual assistida (`reconcile`) com confirmação visual pelo operador no aplicativo móvel.

### 2.4. Autenticação Segura via Terminal (`auth_cli.py`)
- Para proteger credenciais, a senha NUNCA é enviada por formulários web, nem salva em banco de dados ou logs.
- O operador autentica a conta pelo terminal:
  ```bash
  storage\instagram-highlights\venv\Scripts\python.exe tools\instagram-highlights\auth_cli.py
  ```
- O script lê a senha com máscara oculta (`getpass`), trata desafios de segurança (2FA via SMS/Authenticator e Challenge), valida a titularidade do `@estrategia_nerd` e grava o dump da sessão privada exclusivamente em `storage/instagram-highlights/auth/session.json` com permissões restritas no Windows.

---

## 3. Especificação das Rotas e Contratos

Base: `/admin/instagram-destaques` (Menu lateral logo abaixo de Instagram).

| Método | Rota | Descrição |
|---|---|---|
| `GET` | `/admin/instagram-destaques` | Interface web do estúdio de criação e prévia |
| `GET` | `/admin/instagram-destaques/status` | Estado offline: sessão armazenada, verificação e jobs |
| `GET` | `/admin/instagram-destaques/job?id={hex32}` | Consulta detalhes de um job pelo identificador |
| `GET` | `/admin/instagram-destaques/media/{hex32}` | Entrega segura de imagem da biblioteca privada |
| `POST` | `/admin/instagram-destaques/upload` | Upload multipart (`file`, `kind=story\|cover`) |
| `POST` | `/admin/instagram-destaques/prepare` | Congela o manifesto imutável com hashes SHA-256 |
| `POST` | `/admin/instagram-destaques/step` | Executa 1 mutação por chamada (`job_id`) |
| `POST` | `/admin/instagram-destaques/catalog` | Consulta lista remota de destaques ativos |
| `POST` | `/admin/instagram-destaques/reconcile` | Reconciliação assistida após resultado `unknown` |

---

## 4. Assistente de montagem e publicação

O item **Stories e Destaques**, logo abaixo de Instagram no menu principal, abre uma página própria. A tela apresenta uma etapa por vez, com progresso e navegação por **Voltar** e **Continuar**. O operador fornece as imagens e os links: a automação publica os stories e os reúne no destaque.

### 4.1. Configurar conta
- **Conta ainda não configurada** indica que não há sessão local armazenada. Uma sessão armazenada, por sua vez, não comprova que a autenticação ainda é aceita pelo Instagram.
- **Como conectar minha conta** abre instruções e um comando copiável para executar o `auth_cli.py` no terminal, na pasta do projeto. Esse botão não conecta a conta por conta própria nem recebe senha na página.
- É possível avançar para preparar a montagem antes de configurar a conta. A publicação e a consulta dos destaques existentes exigem uma sessão configurada; a identidade remota é conferida pelo motor antes das operações.

### 4.2. Montar destaque
- Escolher **Criar um destaque** ou **Adicionar a um existente**. A tela mostra somente os campos pertinentes à escolha.
- Para um novo destaque, informar um nome de até 15 caracteres (por exemplo, **PC R$400**) e, se desejar, uma capa quadrada. Em destaque existente, omitir nome e capa preserva os valores atuais.
- Adicionar até 20 imagens JPEG 1080×1920, de até 8 MB cada. Cada story reúne a imagem, o campo **Cole o link desta peça** (HTTPS) e os controles para ordenar ou remover.
- A capa opcional deve ser JPEG quadrado, de 320 a 2160 px, até 8 MB. Os requisitos e erros aparecem junto ao envio.
- A prévia compacta aparece após adicionar conteúdo; no celular, o botão **Ver como ficará** abre a visualização. A figurinha de link exibida é uma simulação.

### 4.3. Conferir e publicar
- Conferir conta, destaque, capa, sequência de imagens e links antes da confirmação final **Publicar stories e montar destaque**.
- Voltar para editar preserva a montagem na página. Depois de congelar uma operação para envio, sua revisão permanece imutável: alterar campos não modifica uma publicação já registrada.
- O progresso identifica a publicação de cada story e a montagem do destaque. Resultado incerto interrompe a execução e pede conferência no aplicativo, sem reenviar automaticamente.
- **Publicações anteriores** fica em uma seção recolhida, com consulta e retomada das operações. Retomar consulta o registro local e exige nova revisão antes de continuar.

Essa atualização modifica somente o fluxo da interface e sua documentação. Autenticação por terminal, contratos da API local, motor de publicação e integração antiga permanecem com suas responsabilidades atuais. A montagem antes da revisão não é um rascunho persistente: navegar para trás dentro do assistente preserva os dados, mas isso não garante recuperá-los após fechar ou recarregar a página.

---

## 5. Procedimentos de Teste e Homologação

### 5.1. Testes Automatizados Locais (100% Offline)
- **Suíte Python:**
  ```bash
  storage\instagram-highlights\venv\Scripts\python.exe -m unittest discover -s tools/instagram-highlights/tests -p "test_*.py" -v
  ```
  Na revisão de 22/10/2026: **41 testes aprovados**, incluindo sessão corrompida, identidade remota, 2FA, proteção da sessão antes da gravação, ausência de retentativas, links normalizados e recuperação de revisão não registrada.
- **Interface (sem acesso ao Instagram):**
  ```bash
  node --test tools/instagram-highlights/tests/test_ui.cjs
  ```
  Na revisão anterior à simplificação da interface, **5 testes aprovados**: revisão congelada, ID estável, recuperação por leitura após falha, desbloqueio somente quando a operação comprovadamente não existe e correspondência dos elementos da tela. A atualização para três etapas também requer verificar navegação, preservação da montagem, revisão, erros e retomada; os resultados devem ser registrados após a execução.
- **Ponte PHP:**
  ```bash
  C:\xampp\php\php.exe tools/instagram-highlights/tests/test_php_bridge.php
  ```
- **Suíte Global de Qualidade:**
  ```bash
  C:\xampp\php\php.exe scripts/verify-changes.php
  ```
  Revisão de 22/10/2026: **22/23 verificações aprovadas**. PHPStan apontou dois erros em alterações preexistentes da integração antiga: chamada de `persistInsights()` com argumentos excedentes em `InstagramController.php:99` e comparação redundante em `InstagramPostRepository.php:1373`. Esses arquivos não foram corrigidos nesta tarefa. A ponte PHP passou nos testes de administrador, CSRF persistido, confirmação booleana estrita, prazo de processo e sanitização.

### Limites da validação atual
- A conferência visual autenticada em desktop/celular permanece pendente: o navegador exibiu login do Cloudflare e login local. Não se alterou autenticação para contornar essa etapa.
- Até a entrega inicial de 22/10/2026, nenhum login, catálogo ou publicação real havia sido executado; a homologação real de 29/10/2026 está registrada na seção 6.
- Resultados incertos continuam bloqueados até confirmação do sucesso no aplicativo. Não há ação para presumir falha e reenviar automaticamente.
- A interface guarda apenas o ID da operação na aba. Ao retomar, consulta o registro local e exige nova revisão antes de continuar as etapas.
- Destaques existentes permitem nome e capa opcionais; omitir preserva os valores atuais.

### 5.2. Procedimento de homologação remota
- Para novas homologações com a conta real `@estrategia_nerd`:
  1. Executar `tools\instagram-highlights\auth_cli.py` no terminal para autenticar a sessão do operador.
  2. Acessar `/admin/instagram-destaques`, avançar de **Configurar conta** para **Montar destaque** e preparar um story com link e um novo destaque.
  3. Em **Conferir e publicar**, conferir o resumo e confirmar o envio autorizado. Verificar no aplicativo o story, o link e a criação do destaque.

---

## 6. Correção e homologação real — 29/10/2026

- O destaque **Echo!** foi homologado na conta real com um story JPEG e sticker apontando para `https://estrategianerd.com.br/post/conheca-o-echo-assistente-estrategia-nerd`.
- Em 29/10/2026, o sticker do **Echo!** foi reposicionado acima do CTA da arte e reduzido para `x=0.5`, `y=0.70`, `width=0.38` e `height=0.07` (cerca de 410×134 px em 1080×1920). A substituição foi publicada primeiro, teve o link conferido e entrou no destaque antes da remoção da versão anterior.
- O story anterior, que não continha link confirmado, foi removido do destaque somente depois de o substituto aparecer na consulta remota.
- O Instagram devolve o destino do sticker por `l.instagram.com`, com o endereço original no parâmetro `u` e um `fbclid` volátil. A conferência agora desembrulha esse redirecionamento e ignora somente `fbclid`, mantendo a comparação de esquema, host, caminho e demais parâmetros.
- A publicação do feed criada pelo Instagrapi usou um PK privado diferente do ID devolvido pela Graph API. `InstagramPostRepository::upsertFromFeed()` passou a localizar primeiro um registro local publicado pelo mesmo permalink e vincular o ID da Graph, evitando dois cards para a mesma publicação.
- O post comprovadamente excluído pela API privada e o registro local duplicado foram preservados com status `cancelado`; não houve exclusão física. O snapshot foi regenerado e publicado em produção com apenas o post válido.
