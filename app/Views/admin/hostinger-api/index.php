<?php

declare(strict_types=1);

use App\Support\View;

$data = is_array($hostinger_api ?? null) ? $hostinger_api : [];
$domain = (string) ($data['domain'] ?? 'estrategianerd.com.br');
$connection = is_array($data['connection'] ?? null) ? $data['connection'] : [];
$cards = is_array($data['cards'] ?? null) ? $data['cards'] : [];
$domainDetails = is_array($data['domain_details'] ?? null) ? $data['domain_details'] : [];
$dnsGroups = is_array($data['dns_groups'] ?? null) ? $data['dns_groups'] : [];
$hosting = is_array($data['hosting'] ?? null) ? $data['hosting'] : [];
$databases = is_array($data['databases'] ?? null) ? $data['databases'] : [];
$subscriptions = is_array($data['subscriptions'] ?? null) ? $data['subscriptions'] : [];
$snapshots = is_array($data['snapshots'] ?? null) ? $data['snapshots'] : [];
$alerts = is_array($data['alerts'] ?? null) ? $data['alerts'] : [];
$errors = is_array($connection['errors'] ?? null) ? $connection['errors'] : [];
?>
<section class="space-y-6">
  <?php View::component('admin/v2/page-header', [
      'eyebrow' => 'Central Operacional',
      'title' => 'Hostinger API',
      'description' => 'Leitura operacional somente para ' . $domain . '.',
      'actions' => [
          [
              'href' => url('/admin/central-operacional-v2'),
              'label' => 'Voltar',
              'icon' => 'fa-solid fa-arrow-left',
              'variant' => 'secondary',
          ],
      ],
  ]); ?>

  <div class="rounded-3xl border <?= (($connection['ok'] ?? false) === true) ? 'border-cyan-500/20 bg-cyan-500/5' : 'border-amber-500/25 bg-amber-500/10' ?> p-5">
    <div class="flex flex-wrap items-start justify-between gap-4">
      <div>
        <div class="font-orbitron text-xs font-black uppercase tracking-[0.18em] text-cyan-200">Status da API</div>
        <div class="mt-2 text-lg font-black text-white"><?= htmlspecialchars((string) ($connection['message'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
      </div>
      <?php View::component('admin/v2/status-badge', [
          'label' => (($connection['ok'] ?? false) === true) ? 'OK' : 'Atencao',
          'tone' => (($connection['ok'] ?? false) === true) ? 'success' : 'warning',
      ]); ?>
    </div>
    <?php if ($errors !== []): ?>
      <div class="mt-4 grid gap-2 text-xs font-semibold text-amber-100">
        <?php foreach ($errors as $error): ?>
          <div class="rounded-2xl border border-amber-500/20 bg-slate-950/50 px-4 py-3">
            <?= htmlspecialchars((string) ($error['path'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>:
            <?= htmlspecialchars((string) ($error['error'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
    <?php foreach ($cards as $card): ?>
      <article class="rounded-[1.35rem] border border-slate-800 bg-slate-900/80 p-5">
        <div class="flex items-start justify-between gap-4">
          <div class="flex h-11 w-11 items-center justify-center rounded-2xl border border-cyan-400/15 bg-cyan-500/10 text-cyan-200">
            <i class="<?= htmlspecialchars((string) ($card['icon'] ?? 'fa-solid fa-circle-info'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" aria-hidden="true"></i>
          </div>
          <?php View::component('admin/v2/status-badge', [
              'label' => (string) ($card['tone'] ?? 'info'),
              'tone' => (string) ($card['tone'] ?? 'info'),
          ]); ?>
        </div>
        <div class="mt-5 font-orbitron text-[10px] font-black uppercase tracking-[0.16em] text-slate-500"><?= htmlspecialchars((string) ($card['label'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
        <div class="mt-2 break-words text-xl font-black text-white"><?= htmlspecialchars((string) ($card['value'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
        <div class="mt-2 text-xs font-semibold text-slate-400"><?= htmlspecialchars((string) ($card['hint'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
      </article>
    <?php endforeach; ?>
  </section>

  <section class="grid gap-6 xl:grid-cols-[1fr_0.85fr]">
    <article class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
      <h2 class="font-orbitron text-lg font-black text-white">Dominio</h2>
      <dl class="mt-5 grid gap-3 sm:grid-cols-2">
        <?php foreach ($domainDetails as $label => $value): ?>
          <div class="rounded-2xl border border-slate-800 bg-slate-950/70 px-4 py-3">
            <dt class="font-orbitron text-[10px] font-black uppercase tracking-[0.16em] text-slate-500"><?= htmlspecialchars(str_replace('_', ' ', (string) $label), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></dt>
            <dd class="mt-2 break-words text-sm font-bold text-slate-100"><?= htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></dd>
          </div>
        <?php endforeach; ?>
        <?php if ($domainDetails === []): ?>
          <div class="rounded-2xl border border-slate-800 bg-slate-950/70 px-4 py-8 text-center text-sm font-semibold text-slate-400 sm:col-span-2">Sem dados do dominio.</div>
        <?php endif; ?>
      </dl>
    </article>

    <article class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
      <h2 class="font-orbitron text-lg font-black text-white">Alertas</h2>
      <div class="mt-5 grid gap-3">
        <?php foreach ($alerts as $alert): ?>
          <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
            <div class="flex items-start justify-between gap-3">
              <div>
                <div class="font-black text-white"><?= htmlspecialchars((string) ($alert['label'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                <div class="mt-1 text-xs font-semibold text-slate-400"><?= htmlspecialchars((string) ($alert['text'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
              </div>
              <?php View::component('admin/v2/status-badge', [
                  'label' => (string) ($alert['tone'] ?? 'info'),
                  'tone' => (string) ($alert['tone'] ?? 'info'),
              ]); ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </article>
  </section>

  <section class="grid gap-6 xl:grid-cols-2">
    <article class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
      <h2 class="font-orbitron text-lg font-black text-white">Hospedagem</h2>
      <dl class="mt-5 grid gap-3">
        <?php foreach ($hosting as $label => $value): ?>
          <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-800 bg-slate-950/70 px-4 py-3">
            <dt class="font-orbitron text-[10px] font-black uppercase tracking-[0.16em] text-slate-500"><?= htmlspecialchars(str_replace('_', ' ', (string) $label), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></dt>
            <dd class="max-w-full break-all text-right text-sm font-bold text-slate-100"><?= htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></dd>
          </div>
        <?php endforeach; ?>
        <?php if ($hosting === []): ?>
          <div class="rounded-2xl border border-slate-800 bg-slate-950/70 px-4 py-8 text-center text-sm font-semibold text-slate-400">Sem dados de hospedagem.</div>
        <?php endif; ?>
      </dl>
    </article>

    <article class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
      <h2 class="font-orbitron text-lg font-black text-white">Bancos</h2>
      <div class="mt-5 grid gap-3">
        <?php foreach ($databases as $database): ?>
          <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
            <div class="font-black text-white"><?= htmlspecialchars((string) ($database['name'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
            <div class="mt-2 grid gap-2 text-xs font-semibold text-slate-400 sm:grid-cols-2">
              <div>Usuario: <span class="text-slate-100"><?= htmlspecialchars((string) ($database['user'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span></div>
              <div>Uso: <span class="text-slate-100"><?= htmlspecialchars((string) ($database['disk_usage'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span></div>
              <div>Limite: <span class="text-slate-100"><?= htmlspecialchars((string) ($database['max_size'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span></div>
              <div>Atualizado: <span class="text-slate-100"><?= htmlspecialchars((string) ($database['updated_at'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span></div>
            </div>
          </div>
        <?php endforeach; ?>
        <?php if ($databases === []): ?>
          <div class="rounded-2xl border border-slate-800 bg-slate-950/70 px-4 py-8 text-center text-sm font-semibold text-slate-400">Nenhum banco listado para o dominio.</div>
        <?php endif; ?>
      </div>
    </article>
  </section>

  <section class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
    <h2 class="font-orbitron text-lg font-black text-white">DNS</h2>
    <div class="mt-5 grid gap-4">
      <?php foreach ($dnsGroups as $type => $records): ?>
        <article class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
          <div class="flex items-center justify-between gap-3">
            <h3 class="font-orbitron text-sm font-black text-cyan-200"><?= htmlspecialchars((string) $type, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h3>
            <span class="text-xs font-bold text-slate-500"><?= count((array) $records) ?> registros</span>
          </div>
          <div class="mt-4 overflow-x-auto">
            <table class="min-w-full text-left text-sm text-slate-200">
              <thead>
                <tr class="font-orbitron text-[10px] uppercase tracking-[0.18em] text-slate-500">
                  <th class="px-3 py-2">Nome</th>
                  <th class="px-3 py-2">TTL</th>
                  <th class="px-3 py-2">Conteudo</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-800">
                <?php foreach ((array) $records as $record): ?>
                  <tr>
                    <td class="px-3 py-3 font-bold text-white"><?= htmlspecialchars((string) ($record['name'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                    <td class="px-3 py-3"><?= htmlspecialchars((string) ($record['ttl'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                    <td class="whitespace-pre-wrap break-all px-3 py-3 text-slate-300"><?= htmlspecialchars((string) ($record['content'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </article>
      <?php endforeach; ?>
      <?php if ($dnsGroups === []): ?>
        <div class="rounded-2xl border border-slate-800 bg-slate-950/70 px-4 py-8 text-center text-sm font-semibold text-slate-400">Sem registros DNS para exibir.</div>
      <?php endif; ?>
    </div>
  </section>

  <section class="grid gap-6 xl:grid-cols-2">
    <article class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
      <h2 class="font-orbitron text-lg font-black text-white">Assinaturas</h2>
      <div class="mt-5 divide-y divide-slate-800 overflow-hidden rounded-2xl border border-slate-800 bg-slate-950/70">
        <?php foreach ($subscriptions as $subscription): ?>
          <div class="p-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div>
                <div class="font-black text-white"><?= htmlspecialchars((string) ($subscription['name'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                <div class="mt-1 text-xs font-semibold text-slate-400">Expira: <?= htmlspecialchars((string) ($subscription['expires_at'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
              </div>
              <div class="text-right text-xs font-bold text-slate-300">
                <div><?= htmlspecialchars((string) ($subscription['renewal_price'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                <div class="mt-1">Auto: <?= htmlspecialchars((string) ($subscription['auto_renewed'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
        <?php if ($subscriptions === []): ?>
          <div class="px-4 py-8 text-center text-sm font-semibold text-slate-400">Sem assinaturas listadas.</div>
        <?php endif; ?>
      </div>
    </article>

    <article class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
      <h2 class="font-orbitron text-lg font-black text-white">Snapshots DNS</h2>
      <div class="mt-5 divide-y divide-slate-800 overflow-hidden rounded-2xl border border-slate-800 bg-slate-950/70">
        <?php foreach ($snapshots as $snapshot): ?>
          <div class="p-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div>
                <div class="font-black text-white">#<?= htmlspecialchars((string) ($snapshot['id'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                <div class="mt-1 text-xs font-semibold text-slate-400"><?= htmlspecialchars((string) ($snapshot['reason'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
              </div>
              <div class="text-right text-xs font-bold text-slate-300"><?= htmlspecialchars((string) ($snapshot['created_at'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
            </div>
          </div>
        <?php endforeach; ?>
        <?php if ($snapshots === []): ?>
          <div class="px-4 py-8 text-center text-sm font-semibold text-slate-400">Sem snapshots listados.</div>
        <?php endif; ?>
      </div>
    </article>
  </section>
</section>
