<?php

declare(strict_types=1);

$embedMode = (bool) ($embed_mode ?? false);
$adminEmbed = (bool) ($admin_embed ?? false);
$projectVersion = trim((string) ($project_version ?? 'local'));
$generatedAt = trim((string) ($generated_at ?? date('Y-m-d H:i:s')));
$phaseCount = 5;
$taskCount = 58;
$currentSuggestedPhase = 'Fase 1';
$currentFocus = 'Auditoria critica de seguranca, rotas sensiveis e trilha editorial de midia tipada';
$executionRule = 'Concluir itens criticos da Fase 1 e estabilizar Fase 2 antes de expansao forte em Fase 3/4.';
$publicationRule = 'Backlog e operado no local e so gera mudanca em producao apos validacao. Documentacao permanece somente local.';
$activeSection = trim((string) ($active_section ?? 'visao-geral'));
$sectionBaseUrl = trim((string) ($section_base_url ?? url('/local/backlog?secao=')));

$isActiveSection = static fn (string $section): bool => $activeSection === $section;
$sectionHref = static fn (string $section): string => $sectionBaseUrl . rawurlencode($section) . '#painel-backlog';
$phaseTaskMap = [
    'fase-1' => [
        ['id' => 'F1-T01', 'title' => 'Auditar controllers'],
        ['id' => 'F1-T02', 'title' => 'Auditar services'],
        ['id' => 'F1-T03', 'title' => 'Auditar repositories'],
        ['id' => 'F1-T04', 'title' => 'Revisar views'],
        ['id' => 'F1-T05', 'title' => 'Auditar rotas /admin e /local'],
        ['id' => 'F1-T06', 'title' => 'Validar .env e upload'],
        ['id' => 'F1-T07', 'title' => 'Revisar cobertura de CSRF'],
        ['id' => 'F1-T08', 'title' => 'Validar variaveis de ambiente'],
        ['id' => 'F1-T09', 'title' => 'Comparar fluxo real de deploy'],
        ['id' => 'F1-T10', 'title' => 'Comparar fluxo de backup/restore'],
    ],
    'fase-2' => [
        ['id' => 'F2-T01', 'title' => 'Mapear nomes fora do padrao'],
        ['id' => 'F2-T02', 'title' => 'Padronizar sufixos de camada'],
        ['id' => 'F2-T03', 'title' => 'Padronizar metodos CRUD'],
        ['id' => 'F2-T04', 'title' => 'Alinhar rotas e handlers'],
        ['id' => 'F2-T05', 'title' => 'Consolidar componentes de view'],
        ['id' => 'F2-T06', 'title' => 'Isolar pontos mistos entre camadas'],
        ['id' => 'F2-T07', 'title' => 'Definir template repetivel de modulo'],
    ],
    'fase-3' => [
        ['id' => 'F3-T01', 'title' => 'Auditar estrutura do dashboard'],
        ['id' => 'F3-T02', 'title' => 'Criar padrao reutilizavel do admin'],
        ['id' => 'F3-T03', 'title' => 'Padronizar feedback de operacoes'],
        ['id' => 'F3-T04', 'title' => 'Destacar acoes prioritarias'],
        ['id' => 'F3-T05', 'title' => 'Evoluir area de status/saude'],
        ['id' => 'F3-T06', 'title' => 'Melhorar navegacao por modulos'],
        ['id' => 'F3-T07', 'title' => 'Preparar estrutura dinamica'],
    ],
    'fase-4' => [
        ['id' => 'F4-T01', 'title' => 'Revisar fluxo de criacao/edicao'],
        ['id' => 'F4-T02', 'title' => 'Fortalecer validacao de slug'],
        ['id' => 'F4-T03', 'title' => 'Revisar vinculos de midia'],
        ['id' => 'F4-T04', 'title' => 'Reforcar validacao de upload'],
        ['id' => 'F4-T05', 'title' => 'Adicionar pre-validacoes editoriais'],
        ['id' => 'F4-T06', 'title' => 'Preparar base para preview seguro'],
        ['id' => 'F4-T07', 'title' => 'Revisar integracao com Central Nerd'],
    ],
    'fase-5' => [
        ['id' => 'F5-T01', 'title' => 'Mapear pontos de log'],
        ['id' => 'F5-T02', 'title' => 'Registrar falhas operacionais'],
        ['id' => 'F5-T03', 'title' => 'Registrar acoes sensiveis do admin'],
        ['id' => 'F5-T04', 'title' => 'Padronizar mensagens de erro'],
        ['id' => 'F5-T05', 'title' => 'Rastreabilidade de deploy/sync'],
        ['id' => 'F5-T06', 'title' => 'Rastreabilidade de backup/restore'],
        ['id' => 'F5-T07', 'title' => 'Rotina mensal de revisao'],
    ],
];
$phaseTaskDetails = [
    'fase-1' => [
        ['id' => 'F1-T01', 'title' => 'Auditar controllers para garantir ausencia de SQL/acesso direto ao banco.', 'priority' => 'Alta', 'impact' => 'Alto', 'effort' => 'Medio', 'criterion' => 'Relatorio de controllers revisados com evidencias por modulo.', 'dependencies' => ['Nenhuma'], 'result' => 'Mapa de controllers aderentes e pontos fora do padrao.', 'critical' => false],
        ['id' => 'F1-T02', 'title' => 'Auditar services para confirmar concentracao de regra de negocio e montagem de view model.', 'priority' => 'Alta', 'impact' => 'Alto', 'effort' => 'Medio', 'criterion' => 'Matriz service x regra de negocio validada e registrada.', 'dependencies' => ['F1-T01'], 'result' => 'Relatorio de consistencia da camada de servico.', 'critical' => false],
        ['id' => 'F1-T03', 'title' => 'Auditar repositories para garantir isolamento de acesso a dados.', 'priority' => 'Alta', 'impact' => 'Alto', 'effort' => 'Medio', 'criterion' => 'Inventario de repositories e queries com conformidade marcada.', 'dependencies' => ['F1-T01'], 'result' => 'Lista de queries por repositorio e desvios encontrados.', 'critical' => false],
        ['id' => 'F1-T04', 'title' => 'Verificar views para remover logica pesada e regras nao visuais.', 'priority' => 'Alta', 'impact' => 'Medio', 'effort' => 'Medio', 'criterion' => 'Checklist de views revisadas com pontos fora do padrao.', 'dependencies' => ['F1-T02'], 'result' => 'Checklist de views limpas com pendencias priorizadas.', 'critical' => false],
        ['id' => 'F1-T05', 'title' => 'Auditar protecao de rotas `/admin` e `/local`, inclusive guardas de ambiente.', 'priority' => 'Alta', 'impact' => 'Alto', 'effort' => 'Baixo', 'criterion' => 'Teste manual de acesso nao autorizado anexado com resultado por rota.', 'dependencies' => ['Nenhuma'], 'result' => 'Rotas sensiveis validadas com evidencias de bloqueio.', 'critical' => true],
        ['id' => 'F1-T06', 'title' => 'Validar protecao de `.env`, regras de upload e bloqueio de execucao em `uploads`.', 'priority' => 'Alta', 'impact' => 'Alto', 'effort' => 'Medio', 'criterion' => 'Evidencias de bloqueio `.env` e validacao de upload registradas.', 'dependencies' => ['F1-T05'], 'result' => 'Checklist de seguranca concluido com correcoes propostas.', 'critical' => true],
        ['id' => 'F1-T07', 'title' => 'Revisar cobertura de CSRF em operacoes POST e formularios sensiveis.', 'priority' => 'Alta', 'impact' => 'Alto', 'effort' => 'Baixo', 'criterion' => 'Matriz de endpoints POST com status CSRF preenchida.', 'dependencies' => ['F1-T05'], 'result' => 'Matriz de rotas POST com status CSRF.', 'critical' => true],
        ['id' => 'F1-T08', 'title' => 'Validar variaveis de ambiente chave (`APP_DEBUG`, `APP_ENV`, `APP_URL`) por perfil.', 'priority' => 'Media', 'impact' => 'Alto', 'effort' => 'Baixo', 'criterion' => 'Checklist de ambiente local/producao validado e documentado.', 'dependencies' => ['Nenhuma'], 'result' => 'Padrao de ambiente confirmado para local e producao.', 'critical' => false],
        ['id' => 'F1-T09', 'title' => 'Comparar fluxo real de deploy com o fluxo documentado (`preflight`, pacote e publicacao).', 'priority' => 'Alta', 'impact' => 'Alto', 'effort' => 'Medio', 'criterion' => 'Fluxo de deploy executado em teste com evidencias por etapa.', 'dependencies' => ['F1-T08'], 'result' => 'Gap analysis de deploy com acoes corretivas objetivas.', 'critical' => true],
        ['id' => 'F1-T10', 'title' => 'Comparar fluxo real de backup/restore com a rotina oficial documentada.', 'priority' => 'Alta', 'impact' => 'Alto', 'effort' => 'Medio', 'criterion' => 'Backup e restore de validacao executados com log e evidencias.', 'dependencies' => ['F1-T08'], 'result' => 'Relatorio final de inconsistencias priorizadas (P1/P2/P3).', 'critical' => true],
    ],
    'fase-2' => [
        ['id' => 'F2-T01', 'title' => 'Mapear classes/arquivos fora do padrao de nomenclatura (PascalCase e sufixos).', 'priority' => 'Alta', 'impact' => 'Medio', 'effort' => 'Baixo', 'criterion' => 'Lista de classes/arquivos fora do padrao consolidada.', 'dependencies' => ['F1-T01', 'F1-T03'], 'result' => 'Inventario de nomes fora do padrao.', 'critical' => false],
        ['id' => 'F2-T02', 'title' => 'Padronizar sufixos `Controller`, `Service` e `Repository` onde necessario.', 'priority' => 'Alta', 'impact' => 'Alto', 'effort' => 'Medio', 'criterion' => 'Renomeacoes aplicadas e referencias atualizadas sem quebra de rota.', 'dependencies' => ['F2-T01'], 'result' => 'Nomenclatura uniforme nas camadas principais.', 'critical' => false],
        ['id' => 'F2-T03', 'title' => 'Padronizar estrutura minima de metodos por modulo CRUD no admin.', 'priority' => 'Media', 'impact' => 'Medio', 'effort' => 'Medio', 'criterion' => 'Padrao de metodos aplicado e revisado em todos os modulos CRUD.', 'dependencies' => ['F2-T02'], 'result' => 'Controladores com padrao previsivel de metodos.', 'critical' => false],
        ['id' => 'F2-T04', 'title' => 'Alinhar mapeamento de rotas com controllers documentados e responsaveis reais.', 'priority' => 'Alta', 'impact' => 'Alto', 'effort' => 'Medio', 'criterion' => 'Tabela de rotas reconciliada com teste manual dos endpoints principais.', 'dependencies' => ['F1-T09'], 'result' => 'Rotas e handlers consistentes com documentacao.', 'critical' => false],
        ['id' => 'F2-T05', 'title' => 'Revisar views para consolidar componentes e reduzir duplicacao estrutural.', 'priority' => 'Media', 'impact' => 'Medio', 'effort' => 'Medio', 'criterion' => 'Componentes comuns extraidos e repeticoes removidas.', 'dependencies' => ['F1-T04'], 'result' => 'Padrao visual/estrutural mais reutilizavel.', 'critical' => false],
        ['id' => 'F2-T06', 'title' => 'Isolar pontos mistos entre camadas (ex.: regra de negocio em view/controller).', 'priority' => 'Alta', 'impact' => 'Alto', 'effort' => 'Alto', 'criterion' => 'Pontos mistos corrigidos com diff validado e sem regressao funcional.', 'dependencies' => ['F1-T02', 'F1-T04'], 'result' => 'Reducao de acoplamento e risco de regressao.', 'critical' => false],
        ['id' => 'F2-T07', 'title' => 'Definir template repetivel para novos modulos (controller + service + repository + view).', 'priority' => 'Media', 'impact' => 'Medio', 'effort' => 'Baixo', 'criterion' => 'Template interno documentado e validado em um modulo piloto.', 'dependencies' => ['F2-T02', 'F2-T06'], 'result' => 'Guia interno pronto para expansao sem improviso.', 'critical' => false],
    ],
    'fase-3' => [
        ['id' => 'F3-T01', 'title' => 'Auditar estrutura atual do dashboard e identificar blocos reutilizaveis.', 'priority' => 'Alta', 'impact' => 'Alto', 'effort' => 'Baixo', 'criterion' => 'Mapa de componentes com ranking de reutilizacao aprovado.', 'dependencies' => ['F1-T01', 'F1-T04'], 'result' => 'Mapa de componentes com priorizacao de refino.', 'critical' => false],
        ['id' => 'F3-T02', 'title' => 'Criar padrao reutilizavel para cards, tabelas, formularios e acoes no admin.', 'priority' => 'Alta', 'impact' => 'Alto', 'effort' => 'Medio', 'criterion' => 'Componentes base aplicados em pelo menos dois modulos do admin.', 'dependencies' => ['F3-T01', 'F2-T05'], 'result' => 'Biblioteca interna de componentes administrativos.', 'critical' => false],
        ['id' => 'F3-T03', 'title' => 'Padronizar feedback de operacoes criticas (sucesso, erro, warning e confirmacao).', 'priority' => 'Alta', 'impact' => 'Alto', 'effort' => 'Medio', 'criterion' => 'Padrao de feedback aplicado em operacoes criticas definidas.', 'dependencies' => ['F3-T02'], 'result' => 'Operacao mais segura e menos ambigua para o operador.', 'critical' => false],
        ['id' => 'F3-T04', 'title' => 'Destacar acoes operacionais prioritarias (backup, sync, limpeza de midia e publicacao).', 'priority' => 'Media', 'impact' => 'Medio', 'effort' => 'Baixo', 'criterion' => 'Acoes criticas destacadas e validadas com teste de navegacao.', 'dependencies' => ['F3-T02'], 'result' => 'Navegacao orientada por tarefas criticas.', 'critical' => false],
        ['id' => 'F3-T05', 'title' => 'Evoluir area de status/saude com indicadores de ambiente e operacao.', 'priority' => 'Media', 'impact' => 'Alto', 'effort' => 'Medio', 'criterion' => 'Indicadores minimos exibidos com fonte de dados documentada.', 'dependencies' => ['F3-T01', 'F5-T01'], 'result' => 'Visao de saude mais clara no admin.', 'critical' => false],
        ['id' => 'F3-T06', 'title' => 'Melhorar navegacao interna por modulos com agrupamento funcional consistente.', 'priority' => 'Media', 'impact' => 'Medio', 'effort' => 'Baixo', 'criterion' => 'Navegacao reorganizada e aprovada em revisao operacional.', 'dependencies' => ['F3-T02'], 'result' => 'Fluxo operacional mais rapido no dia a dia.', 'critical' => false],
        ['id' => 'F3-T07', 'title' => 'Preparar estrutura dinamica para novos modulos sem quebrar padrao atual.', 'priority' => 'Baixa', 'impact' => 'Medio', 'effort' => 'Medio', 'criterion' => 'Estrutura de extensao definida e validada com modulo de teste.', 'dependencies' => ['F2-T07', 'F3-T06'], 'result' => 'Escalabilidade administrativa com menor retrabalho.', 'critical' => false],
    ],
    'fase-4' => [
        ['id' => 'F4-T01', 'title' => 'Revisar fluxo de criacao/edicao de post (ordem de campos, usabilidade e validacoes).', 'priority' => 'Alta', 'impact' => 'Alto', 'effort' => 'Medio', 'criterion' => 'Fluxo de formulario revisado e testado ponta a ponta.', 'dependencies' => ['F3-T02'], 'result' => 'Formularios editoriais mais claros e consistentes.', 'critical' => false],
        ['id' => 'F4-T02', 'title' => 'Fortalecer validacao de slug (unicidade, historico e conflitos de rota).', 'priority' => 'Alta', 'impact' => 'Alto', 'effort' => 'Medio', 'criterion' => 'Validacao de slug aplicada com cenarios de colisao testados.', 'dependencies' => ['F1-T10'], 'result' => 'URLs estaveis e menor risco de quebra SEO.', 'critical' => false],
        ['id' => 'F4-T03', 'title' => 'Revisar vinculos de midia em posts e links para reduzir orfas e referencias quebradas.', 'priority' => 'Alta', 'impact' => 'Alto', 'effort' => 'Medio', 'criterion' => 'Relatorio de vinculos revisado e inconsistencias corrigidas/documentadas.', 'dependencies' => ['F1-T06'], 'result' => 'Integridade de midia melhorada no fluxo editorial.', 'critical' => false],
        ['id' => 'F4-T04', 'title' => 'Reforcar validacao de upload (tipo, tamanho, path) com mensagens operacionais claras.', 'priority' => 'Alta', 'impact' => 'Alto', 'effort' => 'Baixo', 'criterion' => 'Regras de upload validadas com testes de erro e sucesso.', 'dependencies' => ['F1-T06'], 'result' => 'Menos erro de publicacao e mais seguranca.', 'critical' => false],
        ['id' => 'F4-T05', 'title' => 'Adicionar pre-validacoes editoriais antes de publicar (titulo, subtitulo, imagem, CTA e tags).', 'priority' => 'Media', 'impact' => 'Alto', 'effort' => 'Medio', 'criterion' => 'Checklist pre-publicacao ativo com validacao manual executada.', 'dependencies' => ['F4-T01'], 'result' => 'Conteudo publicado com padrao editorial minimo.', 'critical' => false],
        ['id' => 'F4-T06', 'title' => 'Preparar base tecnica para preview seguro (analise e implementacao incremental viavel).', 'priority' => 'Baixa', 'impact' => 'Medio', 'effort' => 'Alto', 'criterion' => 'Analise tecnica concluida e plano incremental aprovado.', 'dependencies' => ['F4-T01', 'F4-T05'], 'result' => 'Caminho de preview definido sem quebrar fluxo atual.', 'critical' => false],
        ['id' => 'F4-T07', 'title' => 'Revisar integracao entre posts e Central Nerd para melhorar saida de conversao.', 'priority' => 'Media', 'impact' => 'Alto', 'effort' => 'Medio', 'criterion' => 'Links de saida revisados e testados em posts alvo.', 'dependencies' => ['F4-T05', 'F3-T04'], 'result' => 'Navegacao editorial conectada a objetivo de conversao.', 'critical' => false],
    ],
    'fase-5' => [
        ['id' => 'F5-T01', 'title' => 'Mapear pontos de log para backup, sync, deploy e acoes criticas no admin.', 'priority' => 'Alta', 'impact' => 'Alto', 'effort' => 'Medio', 'criterion' => 'Mapa de logging por fluxo documentado com pontos obrigatorios.', 'dependencies' => ['F1-T09', 'F1-T10'], 'result' => 'Plano de logging operacional por fluxo.', 'critical' => false],
        ['id' => 'F5-T02', 'title' => 'Registrar falhas operacionais relevantes com contexto minimo para reproduzir erro.', 'priority' => 'Alta', 'impact' => 'Alto', 'effort' => 'Medio', 'criterion' => 'Modelo de log de falha aplicado e validado em casos reais.', 'dependencies' => ['F5-T01'], 'result' => 'Diagnostico mais rapido e menos tentativa/erro.', 'critical' => false],
        ['id' => 'F5-T03', 'title' => 'Registrar acoes sensiveis do admin (publicar, excluir, restore e limpar orfas).', 'priority' => 'Media', 'impact' => 'Alto', 'effort' => 'Medio', 'criterion' => 'Trilha de auditoria ativa para acoes administrativas criticas.', 'dependencies' => ['F5-T01'], 'result' => 'Trilha de auditoria para operacoes criticas.', 'critical' => false],
        ['id' => 'F5-T04', 'title' => 'Padronizar mensagens de erro tecnico com contexto de acao recomendada.', 'priority' => 'Media', 'impact' => 'Medio', 'effort' => 'Baixo', 'criterion' => 'Padrao de mensagens aplicado e revisado nos fluxos criticos.', 'dependencies' => ['F5-T02'], 'result' => 'Feedback operacional mais claro para manutencao.', 'critical' => false],
        ['id' => 'F5-T05', 'title' => 'Fortalecer rastreabilidade de deploy/sync com IDs de pacote e status por etapa.', 'priority' => 'Alta', 'impact' => 'Alto', 'effort' => 'Medio', 'criterion' => 'Historico de pacotes e etapas registrado com IDs unicos.', 'dependencies' => ['F5-T01', 'F5-T02'], 'result' => 'Historico confiavel de publicacao e rollback.', 'critical' => false],
        ['id' => 'F5-T06', 'title' => 'Fortalecer rastreabilidade de backup/restore com verificacao e registro de escopo.', 'priority' => 'Alta', 'impact' => 'Alto', 'effort' => 'Medio', 'criterion' => 'Log de backup/restore com escopo, resultado e evidencias.', 'dependencies' => ['F5-T01', 'F1-T10'], 'result' => 'Operacao de backup auditavel ponta a ponta.', 'critical' => false],
        ['id' => 'F5-T07', 'title' => 'Consolidar rotina de revisao mensal da documentacao + backlog + pendencias abertas.', 'priority' => 'Media', 'impact' => 'Medio', 'effort' => 'Baixo', 'criterion' => 'Ritual mensal registrado com ata e atualizacao de backlog.', 'dependencies' => ['F5-T05', 'F5-T06'], 'result' => 'Ciclo continuo de manutencao orientado por dados.', 'critical' => false],
    ],
];
$complementaryTracks = [
    [
        'module' => 'Banco',
        'title' => 'Camada de dados e metadata de midia',
        'tasks' => [
            [
                'id' => 'MID-BD-01',
                'objective' => 'Modelar midia como entidade tipada (`imagem`, `audio`, `video`) com metadata minima.',
                'paths' => 'Banco, migrations e repositories de midia.',
                'rule' => 'MIME real, tamanho, caminho, tipo, post vinculado e data de envio devem ser persistidos.',
                'acceptance' => 'Schema revisado, migration validada e leitura/escrita funcionando para os tres tipos.',
                'risk' => 'Migracao incompleta quebrar listagem atual da midia.',
                'dependencies' => ['Auditoria F1-T06', 'Fluxo atual da midia'],
                'result' => 'Base pronta para tratar midia sem depender de excecoes de imagem.',
            ],
            [
                'id' => 'MID-BD-02',
                'objective' => 'Definir estrutura fisica oficial por post e por tipo.',
                'paths' => '`public/uploads/posts/{slug}/images|audio|video` e servicos de upload.',
                'rule' => 'Slug sanitizado e estavel; backend padroniza nomes e impede colisao.',
                'acceptance' => 'Uploads novos sao gravados nas subpastas corretas com nome padronizado.',
                'risk' => 'Conteudo antigo continuar apontando para caminhos legados.',
                'dependencies' => ['MID-BD-01'],
                'result' => 'Armazenamento previsivel e pronto para limpeza/diagnostico.',
            ],
            [
                'id' => 'MID-BD-03',
                'objective' => 'Definir fallback de compatibilidade para midias e blocos legados.',
                'paths' => 'Services de midia/post e regras de renderizacao.',
                'rule' => 'Novo padrao vale para blocos novos; legado permanece funcional ate migracao futura.',
                'acceptance' => 'Posts antigos continuam abrindo sem quebra e novos posts usam a estrutura oficial.',
                'risk' => 'Regressao em posts que ja salvam HTML manual.',
                'dependencies' => ['MID-BD-01', 'MID-BD-02'],
                'result' => 'Compatibilidade transitoria preservada sem travar a evolucao.',
            ],
        ],
    ],
    [
        'module' => 'Midia',
        'title' => 'Central de Midia e fluxo de upload',
        'tasks' => [
            [
                'id' => 'MID-UP-01',
                'objective' => 'Expandir Central de Midia para aceitar imagem, audio e video.',
                'paths' => 'Controllers/admin, services, views da midia e storage.',
                'rule' => 'Upload pode vir da pagina de midia ou da modal, mas todo arquivo entra na biblioteca oficial.',
                'acceptance' => 'Upload dos tres tipos funcionando com identificacao visual de tipo.',
                'risk' => 'UI ficar confusa e tratar tudo como imagem.',
                'dependencies' => ['MID-BD-01'],
                'result' => 'Biblioteca unificada de ativos do portal.',
            ],
            [
                'id' => 'MID-UP-02',
                'objective' => 'Criar filtros por tipo e preview especifico na biblioteca.',
                'paths' => 'Views/admin de midia e JS admin de midia.',
                'rule' => 'Modal deve conseguir filtrar por tipo e listar so arquivos elegiveis para a acao atual.',
                'acceptance' => 'Filtro por `imagem`, `audio` e `video` funcional e preview coerente por tipo.',
                'risk' => 'Selecao errada de ativo em modal editorial.',
                'dependencies' => ['MID-UP-01'],
                'result' => 'Escolha de midia mais rapida e menos sujeita a erro.',
            ],
            [
                'id' => 'MID-UP-03',
                'objective' => 'Registrar vinculo de uso entre midia e post com rastreabilidade.',
                'paths' => 'Banco/repositorio de midia e servicos editoriais.',
                'rule' => 'Midia enviada/selecionada precisa manter ligacao com o post e continuar rastreavel para limpeza de orfas.',
                'acceptance' => 'Midias novas mostram post vinculado e uso fica consistente na rotina de auditoria.',
                'risk' => 'Arquivo aparentemente orfao ser removido por falta de vinculo.',
                'dependencies' => ['MID-BD-01', 'F4-T03'],
                'result' => 'Menos perda de arquivo e limpeza futura mais segura.',
            ],
        ],
    ],
    [
        'module' => 'Editor',
        'title' => 'Toolbar, modal de audio e operacao editorial',
        'tasks' => [
            [
                'id' => 'EDT-01',
                'objective' => 'Adicionar botao de salvar na barra do editor.',
                'paths' => 'Views/components do editor e JS do editor.',
                'rule' => 'Salvar deve refletir o estado real da instancia ativa do editor.',
                'acceptance' => 'Acao de salvar funcionando tanto em criacao quanto em edicao sem divergencia de conteudo.',
                'risk' => 'Salvar estado antigo do HTML/visual.',
                'dependencies' => ['Fluxo atual do editor'],
                'result' => 'Menor atrito operacional em posts longos.',
            ],
            [
                'id' => 'EDT-02',
                'objective' => 'Reorganizar toolbar: limpar formatacao apos citacao e novo separador.',
                'paths' => 'Toolbar do editor e estilos do admin.',
                'rule' => 'Nova ordem precisa ser identica em criar e editar.',
                'acceptance' => 'Toolbar padronizada e validada visualmente nas duas telas.',
                'risk' => 'Quebra de consistencia entre formularios.',
                'dependencies' => ['EDT-01'],
                'result' => 'Barra mais previsivel e profissional.',
            ],
            [
                'id' => 'EDT-03',
                'objective' => 'Criar modal oficial do bloco de audio com upload/selecao de biblioteca.',
                'paths' => 'Views/components do editor, JS admin e modal de midia.',
                'rule' => 'Titulo, subtitulo, texto do botao, narracao e ambiente; pelo menos um audio obrigatorio.',
                'acceptance' => 'Modal insere bloco sem HTML manual e permite escolher/subir audio pela propria janela.',
                'risk' => 'Fluxo de insercao ficar mais fragil que o bloco de imagem.',
                'dependencies' => ['MID-UP-01', 'MID-UP-02'],
                'result' => 'Bloco de audio vira recurso oficial do editorial.',
            ],
            [
                'id' => 'EDT-04',
                'objective' => 'Integrar selecao de midia por tipo nas modais do editor.',
                'paths' => 'JS do editor, modal/biblioteca e endpoints de midia.',
                'rule' => 'Imagem busca imagem, audio busca audio, video busca video; upload via modal reaproveita a mesma biblioteca.',
                'acceptance' => 'Seletores de midia filtram corretamente e retornam ativos validos para cada bloco.',
                'risk' => 'Escolha de midia errada ou upload indo para fluxo paralelo.',
                'dependencies' => ['MID-UP-02', 'EDT-03'],
                'result' => 'Experiencia consistente entre blocos e biblioteca.',
            ],
        ],
    ],
    [
        'module' => 'Frontend',
        'title' => 'Renderizacao publica do bloco de audio',
        'tasks' => [
            [
                'id' => 'FRT-AUD-01',
                'objective' => 'Definir HTML estruturado oficial do bloco de audio.',
                'paths' => 'Editor, PostService e render do post.',
                'rule' => 'Post salva so marcacao estruturada e dados necessarios; nada de `<style>` ou `<script>` inline.',
                'acceptance' => 'Markup padrao fechado e reutilizavel entre posts.',
                'risk' => 'Estrutura ambigua dificultar compatibilidade futura.',
                'dependencies' => ['EDT-03'],
                'result' => 'Bloco editorial limpo e pronto para JS/CSS centralizados.',
            ],
            [
                'id' => 'FRT-AUD-02',
                'objective' => 'Criar CSS oficial do bloco seguindo o padrao visual do portal.',
                'paths' => '`public/assets/css/site.css` e renders relacionados.',
                'rule' => 'Visual coerente com o portal e sem depender de estilo salvo no conteudo.',
                'acceptance' => 'Bloco fica bonito e consistente em post, preview e stage.',
                'risk' => 'Repetir erro de estilo diferente entre preview e site.',
                'dependencies' => ['FRT-AUD-01'],
                'result' => 'Estilo centralizado e seguro.',
            ],
            [
                'id' => 'FRT-AUD-03',
                'objective' => 'Criar JS oficial do bloco no mesmo ecossistema dos scripts publicos atuais.',
                'paths' => '`public/assets/js/site-home.js` ou arquivo dedicado de post.',
                'rule' => 'Suportar narracao, ambiente ou ambos; resetar botao ao fim; evitar multiplas instancias tocando ao mesmo tempo, se aprovado editorialmente.',
                'acceptance' => 'Bloco funciona sem JS inline e com comportamento previsivel no post publico.',
                'risk' => 'Conflito entre multiplos blocos na mesma pagina.',
                'dependencies' => ['FRT-AUD-01'],
                'result' => 'Interacao oficial do audio funcionando no portal.',
            ],
            [
                'id' => 'FRT-AUD-04',
                'objective' => 'Definir estrategia de compatibilidade para blocos artesanais ja existentes.',
                'paths' => 'PostService, normalizacao de conteudo e render do post.',
                'rule' => 'Legado continua abrindo; novos blocos devem usar o padrao oficial. Migracao futura fica opcional e controlada.',
                'acceptance' => 'Post antigo com audio manual nao quebra apos a introducao do novo recurso.',
                'risk' => 'Regressao em posts que ja usam HTML customizado.',
                'dependencies' => ['MID-BD-03', 'FRT-AUD-01'],
                'result' => 'Transicao segura entre formato antigo e oficial.',
            ],
        ],
    ],
    [
        'module' => 'CodeMirror',
        'title' => 'Modo HTML profissional',
        'tasks' => [
            [
                'id' => 'CM-01',
                'objective' => 'Integrar CodeMirror somente no modo HTML.',
                'paths' => 'Assets do admin, editor JS e views de criar/editar.',
                'rule' => 'Modo visual continua sendo principal; modo HTML ganha syntax highlighting com `htmlmixed` e `lineNumbers`.',
                'acceptance' => 'Modo HTML abre com destaque de sintaxe e numeros de linha sem quebrar o editor visual.',
                'risk' => 'Transformar o modo HTML em editor principal por acidente.',
                'dependencies' => ['EDT-01'],
                'result' => 'Edicao de HTML mais profissional e legivel.',
            ],
            [
                'id' => 'CM-02',
                'objective' => 'Garantir sincronizacao bidirecional visual ↔ HTML ↔ conteudo salvo.',
                'paths' => 'JS do editor, fluxo de preview, salvar e alternancia de modo.',
                'rule' => 'Nao pode haver divergencia entre o conteudo exibido, o salvo e o renderizado em preview.',
                'acceptance' => 'Teste de ida e volta passa sem perda de markup nem diferenca entre modos.',
                'risk' => 'Salvar uma versao e exibir outra.',
                'dependencies' => ['CM-01', 'EDT-01'],
                'result' => 'Confianca no editor restaurada mesmo com modo HTML avancado.',
            ],
            [
                'id' => 'CM-03',
                'objective' => 'Validar reabertura e edicao posterior de posts com blocos especiais.',
                'paths' => 'Editor, preview e render de post.',
                'rule' => 'Post com bloco de audio, imagem e HTML estruturado deve reabrir exatamente como foi salvo.',
                'acceptance' => 'Abertura, edicao e resave de posts especiais sem alterar markup por acidente.',
                'risk' => 'Editor limpar classes/data-attributes necessarios.',
                'dependencies' => ['CM-02', 'EDT-03'],
                'result' => 'Persistencia estavel para conteudo complexo.',
            ],
        ],
    ],
    [
        'module' => 'Checklist',
        'title' => 'Validacao de preview, stage e producao',
        'tasks' => [
            [
                'id' => 'CHK-01',
                'objective' => 'Criar checklist fixo de validacao para bloco de audio e midia tipada.',
                'paths' => 'Documentacao local, backlog e rotina operacional.',
                'rule' => 'Validar editor visual, modo HTML, preview, stage e producao antes de empacotar qualquer envio.',
                'acceptance' => 'Checklist publicado e usado em ao menos uma rodada completa de homologacao.',
                'risk' => 'Voltar o problema de local mostrar uma coisa e remoto outra.',
                'dependencies' => ['FRT-AUD-03', 'CM-02'],
                'result' => 'Processo de homologacao repetivel e auditavel.',
            ],
            [
                'id' => 'CHK-02',
                'objective' => 'Definir pacote controlado de deploy para a trilha de audio/editor.',
                'paths' => 'Content sync, empacotamento e manifesto de deploy.',
                'rule' => 'Pacote deve separar claramente assets publicos, codigo de app e migracoes/ajustes de banco.',
                'acceptance' => 'Pacote de stage aplicado com manifesto completo e pos-check sem regressao.',
                'risk' => 'Enviar mudanca incompleta para remoto e quebrar layout/JS.',
                'dependencies' => ['CHK-01', 'MID-BD-01'],
                'result' => 'Publicacao previsivel e menos sujeita a retrabalho.',
            ],
            [
                'id' => 'CHK-03',
                'objective' => 'Executar validacao final em posts legados e novos antes de liberar producao.',
                'paths' => 'Stage, producao e posts alvo de teste.',
                'rule' => 'Conferir post legado, post com bloco de audio novo, upload de audio e alternancia visual/HTML.',
                'acceptance' => 'Suite manual de validacao concluida em stage e repetida apos publicacao.',
                'risk' => 'Feature nova funcionar apenas no caso feliz.',
                'dependencies' => ['CHK-01', 'CHK-02', 'FRT-AUD-04'],
                'result' => 'Liberacao para producao com risco reduzido e rastreavel.',
            ],
        ],
    ],
];
?>
<section class="<?= $adminEmbed ? 'text-slate-100' : 'min-h-screen bg-slate-950 px-4 py-8 text-slate-100' ?>">
  <style>
    .doc-card {
      border: 1px solid rgba(51, 65, 85, 0.9);
      background: rgba(2, 6, 23, 0.65);
      border-radius: 1rem;
      padding: 1rem;
    }

    .doc-label {
      font-family: Orbitron, ui-sans-serif, system-ui;
      font-size: 0.66rem;
      letter-spacing: 0.2em;
      text-transform: uppercase;
      color: rgba(148, 163, 184, 0.95);
    }

    .doc-rule {
      border: 1px solid rgba(34, 211, 238, 0.25);
      background: rgba(8, 47, 73, 0.45);
    }

    .doc-alert {
      border: 1px solid rgba(251, 191, 36, 0.28);
      background: rgba(120, 53, 15, 0.28);
    }

    .doc-table {
      width: 100%;
      min-width: 0;
      table-layout: fixed;
      border-collapse: separate;
      border-spacing: 0 0.55rem;
      font-size: clamp(0.74rem, 0.16vw + 0.68rem, 0.84rem);
      line-height: 1.35;
    }

    .doc-table th {
      text-align: left;
      font-size: clamp(0.56rem, 0.12vw + 0.52rem, 0.66rem);
      text-transform: uppercase;
      letter-spacing: 0.18em;
      color: rgba(100, 116, 139, 1);
      padding: 0.5rem 0.62rem;
      white-space: normal;
    }

    .doc-table td {
      padding: 0.62rem 0.62rem;
      vertical-align: top;
      overflow-wrap: break-word;
      word-break: break-word;
    }

    .doc-row {
      border: 1px solid rgba(51, 65, 85, 0.95);
      background: rgba(2, 6, 23, 0.72);
    }

    .doc-critical {
      border-color: rgba(251, 191, 36, 0.7);
      background: rgba(120, 53, 15, 0.24);
      box-shadow: inset 0 0 0 1px rgba(251, 191, 36, 0.22);
    }

    .status-badge {
      display: inline-flex;
      align-items: center;
      border-radius: 9999px;
      border: 1px solid rgba(71, 85, 105, 0.85);
      background: rgba(15, 23, 42, 0.9);
      padding: 0.15rem 0.55rem;
      font-size: 0.7rem;
      font-weight: 700;
      letter-spacing: 0.04em;
      color: rgba(191, 219, 254, 0.95);
      white-space: nowrap;
    }

    .status-nao-iniciada {
      border-color: rgba(59, 130, 246, 0.5);
      color: rgba(147, 197, 253, 0.98);
      background: rgba(30, 58, 138, 0.36);
    }

    .task-badge {
      margin-left: 0.45rem;
      border-radius: 9999px;
      border: 1px solid rgba(251, 191, 36, 0.55);
      background: rgba(120, 53, 15, 0.35);
      padding: 0.1rem 0.45rem;
      font-size: 0.62rem;
      font-weight: 700;
      letter-spacing: 0.06em;
      text-transform: uppercase;
      color: rgba(254, 243, 199, 0.95);
      white-space: nowrap;
    }

    .doc-table th:nth-child(1), .doc-table td:nth-child(1) { width: 4%; }
    .doc-table th:nth-child(2), .doc-table td:nth-child(2) { width: 7%; }
    .doc-table th:nth-child(3), .doc-table td:nth-child(3) { width: 21%; }
    .doc-table th:nth-child(4), .doc-table td:nth-child(4) { width: 6%; }
    .doc-table th:nth-child(5), .doc-table td:nth-child(5) { width: 6%; }
    .doc-table th:nth-child(6), .doc-table td:nth-child(6) { width: 6%; }
    .doc-table th:nth-child(7), .doc-table td:nth-child(7) { width: 8%; }
    .doc-table th:nth-child(8), .doc-table td:nth-child(8) { width: 18%; }
    .doc-table th:nth-child(9), .doc-table td:nth-child(9) { width: 8%; }
    .doc-table th:nth-child(10), .doc-table td:nth-child(10) { width: 16%; }

    .module-pill {
      display: inline-flex;
      align-items: center;
      border-radius: 9999px;
      border: 1px solid rgba(34, 211, 238, 0.28);
      background: rgba(8, 47, 73, 0.38);
      padding: 0.2rem 0.7rem;
      font-size: 0.65rem;
      font-weight: 700;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      color: rgba(165, 243, 252, 0.95);
      white-space: nowrap;
    }

    .doc-track-grid {
      display: grid;
      gap: 1rem;
    }

    .doc-track-grid + .doc-track-grid {
      margin-top: 1rem;
    }

    .track-task-card {
      border: 1px solid rgba(51, 65, 85, 0.95);
      background: rgba(2, 6, 23, 0.78);
      border-radius: 1rem;
      padding: 1rem;
    }

    .track-meta-grid {
      display: grid;
      gap: 0.85rem;
      margin-top: 1rem;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    }

    .track-dependency-chip {
      display: inline-flex;
      align-items: center;
      border-radius: 9999px;
      border: 1px solid rgba(71, 85, 105, 0.9);
      background: rgba(15, 23, 42, 0.92);
      padding: 0.18rem 0.6rem;
      font-size: 0.7rem;
      color: rgba(226, 232, 240, 0.96);
    }

    .backlog-tab-panel[hidden] {
      display: none;
    }

    .backlog-tab-button.is-active {
      border-color: rgba(34, 211, 238, 0.65);
      background: rgba(8, 145, 178, 0.18);
      color: #e0faff;
      box-shadow: 0 0 24px rgba(6, 182, 212, 0.12);
    }

    @media (max-width: 1280px) {
      .doc-table {
        font-size: 0.77rem;
      }

      .doc-table th {
        font-size: 0.58rem;
        letter-spacing: 0.14em;
      }
    }
  </style>

  <div class="<?= $adminEmbed ? 'space-y-6' : 'mx-auto max-w-[1720px] space-y-6' ?>">
    <?php if (!$embedMode): ?>
      <?php \App\Support\View::component('site/local-tools-nav', ['active' => 'backlog']); ?>
    <?php endif; ?>

    <header class="rounded-3xl border border-cyan-500/20 bg-slate-900/80 p-6 shadow-[0_0_40px_rgba(6,182,212,0.08)]">
      <p class="font-orbitron text-xs uppercase tracking-[0.35em] text-cyan-300/70">Backlog Tecnico Local</p>
      <h1 class="mt-2 font-orbitron text-3xl font-black tracking-tight text-white">Plano de evolucao do sistema Estrategia Nerd</h1>
      <p class="mt-3 max-w-5xl text-sm leading-7 text-slate-300">
        Este backlog e complementar a documentacao local. Nao substitui a base tecnica atual.
        O foco e transformar diretrizes em execucao por fases, mantendo stack, arquitetura e padroes definidos.
      </p>
      <div class="mt-5 grid gap-3 md:grid-cols-3">
        <div class="rounded-2xl border border-slate-700 bg-slate-950/70 p-4">
          <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Versao de referencia</p>
          <p class="mt-1 font-rajdhani text-2xl font-bold text-white"><?= htmlspecialchars($projectVersion, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
        </div>
        <div class="rounded-2xl border border-slate-700 bg-slate-950/70 p-4">
          <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Atualizado em</p>
          <p class="mt-1 text-sm text-slate-200"><?= htmlspecialchars($generatedAt, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
        </div>
        <div class="rounded-2xl border border-slate-700 bg-slate-950/70 p-4">
          <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Escopo</p>
          <p class="mt-1 text-sm text-slate-200">Auditoria, padronizacao, admin, editorial e observabilidade</p>
        </div>
      </div>
    </header>

    <article class="space-y-6">
      <section class="rounded-3xl border border-slate-800 bg-slate-900/80 p-5">
        <?php if ($isActiveSection('visao-geral')): ?>
          <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
            <div class="max-w-3xl">
              <h2 class="font-orbitron text-base font-bold text-white">Mapa do backlog</h2>
              <p class="mt-2 text-sm leading-6 text-slate-300">
                Use as abas para alternar entre a visao geral, a trilha complementar e cada fase do plano.
                Assim, cada bloco usa toda a largura da tela e voce nao fica preso em uma pagina longa.
              </p>
            </div>
            <div class="doc-card doc-alert w-full xl:max-w-sm">
              <p class="doc-label">Regra obrigatoria</p>
              <p class="mt-2 text-sm text-amber-100">Executar por fase e validar criterios de saida antes de avancar para a proxima.</p>
            </div>
          </div>
          <div class="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-4 2xl:grid-cols-7">
        <?php else: ?>
          <div class="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
            <div>
              <h2 class="font-orbitron text-base font-bold text-white">Navegacao do backlog</h2>
              <p class="mt-1 text-sm text-slate-400">Troque de trilha ou fase sem perder a largura total do conteudo.</p>
            </div>
            <div class="rounded-2xl border border-amber-400/25 bg-amber-500/10 px-4 py-3 text-sm text-amber-100">
              Validar criterios de saida antes de avancar para a proxima fase.
            </div>
          </div>
          <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-4 2xl:grid-cols-7">
        <?php endif; ?>
          <a href="<?= htmlspecialchars($sectionHref('visao-geral'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="backlog-tab-button <?= $isActiveSection('visao-geral') ? 'is-active' : '' ?> rounded-2xl border border-slate-700 bg-slate-950/70 px-4 py-3 text-left transition hover:border-cyan-400/60">
            <div class="font-semibold text-white">Visao geral</div>
            <div class="mt-1 text-sm text-slate-400">Resumo, prioridades e ordem de execucao.</div>
          </a>
          <a href="<?= htmlspecialchars($sectionHref('trilha-complementar'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="backlog-tab-button <?= $isActiveSection('trilha-complementar') ? 'is-active' : '' ?> rounded-2xl border border-slate-700 bg-slate-950/70 px-4 py-3 text-left transition hover:border-cyan-400/60">
            <div class="font-semibold text-white">Trilha complementar</div>
            <div class="mt-1 text-sm text-slate-400">Midia tipada, audio e editor.</div>
          </a>
          <a href="<?= htmlspecialchars($sectionHref('fase-1'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="backlog-tab-button <?= $isActiveSection('fase-1') ? 'is-active' : '' ?> rounded-2xl border border-slate-700 bg-slate-950/70 px-4 py-3 text-left transition hover:border-cyan-400/60">
            <div class="font-semibold text-white">Fase 1</div>
            <div class="mt-1 text-sm text-slate-400">Auditoria critica e seguranca.</div>
          </a>
          <a href="<?= htmlspecialchars($sectionHref('fase-2'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="backlog-tab-button <?= $isActiveSection('fase-2') ? 'is-active' : '' ?> rounded-2xl border border-slate-700 bg-slate-950/70 px-4 py-3 text-left transition hover:border-cyan-400/60">
            <div class="font-semibold text-white">Fase 2</div>
            <div class="mt-1 text-sm text-slate-400">Padronizacao estrutural do codigo.</div>
          </a>
          <a href="<?= htmlspecialchars($sectionHref('fase-3'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="backlog-tab-button <?= $isActiveSection('fase-3') ? 'is-active' : '' ?> rounded-2xl border border-slate-700 bg-slate-950/70 px-4 py-3 text-left transition hover:border-cyan-400/60">
            <div class="font-semibold text-white">Fase 3</div>
            <div class="mt-1 text-sm text-slate-400">Evolucao do admin e da camada local.</div>
          </a>
          <a href="<?= htmlspecialchars($sectionHref('fase-4'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="backlog-tab-button <?= $isActiveSection('fase-4') ? 'is-active' : '' ?> rounded-2xl border border-slate-700 bg-slate-950/70 px-4 py-3 text-left transition hover:border-cyan-400/60">
            <div class="font-semibold text-white">Fase 4</div>
            <div class="mt-1 text-sm text-slate-400">Evolucao editorial e fluxo de publicacao.</div>
          </a>
          <a href="<?= htmlspecialchars($sectionHref('fase-5'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="backlog-tab-button <?= $isActiveSection('fase-5') ? 'is-active' : '' ?> rounded-2xl border border-slate-700 bg-slate-950/70 px-4 py-3 text-left transition hover:border-cyan-400/60">
            <div class="font-semibold text-white">Fase 5</div>
            <div class="mt-1 text-sm text-slate-400">Observabilidade e manutencao operacional.</div>
          </a>
        </div>
      </section>

      <div id="painel-backlog" class="space-y-6 scroll-mt-6">
        <?php if ($isActiveSection('visao-geral')): ?>
        <div class="space-y-6">
          <section class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
            <h2 class="font-orbitron text-xl font-bold text-cyan-200">Visao geral do backlog</h2>
            <div class="mt-4 grid gap-3 md:grid-cols-3">
              <div class="doc-card">
                <p class="doc-label">Resumo executivo</p>
                <p class="mt-2 text-sm text-slate-200">Escopo, regra principal de execucao e foco atual do projeto.</p>
              </div>
              <div class="doc-card">
                <p class="doc-label">Atualizacao operacional</p>
                <p class="mt-2 text-sm text-slate-200">O que ja foi entregue e qual e o ponto critico ainda em aberto.</p>
              </div>
              <div class="doc-card">
                <p class="doc-label">Ordem sugerida</p>
                <p class="mt-2 text-sm text-slate-200">Sequencia recomendada para executar as fases com menor risco.</p>
              </div>
            </div>
          </section>

          <section class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
            <div class="space-y-6">
              <div>
                <h2 class="font-orbitron text-xl font-bold text-cyan-200">Resumo executivo</h2>
                <div class="mt-4 grid gap-3 md:grid-cols-5">
                  <div class="doc-card">
                    <p class="doc-label">Total de fases</p>
                    <p class="mt-2 font-rajdhani text-2xl font-bold text-white"><?= (int) $phaseCount ?></p>
                  </div>
                  <div class="doc-card">
                    <p class="doc-label">Total de tarefas</p>
                    <p class="mt-2 font-rajdhani text-2xl font-bold text-white"><?= (int) $taskCount ?></p>
                  </div>
                  <div class="doc-card">
                    <p class="doc-label">Fase atual sugerida</p>
                    <p class="mt-2 text-sm font-semibold text-slate-100"><?= htmlspecialchars($currentSuggestedPhase, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
                  </div>
                  <div class="doc-card md:col-span-2">
                    <p class="doc-label">Foco atual</p>
                    <p class="mt-2 text-sm text-slate-200"><?= htmlspecialchars($currentFocus, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
                  </div>
                </div>
                <div class="doc-card doc-alert mt-4">
                  <p class="doc-label">Regra principal de execucao</p>
                  <p class="mt-2 text-sm text-amber-100"><?= htmlspecialchars($executionRule, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
                </div>
                <div class="doc-card doc-alert mt-4">
                  <p class="doc-label">Regra de publicacao</p>
                  <p class="mt-2 text-sm text-amber-100"><?= htmlspecialchars($publicationRule, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
                </div>
              </div>

              <div>
                <h2 class="font-orbitron text-xl font-bold text-cyan-200">Atualizacao operacional recente</h2>
                <div class="mt-4 grid gap-3 md:grid-cols-2">
                  <div class="doc-card doc-rule">
                    <p class="doc-label">Ja entregue</p>
                    <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-slate-200">
                      <li>Admin multiambiente com `local` tecnico e `stage/producao` editoriais.</li>
                      <li>Health Check e Auditoria Geral centralizados no local.</li>
                      <li>Central de sincronizacao `producao -> stage` validada.</li>
                      <li>Auth endurecido com smoke de login/logout/CSRF e migracao de senha legada.</li>
                    </ul>
                  </div>
                  <div class="doc-card doc-alert">
                    <p class="doc-label">Ponto critico ainda em aberto</p>
                    <p class="mt-2 text-sm text-amber-100">
                      O fluxo tecnico real ainda permite promover pacote gerado no `local` ate a `producao`.
                      Isso contraria a regra permanente: pacote de producao deve nascer da `stage` validada.
                    </p>
                  </div>
                </div>
                <div class="doc-card doc-alert mt-4">
                  <p class="doc-label">Prioridade imediata</p>
                  <ol class="mt-2 list-decimal space-y-1 pl-5 text-sm text-amber-100">
                    <li>Implementar pipeline real `local -> stage -> pacote da stage -> producao`.</li>
                    <li>Automatizar bloqueio para impedir publish tecnico direto do `local` para `producao`.</li>
                    <li>Adicionar testes automatizados de smoke para auth, ambiente e rotas criticas.</li>
                  </ol>
                </div>
              </div>

              <div>
                <h2 class="font-orbitron text-xl font-bold text-cyan-200">Ordem sugerida de execucao</h2>
                <div class="doc-card doc-rule mt-4">
                  <p class="doc-label">Diretriz operacional</p>
                  <ol class="mt-2 list-decimal space-y-1 pl-5 text-sm text-slate-200">
                    <li>Comecar por tarefas de prioridade Alta com impacto Alto e esforco Baixo/Medio.</li>
                    <li>Concluir os pontos criticos da Fase 1 antes de acelerar Fase 3 e Fase 4.</li>
                    <li>Usar a Fase 2 como etapa de estabilizacao estrutural antes da expansao.</li>
                    <li>Evitar iniciar tarefas de prioridade Baixa antes das estruturais.</li>
                    <li>Executar Fase 5 progressivamente para sustentar operacao, sem esperar fim total da evolucao visual.</li>
                  </ol>
                </div>
              </div>
            </div>
          </section>
        </div>
        <?php endif; ?>

        <?php if ($isActiveSection('trilha-complementar')): ?>
        <div>
        <section id="trilha-editorial-midias" class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
          <h2 class="font-orbitron text-xl font-bold text-cyan-200">Trilha complementar - Midia tipada, bloco de audio e editor</h2>
          <div class="doc-card doc-rule mt-4">
            <p class="doc-label">Objetivo</p>
            <p class="mt-2 text-sm text-slate-200">Transformar a diretriz oficial de midia por tipo, bloco de audio, toolbar revisada e modo HTML com CodeMirror em backlog executavel, sem quebrar compatibilidade entre editor visual, preview, stage e producao.</p>
          </div>
          <div class="doc-card doc-alert mt-4">
            <p class="doc-label">Regra estrutural</p>
            <p class="mt-2 text-sm text-amber-100">Posts podem salvar HTML estruturado, mas nao devem salvar CSS ou JS arbitrarios. Comportamento e estilo de blocos especiais ficam centralizados no frontend oficial do portal.</p>
          </div>
          <div class="mt-4 grid gap-3 md:grid-cols-4">
            <div class="doc-card"><p class="doc-label">Escopo</p><p class="mt-2 text-sm text-slate-200">Admin, backend, banco, editor, midia, frontend publico e checklist de stage/producao.</p></div>
            <div class="doc-card"><p class="doc-label">Critico</p><p class="mt-2 text-sm text-slate-200">Sincronizacao visual x HTML x preview x salvo.</p></div>
            <div class="doc-card"><p class="doc-label">Risco-chave</p><p class="mt-2 text-sm text-slate-200">Regressao em posts legados e portal quebrando por assets inline.</p></div>
            <div class="doc-card"><p class="doc-label">Saida esperada</p><p class="mt-2 text-sm text-slate-200">Fluxo oficial de audio e midia tipada validado em stage antes de qualquer pacote de producao.</p></div>
          </div>

          <div class="doc-track-grid mt-6">
            <?php foreach ($complementaryTracks as $track): ?>
              <div class="doc-card">
                <div class="flex flex-wrap items-center gap-3">
                  <span class="module-pill"><?= htmlspecialchars($track['module'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                  <h3 class="font-orbitron text-base font-bold text-white"><?= htmlspecialchars($track['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h3>
                </div>
                <div class="mt-4 space-y-4">
                  <?php foreach ($track['tasks'] as $task): ?>
                    <article class="track-task-card">
                      <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                        <div class="max-w-3xl">
                          <p class="font-orbitron text-xs font-bold tracking-[0.18em] text-cyan-200"><?= htmlspecialchars($task['id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
                          <p class="mt-2 text-sm leading-7 text-slate-100"><?= htmlspecialchars($task['objective'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
                        </div>
                        <div class="rounded-2xl border border-slate-700 bg-slate-900/80 px-4 py-3 text-sm text-slate-300 lg:max-w-xs">
                          <div class="doc-label">Resultado esperado</div>
                          <div class="mt-2 text-slate-100"><?= htmlspecialchars($task['result'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                        </div>
                      </div>
                      <div class="track-meta-grid">
                        <div>
                          <div class="doc-label">Arquivos/pastas</div>
                          <div class="mt-2 text-sm leading-6 text-slate-300"><?= htmlspecialchars($task['paths'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                        </div>
                        <div>
                          <div class="doc-label">Regra de negocio</div>
                          <div class="mt-2 text-sm leading-6 text-slate-300"><?= htmlspecialchars($task['rule'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                        </div>
                        <div>
                          <div class="doc-label">Criterio de aceite</div>
                          <div class="mt-2 text-sm leading-6 text-slate-300"><?= htmlspecialchars($task['acceptance'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                        </div>
                        <div>
                          <div class="doc-label">Risco</div>
                          <div class="mt-2 text-sm leading-6 text-amber-100"><?= htmlspecialchars($task['risk'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                        </div>
                      </div>
                      <div class="mt-4">
                        <div class="doc-label">Dependencias</div>
                        <div class="mt-2 flex flex-wrap gap-2">
                          <?php foreach ($task['dependencies'] as $dependency): ?>
                            <span class="track-dependency-chip"><?= htmlspecialchars($dependency, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                          <?php endforeach; ?>
                        </div>
                      </div>
                    </article>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </section>
        </div>
        <?php endif; ?>

        <?php if ($isActiveSection('fase-1')): ?>
        <div>
        <section id="fase-1" class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
          <h2 class="font-orbitron text-xl font-bold text-cyan-200">Fase 1 - Auditoria do sistema contra a documentacao</h2>
          <div class="doc-card doc-rule mt-4">
            <p class="doc-label">Objetivo</p>
            <p class="mt-2 text-sm text-slate-200">Confirmar aderencia entre o que esta documentado e o comportamento real do sistema.</p>
          </div>
          <div class="doc-card doc-alert mt-4">
            <p class="doc-label">Limite operacional da fase</p>
            <p class="mt-2 text-sm text-amber-100">A Fase 1 e de auditoria: inspecionar, validar e registrar inconsistencias. Correcao estrutural fica para as fases seguintes para evitar refatoracao descontrolada.</p>
          </div>
          <div class="mt-4 grid gap-3 md:grid-cols-3">
            <div class="doc-card">
              <p class="doc-label">Foco principal</p>
              <p class="mt-2 text-sm text-slate-200">Seguranca, rotas sensiveis, deploy, backup e aderencia das camadas centrais.</p>
            </div>
            <div class="doc-card">
              <p class="doc-label">Prioridade</p>
              <p class="mt-2 text-sm text-slate-200">Alta. E a fase que trava o avanço seguro das proximas etapas.</p>
            </div>
            <div class="doc-card">
              <p class="doc-label">Saida esperada</p>
              <p class="mt-2 text-sm text-slate-200">Relatorio objetivo de gaps com evidencias e ordem de correcao.</p>
            </div>
          </div>
          <div class="doc-card mt-4">
            <p class="doc-label">Mapa de tarefas da fase</p>
            <div class="mt-3 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
              <?php foreach ($phaseTaskMap['fase-1'] as $task): ?>
                <div class="rounded-2xl border border-slate-700 bg-slate-950/70 px-4 py-3">
                  <div class="font-orbitron text-xs font-bold tracking-[0.18em] text-cyan-200"><?= htmlspecialchars($task['id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                  <div class="mt-2 text-sm text-slate-200"><?= htmlspecialchars($task['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="mt-4 space-y-4">
            <?php foreach ($phaseTaskDetails['fase-1'] as $task): ?>
              <article class="track-task-card<?= $task['critical'] ? ' doc-critical' : '' ?>">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                  <div class="max-w-3xl">
                    <div class="flex flex-wrap items-center gap-2">
                      <p class="font-orbitron text-xs font-bold tracking-[0.18em] text-cyan-200"><?= htmlspecialchars($task['id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
                      <?php if ($task['critical']): ?><span class="task-badge">Critica</span><?php endif; ?>
                    </div>
                    <p class="mt-2 text-sm leading-7 text-slate-100"><?= htmlspecialchars($task['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
                  </div>
                  <div class="rounded-2xl border border-slate-700 bg-slate-900/80 px-4 py-3 text-sm text-slate-300 lg:max-w-xs">
                    <div class="doc-label">Resultado esperado</div>
                    <div class="mt-2 text-slate-100"><?= htmlspecialchars($task['result'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                  </div>
                </div>
                <div class="track-meta-grid">
                  <div><div class="doc-label">Prioridade</div><div class="mt-2 text-sm text-slate-200"><?= htmlspecialchars($task['priority'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div></div>
                  <div><div class="doc-label">Impacto</div><div class="mt-2 text-sm text-slate-200"><?= htmlspecialchars($task['impact'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div></div>
                  <div><div class="doc-label">Esforco</div><div class="mt-2 text-sm text-slate-200"><?= htmlspecialchars($task['effort'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div></div>
                  <div><div class="doc-label">Status</div><div class="mt-2"><span class="status-badge status-nao-iniciada">Nao iniciada</span></div></div>
                </div>
                <div class="mt-4">
                  <div class="doc-label">Criterio de conclusao</div>
                  <div class="mt-2 text-sm leading-6 text-slate-300"><?= htmlspecialchars($task['criterion'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                </div>
                <div class="mt-4">
                  <div class="doc-label">Dependencias</div>
                  <div class="mt-2 flex flex-wrap gap-2">
                    <?php foreach ($task['dependencies'] as $dependency): ?>
                      <span class="track-dependency-chip"><?= htmlspecialchars($dependency, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                    <?php endforeach; ?>
                  </div>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        </section>
        </div>
        <?php endif; ?>

        <?php if ($isActiveSection('fase-2')): ?>
        <div>
        <section id="fase-2" class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
          <h2 class="font-orbitron text-xl font-bold text-cyan-200">Fase 2 - Padronizacao estrutural do codigo</h2>
          <div class="doc-card doc-rule mt-4">
            <p class="doc-label">Objetivo</p>
            <p class="mt-2 text-sm text-slate-200">Uniformizar a base para manutencao previsivel, com regras de nomenclatura e separacao de camadas.</p>
          </div>
          <div class="mt-4 grid gap-3 md:grid-cols-3">
            <div class="doc-card">
              <p class="doc-label">Foco principal</p>
              <p class="mt-2 text-sm text-slate-200">Nomenclatura, padrao de rotas, separacao de responsabilidades e reduçao de acoplamento.</p>
            </div>
            <div class="doc-card">
              <p class="doc-label">Prioridade</p>
              <p class="mt-2 text-sm text-slate-200">Alta. Serve de base para escalar admin, editorial e manutencao.</p>
            </div>
            <div class="doc-card">
              <p class="doc-label">Saida esperada</p>
              <p class="mt-2 text-sm text-slate-200">Base mais previsivel, com menos excecoes e menos improviso estrutural.</p>
            </div>
          </div>
          <div class="doc-card mt-4">
            <p class="doc-label">Mapa de tarefas da fase</p>
            <div class="mt-3 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
              <?php foreach ($phaseTaskMap['fase-2'] as $task): ?>
                <div class="rounded-2xl border border-slate-700 bg-slate-950/70 px-4 py-3">
                  <div class="font-orbitron text-xs font-bold tracking-[0.18em] text-cyan-200"><?= htmlspecialchars($task['id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                  <div class="mt-2 text-sm text-slate-200"><?= htmlspecialchars($task['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="mt-4 space-y-4">
            <?php foreach ($phaseTaskDetails['fase-2'] as $task): ?>
              <article class="track-task-card">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                  <div class="max-w-3xl">
                    <p class="font-orbitron text-xs font-bold tracking-[0.18em] text-cyan-200"><?= htmlspecialchars($task['id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
                    <p class="mt-2 text-sm leading-7 text-slate-100"><?= htmlspecialchars($task['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
                  </div>
                  <div class="rounded-2xl border border-slate-700 bg-slate-900/80 px-4 py-3 text-sm text-slate-300 lg:max-w-xs">
                    <div class="doc-label">Resultado esperado</div>
                    <div class="mt-2 text-slate-100"><?= htmlspecialchars($task['result'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                  </div>
                </div>
                <div class="track-meta-grid">
                  <div><div class="doc-label">Prioridade</div><div class="mt-2 text-sm text-slate-200"><?= htmlspecialchars($task['priority'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div></div>
                  <div><div class="doc-label">Impacto</div><div class="mt-2 text-sm text-slate-200"><?= htmlspecialchars($task['impact'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div></div>
                  <div><div class="doc-label">Esforco</div><div class="mt-2 text-sm text-slate-200"><?= htmlspecialchars($task['effort'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div></div>
                  <div><div class="doc-label">Status</div><div class="mt-2"><span class="status-badge status-nao-iniciada">Nao iniciada</span></div></div>
                </div>
                <div class="mt-4">
                  <div class="doc-label">Criterio de conclusao</div>
                  <div class="mt-2 text-sm leading-6 text-slate-300"><?= htmlspecialchars($task['criterion'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                </div>
                <div class="mt-4">
                  <div class="doc-label">Dependencias</div>
                  <div class="mt-2 flex flex-wrap gap-2">
                    <?php foreach ($task['dependencies'] as $dependency): ?>
                      <span class="track-dependency-chip"><?= htmlspecialchars($dependency, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                    <?php endforeach; ?>
                  </div>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        </section>
        </div>
        <?php endif; ?>

        <?php if ($isActiveSection('fase-3')): ?>
        <div>
        <section id="fase-3" class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
          <h2 class="font-orbitron text-xl font-bold text-cyan-200">Fase 3 - Evolucao do admin</h2>
          <div class="doc-card doc-rule mt-4">
            <p class="doc-label">Objetivo</p>
            <p class="mt-2 text-sm text-slate-200">Consolidar o admin como centro operacional com componentes reutilizaveis e feedback confiavel.</p>
          </div>
          <div class="mt-4 grid gap-3 md:grid-cols-3">
            <div class="doc-card">
              <p class="doc-label">Foco principal</p>
              <p class="mt-2 text-sm text-slate-200">Dashboard, componentes internos, navegacao funcional e feedback de operacoes criticas.</p>
            </div>
            <div class="doc-card">
              <p class="doc-label">Prioridade</p>
              <p class="mt-2 text-sm text-slate-200">Media/Alta. Depende da estabilizacao estrutural para nao virar retrabalho visual.</p>
            </div>
            <div class="doc-card">
              <p class="doc-label">Saida esperada</p>
              <p class="mt-2 text-sm text-slate-200">Admin mais claro, reutilizavel e com operacao mais segura.</p>
            </div>
          </div>
          <div class="doc-card mt-4">
            <p class="doc-label">Mapa de tarefas da fase</p>
            <div class="mt-3 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
              <?php foreach ($phaseTaskMap['fase-3'] as $task): ?>
                <div class="rounded-2xl border border-slate-700 bg-slate-950/70 px-4 py-3">
                  <div class="font-orbitron text-xs font-bold tracking-[0.18em] text-cyan-200"><?= htmlspecialchars($task['id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                  <div class="mt-2 text-sm text-slate-200"><?= htmlspecialchars($task['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="mt-4 space-y-4">
            <?php foreach ($phaseTaskDetails['fase-3'] as $task): ?>
              <article class="track-task-card">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                  <div class="max-w-3xl">
                    <p class="font-orbitron text-xs font-bold tracking-[0.18em] text-cyan-200"><?= htmlspecialchars($task['id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
                    <p class="mt-2 text-sm leading-7 text-slate-100"><?= htmlspecialchars($task['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
                  </div>
                  <div class="rounded-2xl border border-slate-700 bg-slate-900/80 px-4 py-3 text-sm text-slate-300 lg:max-w-xs">
                    <div class="doc-label">Resultado esperado</div>
                    <div class="mt-2 text-slate-100"><?= htmlspecialchars($task['result'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                  </div>
                </div>
                <div class="track-meta-grid">
                  <div><div class="doc-label">Prioridade</div><div class="mt-2 text-sm text-slate-200"><?= htmlspecialchars($task['priority'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div></div>
                  <div><div class="doc-label">Impacto</div><div class="mt-2 text-sm text-slate-200"><?= htmlspecialchars($task['impact'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div></div>
                  <div><div class="doc-label">Esforco</div><div class="mt-2 text-sm text-slate-200"><?= htmlspecialchars($task['effort'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div></div>
                  <div><div class="doc-label">Status</div><div class="mt-2"><span class="status-badge status-nao-iniciada">Nao iniciada</span></div></div>
                </div>
                <div class="mt-4">
                  <div class="doc-label">Criterio de conclusao</div>
                  <div class="mt-2 text-sm leading-6 text-slate-300"><?= htmlspecialchars($task['criterion'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                </div>
                <div class="mt-4">
                  <div class="doc-label">Dependencias</div>
                  <div class="mt-2 flex flex-wrap gap-2">
                    <?php foreach ($task['dependencies'] as $dependency): ?>
                      <span class="track-dependency-chip"><?= htmlspecialchars($dependency, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                    <?php endforeach; ?>
                  </div>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        </section>
        </div>
        <?php endif; ?>

        <?php if ($isActiveSection('fase-4')): ?>
        <div>
        <section id="fase-4" class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
          <h2 class="font-orbitron text-xl font-bold text-cyan-200">Fase 4 - Evolucao do fluxo editorial</h2>
          <div class="doc-card doc-rule mt-4">
            <p class="doc-label">Objetivo</p>
            <p class="mt-2 text-sm text-slate-200">Melhorar criacao, edicao e publicacao de conteudo com consistencia, seguranca e menor friccao.</p>
          </div>
          <div class="mt-4 grid gap-3 md:grid-cols-3">
            <div class="doc-card">
              <p class="doc-label">Foco principal</p>
              <p class="mt-2 text-sm text-slate-200">Posts, slugs, midias, validacoes editoriais e ligacao com conversao do portal.</p>
            </div>
            <div class="doc-card">
              <p class="doc-label">Prioridade</p>
              <p class="mt-2 text-sm text-slate-200">Media/Alta. Deve avancar depois que a base e o admin estiverem mais estaveis.</p>
            </div>
            <div class="doc-card">
              <p class="doc-label">Saida esperada</p>
              <p class="mt-2 text-sm text-slate-200">Fluxo editorial mais forte, menos erro de publicacao e melhor consistencia.</p>
            </div>
          </div>
          <div class="doc-card mt-4">
            <p class="doc-label">Mapa de tarefas da fase</p>
            <div class="mt-3 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
              <?php foreach ($phaseTaskMap['fase-4'] as $task): ?>
                <div class="rounded-2xl border border-slate-700 bg-slate-950/70 px-4 py-3">
                  <div class="font-orbitron text-xs font-bold tracking-[0.18em] text-cyan-200"><?= htmlspecialchars($task['id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                  <div class="mt-2 text-sm text-slate-200"><?= htmlspecialchars($task['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="mt-4 space-y-4">
            <?php foreach ($phaseTaskDetails['fase-4'] as $task): ?>
              <article class="track-task-card">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                  <div class="max-w-3xl">
                    <p class="font-orbitron text-xs font-bold tracking-[0.18em] text-cyan-200"><?= htmlspecialchars($task['id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
                    <p class="mt-2 text-sm leading-7 text-slate-100"><?= htmlspecialchars($task['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
                  </div>
                  <div class="rounded-2xl border border-slate-700 bg-slate-900/80 px-4 py-3 text-sm text-slate-300 lg:max-w-xs">
                    <div class="doc-label">Resultado esperado</div>
                    <div class="mt-2 text-slate-100"><?= htmlspecialchars($task['result'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                  </div>
                </div>
                <div class="track-meta-grid">
                  <div><div class="doc-label">Prioridade</div><div class="mt-2 text-sm text-slate-200"><?= htmlspecialchars($task['priority'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div></div>
                  <div><div class="doc-label">Impacto</div><div class="mt-2 text-sm text-slate-200"><?= htmlspecialchars($task['impact'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div></div>
                  <div><div class="doc-label">Esforco</div><div class="mt-2 text-sm text-slate-200"><?= htmlspecialchars($task['effort'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div></div>
                  <div><div class="doc-label">Status</div><div class="mt-2"><span class="status-badge status-nao-iniciada">Nao iniciada</span></div></div>
                </div>
                <div class="mt-4">
                  <div class="doc-label">Criterio de conclusao</div>
                  <div class="mt-2 text-sm leading-6 text-slate-300"><?= htmlspecialchars($task['criterion'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                </div>
                <div class="mt-4">
                  <div class="doc-label">Dependencias</div>
                  <div class="mt-2 flex flex-wrap gap-2">
                    <?php foreach ($task['dependencies'] as $dependency): ?>
                      <span class="track-dependency-chip"><?= htmlspecialchars($dependency, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                    <?php endforeach; ?>
                  </div>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        </section>
        </div>
        <?php endif; ?>

        <?php if ($isActiveSection('fase-5')): ?>
        <div>
        <section id="fase-5" class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
          <h2 class="font-orbitron text-xl font-bold text-cyan-200">Fase 5 - Observabilidade e manutencao operacional</h2>
          <div class="doc-card doc-rule mt-4">
            <p class="doc-label">Objetivo</p>
            <p class="mt-2 text-sm text-slate-200">Aumentar rastreabilidade, diagnostico e suporte operacional continuo do projeto.</p>
          </div>
          <div class="mt-4 grid gap-3 md:grid-cols-3">
            <div class="doc-card">
              <p class="doc-label">Foco principal</p>
              <p class="mt-2 text-sm text-slate-200">Logs, auditoria, rastreabilidade de pacotes, backup, restore e rotina de revisao.</p>
            </div>
            <div class="doc-card">
              <p class="doc-label">Prioridade</p>
              <p class="mt-2 text-sm text-slate-200">Progressiva. Pode evoluir em paralelo, mas fica mais valiosa apos as fases 1 e 2.</p>
            </div>
            <div class="doc-card">
              <p class="doc-label">Saida esperada</p>
              <p class="mt-2 text-sm text-slate-200">Projeto mais auditavel, diagnosticavel e sustentavel no longo prazo.</p>
            </div>
          </div>
          <div class="doc-card mt-4">
            <p class="doc-label">Mapa de tarefas da fase</p>
            <div class="mt-3 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
              <?php foreach ($phaseTaskMap['fase-5'] as $task): ?>
                <div class="rounded-2xl border border-slate-700 bg-slate-950/70 px-4 py-3">
                  <div class="font-orbitron text-xs font-bold tracking-[0.18em] text-cyan-200"><?= htmlspecialchars($task['id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                  <div class="mt-2 text-sm text-slate-200"><?= htmlspecialchars($task['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="mt-4 space-y-4">
            <?php foreach ($phaseTaskDetails['fase-5'] as $task): ?>
              <article class="track-task-card">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                  <div class="max-w-3xl">
                    <p class="font-orbitron text-xs font-bold tracking-[0.18em] text-cyan-200"><?= htmlspecialchars($task['id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
                    <p class="mt-2 text-sm leading-7 text-slate-100"><?= htmlspecialchars($task['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
                  </div>
                  <div class="rounded-2xl border border-slate-700 bg-slate-900/80 px-4 py-3 text-sm text-slate-300 lg:max-w-xs">
                    <div class="doc-label">Resultado esperado</div>
                    <div class="mt-2 text-slate-100"><?= htmlspecialchars($task['result'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                  </div>
                </div>
                <div class="track-meta-grid">
                  <div><div class="doc-label">Prioridade</div><div class="mt-2 text-sm text-slate-200"><?= htmlspecialchars($task['priority'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div></div>
                  <div><div class="doc-label">Impacto</div><div class="mt-2 text-sm text-slate-200"><?= htmlspecialchars($task['impact'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div></div>
                  <div><div class="doc-label">Esforco</div><div class="mt-2 text-sm text-slate-200"><?= htmlspecialchars($task['effort'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div></div>
                  <div><div class="doc-label">Status</div><div class="mt-2"><span class="status-badge status-nao-iniciada">Nao iniciada</span></div></div>
                </div>
                <div class="mt-4">
                  <div class="doc-label">Criterio de conclusao</div>
                  <div class="mt-2 text-sm leading-6 text-slate-300"><?= htmlspecialchars($task['criterion'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                </div>
                <div class="mt-4">
                  <div class="doc-label">Dependencias</div>
                  <div class="mt-2 flex flex-wrap gap-2">
                    <?php foreach ($task['dependencies'] as $dependency): ?>
                      <span class="track-dependency-chip"><?= htmlspecialchars($dependency, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                    <?php endforeach; ?>
                  </div>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        </section>

        <section class="rounded-3xl border border-fuchsia-500/20 bg-slate-900/80 p-6">
          <h2 class="font-orbitron text-xl font-bold text-fuchsia-200">Resultado esperado por fase</h2>
          <div class="mt-4 grid gap-3 md:grid-cols-2">
            <div class="doc-card"><p class="doc-label">Fase 1</p><p class="mt-2 text-sm text-slate-200">Relatorio de inconsistencias doc x sistema, com prioridade e acao.</p></div>
            <div class="doc-card"><p class="doc-label">Fase 2</p><p class="mt-2 text-sm text-slate-200">Base mais previsivel e aderente ao padrao arquitetural.</p></div>
            <div class="doc-card"><p class="doc-label">Fase 3</p><p class="mt-2 text-sm text-slate-200">Admin mais forte, reutilizavel e orientado por operacao.</p></div>
            <div class="doc-card"><p class="doc-label">Fase 4</p><p class="mt-2 text-sm text-slate-200">Fluxo editorial mais seguro, pratico e consistente.</p></div>
            <div class="doc-card md:col-span-2"><p class="doc-label">Fase 5</p><p class="mt-2 text-sm text-slate-200">Sistema mais auditavel e facil de manter com rastreabilidade operacional real.</p></div>
          </div>
        </section>
        </div>
        <?php endif; ?>
      </div>
    </article>
  </div>

</section>
