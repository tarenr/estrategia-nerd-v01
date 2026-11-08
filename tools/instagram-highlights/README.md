# Motor Instagram Stories & Destaques (@estrategia_nerd)

Motor desacoplado e durável em Python para publicação passo a passo de Stories e Destaques no Instagram, com proteção estrita contra duplicação de mutações e reconciliação assistida em caso de timeout/erro de rede.

## Arquitetura e Decisões

1. **Execução Passo a Passo e Mutação Atômica**:
   - Cada chamada de `step` realiza no máximo UMA mutação externa (upload de 1 story ou criação/edição do destaque).
   - Antes de cada requisição remota, o estado é gravado como `pending`.
   - Se ocorrer falha de rede ou timeout, o status torna-se `unknown`, exigindo reconciliação manual assistida (`reconcile`) com confirmação visual pelo operador no aplicativo do Instagram.
   - NUNCA há retentativa automática ou duplo envio cego após resposta incerta.

2. **Adaptador Instagrapi (`adapter.py`)**:
   - Subclasse `SingleAttemptClient` que desativa laços de repetição interna para mutações privadas (`session_retry_total = 0` e tratamento cirúrgico de `ClientRequestTimeout` e `ClientIncompleteReadError`).
   - Normalização de identificadores: trata PK numérico (`pk`) e IDs completos compostos (`pk_uid`), evitando comparações incorretas.
   - Posicionamento explícito da figurinha de link acima do CTA da arte (`x: 0.5`, `y: 0.70`, `width: 0.38`, `height: 0.07`; cerca de 410×134 px em 1080×1920).
   - Validação de titularidade: exige estritamente correspondência com `@estrategia_nerd` e UID numérico.

3. **CLI Padronizada (`cli.py`)**:
   - Comandos permitidos: `status`, `job`, `prepare`, `step`, `catalog`, `reconcile`.
   - Comunicação via JSON em `stdin` / `stdout`.
   - Tratamento de exceções com sanitização: segredos, tokens e senhas nunca são emitidos em mensagens de erro ou logs.

4. **Autenticação Segura via Terminal (`auth_cli.py`)**:
   - Comando interativo:
     ```bash
     storage\instagram-highlights\venv\Scripts\python.exe tools\instagram-highlights\auth_cli.py
     ```
   - Solicita senha com máscara oculta (`getpass`) e suporte a 2FA (SMS / Authenticator app).
   - Salva a sessão privada exclusivamente em `storage/instagram-highlights/auth/session.json` com permissões restritas no Windows. Nenhuma senha em texto puro é gravada.

5. **Testes Unitários Offline**:
   - Executar suíte completa:
     ```bash
     storage\instagram-highlights\venv\Scripts\python.exe -m unittest discover -s tools/instagram-highlights/tests -p "test_*.py" -v
     ```

   - Interface: `node --test tools/instagram-highlights/tests/test_ui.cjs`.
   - Ponte PHP: `C:\xampp\php\php.exe tools/instagram-highlights/tests/test_php_bridge.php`.
   - Estado da validação e pendências: [documentação da funcionalidade](../../docs/features/FEAT-instagram-highlights.md#5-procedimentos-de-teste-e-homologação).
   - Abertura da tela, status e revisão são locais e não carregam a sessão privada. Apenas ações remotas explícitas inicializam o adaptador.
   - A ponte limita duração e saída do processo. No Windows, usa arquivos temporários fora da pasta pública, removidos ao encerrar, para evitar bloqueio de pipes.
