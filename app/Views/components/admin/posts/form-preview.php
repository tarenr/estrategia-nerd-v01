<?php
declare(strict_types=1);
?>

<div id="previewModal" class="modal" onclick="fecharPreview(event)">
  <div class="modal-content p-6">
    <div class="flex items-center justify-between gap-4 mb-6">
      <h2 class="font-orbitron text-2xl font-black text-white">Preview do Post</h2>
      <button type="button" class="admin-btn admin-btn-secondary" onclick="fecharPreview()">Fechar</button>
    </div>
    <div id="previewContent"></div>
  </div>
</div>
