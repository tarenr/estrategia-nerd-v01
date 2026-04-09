document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-users-avatar-form]').forEach((form) => {
    initAvatarForm(form);
  });
});

function initAvatarForm(form) {
  const preview = form.querySelector('[data-avatar-preview]');
  const previewPhoto = form.querySelector('[data-avatar-preview-photo]');
  const previewIcon = form.querySelector('[data-avatar-preview-icon]');
  const previewMeta = form.querySelector('[data-avatar-preview-meta]');
  const previewName = form.querySelector('[data-avatar-preview-name]');
  const nameInput = form.querySelector('[data-avatar-name]');
  const typeSelect = form.querySelector('[data-avatar-type]');
  const iconSelect = form.querySelector('[data-avatar-icon]');
  const colorInput = form.querySelector('[data-avatar-color]');
  const uploadInput = form.querySelector('[data-avatar-upload]');
  const imagePathInput = form.querySelector('input[name="avatar_imagem"]');
  const focusXInput = form.querySelector('[data-avatar-focal-x]');
  const focusYInput = form.querySelector('[data-avatar-focal-y]');
  const focusPanel = form.querySelector('[data-avatar-focus-panel]');
  const focusStage = form.querySelector('[data-avatar-focus-stage]');
  const focusImage = form.querySelector('[data-avatar-focus-image]');
  const focusEmpty = form.querySelector('[data-avatar-focus-empty]');
  const focusMarker = form.querySelector('[data-avatar-focus-marker]');
  const focusMeta = form.querySelector('[data-avatar-focus-meta]');
  const clearToggle = form.querySelector('input[name="limpar_avatar_imagem"]');

  if (!preview || !previewPhoto || !previewIcon || !previewMeta || !typeSelect || !iconSelect || !colorInput || !focusXInput || !focusYInput || !focusPanel || !focusStage || !focusImage || !focusEmpty || !focusMarker || !focusMeta) {
    return;
  }

  const iconFieldGroups = Array.from(form.querySelectorAll('[data-avatar-icon-fields]'));
  const photoFieldGroups = Array.from(form.querySelectorAll('[data-avatar-photo-fields]'));

  const state = {
    savedPhotoUrl: (form.dataset.avatarCurrentUrl || imagePathInput?.value || '').trim(),
    currentPhotoUrl: '',
    objectUrl: '',
  };

  state.currentPhotoUrl = state.savedPhotoUrl;

  const clampPercent = (value) => {
    const parsed = Number.parseFloat(String(value));
    if (!Number.isFinite(parsed)) {
      return 50;
    }

    return Math.min(100, Math.max(0, parsed));
  };

  const getFocus = () => ({
    x: clampPercent(focusXInput.value),
    y: clampPercent(focusYInput.value),
  });

  const setFocus = (x, y) => {
    const safeX = clampPercent(x);
    const safeY = clampPercent(y);
    focusXInput.value = safeX.toFixed(2);
    focusYInput.value = safeY.toFixed(2);

    const position = `${safeX.toFixed(2)}% ${safeY.toFixed(2)}%`;
    previewPhoto.style.objectPosition = position;
    focusImage.style.objectPosition = position;
    focusMarker.style.left = `calc(${safeX.toFixed(2)}% - 10px)`;
    focusMarker.style.top = `calc(${safeY.toFixed(2)}% - 10px)`;
    focusMeta.textContent = `Foco: ${Math.round(safeX)}% x ${Math.round(safeY)}%`;
  };

  const setPreviewName = () => {
    if (!previewName || !nameInput) {
      return;
    }

    const value = nameInput.value.trim();
    previewName.textContent = value !== '' ? value : 'Usuario';
  };

  const currentGradient = () => `linear-gradient(135deg, ${colorInput.value || '#38bdf8'}, rgba(15, 23, 42, 0.92))`;

  const hasPhoto = () => typeSelect.value === 'foto' && state.currentPhotoUrl !== '';

  const syncFocusStage = () => {
    const photoActive = hasPhoto();
    const focus = getFocus();

    focusPanel.hidden = typeSelect.value !== 'foto';
    focusStage.classList.toggle('has-image', photoActive);
    focusImage.hidden = !photoActive;
    focusMarker.hidden = !photoActive;
    focusEmpty.hidden = photoActive;

    if (photoActive) {
      focusImage.src = state.currentPhotoUrl;
      setFocus(focus.x, focus.y);
    } else {
      focusImage.removeAttribute('src');
      focusMeta.textContent = 'Foco: centro padrao';
    }
  };

  const syncPreview = () => {
    const photoActive = hasPhoto();
    preview.style.background = photoActive ? 'rgba(15, 23, 42, 0.88)' : currentGradient();
    previewPhoto.hidden = !photoActive;
    previewIcon.hidden = photoActive ? true : false;

    if (photoActive) {
      previewPhoto.src = state.currentPhotoUrl;
      previewMeta.textContent = 'Foto ativa no admin';
      const focus = getFocus();
      setFocus(focus.x, focus.y);
    } else {
      previewPhoto.removeAttribute('src');
      previewMeta.textContent = typeSelect.value === 'foto'
        ? 'Envie uma foto para ativar o avatar'
        : 'Icone ativo no admin';
    }

    syncFocusStage();
  };

  const syncMode = () => {
    const photoMode = typeSelect.value === 'foto';

    iconFieldGroups.forEach((element) => {
      element.hidden = photoMode;
    });

    photoFieldGroups.forEach((element) => {
      element.hidden = !photoMode;
    });

    syncPreview();
  };

  const syncIcon = () => {
    previewIcon.className = iconSelect.value || 'fa-solid fa-user';
  };

  const syncColor = () => {
    if (!hasPhoto()) {
      preview.style.background = currentGradient();
    }
  };

  const restoreSavedPhoto = () => {
    state.currentPhotoUrl = clearToggle?.checked ? '' : state.savedPhotoUrl;
    syncPreview();
  };

  typeSelect.addEventListener('change', syncMode);
  iconSelect.addEventListener('change', syncIcon);
  colorInput.addEventListener('input', syncColor);
  nameInput?.addEventListener('input', setPreviewName);

  if (clearToggle) {
    clearToggle.addEventListener('change', () => {
      if (clearToggle.checked && state.objectUrl === '') {
        restoreSavedPhoto();
      } else if (!clearToggle.checked && state.objectUrl === '') {
        restoreSavedPhoto();
      }
    });
  }

  if (uploadInput) {
    uploadInput.addEventListener('change', () => {
      const file = uploadInput.files && uploadInput.files[0] ? uploadInput.files[0] : null;

      if (state.objectUrl) {
        URL.revokeObjectURL(state.objectUrl);
        state.objectUrl = '';
      }

      if (!file) {
        restoreSavedPhoto();
        return;
      }

      state.objectUrl = URL.createObjectURL(file);
      state.currentPhotoUrl = state.objectUrl;
      if (clearToggle) {
        clearToggle.checked = false;
      }
      syncPreview();
    });
  }

  focusStage.addEventListener('click', (event) => {
    if (!hasPhoto()) {
      return;
    }

    const rect = focusStage.getBoundingClientRect();
    if (rect.width <= 0 || rect.height <= 0) {
      return;
    }

    const x = ((event.clientX - rect.left) / rect.width) * 100;
    const y = ((event.clientY - rect.top) / rect.height) * 100;
    setFocus(x, y);
  });

  syncIcon();
  setPreviewName();
  syncMode();

  window.addEventListener('beforeunload', () => {
    if (state.objectUrl) {
      URL.revokeObjectURL(state.objectUrl);
    }
  }, { once: true });
}
