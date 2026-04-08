(function () {
  const buttons = document.querySelectorAll('[data-copy-url]');
  const uploadForm = document.querySelector('form[action$="/admin/midia/upload"]');

  async function optimizeUploadFile(file, options = {}) {
    if (!(file instanceof File)) return file;

    const type = String(file.type || '').toLowerCase();
    if (!['image/jpeg', 'image/png'].includes(type)) {
      return file;
    }

    const bitmap = await loadImageSource(file);
    const maxWidth = options.maxWidth || 2000;
    const maxHeight = options.maxHeight || 2000;
    const quality = typeof options.quality === 'number' ? options.quality : 0.84;
    const size = fitSize(bitmap.width, bitmap.height, maxWidth, maxHeight);

    const canvas = document.createElement('canvas');
    canvas.width = size.width;
    canvas.height = size.height;

    const ctx = canvas.getContext('2d');
    if (!ctx) {
      releaseImageSource(bitmap);
      return file;
    }

    ctx.drawImage(bitmap.source, 0, 0, size.width, size.height);
    releaseImageSource(bitmap);

    const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/webp', quality));
    if (!(blob instanceof Blob)) {
      return file;
    }

    const outputName = renameToWebp(file.name || 'imagem.webp');
    return new File([blob], outputName, { type: 'image/webp', lastModified: Date.now() });
  }

  async function loadImageSource(file) {
    if ('createImageBitmap' in window) {
      try {
        const bitmap = await createImageBitmap(file);
        return { source: bitmap, width: bitmap.width, height: bitmap.height, close: () => bitmap.close() };
      } catch (error) {}
    }

    return await new Promise((resolve, reject) => {
      const image = new Image();
      const objectUrl = URL.createObjectURL(file);
      image.onload = function () {
        URL.revokeObjectURL(objectUrl);
        resolve({ source: image, width: image.naturalWidth || image.width, height: image.naturalHeight || image.height, close: null });
      };
      image.onerror = function () {
        URL.revokeObjectURL(objectUrl);
        reject(new Error('Falha ao carregar imagem.'));
      };
      image.src = objectUrl;
    });
  }

  function releaseImageSource(bitmap) {
    if (bitmap && typeof bitmap.close === 'function') {
      bitmap.close();
    }
  }

  function fitSize(width, height, maxWidth, maxHeight) {
    if (!width || !height) {
      return { width: width || maxWidth, height: height || maxHeight };
    }

    const ratio = Math.min(maxWidth / width, maxHeight / height, 1);
    return {
      width: Math.max(1, Math.round(width * ratio)),
      height: Math.max(1, Math.round(height * ratio)),
    };
  }

  function renameToWebp(name) {
    return String(name || 'imagem').replace(/\.[^.]+$/, '') + '.webp';
  }

  function setInputFiles(input, files) {
    if (!input || !files || typeof DataTransfer === 'undefined') {
      return false;
    }

    const transfer = new DataTransfer();
    Array.from(files).forEach((file) => transfer.items.add(file));
    input.files = transfer.files;
    return true;
  }

  function updateFileLabel(label, input) {
    if (!label || !input) return;

    const file = input.files && input.files[0] ? input.files[0] : null;
    label.textContent = file ? file.name : 'Nenhum arquivo selecionado';
  }

  if (buttons.length) {
    buttons.forEach((button) => {
      button.addEventListener('click', async () => {
        const url = button.getAttribute('data-copy-url') || '';
        if (!url) return;

        const original = button.textContent;

        try {
          if (navigator.clipboard && navigator.clipboard.writeText) {
            await navigator.clipboard.writeText(url);
          } else {
            const input = document.createElement('input');
            input.value = url;
            document.body.appendChild(input);
            input.select();
            document.execCommand('copy');
            input.remove();
          }

          button.textContent = 'URL copiada';
        } catch (error) {
          button.textContent = 'Falhou';
        }

        window.setTimeout(() => {
          button.textContent = original || 'Copiar URL';
        }, 1400);
      });
    });
  }

  if (uploadForm) {
    const fileInput = uploadForm.querySelector('[data-media-file-input]');
    const dropzone = uploadForm.querySelector('[data-media-dropzone]');
    const fileLabel = uploadForm.querySelector('[data-media-file-label]');
    const submitButton = uploadForm.querySelector('button[type="submit"]');

    if (fileInput && fileLabel) {
      updateFileLabel(fileLabel, fileInput);
      fileInput.addEventListener('change', () => updateFileLabel(fileLabel, fileInput));
    }

    if (dropzone && fileInput) {
      dropzone.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' || event.key === ' ') {
          event.preventDefault();
          fileInput.click();
        }
      });

      ['dragenter', 'dragover'].forEach((eventName) => {
        dropzone.addEventListener(eventName, (event) => {
          event.preventDefault();
          event.stopPropagation();
          dropzone.classList.add('is-dragover');
        });
      });

      ['dragleave', 'dragend'].forEach((eventName) => {
        dropzone.addEventListener(eventName, (event) => {
          event.preventDefault();
          event.stopPropagation();
          if (!dropzone.contains(event.relatedTarget)) {
            dropzone.classList.remove('is-dragover');
          }
        });
      });

      dropzone.addEventListener('drop', (event) => {
        event.preventDefault();
        event.stopPropagation();
        dropzone.classList.remove('is-dragover');

        const files = event.dataTransfer && event.dataTransfer.files ? event.dataTransfer.files : null;
        if (!files || !files.length) return;

        if (setInputFiles(fileInput, [files[0]])) {
          updateFileLabel(fileLabel, fileInput);
        }
      });
    }

    uploadForm.addEventListener('submit', async (event) => {
      if (uploadForm.dataset.optimized === '1') {
        return;
      }

      event.preventDefault();

      const originalLabel = submitButton ? submitButton.textContent : '';
      if (submitButton) {
        submitButton.disabled = true;
        submitButton.textContent = 'Otimizando...';
      }

      try {
        const file = fileInput && fileInput.files ? fileInput.files[0] : null;
        if (file) {
          const optimized = await optimizeUploadFile(file, { maxWidth: 2200, maxHeight: 2200, quality: 0.84 });
          if (optimized !== file) {
            setInputFiles(fileInput, [optimized]);
            updateFileLabel(fileLabel, fileInput);
          }
        }
      } catch (error) {
      } finally {
        uploadForm.dataset.optimized = '1';
        if (submitButton) {
          submitButton.textContent = originalLabel || 'Enviar';
        }
        uploadForm.submit();
      }
    });
  }
})();