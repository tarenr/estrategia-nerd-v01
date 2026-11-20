<?php
/**
 * @file        app/Views/admin/youtube/_lista-videos.php
 * @project     Estrategia Nerd
 * @purpose     Lista da aba "Vídeos e Shorts" (planilha ou cards) e paginação.
 *
 * Incluída por admin/youtube/index.php, que define as variáveis e os helpers ($items, $buildUrl, $sortLink...).
 * O mesmo arquivo gera a página inteira e a resposta de ?ajax=videos (index.php com only_list).
 */
?>
  <section class="admin-panel posts-table-panel !p-0 overflow-hidden bg-slate-900/90 border border-slate-800 rounded-xl">
    <div class="p-4 border-b border-slate-800 flex items-center justify-between flex-wrap gap-2">
      <div class="flex items-center gap-2">
        <h3 class="font-orbitron text-sm font-bold text-white flex items-center gap-2">
          <i class="fa-solid fa-list-check text-cyan-400"></i>
          <span>Acervo de Vídeos</span>
        </h3>
        <span class="text-xs text-slate-400">(<?= $totalVideos ?> itens)</span>
      </div>

      <div class="flex items-center gap-3">
        <div class="flex items-center bg-slate-800/80 rounded-xl p-1 border border-slate-700 text-xs font-bold">
          <a
            href="<?= htmlspecialchars($buildUrl(['view' => 'table']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
            class="px-3 py-1.5 rounded-lg transition <?= $viewMode === 'table' ? 'bg-cyan-500/20 text-cyan-200 shadow' : 'text-slate-400 hover:text-white' ?>"
            title="Exibir como Planilha"
          >
            <i class="fa-solid fa-table-list mr-1.5" aria-hidden="true"></i> Planilha
          </a>
          <a
            href="<?= htmlspecialchars($buildUrl(['view' => 'grid']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
            class="px-3 py-1.5 rounded-lg transition <?= $viewMode === 'grid' ? 'bg-cyan-500/20 text-cyan-200 shadow' : 'text-slate-400 hover:text-white' ?>"
            title="Exibir como Cards"
          >
            <i class="fa-solid fa-grip mr-1.5" aria-hidden="true"></i> Cards
          </a>
        </div>

        <span class="posts-table-order"><?= $sort === '' ? 'PADRÃO' : strtoupper($esc($sort)) . ' / ' . strtoupper($esc($dir)) ?></span>
      </div>
    </div>

    <?php if ($items === []): ?>
      <div class="p-8 text-center space-y-3">
        <i class="fa-solid fa-film text-3xl text-slate-600"></i>
        <p class="text-slate-400 text-sm">Nenhum vídeo encontrado com os filtros selecionados.</p>
        <a href="<?= $baseUrl ?>" data-yt-clear class="admin-btn admin-btn-secondary !py-1.5 !px-3 text-xs inline-block">Limpar filtros</a>
      </div>
    <?php elseif ($viewMode === 'grid'): ?>
      <!-- Visualização em Cards / Grid -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 p-4">
        <?php foreach ($items as $v): ?>
          <?php
          $isShort = ($v['content_kind'] ?? 'video') === 'short';
          $localThumb = !empty($v['thumbnail_path']) ? url('/' . ltrim($v['thumbnail_path'], '/')) : null;
          // Para shorts que possuem capa local oficial, prioriza a imagem local nativa vertical
          $thumb = ($isShort && $localThumb) ? $localThumb : (!empty($v['thumbnail_url']) ? $v['thumbnail_url'] : $localThumb);
          $permalink = !empty($v['permalink']) ? $v['permalink'] : (!empty($v['youtube_video_id']) ? 'https://youtu.be/' . $v['youtube_video_id'] : null);
          ?>
          <div class="bg-slate-950/60 border border-slate-800 rounded-xl overflow-hidden flex flex-col justify-between group hover:border-slate-700 transition">
            <div>
              <div class="aspect-video bg-slate-900 relative overflow-hidden flex items-center justify-center">
                <?php if ($thumb): ?>
                  <img src="<?= $esc($thumb) ?>" alt="Capa" referrerpolicy="no-referrer" class="w-full h-full object-cover group-hover:scale-105 transition duration-300"
                    <?php if ($localThumb && $thumb !== $localThumb): ?>
                      onerror="if(this.src!=='<?= $esc($localThumb) ?>'){this.src='<?= $esc($localThumb) ?>';}"
                    <?php endif; ?>
                  >
                <?php else: ?>
                  <i class="fa-solid fa-video text-2xl text-slate-700"></i>
                <?php endif; ?>
                <span class="absolute bottom-2 right-2 px-1.5 py-0.5 rounded bg-black/80 text-white font-mono text-[10px]">
                  <?= $formatDuration((int) ($v['duration_seconds'] ?? 0)) ?>
                </span>
                <span class="absolute top-2 left-2 px-2 py-0.5 rounded-full text-[10px] font-bold <?= $isShort ? 'bg-purple-500/80 text-white' : 'bg-cyan-500/80 text-white' ?>">
                  <?= $isShort ? 'Short' : 'Vídeo' ?>
                </span>
              </div>

              <div class="p-3.5 space-y-2">
                <h4 class="text-xs font-bold text-white line-clamp-2" title="<?= $esc($v['title'] ?? '') ?>">
                  <?= $esc($v['title'] ?? 'Sem título') ?>
                </h4>
                <div class="flex items-center gap-3 text-[11px] text-slate-400">
                  <span><i class="fa-regular fa-eye"></i> <?= $fmt((int) ($v['views_count'] ?? 0)) ?></span>
                  <span><i class="fa-regular fa-thumbs-up"></i> <?= $fmt((int) ($v['likes_count'] ?? 0)) ?></span>
                  <span><i class="fa-regular fa-comment"></i> <?= $fmt((int) ($v['comments_count'] ?? 0)) ?></span>
                </div>
                <?php $dInfo = $videoDate($v); ?>
                <div class="space-y-0.5 text-[11px]">
                  <div class="flex items-center gap-1.5 text-slate-400">
                    <span class="text-slate-500 font-normal">Postado:</span>
                    <span class="text-slate-300"><?= $esc($dInfo['postado']) ?></span>
                  </div>
                  <div class="flex items-center gap-1.5 <?= $dInfo['classe'] ?>">
                    <span class="opacity-80"><?= $esc($dInfo['label']) ?>:</span>
                    <span><?= $esc($dInfo['valor']) ?></span>
                  </div>
                </div>
              </div>
            </div>

            <div class="p-3 border-t border-slate-800/80 flex items-center justify-between text-[11px]">
              <span class="flex items-center gap-1.5">
                <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold border <?= $statusClasses((string) ($v['status'] ?? '')) ?>">
                  <?= $statusLabel[$v['status'] ?? ''] ?? $v['status'] ?>
                </span>
                <?= $privacyBadge($v) ?>
              </span>
              <div class="flex items-center gap-2">
                <?php if ($permalink): ?>
                  <a href="<?= $esc($permalink) ?>" target="_blank" rel="noopener noreferrer" class="text-cyan-400 hover:text-cyan-300" title="Ver no YouTube">
                    <i class="fa-solid fa-arrow-up-right-from-square"></i>
                  </a>
                <?php endif; ?>
                <a href="<?= url('/admin/youtube/posts/' . (int) $v['id']) ?>" class="text-slate-400 hover:text-white" title="Detalhes locais">
                  <i class="fa-solid fa-gear"></i>
                </a>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <!-- Visualização em Tabela (Planilha) -->
      <div class="posts-table-wrap border-0 rounded-none">
        <!-- Título limitado (yt-title-box) e as demais colunas em uma linha; em janela estreita status e data empilham para não rolar. -->
        <table class="posts-table yt-videos-table" style="min-width: 820px;">
          <thead class="posts-table-thead">
            <tr>
              <th class="posts-table-th posts-table-th-center">Mídia</th>
              <th class="posts-table-th posts-table-th-center">
                <a class="posts-table-sort posts-table-sort-center" href="<?= htmlspecialchars($sortLink('content_kind'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                  Formato <?= $sortIcon('content_kind') ?>
                </a>
              </th>
              <th class="posts-table-th posts-table-th-left yt-title-col">
                <a class="posts-table-sort posts-table-sort-left" href="<?= htmlspecialchars($sortLink('title'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                  Título <?= $sortIcon('title') ?>
                </a>
              </th>
              <th class="posts-table-th posts-table-th-center">
                <a class="posts-table-sort posts-table-sort-center" href="<?= htmlspecialchars($sortLink('status'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                  Status <?= $sortIcon('status') ?>
                </a>
              </th>
              <th class="posts-table-th posts-table-th-center">
                <a class="posts-table-sort posts-table-sort-center" href="<?= htmlspecialchars($sortLink('published_at'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                  Datas (Postado / Público) <?= $sortIcon('published_at') ?>
                </a>
              </th>
              <th class="posts-table-th posts-table-th-center">
                <a class="posts-table-sort posts-table-sort-center" href="<?= htmlspecialchars($sortLink('views_count'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" title="Visualizações" aria-label="Ordenar por visualizações">
                  <i class="fa-solid fa-eye text-cyan-400" aria-hidden="true"></i> <?= $sortIcon('views_count') ?>
                </a>
              </th>
              <th class="posts-table-th posts-table-th-center">
                <a class="posts-table-sort posts-table-sort-center" href="<?= htmlspecialchars($sortLink('likes_count'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" title="Curtidas" aria-label="Ordenar por curtidas">
                  <i class="fa-solid fa-heart text-pink-400" aria-hidden="true"></i> <?= $sortIcon('likes_count') ?>
                </a>
              </th>
              <th class="posts-table-th posts-table-th-center">
                <a class="posts-table-sort posts-table-sort-center" href="<?= htmlspecialchars($sortLink('comments_count'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" title="Comentários" aria-label="Ordenar por comentários">
                  <i class="fa-solid fa-comment text-amber-400" aria-hidden="true"></i> <?= $sortIcon('comments_count') ?>
                </a>
              </th>
              <th class="posts-table-th posts-table-th-center">Ações</th>
            </tr>
          </thead>
          <tbody class="posts-table-body">
            <?php foreach ($items as $v): ?>
              <?php
              $isShort = ($v['content_kind'] ?? 'video') === 'short';
              $localThumb = !empty($v['thumbnail_path']) ? url('/' . ltrim($v['thumbnail_path'], '/')) : null;
              // Para shorts que possuem capa local oficial, prioriza a imagem local nativa vertical
              $thumb = ($isShort && $localThumb) ? $localThumb : (!empty($v['thumbnail_url']) ? $v['thumbnail_url'] : $localThumb);
              $permalink = !empty($v['permalink']) ? $v['permalink'] : (!empty($v['youtube_video_id']) ? 'https://youtu.be/' . $v['youtube_video_id'] : null);
              ?>
              <tr class="posts-table-row">
                <td class="posts-table-td posts-table-td-center">
                  <div class="w-16 h-10 rounded-lg bg-slate-900 overflow-hidden relative shrink-0 border border-slate-800 flex items-center justify-center mx-auto">
                    <?php if ($thumb): ?>
                      <img src="<?= $esc($thumb) ?>" alt="Capa" referrerpolicy="no-referrer" class="w-full h-full object-cover"
                        <?php if ($localThumb && $thumb !== $localThumb): ?>
                          onerror="if(this.src!=='<?= $esc($localThumb) ?>'){this.src='<?= $esc($localThumb) ?>';}"
                        <?php endif; ?>
                      >
                    <?php else: ?>
                      <i class="fa-solid fa-video text-slate-700"></i>
                    <?php endif; ?>
                    <span class="absolute bottom-0.5 right-0.5 px-1 rounded bg-black/80 text-[9px] text-white font-mono">
                      <?= $formatDuration((int) ($v['duration_seconds'] ?? 0)) ?>
                    </span>
                  </div>
                </td>
                <td class="posts-table-td posts-table-td-center whitespace-nowrap">
                  <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold <?= $isShort ? 'bg-purple-500/20 text-purple-300 border border-purple-500/30' : 'bg-cyan-500/20 text-cyan-300 border border-cyan-500/30' ?>">
                    <i class="fa-solid <?= $isShort ? 'fa-mobile-screen-button' : 'fa-film' ?>"></i>
                    <?= $isShort ? 'Short' : 'Vídeo' ?>
                  </span>
                </td>
                <td class="posts-table-td posts-table-title-cell">
                  <div class="yt-title-box">
                    <div class="font-bold text-slate-100 truncate" title="<?= $esc($v['title'] ?? '') ?>">
                      <?= $esc($v['title'] ?? 'Sem título') ?>
                    </div>
                    <div class="text-[11px] text-slate-400 truncate">
                      <?= $esc($v['description'] ?? '—') ?>
                    </div>
                  </div>
                </td>
                <td class="posts-table-td posts-table-td-center whitespace-nowrap">
                  <div class="yt-cell-line">
                    <span class="inline-block px-2 py-0.5 rounded-full text-[10px] font-semibold border <?= $statusClasses((string) ($v['status'] ?? '')) ?>">
                      <?= $statusLabel[$v['status'] ?? ''] ?? $v['status'] ?>
                    </span>
                    <?= $privacyBadge($v) ?>
                  </div>
                </td>
                <td class="posts-table-td posts-table-td-center whitespace-nowrap text-[11px]">
                  <?php $dInfo = $videoDate($v); ?>
                  <div class="flex flex-col items-center justify-center gap-0.5 leading-tight">
                    <div class="flex items-center gap-1 text-slate-300">
                      <span class="text-slate-500 font-normal text-[10px]">Postado:</span>
                      <span class="text-slate-300 font-mono"><?= $esc($dInfo['postado']) ?></span>
                    </div>
                    <div class="flex items-center gap-1 <?= $dInfo['classe'] ?>">
                      <span class="opacity-80 text-[10px]"><?= $esc($dInfo['label']) ?>:</span>
                      <span class="font-mono"><?= $esc($dInfo['valor']) ?></span>
                    </div>
                  </div>
                </td>
                <td class="posts-table-td posts-table-td-center font-mono text-slate-200 font-semibold whitespace-nowrap">
                  <?= $fmt((int) ($v['views_count'] ?? 0)) ?>
                </td>
                <td class="posts-table-td posts-table-td-center font-mono text-slate-200 font-semibold whitespace-nowrap">
                  <?= $fmt((int) ($v['likes_count'] ?? 0)) ?>
                </td>
                <td class="posts-table-td posts-table-td-center font-mono text-slate-200 font-semibold whitespace-nowrap">
                  <?= $fmt((int) ($v['comments_count'] ?? 0)) ?>
                </td>
                <td class="posts-table-td posts-table-td-center whitespace-nowrap">
                  <div class="flex items-center justify-center gap-2">
                    <?php if ($permalink): ?>
                      <a href="<?= $esc($permalink) ?>" target="_blank" rel="noopener noreferrer" class="p-1 text-cyan-400 hover:text-cyan-300" title="Ver no YouTube">
                        <i class="fa-solid fa-arrow-up-right-from-square"></i>
                      </a>
                    <?php endif; ?>
                    <a href="<?= url('/admin/youtube/posts/' . (int) $v['id']) ?>" class="p-1 text-slate-400 hover:text-white" title="Detalhes técnicos">
                      <i class="fa-solid fa-gear"></i>
                    </a>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>

  <!-- Paginação Oficial Estratégia Nerd -->
  <section class="admin-panel posts-pagination-panel">
    <div class="posts-pagination-shell">
      <div class="posts-pagination-summary">
        <?php if ($totalVideos > 0): ?>
          Exibindo <span><?= number_format($firstItem, 0, ',', '.') ?></span> até <span><?= number_format($lastItem, 0, ',', '.') ?></span> de <span><?= number_format($totalVideos, 0, ',', '.') ?></span> vídeos
        <?php else: ?>
          Nenhum vídeo para paginar no momento.
        <?php endif; ?>
      </div>

      <div class="posts-pagination-controls">
        <div class="posts-pagination-per-page">
          <span class="posts-pagination-kicker">Por página</span>
          <div class="posts-pagination-chip-group">
            <?php foreach ([8 => '8', 16 => '16', 24 => '24', 32 => '32', 48 => '48', 'todos' => 'Todos'] as $optVal => $optLabel): ?>
              <?php $activeChip = ($optVal === 'todos' ? $perPage >= 9999 : $perPage === (int) $optVal); ?>
              <a
                class="posts-pagination-chip<?= $activeChip ? ' is-active' : '' ?>"
                href="<?= htmlspecialchars($buildUrl(['per_page' => $optVal, 'page' => 1]), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
              >
                <?= $optLabel ?>
              </a>
            <?php endforeach; ?>
          </div>
        </div>

        <?php if ($perPage < 9999 && $pages > 1): ?>
          <nav class="posts-pagination-nav" aria-label="Paginação dos vídeos do YouTube">
            <a
              class="posts-pagination-link posts-pagination-link-wide<?= $totalVideos === 0 || $page <= 1 ? ' is-disabled' : '' ?>"
              href="<?= htmlspecialchars($buildUrl(['page' => $page - 1]), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
            >Anterior</a>

            <?php for ($curr = $startRange; $curr <= $endRange; $curr++): ?>
              <a
                class="posts-pagination-link<?= $curr === $page ? ' is-active' : '' ?>"
                href="<?= htmlspecialchars($buildUrl(['page' => $curr]), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
              ><?= $curr ?></a>
            <?php endfor; ?>

            <a
              class="posts-pagination-link posts-pagination-link-wide<?= $totalVideos === 0 || $page >= $pages ? ' is-disabled' : '' ?>"
              href="<?= htmlspecialchars($buildUrl(['page' => $page + 1]), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
            >Próxima</a>
          </nav>
        <?php endif; ?>
      </div>
    </div>
  </section>
