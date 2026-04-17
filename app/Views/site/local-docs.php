<?php

declare(strict_types=1);

$projectVersion = trim((string) ($project_version ?? 'local'));
$generatedAt = trim((string) ($generated_at ?? date('Y-m-d H:i:s')));
?>
<section class="min-h-screen bg-slate-950 px-4 py-8 text-slate-100">
  <div class="mx-auto max-w-7xl space-y-6">
    <?php \App\Support\View::component('site/local-tools-nav', ['active' => 'docs']); ?>

    <header class="rounded-3xl border border-cyan-500/20 bg-slate-900/80 p-6 shadow-[0_0_40px_rgba(6,182,212,0.08)]">
      <p class="font-orbitron text-xs uppercase tracking-[0.35em] text-cyan-300/70">Documentacao Local</p>
      <h1 class="mt-2 font-orbitron text-3xl font-black tracking-tight text-white">Base Tecnica do Projeto Estrategia Nerd</h1>
      <p class="mt-3 max-w-4xl text-sm leading-7 text-slate-300">
        Documento oficial de operacao do sistema em ambiente local. Aqui ficam arquitetura, regras, fluxo de deploy, rotinas de backup,
        rotina de conteudo, padrao editorial e checklist obrigatorio para evolucao do projeto.
      </p>
      <div class="mt-5 grid gap-3 md:grid-cols-3">
        <div class="rounded-2xl border border-slate-700 bg-slate-950/70 p-4">
          <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Versao atual</p>
          <p class="mt-1 font-rajdhani text-2xl font-bold text-white"><?= htmlspecialchars($projectVersion, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
        </div>
        <div class="rounded-2xl border border-slate-700 bg-slate-950/70 p-4">
          <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Atualizado em</p>
          <p class="mt-1 text-sm text-slate-200"><?= htmlspecialchars($generatedAt, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
        </div>
        <div class="rounded-2xl border border-slate-700 bg-slate-950/70 p-4">
          <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Escopo</p>
          <p class="mt-1 text-sm text-slate-200">Local (homologacao), backup, deploy e operacao editorial</p>
        </div>
      </div>
    </header>

    <article class="grid gap-6 xl:grid-cols-[0.9fr_2fr]">
      <aside class="rounded-3xl border border-slate-800 bg-slate-900/80 p-5">
        <h2 class="font-orbitron text-base font-bold text-white">Indice rapido</h2>
        <nav class="mt-4 space-y-2 text-sm">
          <a class="block rounded-xl border border-slate-700 bg-slate-950/70 px-3 py-2 hover:border-cyan-400/60" href="#visao-geral">1. Visao geral</a>
          <a class="block rounded-xl border border-slate-700 bg-slate-950/70 px-3 py-2 hover:border-cyan-400/60" href="#arquitetura">2. Arquitetura</a>
          <a class="block rounded-xl border border-slate-700 bg-slate-950/70 px-3 py-2 hover:border-cyan-400/60" href="#pastas">3. Mapa de pastas</a>
          <a class="block rounded-xl border border-slate-700 bg-slate-950/70 px-3 py-2 hover:border-cyan-400/60" href="#modulos">4. Modulos funcionais</a>
          <a class="block rounded-xl border border-slate-700 bg-slate-950/70 px-3 py-2 hover:border-cyan-400/60" href="#banco">5. Banco de dados</a>
          <a class="block rounded-xl border border-slate-700 bg-slate-950/70 px-3 py-2 hover:border-cyan-400/60" href="#midia">6. Midia e uploads</a>
          <a class="block rounded-xl border border-slate-700 bg-slate-950/70 px-3 py-2 hover:border-cyan-400/60" href="#backup">7. Rotina de backup</a>
          <a class="block rounded-xl border border-slate-700 bg-slate-950/70 px-3 py-2 hover:border-cyan-400/60" href="#conteudo">8. Rotina de conteudo</a>
          <a class="block rounded-xl border border-slate-700 bg-slate-950/70 px-3 py-2 hover:border-cyan-400/60" href="#deploy">9. Deploy e producao</a>
          <a class="block rounded-xl border border-slate-700 bg-slate-950/70 px-3 py-2 hover:border-cyan-400/60" href="#editorial">10. Padrao editorial</a>
          <a class="block rounded-xl border border-slate-700 bg-slate-950/70 px-3 py-2 hover:border-cyan-400/60" href="#checklist">11. Checklist oficial</a>
          <a class="block rounded-xl border border-slate-700 bg-slate-950/70 px-3 py-2 hover:border-cyan-400/60" href="#manutencao">12. Atualizacao da doc</a>
        </nav>
      </aside>

      <div class="space-y-6">
        <section id="visao-geral" class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
          <h2 class="font-orbitron text-xl font-bold text-cyan-200">1) Visao geral do produto</h2>
          <p class="mt-3 text-sm leading-7 text-slate-300">
            O Estrategia Nerd opera como portal editorial com quatro pilares: aquisicao org&acirc;nica no blog, decisao nos posts,
            conversao na Central Nerd e retencao pela newsletter. O painel admin controla conteudo, links, midia e configuracoes de navegacao.
          </p>
          <div class="mt-4 grid gap-3 md:grid-cols-2">
            <div class="rounded-2xl border border-slate-700 bg-slate-950/70 p-4">
              <h3 class="font-orbitron text-sm font-semibold text-white">Publico e proposta</h3>
              <p class="mt-2 text-sm text-slate-300">Tecnologia, hardware, games, cultura geek, ofertas e guias de decisao com linguagem acessivel.</p>
            </div>
            <div class="rounded-2xl border border-slate-700 bg-slate-950/70 p-4">
              <h3 class="font-orbitron text-sm font-semibold text-white">Regra de ouro</h3>
              <p class="mt-2 text-sm text-slate-300">Layout serve ao conteudo. O sistema prioriza leitura, clareza, SEO util e fluxo de conversao.</p>
            </div>
          </div>
        </section>

        <section id="arquitetura" class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
          <h2 class="font-orbitron text-xl font-bold text-cyan-200">2) Arquitetura tecnica</h2>
          <div class="mt-4 overflow-x-auto">
            <table class="min-w-full border-separate border-spacing-y-2 text-sm">
              <thead>
                <tr class="text-left text-xs uppercase tracking-[0.2em] text-slate-500">
                  <th class="px-3 py-2">Camada</th>
                  <th class="px-3 py-2">Implementacao</th>
                  <th class="px-3 py-2">Observacoes</th>
                </tr>
              </thead>
              <tbody>
                <tr class="rounded-2xl border border-slate-800 bg-slate-950/70">
                  <td class="px-3 py-3 font-semibold text-white">Back-end</td>
                  <td class="px-3 py-3 text-slate-300">PHP 8.x (MVC leve)</td>
                  <td class="px-3 py-3 text-slate-400">Controladores em `app/Controllers`, servicos em `app/Services`</td>
                </tr>
                <tr class="rounded-2xl border border-slate-800 bg-slate-950/70">
                  <td class="px-3 py-3 font-semibold text-white">Views</td>
                  <td class="px-3 py-3 text-slate-300">PHP + Tailwind (CDN)</td>
                  <td class="px-3 py-3 text-slate-400">Layouts em `app/Views/layouts` e componentes em `app/Views/components`</td>
                </tr>
                <tr class="rounded-2xl border border-slate-800 bg-slate-950/70">
                  <td class="px-3 py-3 font-semibold text-white">Banco</td>
                  <td class="px-3 py-3 text-slate-300">MySQL</td>
                  <td class="px-3 py-3 text-slate-400">Config em `config/database.php` e variaveis `.env`</td>
                </tr>
                <tr class="rounded-2xl border border-slate-800 bg-slate-950/70">
                  <td class="px-3 py-3 font-semibold text-white">Rotinas locais</td>
                  <td class="px-3 py-3 text-slate-300">`scripts/backup.php` e `scripts/content-sync.php`</td>
                  <td class="px-3 py-3 text-slate-400">UI dedicada em `/local/backup` e `/local/conteudo`</td>
                </tr>
                <tr class="rounded-2xl border border-slate-800 bg-slate-950/70">
                  <td class="px-3 py-3 font-semibold text-white">Deploy</td>
                  <td class="px-3 py-3 text-slate-300">Pacote ZIP de codigo</td>
                  <td class="px-3 py-3 text-slate-400">Sem dump de banco; envio via rotina local de conteudo</td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>

        <section id="pastas" class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
          <h2 class="font-orbitron text-xl font-bold text-cyan-200">3) Mapa de pastas essenciais</h2>
          <pre class="mt-4 overflow-x-auto rounded-2xl border border-slate-800 bg-slate-950/80 p-4 text-xs leading-6 text-slate-300"><code>C:\xampp\htdocs\estrategia-nerd
|- app
|  |- Controllers
|  |- Services
|  |- Repositories
|  |- Views
|  `- Support
|- config
|- public
|  |- assets
|  `- uploads
|- scripts
|  |- backup.php
|  |- content-sync.php
|  |- preflight-check.php
|  `- backup/ + content-sync/
|- storage
|  |- backups
|  |- content-sync
|  `- code-sync
`- .env</code></pre>
        </section>

        <section id="modulos" class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
          <h2 class="font-orbitron text-xl font-bold text-cyan-200">4) Modulos funcionais do sistema</h2>
          <div class="mt-4 grid gap-3 md:grid-cols-2">
            <div class="rounded-2xl border border-slate-700 bg-slate-950/70 p-4">
              <h3 class="font-orbitron text-sm font-semibold text-white">Home</h3>
              <p class="mt-2 text-sm text-slate-300">Posicionamento do portal, SEO principal, temas centrais, blocos de descoberta.</p>
            </div>
            <div class="rounded-2xl border border-slate-700 bg-slate-950/70 p-4">
              <h3 class="font-orbitron text-sm font-semibold text-white">Blog e Post</h3>
              <p class="mt-2 text-sm text-slate-300">Aquisição organica, navegacao por categoria, retencao e CTAs internos.</p>
            </div>
            <div class="rounded-2xl border border-slate-700 bg-slate-950/70 p-4">
              <h3 class="font-orbitron text-sm font-semibold text-white">Central Nerd</h3>
              <p class="mt-2 text-sm text-slate-300">Pagina de conversao com destaque, secoes de ofertas, cupons, conteudo e redes.</p>
            </div>
            <div class="rounded-2xl border border-slate-700 bg-slate-950/70 p-4">
              <h3 class="font-orbitron text-sm font-semibold text-white">Admin</h3>
              <p class="mt-2 text-sm text-slate-300">Gestao editorial, links, categorias, midia, configuracoes e saude do sistema.</p>
            </div>
          </div>
        </section>

        <section id="banco" class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
          <h2 class="font-orbitron text-xl font-bold text-cyan-200">5) Banco de dados (escopo pratico)</h2>
          <p class="mt-3 text-sm leading-7 text-slate-300">
            As tabelas abaixo sustentam o fluxo editorial e de conversao. O pacote de conteudo sincroniza somente o recorte definido para publicacao segura.
          </p>
          <ul class="mt-4 list-disc space-y-1 pl-5 text-sm text-slate-300">
            <li>`posts`, `categoria_post`, `post_slug_history`</li>
            <li>`links` (com tipos: produto, cupom, oferta, conteudo, rede social)</li>
            <li>`configuracoes` (chaves publicas selecionadas)</li>
            <li>`comentarios`, `newsletter`, `link_clicks` e metricas seguem escopo operacional separado</li>
          </ul>
        </section>

        <section id="midia" class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
          <h2 class="font-orbitron text-xl font-bold text-cyan-200">6) Midia e uploads</h2>
          <p class="mt-3 text-sm leading-7 text-slate-300">
            O sistema valida vinculo de imagem em posts, links, configuracoes e referencias de template. A limpeza de orfas deve agir apenas
            no que realmente nao tem dependencia visivel.
          </p>
          <div class="mt-4 rounded-2xl border border-slate-700 bg-slate-950/70 p-4 text-sm text-slate-300">
            <p class="font-semibold text-white">Regras atuais de seguranca:</p>
            <ul class="mt-2 list-disc space-y-1 pl-5">
              <li>Troca de imagem de link remove a imagem antiga quando nao esta mais vinculada.</li>
              <li>Limpeza de orfas usa validacao de path para evitar exclusao fora da raiz gerenciada.</li>
              <li>Arquivos de marca (`logo-main.png`, `logo-symbol.png`, `about-mark.png`, `favicon.ico`) devem ser preservados quando referenciados.</li>
            </ul>
          </div>
        </section>

        <section id="backup" class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
          <h2 class="font-orbitron text-xl font-bold text-cyan-200">7) Rotina de backup (local)</h2>
          <p class="mt-3 text-sm leading-7 text-slate-300">
            A rotina gera backup local/homologacao ou producao, verifica integridade, permite marcar envio manual para nuvem e oferece restore controlado.
          </p>
          <ol class="mt-4 list-decimal space-y-1 pl-5 text-sm text-slate-300">
            <li>Acessar `/local/backup`</li>
            <li>Executar backup do perfil desejado</li>
            <li>Verificar backup</li>
            <li>Marcar envio para nuvem (quando feito manualmente)</li>
            <li>Restore apenas com confirmacao `RESTAURAR`</li>
          </ol>
        </section>

        <section id="conteudo" class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
          <h2 class="font-orbitron text-xl font-bold text-cyan-200">8) Rotina de conteudo e codigo</h2>
          <p class="mt-3 text-sm leading-7 text-slate-300">
            O modulo de conteudo separa publicacao editorial (banco + uploads referenciados) de deploy tecnico (pacote de codigo sem banco).
          </p>
          <div class="mt-4 grid gap-3 md:grid-cols-2">
            <div class="rounded-2xl border border-slate-700 bg-slate-950/70 p-4">
              <h3 class="font-orbitron text-sm font-semibold text-white">Conteudo</h3>
              <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-slate-300">
                <li>Exportar pacote</li>
                <li>Verificar pacote</li>
                <li>Aplicar local (teste)</li>
                <li>Publicar producao (controlado)</li>
              </ul>
            </div>
            <div class="rounded-2xl border border-slate-700 bg-slate-950/70 p-4">
              <h3 class="font-orbitron text-sm font-semibold text-white">Codigo</h3>
              <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-slate-300">
                <li>Gerar ZIP em `storage/code-sync`</li>
                <li>Conter apenas arquivos validados</li>
                <li>Nao incluir dados de banco</li>
                <li>Publicacao via botao `Publicar codigo`</li>
              </ul>
            </div>
          </div>
        </section>

        <section id="deploy" class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
          <h2 class="font-orbitron text-xl font-bold text-cyan-200">9) Fluxo oficial de deploy</h2>
          <div class="rounded-2xl border border-cyan-400/20 bg-cyan-500/10 p-4">
            <p class="font-orbitron text-sm font-semibold text-cyan-100">Fluxo padrao aprovado</p>
            <ol class="mt-2 list-decimal space-y-1 pl-5 text-sm text-cyan-50">
              <li>Alteracao local</li>
              <li>Teste local</li>
              <li>Commit</li>
              <li>Gerar pacote</li>
              <li>Envio para producao</li>
            </ol>
          </div>
          <p class="mt-4 text-sm text-slate-300">
            Sempre manter separacao entre frente de codigo e frente de dados. Pacote de codigo nao deve levar dados ficticios.
          </p>
        </section>

        <section id="editorial" class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
          <h2 class="font-orbitron text-xl font-bold text-cyan-200">10) Padrao editorial dos posts</h2>
          <ul class="mt-3 list-disc space-y-1 pl-5 text-sm text-slate-300">
            <li>Abertura forte com conflito/pergunta real.</li>
            <li>H2 e H3 com hierarquia clara e leitura escaneavel.</li>
            <li>Conclusao que fecha decisao + CTA de proximo passo.</li>
            <li>Imagem contextual por secao relevante (sem repeticao da capa no corpo).</li>
            <li>Links internos estrategicos para blog, central e newsletter.</li>
          </ul>
        </section>

        <section id="checklist" class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
          <h2 class="font-orbitron text-xl font-bold text-cyan-200">11) Checklist antes de publicar</h2>
          <div class="mt-4 grid gap-3 md:grid-cols-2">
            <div class="rounded-2xl border border-slate-700 bg-slate-950/70 p-4 text-sm text-slate-300">
              <p class="font-semibold text-white">Tecnico</p>
              <ul class="mt-2 list-disc space-y-1 pl-5">
                <li>Preflight executado sem bloqueios</li>
                <li>Sem erro de encoding/acentuacao</li>
                <li>Rotina testada localmente</li>
                <li>Arquivos corretos no pacote</li>
              </ul>
            </div>
            <div class="rounded-2xl border border-slate-700 bg-slate-950/70 p-4 text-sm text-slate-300">
              <p class="font-semibold text-white">Conteudo e UX</p>
              <ul class="mt-2 list-disc space-y-1 pl-5">
                <li>Titulos e descricoes sem caracteres quebrados</li>
                <li>Imagens com contexto real do texto</li>
                <li>CTA e navegacao entre posts coerentes</li>
                <li>Sem loops de proximo passo</li>
              </ul>
            </div>
          </div>
        </section>

        <section id="manutencao" class="rounded-3xl border border-fuchsia-500/20 bg-slate-900/80 p-6">
          <h2 class="font-orbitron text-xl font-bold text-fuchsia-200">12) Regra de manutencao da documentacao</h2>
          <p class="mt-3 text-sm leading-7 text-slate-300">
            A partir de agora, toda alteracao funcional deve atualizar esta pagina no mesmo ciclo de trabalho.
            Isso evita regressao de conhecimento e reduz retrabalho em deploy.
          </p>
          <div class="mt-4 rounded-2xl border border-fuchsia-400/20 bg-fuchsia-500/10 p-4 text-sm text-fuchsia-50">
            <p class="font-semibold">Padrao obrigatorio de registro:</p>
            <ol class="mt-2 list-decimal space-y-1 pl-5">
              <li>Atualizar secao impactada da documentacao</li>
              <li>Incluir resumo no commit da mudanca</li>
              <li>Gerar pacote somente apos teste da mudanca + doc</li>
            </ol>
          </div>
        </section>
      </div>
    </article>
  </div>
</section>
