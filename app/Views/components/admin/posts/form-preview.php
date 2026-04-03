<?php
declare(strict_types=1);
?>

<div id="previewModal" class="modal" style="display:none; position:fixed; inset:0; background:rgba(2,6,23,0.92); z-index:1000; overflow-y:auto; align-items:center; justify-content:center; padding:24px;" onclick="fecharPreview(event)">
  <div class="modal-content p-6" style="background:#0f172a; border:1px solid rgba(0,212,255,0.3); border-radius:16px; max-width:960px; width:min(960px, 100%); max-height:90vh; overflow-y:auto; box-shadow:0 24px 80px rgba(2,6,23,0.65); padding:24px;">
    <div class="flex items-center justify-between gap-4 mb-6" style="display:flex; align-items:center; justify-content:space-between; gap:16px; margin-bottom:24px;">
      <h2 class="font-orbitron text-2xl font-black text-white" style="font-family:Orbitron,sans-serif; font-size:1.5rem; font-weight:900; color:#fff; margin:0;">Preview do Post</h2>
      <button type="button" class="admin-btn admin-btn-secondary" onclick="fecharPreview()">Fechar</button>
    </div>
    <div id="previewContent" style="color:#e2e8f0; min-height:180px; display:block;"></div>
  </div>
</div>

<script>
(function () {
  if (typeof window.fecharPreview !== 'function') {
    window.fecharPreview = function fecharPreview(event) {
      var modal = document.getElementById('previewModal');
      if (!modal) return;
      if (!event || !event.target || event.target.id === 'previewModal') {
        modal.style.display = 'none';
        document.body.style.overflow = '';
      }
    };
  }

  if (typeof window.abrirPreview !== 'function') {
    window.abrirPreview = function abrirPreview() {
      var modal = document.getElementById('previewModal');
      var previewContent = document.getElementById('previewContent');
      var editor = document.getElementById('editor-visual');
      var hidden = document.getElementById('conteudoHidden');
      var tituloEl = document.getElementById('titulo');
      var categoriaSelect = document.getElementById('categoria_post_id');
      if (!modal || !previewContent || !editor || !hidden) return;

      hidden.value = editor.innerHTML;
      var titulo = tituloEl && tituloEl.value ? tituloEl.value : 'Sem titulo';
      var conteudo = editor.innerHTML || '<p><em>Sem conteudo.</em></p>';
      var categoriaNome = 'Geral';

      if (categoriaSelect && categoriaSelect.selectedIndex >= 0) {
        var option = categoriaSelect.options[categoriaSelect.selectedIndex];
        if (option && option.value) {
          categoriaNome = option.textContent.trim();
        }
      }

      previewContent.innerHTML = '' +
        '<style>#previewContent img{max-width:100%;height:auto;display:block;margin:24px auto;border-radius:18px;}#previewContent figure{margin:0 0 24px;}#previewContent figcaption{margin-top:10px;color:#94a3b8;font-size:13px;text-align:center;}#previewContent iframe,#previewContent video{width:100%;min-height:320px;border:0;border-radius:18px;display:block;margin:24px 0;}#previewContent table{width:100%;border-collapse:collapse;margin:24px 0;background:rgba(15,23,42,.82);}#previewContent th,#previewContent td{border:1px solid rgba(51,65,85,.8);padding:12px 14px;text-align:left;vertical-align:top;}#previewContent th{background:rgba(30,41,59,.55);}#previewContent h1,#previewContent h2,#previewContent h3{font-family:Orbitron,sans-serif;}#previewContent h1{color:#fff;}#previewContent h2{color:#67e8f9;}#previewContent h3{color:#c084fc;}</style>' +
        '<div style="margin-bottom:16px;"><span style="display:inline-flex;align-items:center;padding:6px 12px;background:rgba(0,212,255,.92);color:#020617;font-size:12px;font-weight:900;border-radius:999px;text-transform:uppercase;">' + categoriaNome + '</span></div>' +
        '<h1 style="font-size:2.4rem;line-height:1.1;margin:0 0 24px;">' + titulo + '</h1>' +
        '<div style="color:#e2e8f0;line-height:1.85;">' + conteudo + '</div>';

      modal.style.display = 'flex';
      document.body.style.overflow = 'hidden';
    };
  }
})();
</script>
