<?php

declare(strict_types=1);

use App\Support\View;

$overview = is_array($overview ?? null) ? $overview : [];
$facts = is_array($overview['facts'] ?? null) ? $overview['facts'] : [];
?>
<style>
  @keyframes admin-v2-fade-in {
    from { opacity: 0; transform: translateY(6px); }
    to { opacity: 1; transform: translateY(0); }
  }
</style>

<section class="space-y-6" data-operations-v2-root style="animation: admin-v2-fade-in .22s ease-out both;">
  <?php View::component('admin/v2/page-header', [
      'eyebrow' => 'Operacional V2',
      'title' => 'Central Operacional V2',
      'description' => '',
  ]); ?>

  <div data-operations-v2-panel aria-live="polite">
    <?= View::fragment('admin/operations-v2/panel', ['overview' => $overview]) ?>
  </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var root = document.querySelector('[data-operations-v2-root]');
  if (!root) {
    return;
  }

  var panel = root.querySelector('[data-operations-v2-panel]');
  var controller = null;

  root.addEventListener('click', function (event) {
    var tab = event.target.closest('[data-operations-v2-tab]');
    if (!tab || !panel) {
      return;
    }

    event.preventDefault();

    var url = new URL(tab.href, window.location.origin);
    url.searchParams.set('fragment', '1');

    if (controller) {
      controller.abort();
    }
    controller = new AbortController();

    panel.classList.add('opacity-55', 'pointer-events-none');

    fetch(url.toString(), {
      headers: {
        'X-Requested-With': 'XMLHttpRequest'
      },
      signal: controller.signal
    })
      .then(function (response) {
        if (!response.ok) {
          throw new Error('Falha ao carregar painel.');
        }

        return response.text();
      })
      .then(function (html) {
        panel.innerHTML = html;
        panel.classList.remove('opacity-55', 'pointer-events-none');

        url.searchParams.delete('fragment');
        window.history.pushState({}, '', url.toString());
      })
      .catch(function (error) {
        if (error.name === 'AbortError') {
          return;
        }

        panel.classList.remove('opacity-55', 'pointer-events-none');
        window.location.href = tab.href;
      });
  });

  window.addEventListener('popstate', function () {
    window.location.reload();
  });
});
</script>
