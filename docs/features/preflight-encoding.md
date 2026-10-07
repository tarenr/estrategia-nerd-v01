# Preflight — acentos legítimos e sequências corrompidas

Correção de 07/10/2026, tarefa Forge #869. O scanner anterior tratava qualquer letra depois de `Ã` ou `Â` como corrupção. Isso bloqueava textos corretos como “Âmbar”, “BOTÃO” e a lista de letras acentuadas do renderizador editorial. Nenhum desses arquivos precisava ser reescrito.

`scripts/preflight-encoding.php` centraliza a classificação e é usado pelo preflight normal. A detecção exige sequências características de bytes de continuação UTF-8 interpretados como Latin-1/Windows-1252, em vez de apenas procurar uma letra acentuada. Continua identificando exemplos de dupla conversão, pontuação/emoji corrompidos, caractere de substituição e bytes que não formam UTF-8 válido. As verificações existentes de interrogação dentro de palavras, conflitos de merge, caminho canônico e arquivos críticos permanecem ativas. Não há lista de arquivos dispensados nem opção para ignorar erros.

```powershell
C:\xampp\php\php.exe scripts/verify-preflight-encoding.php
C:\xampp\php\php.exe scripts/preflight-check.php
```

Resultado: 14 casos isolados aprovados, incluindo letras legítimas e exemplos corrompidos; preflight completo aprovado com 262 arquivos verificados. Os avisos sobre alterações locais anteriores e header explícito de status foram preservados. A suíte obrigatória teve 17 aprovações e PHPStan nível 5 sem erros. O detector é uma verificação heurística: não substitui revisão de texto e encoding.

A correção é uma ferramenta local de publicação. Não foi incluída no pacote remoto de SEO nem introduz dependências ou mudança de banco. Resultado da publicação: `docs/releases/RELEASE-2026-10-07-seo-stage.md`.
