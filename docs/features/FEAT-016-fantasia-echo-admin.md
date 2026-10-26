ID: FEAT-016
Tipo: Feature
Nome: Fantasia do Echo escolhida no admin (por ambiente)

Objetivo:
Escolher pelo admin com que roupa o Echo aparece na home, em cada ambiente (local, stage,
produção), sem mexer em código.

Contexto:
A home com o rio de luz (FEAT-015) mostra o Echo saindo de um portal. A fantasia era fixa no
código (Jack Esqueleto, colocada por outra IA). O dono pediu uma tela no admin para escolher, que
funcione igual às outras telas: segue o seletor de ambiente do admin.

Como funciona:
- Lista de fantasias em config/echo-fantasias.php: id, nome e peças (chaves de RoboRoupas.ITEMS em
  public/assets/vendor/echo-robo/robo-roupas-echo.js; só peças que não são de teste). Hoje: Sem
  fantasia, Jack Esqueleto (padrão), Kratos, Tyrael, Darth Vader, Suporte de TI, Programador e
  Moletom Estratégia Nerd. Para mudar o padrão, troque "padrao" no arquivo.
- Tela /admin/echo (menu Sistema > Fantasia do Echo; permissão multi_env_settings, a mesma de
  Configurações): cards com o Echo vestido de verdade (mesmo motor da home), marca a fantasia e
  salva. Lê e grava a chave echo_fantasia na tabela configuracoes do ambiente alvo do seletor
  (TargetEnvironmentDatabase), com CSRF e a confirmação "PRODUCAO" para produção, como em
  Configurações. Fantasia fora da lista é recusada.
- Site: HomeService entrega echo_outfit (peças da fantasia salva no banco do próprio ambiente, via
  portal_config); a view grava window.EN_ECHO_OUTFIT e site-home-flow.js veste o Echo. Sem escolha
  ou com falha de leitura, vale o padrão; peça que não existir no motor é ignorada.

Arquivos criados:
- config/echo-fantasias.php
- app/Support/EchoFantasias.php
- app/Controllers/Admin/EchoController.php
- app/Views/admin/echo/index.php
- docs/features/FEAT-016-fantasia-echo-admin.md

Arquivos alterados:
- config/routes.php (GET e POST /admin/echo)
- app/Views/components/admin/sidebar.php (item Fantasia do Echo)
- app/Services/Site/HomeService.php (echo_outfit no ViewModel)
- app/Views/site/home-circuito-preview.php (window.EN_ECHO_OUTFIT)
- public/assets/js/site-home-flow.js (veste as peças recebidas; saiu o Jack fixo)
- app/Views/site/local-docs-v2.php e README.md (documentação)

Banco de dados:
- Mudanca de schema: nao
- Mudanca de dados: sim, só ao salvar pela tela (linha echo_fantasia em configuracoes do ambiente
  alvo). Sem a linha, vale o padrão.
- Script necessario: nao

Dependencias:
- Nenhuma nova (motor do Echo já copiado em public/assets/vendor/echo-robo/)

Impacto em producao: nulo até o deploy (o admin só existe no local; gravar a escolha de stage ou
produção antes do deploy não muda o site de lá, porque o código atual não lê a chave)
Afeta rotas criticas: nao

Risco: baixo

Origem validada: local (branch feature/homepage-circuito-continuo)
Paridade local -> stage: validada (EchoFantasias.php, config/echo-fantasias.php e robo-* idênticos por hash; HomeService = versão da stage + a linha echo_outfit)
Paridade stage -> pacote: validada (publicado em produção em 25/10/2026 junto com a FEAT-015, Forge #1676)
Validado producao: sim (home lê a fantasia; sem escolha gravada em produção, vale o padrão Jack. Para
trocar: admin local, alvo Produção, /admin/echo, digitar PRODUCAO)

Validado local: sim (25/10/2026)
- Tela renderizada com o alvo em local, stage e produção: lê o banco de cada um sem erro; sem
  escolha gravada mostra o padrão; em produção pede "PRODUCAO".
- Gravação só no local: Kratos, Sem fantasia e Jack chegam à home com as peças certas; fantasia
  inexistente é recusada sem gravar. O banco local terminou com echo_fantasia = jack.
- Prévia visual dos 8 cards conferida em captura.
- verify-changes: 22 OK e 1 falha antiga (2 erros do PHPStan em InstagramController.php:99 e
  InstagramPostRepository.php:1373, fora desta entrega); smoke-test local: 26/26.
Validado stage: sim (25/10/2026, junto com o deploy da FEAT-015, Forge #1675)
- Admin local com o alvo em Stage: Kratos gravado no banco da stage chegou à home da stage
  (window.EN_ECHO_OUTFIT com as peças de Kratos); depois Jack, idem. O banco da stage ficou com
  echo_fantasia = jack. Local intacto.
- Arquivos do admin (EchoController, view, sidebar, rotas) não foram para a stage: o admin não roda lá.

Apto para release: sim, junto com a FEAT-015 (pacote tirado da stage)

Observacoes:
- Tarefas no Forge: #1673 (tela) e #1674 (documentação).
