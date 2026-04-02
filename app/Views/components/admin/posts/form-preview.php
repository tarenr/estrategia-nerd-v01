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
