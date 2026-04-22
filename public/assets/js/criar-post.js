/**
 * File: /assets/js/criar-post.js
 * Purpose: Editor (abas/toolbar/sync) e preview para /admin/pages/criar-post.php
 *
 * Notes:
 * - Este arquivo expoe funcoes globais (switchTab, formatar, etc.) porque o HTML usa onclick="...".
 * - Carregue com `defer` no footer.
 */

/* global document, window */

(function () {
  "use strict";

  function byId(id) {
    return document.getElementById(id);
  }

  function existsEditor() {
    return Boolean(byId("editor-visual") && byId("conteudoHidden"));
  }

  // -------------------------
  // Abas
  // -------------------------
  window.switchTab = function switchTab(tabName) {
    if (!existsEditor()) return;
    // Esconde todos os paineis
    document.querySelectorAll(".editor-panel").forEach(function (p) {
      p.classList.add("hidden");
    });

    var panel = byId("panel-" + tabName);
    if (panel) panel.classList.remove("hidden");
    // Botoes de aba
    document.querySelectorAll('[id^="tab-btn-"]').forEach(function (btn) {
      btn.classList.remove(
        "bg-cyan-500/20",
        "text-cyan-400",
        "border-t",
        "border-x",
        "border-cyan-500/30"
      );
      btn.classList.add("bg-slate-800", "text-gray-400");
    });

    var activeBtn = byId("tab-btn-" + tabName);
    if (activeBtn) {
      activeBtn.classList.remove("bg-slate-800", "text-gray-400");
      activeBtn.classList.add(
        "bg-cyan-500/20",
        "text-cyan-400",
        "border-t",
        "border-x",
        "border-cyan-500/30"
      );
    }

    // Texto ajuda
    var ajudas = {
      visual: "Use a barra acima para formatar.",
      html: "Edite diretamente o codigo HTML.",
    };

    var ajudaEl = byId("editor-ajuda");
    if (ajudaEl) ajudaEl.textContent = ajudas[tabName] || "";

    // Sync visual <-> html

    var visual = byId("editor-visual");
    var htmlArea = byId("editor-html");

    if (tabName === "html" && visual && htmlArea) {
      htmlArea.value = visual.innerHTML;
    } else if (tabName === "visual" && visual && htmlArea) {
      visual.innerHTML = htmlArea.value;
      window.atualizarTextarea();
    }

  };

  // -------------------------
  // Slug
  // -------------------------
  window.gerarSlug = function gerarSlug() {
    var tituloEl = byId("titulo");
    var slugEl = byId("slug");
    if (!tituloEl || !slugEl) return;

    var titulo = tituloEl.value || "";
    if (!titulo) return;

    var slug = titulo.toLowerCase();
    try {
      slug = slug.normalize("NFD").replace(/[\u0300-\u036f]/g, "");
    } catch (e) {}
    slug = slug.replace(/[^a-z0-9]+/g, "-").replace(/^-+|-+$/g, "");
    slugEl.value = slug;
  };

  // -------------------------
  // Toolbar / editor
  // -------------------------
  window.formatar = function formatar(command, value) {
    if (!existsEditor()) return;

    try {
      document.execCommand(command, false, value != null ? value : null);
    } catch (e) {}

    var visual = byId("editor-visual");
    if (visual) visual.focus();

    window.atualizarTextarea();
  };

  window.inserirLink = function inserirLink() {
    if (!existsEditor()) return;

    var url = window.prompt("Digite a URL:");
    if (!url) return;

    try {
      document.execCommand("createLink", false, url);
    } catch (e) {}

    window.atualizarTextarea();
  };

  window.limparFormatacao = function limparFormatacao() {
    if (!existsEditor()) return;

    try {
      document.execCommand("removeFormat", false, null);
      document.execCommand("unlink", false, null);
    } catch (e) {}

    window.atualizarTextarea();
  };

  window.atualizarTextarea = function atualizarTextarea() {
    if (!existsEditor()) return;

    var editor = byId("editor-visual");
    var html = editor ? editor.innerHTML : "";

    var hidden = byId("conteudoHidden");
    if (hidden) hidden.value = html;

    var htmlArea = byId("editor-html");
    if (htmlArea) htmlArea.value = html;

    // Word count
    var texto = editor ? (editor.innerText || "") : "";
    texto = texto.trim();
    var palavras = texto ? texto.split(/\s+/).filter(function (w) { return w.length > 0; }).length : 0;

    var wc = byId("wordCount");
    if (wc) wc.textContent = palavras + " palavra" + (palavras !== 1 ? "s" : "");
  };

  window.syncFromHtml = function syncFromHtml() {
    if (!existsEditor()) return;

    var htmlArea = byId("editor-html");
    var html = htmlArea ? htmlArea.value : "";

    var visual = byId("editor-visual");
    if (visual) visual.innerHTML = html;

    var hidden = byId("conteudoHidden");
    if (hidden) hidden.value = html;

    // Word count
    var texto = visual ? (visual.innerText || "") : "";
    texto = texto.trim();
    var palavras = texto ? texto.split(/\s+/).filter(function (w) { return w.length > 0; }).length : 0;

    var wc = byId("wordCount");
    if (wc) wc.textContent = palavras + " palavra" + (palavras !== 1 ? "s" : "");
  };

  // -------------------------
  // Categoria / destaque
  // -------------------------
  window.syncDestaqueToggle = function syncDestaqueToggle() {
    var input = document.querySelector('input[name="destaque"]');
    if (!input) return;

    var toggle = input.closest('.post-destaque-toggle');
    var state = toggle ? toggle.querySelector('.post-destaque-toggle-state') : null;
    if (!toggle) return;

    toggle.classList.toggle('is-active', Boolean(input.checked));
    if (state) {
      state.textContent = input.checked ? 'Ativo' : 'Desligado';
    }
  };

  window.syncCategoriaIndicator = function syncCategoriaIndicator() {
    var categoriaSelect = byId("categoria_post_id");
    var dot = byId("categoria-indicator-dot");
    var label = byId("categoria-indicator-label");
    var indicator = byId("categoria-indicator");
    var state = byId("categoria-indicator-state");
    if (!categoriaSelect || !dot || !label) return;

    var option = categoriaSelect.options[categoriaSelect.selectedIndex] || null;
    var hasCategoria = Boolean(option && option.value && option.value !== "0");
    var color = option && option.getAttribute("data-category-color") ? option.getAttribute("data-category-color") : "#475569";
    var text = hasCategoria ? option.textContent.trim() : "Nenhuma categoria selecionada";

    dot.style.background = color || "#475569";
    label.textContent = text;

    if (indicator) {
      indicator.classList.toggle("is-active", hasCategoria);
    }

    if (state) {
      state.textContent = hasCategoria ? "Selecionada" : "Pendente";
    }
  };

  function appBasePath() {
    var path = String(window.location.pathname || "");
    var adminIndex = path.indexOf("/admin/");
    if (adminIndex !== -1) {
      return path.slice(0, adminIndex);
    }
    if (path.endsWith("/admin")) {
      return path.slice(0, -6);
    }
    return "";
  }

  function normalizeMediaUrl(value) {
    var raw = String(value || "").trim();
    if (!raw) return "";
    if (/^https?:\/\//i.test(raw)) return raw;
    if (raw.indexOf("//") === 0) return window.location.protocol + raw;

    var base = appBasePath();
    if (raw.charAt(0) === "/") {
      if (base !== "" && raw.indexOf(base + "/") !== 0) {
        return window.location.origin + base + raw;
      }
      return window.location.origin + raw;
    }

    return window.location.origin + (base !== "" ? base : "") + "/" + raw.replace(/^\/+/, "");
  }

  function normalizePreviewContent(html) {
    var wrapper = document.createElement("div");
    wrapper.innerHTML = String(html || "");

    wrapper.querySelectorAll("img, iframe, video, source, a").forEach(function (node) {
      if (node.hasAttribute("src")) {
        node.setAttribute("src", normalizeMediaUrl(node.getAttribute("src")));
      }
      if (node.hasAttribute("href")) {
        node.setAttribute("href", normalizeMediaUrl(node.getAttribute("href")));
      }
      if (node.hasAttribute("poster")) {
        node.setAttribute("poster", normalizeMediaUrl(node.getAttribute("poster")));
      }
    });

    wrapper.querySelectorAll(".en-audio-block").forEach(function (block) {
      ["data-audio-narracao", "data-audio-ambiente"].forEach(function (attr) {
        if (block.hasAttribute(attr)) {
          block.setAttribute(attr, normalizeMediaUrl(block.getAttribute(attr)));
        }
      });
    });

    return wrapper.innerHTML;
  }

  function initPreviewAudioBlocks(root) {
    var scope = root || document;
    var blocks = Array.prototype.slice.call(scope.querySelectorAll(".en-audio-block"));
    var active = null;

    function stopActive() {
      if (!active) return;
      try { if (active.narracao) { active.narracao.pause(); active.narracao.currentTime = 0; } } catch (e) {}
      try { if (active.ambiente) { active.ambiente.pause(); active.ambiente.currentTime = 0; } } catch (e) {}
      if (active.button) {
        active.button.textContent = active.initialText || "Ouvir narracao";
        active.button.removeAttribute("aria-pressed");
      }
      if (active.block) active.block.classList.remove("is-playing");
      active = null;
    }

    blocks.forEach(function (block) {
      var button = block.querySelector("[data-en-audio-toggle]");
      if (!button || button.dataset.previewAudioBound === "1") return;
      button.dataset.previewAudioBound = "1";

      var narracaoSrc = normalizeMediaUrl(block.getAttribute("data-audio-narracao") || "");
      var ambienteSrc = normalizeMediaUrl(block.getAttribute("data-audio-ambiente") || "");
      var initialText = button.textContent || "Ouvir narracao";
      if (!narracaoSrc && !ambienteSrc) {
        button.textContent = "Audio indisponivel";
        button.disabled = true;
        return;
      }

      var narracao = narracaoSrc ? new Audio(narracaoSrc) : null;
      var ambiente = ambienteSrc ? new Audio(ambienteSrc) : null;
      if (ambiente) ambiente.loop = true;

      if (narracao) {
        narracao.addEventListener("ended", function () {
          try { if (ambiente) { ambiente.pause(); ambiente.currentTime = 0; } } catch (e) {}
          button.textContent = initialText;
          button.removeAttribute("aria-pressed");
          block.classList.remove("is-playing");
          active = null;
        });
      }

      button.addEventListener("click", function () {
        var isCurrent = active && active.block === block;
        var isPlaying = isCurrent && ((narracao && !narracao.paused) || (ambiente && !ambiente.paused));
        if (isPlaying) {
          stopActive();
          return;
        }
        if (active && !isCurrent) stopActive();

        Promise.resolve()
          .then(function () {
            if (narracao) narracao.volume = 1;
            if (ambiente) ambiente.volume = narracao ? 0.12 : 0.35;
            if (narracao) {
              return narracao.play().then(function () {
                if (ambiente) ambiente.play().catch(function () {});
              });
            }
            return ambiente ? ambiente.play() : null;
          })
          .then(function () {
            button.textContent = "Pausar";
            button.setAttribute("aria-pressed", "true");
            block.classList.add("is-playing");
            active = { block: block, button: button, narracao: narracao, ambiente: ambiente, initialText: initialText };
          })
          .catch(function () {
            stopActive();
            button.textContent = "Audio indisponivel";
          });
      });
    });
  }

  function initPreviewVideos(root) {
    var scope = root || document;
    scope.querySelectorAll("video").forEach(function (video) {
      try { video.load(); } catch (e) {}
    });
  }

  function initPreviewMedia(root) {
    initPreviewAudioBlocks(root);
    initPreviewVideos(root);
  }

  function escapeHtml(value) {
    return String(value || "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  function renderHighlightedTitle(value) {
    var raw = String(value || "").trim();
    if (!raw) return "Sem titulo";

    return escapeHtml(raw).replace(/\[\[(.+?)\]\]/g, '<span class="post-preview-title-accent">$1</span>');
  }

  function syncMediaPreview(targetId) {
    var input = byId(targetId);
    var wrap = byId(targetId + "_preview_wrap");
    var preview = byId(targetId + "_preview");
    var empty = byId(targetId + "_preview_empty");
    var trigger = wrap ? wrap.querySelector("[data-media-preview-open]") : null;
    if (!input || !wrap || !preview || !trigger) return;

    var rawPath = String(input.value || "").trim();
    var url = normalizeMediaUrl(rawPath);
    var titles = {
      imagem_capa: "Imagem de capa",
      imagem_thumb: "Thumbnail"
    };

    trigger.setAttribute("data-media-preview-src", url);
    trigger.setAttribute("data-media-preview-title", titles[targetId] || "Preview da imagem");
    trigger.setAttribute("data-media-preview-path", rawPath);
    trigger.disabled = !url;
    trigger.classList.toggle("is-disabled", !url);
    wrap.classList.toggle("has-media", Boolean(url));

    if (url) {
      preview.src = url;
      preview.classList.remove("hidden");
      if (empty) empty.classList.add("hidden");
    } else {
      preview.src = "";
      preview.classList.add("hidden");
      if (empty) empty.classList.remove("hidden");
    }
  }

  window.selecionarMidia = function selecionarMidia(targetId, url) {
    var input = byId(targetId);
    if (!input) return;
    input.value = url || "";
    syncMediaPreview(targetId);
  };

  window.inserirImagem = function inserirImagem() {
    if (!existsEditor()) return;

    var url = window.prompt("Cole a URL/caminho da imagem:");
    if (!url) return;

    var alt = window.prompt("Texto alternativo (opcional):") || "";
    var legenda = window.prompt("Legenda (opcional):") || "";
    var html = '<figure><img src="' + url.replace(/"/g, "&quot;") + '" alt="' + alt.replace(/"/g, "&quot;") + '">' + (legenda ? '<figcaption>' + legenda + '</figcaption>' : '') + '</figure>';

    try {
      document.execCommand("insertHTML", false, html);
    } catch (e) {
      var visual = byId("editor-visual");
      if (visual) visual.innerHTML += html;
    }

    window.atualizarTextarea();
  };

  function toYoutubeEmbed(url) {
    var match = String(url || "").match(/(?:youtu\.be\/|youtube\.com\/(?:watch\?v=|embed\/|shorts\/))([A-Za-z0-9_-]{6,})/i);
    return match ? "https://www.youtube.com/embed/" + match[1] : "";
  }

  window.inserirVideo = function inserirVideo() {
    if (!existsEditor()) return;

    var url = window.prompt("Cole a URL do video (YouTube ou arquivo proprio):");
    if (!url) return;

    var embed = toYoutubeEmbed(url);
    var html = embed
      ? '<div class="content-block content-block-video"><div class="content-block-label">Video</div><div class="aspect-video"><iframe src="' + embed + '" title="Video incorporado" loading="lazy" allowfullscreen></iframe></div></div>'
      : '<video controls preload="metadata" style="width:100%;border-radius:16px;overflow:hidden;"><source src="' + url.replace(/"/g, "&quot;") + '"></video>';

    try {
      document.execCommand("insertHTML", false, html);
    } catch (e) {
      var visual = byId("editor-visual");
      if (visual) visual.innerHTML += html;
    }

    window.atualizarTextarea();
  };

  window.abrirPreviewMidiaPost = function abrirPreviewMidiaPost(src, title, path) {
    var panel = byId("postMediaPreviewPanel");
    var image = byId("postMediaPreviewImage");
    var titleEl = byId("postMediaPreviewTitle");
    var pathEl = byId("postMediaPreviewPath");
    if (!src || !panel || !image) return;

    image.src = src || "";
    if (titleEl) titleEl.textContent = title || "Preview da imagem";
    if (pathEl) pathEl.textContent = path || "";
    panel.classList.remove("hidden");
    panel.scrollIntoView({ behavior: "smooth", block: "nearest" });
  };

  window.fecharPreviewMidiaPost = function fecharPreviewMidiaPost() {
    var panel = byId("postMediaPreviewPanel");
    var image = byId("postMediaPreviewImage");
    if (!panel || !image) return;

    panel.classList.add("hidden");
    image.src = "";
  };

  function initQuickMediaSearch() {
    var input = byId("postMediaQuickSearch");
    var originSelect = byId("postMediaQuickOrigin");
    var usageSelect = byId("postMediaQuickUsage");
    if (!input) return;

    var items = Array.prototype.slice.call(document.querySelectorAll("[data-post-media-item]"));
    var emptyState = byId("postMediaQuickEmpty");
    if (!items.length) return;

    var normalize = function (value) {
      return String(value || "").toLocaleLowerCase("pt-BR").trim();
    };

    var applyFilter = function () {
      var term = normalize(input.value);
      var origin = normalize(originSelect ? originSelect.value : "all");
      var usage = normalize(usageSelect ? usageSelect.value : "all");
      var visibleCount = 0;

      items.forEach(function (item) {
        var haystack = normalize(item.getAttribute("data-post-media-search") || "");
        var itemOrigin = normalize(item.getAttribute("data-post-media-origin") || "library");
        var itemUsage = normalize(item.getAttribute("data-post-media-usage") || "");
        var matchesTerm = !term || haystack.indexOf(term) !== -1;
        var matchesOrigin = origin === "all" || itemOrigin === origin;
        var matchesUsage = usage === "all" || itemUsage.split(",").indexOf(usage) !== -1;
        var visible = matchesTerm && matchesOrigin && matchesUsage;
        item.classList.toggle("hidden", !visible);
        if (visible) visibleCount += 1;
      });

      if (emptyState) {
        emptyState.classList.toggle("hidden", visibleCount > 0);
      }
    };

    input.addEventListener("input", applyFilter);
    if (originSelect) originSelect.addEventListener("change", applyFilter);
    if (usageSelect) usageSelect.addEventListener("change", applyFilter);
    applyFilter();
  }

  function initMediaHelpers() {
    ["imagem_capa", "imagem_thumb"].forEach(function (targetId) {
      var input = byId(targetId);
      if (!input) return;
      input.addEventListener("input", function () {
        syncMediaPreview(targetId);
      });
      syncMediaPreview(targetId);
    });

    document.querySelectorAll("[data-media-pick]").forEach(function (button) {
      button.addEventListener("click", function () {
        var target = button.getAttribute("data-media-target") || "";
        var url = button.getAttribute("data-media-url") || "";
        window.selecionarMidia(target, url);
      });
    });

    document.querySelectorAll("[data-media-preview-open]").forEach(function (trigger) {
      trigger.addEventListener("click", function () {
        var src = trigger.getAttribute("data-media-preview-src") || "";
        var title = trigger.getAttribute("data-media-preview-title") || "Preview da imagem";
        var path = trigger.getAttribute("data-media-preview-path") || "";
        window.abrirPreviewMidiaPost(src, title, path);
      });
    });

    initQuickMediaSearch();
  }

  function getCsrfToken() {
    var token = document.querySelector('input[name="_csrf_token"]');
    return token ? token.value : "";
  }

  function getPostSlugBase() {
    var slugEl = byId("slug");
    var titleEl = byId("titulo");
    return (slugEl && slugEl.value) ? slugEl.value : ((titleEl && titleEl.value) ? titleEl.value : "");
  }

  function insertHtmlIntoEditor(html) {
    try {
      document.execCommand("insertHTML", false, html);
    } catch (e) {
      var visual = byId("editor-visual");
      if (visual) visual.innerHTML += html;
    }
    window.atualizarTextarea();
  }

  async function optimizeImageUploadFile(file, options) {
    if (!(file instanceof File)) return file;

    var type = String(file.type || "").toLowerCase();
    if (["image/gif", "image/svg+xml", "image/webp"].indexOf(type) !== -1) {
      return file;
    }

    if (["image/jpeg", "image/png"].indexOf(type) === -1) {
      return file;
    }

    var source = await loadImageSource(file);
    var maxWidth = options && options.maxWidth ? options.maxWidth : 1600;
    var maxHeight = options && options.maxHeight ? options.maxHeight : 1600;
    var quality = options && typeof options.quality === "number" ? options.quality : 0.84;
    var cropAspect = options && options.cropAspect ? options.cropAspect : 0;
    var targetWidth = options && options.targetWidth ? options.targetWidth : maxWidth;
    var targetHeight = options && options.targetHeight ? options.targetHeight : maxHeight;

    var canvas = document.createElement("canvas");
    var ctx = canvas.getContext("2d");
    if (!ctx) {
      releaseImageSource(source);
      return file;
    }

    if (cropAspect > 0) {
      canvas.width = targetWidth;
      canvas.height = targetHeight;

      var sourceRatio = source.width / source.height;
      var targetRatio = targetWidth / targetHeight;
      var sx = 0;
      var sy = 0;
      var sw = source.width;
      var sh = source.height;

      if (sourceRatio > targetRatio) {
        sw = Math.round(source.height * targetRatio);
        sx = Math.max(0, Math.round((source.width - sw) / 2));
      } else if (sourceRatio < targetRatio) {
        sh = Math.round(source.width / targetRatio);
        sy = Math.max(0, Math.round((source.height - sh) / 2));
      }

      ctx.drawImage(source.node, sx, sy, sw, sh, 0, 0, targetWidth, targetHeight);
    } else {
      var fitted = fitImageSize(source.width, source.height, maxWidth, maxHeight);
      canvas.width = fitted.width;
      canvas.height = fitted.height;
      ctx.drawImage(source.node, 0, 0, fitted.width, fitted.height);
    }

    releaseImageSource(source);

    var blob = await new Promise(function (resolve) {
      canvas.toBlob(resolve, "image/webp", quality);
    });

    if (!(blob instanceof Blob)) {
      return file;
    }

    return new File([blob], renameFileToWebp(file.name || "imagem.webp"), {
      type: "image/webp",
      lastModified: Date.now(),
    });
  }

  async function loadImageSource(file) {
    if ("createImageBitmap" in window) {
      try {
        var bitmap = await createImageBitmap(file);
        return {
          node: bitmap,
          width: bitmap.width,
          height: bitmap.height,
          close: function () { bitmap.close(); },
        };
      } catch (error) {}
    }

    return await new Promise(function (resolve, reject) {
      var image = new Image();
      var objectUrl = URL.createObjectURL(file);
      image.onload = function () {
        URL.revokeObjectURL(objectUrl);
        resolve({
          node: image,
          width: image.naturalWidth || image.width,
          height: image.naturalHeight || image.height,
          close: null,
        });
      };
      image.onerror = function () {
        URL.revokeObjectURL(objectUrl);
        reject(new Error("Falha ao carregar a imagem."));
      };
      image.src = objectUrl;
    });
  }

  function releaseImageSource(source) {
    if (source && typeof source.close === "function") {
      source.close();
    }
  }

  function fitImageSize(width, height, maxWidth, maxHeight) {
    if (!width || !height) {
      return { width: width || maxWidth, height: height || maxHeight };
    }

    var ratio = Math.min(maxWidth / width, maxHeight / height, 1);
    return {
      width: Math.max(1, Math.round(width * ratio)),
      height: Math.max(1, Math.round(height * ratio)),
    };
  }

  function renameFileToWebp(name) {
    return String(name || "imagem").replace(/\.[^.]+$/, "") + ".webp";
  }

  function replaceInputFile(input, file) {
    if (!input || !(file instanceof File) || typeof DataTransfer === "undefined") {
      return;
    }

    var transfer = new DataTransfer();
    transfer.items.add(file);
    input.files = transfer.files;
  }

  async function optimizePostMediaInputs(form) {
    var map = {
      imagem_capa_upload: { cropAspect: 16 / 9, targetWidth: 1600, targetHeight: 900, quality: 0.84 },
      imagem_thumb_upload: { cropAspect: 16 / 9, targetWidth: 640, targetHeight: 360, quality: 0.82 },
    };

    for (var field in map) {
      if (!Object.prototype.hasOwnProperty.call(map, field)) continue;
      var input = form.querySelector('[name="' + field + '"]');
      var file = input && input.files ? input.files[0] : null;
      if (!file) continue;

      var optimized = await optimizeImageUploadFile(file, map[field]);
      if (optimized !== file) {
        replaceInputFile(input, optimized);
      }
    }
  }

  window.__optimizeImageUploadFile = optimizeImageUploadFile;

  function uploadInlineEditorImage(file) {
    if (!file) return;

    var csrf = getCsrfToken();
    var slugBase = getPostSlugBase();
    if (!slugBase) {
      window.alert("Informe um titulo ou slug antes de enviar a imagem do conteudo.");
      return;
    }

    Promise.resolve(optimizeImageUploadFile(file, { maxWidth: 1600, maxHeight: 1600, quality: 0.84 }))
      .catch(function () {
        return file;
      })
      .then(function (processedFile) {
        var data = new window.FormData();
        data.append("_csrf_token", csrf);
        data.append("titulo", byId("titulo") ? byId("titulo").value : "");
        data.append("slug", byId("slug") ? byId("slug").value : "");
        data.append("imagem", processedFile);

        return window.fetch("/estrategia-nerd/public/admin/upload-post-imagem", {
          method: "POST",
          body: data,
          credentials: "same-origin"
        });
      })
      .then(function (response) {
        return response.json().catch(function () {
          return { ok: false, error: "Nao foi possivel interpretar a resposta do upload." };
        });
      })
      .then(function (payload) {
        if (!payload || payload.ok !== true) {
          window.alert(payload && payload.error ? payload.error : "Falha no upload da imagem.");
          return;
        }

        var alt = window.prompt("Texto alternativo da imagem (opcional):") || "";
        var legenda = window.prompt("Legenda da imagem (opcional):") || "";
        var html = '<figure><img src="' + String(payload.url || "").replace(/"/g, "&quot;") + '" alt="' + alt.replace(/"/g, "&quot;") + '">' + (legenda ? '<figcaption>' + legenda + '</figcaption>' : '') + '</figure>';
        insertHtmlIntoEditor(html);
      })
      .catch(function () {
        window.alert("Falha ao enviar a imagem do conteudo.");
      });
  }

  window.enviarImagemDoEditor = function enviarImagemDoEditor() {
    var input = byId("editorImageUpload");
    if (!input) return;
    input.click();
  };

  // -------------------------
  // Contadores (resumo/seo)
  // -------------------------
  function initCounts() {
    var resumoField = byId("resumo");
    var resumoCount = byId("resumoCount");
    if (resumoField && resumoCount) {
      var updateResumo = function () {
        resumoCount.textContent = String(resumoField.value.length);
      };
      resumoField.addEventListener("input", updateResumo);
      updateResumo();
    }

    var seoTitleField = byId("seo_title");
    var seoDescField = byId("seo_description");
    var seoTitleCount = byId("seoTitleCount");
    var seoDescCount = byId("seoDescCount");

    function updateSeoCounts() {
      if (seoTitleField && seoTitleCount) seoTitleCount.textContent = String(seoTitleField.value.length);
      if (seoDescField && seoDescCount) seoDescCount.textContent = String(seoDescField.value.length);
    }

    if (seoTitleField) seoTitleField.addEventListener("input", updateSeoCounts);
    if (seoDescField) seoDescField.addEventListener("input", updateSeoCounts);
    updateSeoCounts();
  }

  // -------------------------
  // Preview modal (igual ao editar-post)
  // -------------------------
  window.abrirPreview = function abrirPreview() {
    if (!existsEditor()) return;

    window.atualizarTextarea();

    var tituloEl = byId("titulo");
    var titulo = (tituloEl && tituloEl.value) ? tituloEl.value : "Sem titulo";
    titulo = renderHighlightedTitle(titulo);
    var visual = byId("editor-visual");
    var conteudo = visual ? normalizePreviewContent(visual.innerHTML) : "";

    var categoriaSelect = byId("categoria_post_id");
    var categoriaNome = "Geral";
    if (categoriaSelect && categoriaSelect.selectedIndex >= 0) {
      var categoriaOption = categoriaSelect.options[categoriaSelect.selectedIndex];
      if (categoriaOption && categoriaOption.value && categoriaOption.textContent) {
        categoriaNome = categoriaOption.textContent.trim();
      }
    }

    var previewContent = byId("previewContent");
    if (previewContent) {
      previewContent.innerHTML =
        '<style>' +
          '#previewContent .preview-article{color:#cbd5e1;line-height:1.85;}' +
          '#previewContent .preview-title{font-family:Orbitron,sans-serif;font-size:2.2rem;line-height:1.12;font-weight:900;color:#fff;margin:0 0 1.35rem;}' +
          '#previewContent .post-preview-title-accent{background:linear-gradient(90deg,#38bdf8 0%,#60a5fa 34%,#8b5cf6 68%,#22d3ee 100%);-webkit-background-clip:text;background-clip:text;color:transparent;}' +
          '#previewContent .preview-chip{display:inline-flex;align-items:center;padding:.35rem .75rem;background:#00d4ff;color:#020617;font-size:.72rem;font-weight:900;border-radius:999px;text-transform:uppercase;letter-spacing:.08em;}' +
          '#previewContent h2{font-family:Orbitron,sans-serif;font-size:2rem;font-weight:700;color:#22d3ee;margin:2.3rem 0 1.2rem;padding-bottom:.55rem;border-bottom:2px solid rgba(34,211,238,.3);}' +
          '#previewContent h3{font-family:Orbitron,sans-serif;font-size:1.4rem;font-weight:700;color:#c084fc;margin:1.8rem 0 .9rem;}' +
          '#previewContent p{margin:0 0 1.25rem;}' +
          '#previewContent ul,#previewContent ol{margin:0 0 1.35rem;}' +
          '#previewContent ul{list-style:none;padding-left:.35rem;}' +
          '#previewContent ul li{position:relative;padding-left:1.35rem;}' +
          '#previewContent ul li::before{content:"✦";position:absolute;left:0;top:.03rem;color:#22d3ee;font-size:.85em;}' +
          '#previewContent ol{padding-left:1.45rem;}' +
          '#previewContent li{margin-bottom:.6rem;}' +
          '#previewContent blockquote{margin:1.7rem 0;padding:1.2rem 1.35rem;border-left:4px solid #22d3ee;border-radius:0 .9rem .9rem 0;background:linear-gradient(135deg,rgba(34,211,238,.08),rgba(15,23,42,.55));color:#cbd5e1;}' +
          '#previewContent img{display:block;width:auto;max-width:100%;max-height:56vh;height:auto;border-radius:12px;margin:0 auto;border:1px solid rgba(34,211,238,.2);}' +
          '#previewContent figure{margin:1.8rem auto;max-width:min(100%,760px);display:flex;flex-direction:column;align-items:center;text-align:center;}' +
          '#previewContent figcaption{margin-top:.75rem;color:#94a3b8;font-size:.9rem;font-style:italic;}' +
          '#previewContent iframe,#previewContent video{display:block;width:100%;min-height:320px;border:0;border-radius:16px;margin:1.8rem 0;}' +
          '#previewContent .content-grid-two{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem;margin:1.4rem 0;}' +
          '#previewContent .content-block{border-radius:1rem;border:1px solid rgba(34,211,238,.22);background:linear-gradient(145deg,rgba(15,23,42,.88),rgba(2,6,23,.84));padding:1rem 1.1rem;box-shadow:0 12px 28px rgba(2,6,23,.24);}' +
          '#previewContent .content-block > *:last-child{margin-bottom:0 !important;}' +
          '#previewContent .content-block-label{margin:0 0 .75rem;color:#67e8f9;font-family:Orbitron,sans-serif;font-size:.95rem;font-weight:800;letter-spacing:.02em;text-transform:uppercase;}' +
          '#previewContent .content-block-note{border-color:rgba(34,211,238,.28);background:linear-gradient(145deg,rgba(6,78,92,.32),rgba(2,6,23,.84));}' +
          '#previewContent .content-block-highlight{border-color:rgba(56,189,248,.3);background:linear-gradient(145deg,rgba(12,74,110,.35),rgba(2,6,23,.84));}' +
          '#previewContent .content-block-success{border-color:rgba(16,185,129,.3);background:linear-gradient(145deg,rgba(6,78,59,.28),rgba(2,6,23,.84));}' +
          '#previewContent .content-block-warning{border-color:rgba(245,158,11,.3);background:linear-gradient(145deg,rgba(120,53,15,.3),rgba(2,6,23,.84));}' +
          '#previewContent .content-block-image{padding:0;overflow:hidden;}' +
          '#previewContent .content-block-image .content-block-label{padding:1rem 1rem 0;margin-bottom:.35rem;}' +
          '#previewContent .content-block-image figure{margin:0;max-width:100%;}' +
          '#previewContent .content-block-video .aspect-video{position:relative;padding-top:56.25%;border-radius:.75rem;overflow:hidden;background:#0f172a;}' +
          '#previewContent .content-block-video iframe{position:absolute;inset:0;width:100%;height:100%;margin:0;border-radius:0;min-height:0;}' +
          '#previewContent .content-block-table{padding:0;overflow:hidden;}' +
          '#previewContent .content-block-table .content-block-label{padding:1rem 1rem 0;}' +
          '#previewContent .content-block-faq h3{margin-top:0;}' +
          '#previewContent .en-audio-block{background:linear-gradient(180deg,rgba(18,16,24,.96),rgba(8,10,18,.96));border:1px solid rgba(103,232,249,.22);border-radius:16px;padding:18px 18px 16px;margin:18px 0 22px;box-shadow:0 0 22px rgba(0,0,0,.32);}' +
          '#previewContent .en-audio-header{display:flex;align-items:center;gap:10px;margin-bottom:8px;color:rgba(248,250,252,.95);}' +
          '#previewContent .en-audio-title{font-family:Orbitron,sans-serif;font-weight:900;letter-spacing:.06em;text-transform:uppercase;font-size:.95rem;color:rgba(165,243,252,.95);}' +
          '#previewContent .en-audio-subtitle{margin:0 0 14px;color:rgba(226,232,240,.92);font-style:italic;line-height:1.6;}' +
          '#previewContent .en-audio-button{display:inline-flex;align-items:center;gap:10px;border-radius:10px;border:1px solid rgba(251,191,36,.28);background:linear-gradient(180deg,rgba(59,36,28,.92),rgba(22,13,12,.92));color:rgba(255,237,213,.95);padding:10px 16px;cursor:pointer;font-weight:800;font-size:.95rem;}' +
          '#previewContent .en-audio-block.is-playing{border-color:rgba(34,211,238,.36);box-shadow:0 0 26px rgba(34,211,238,.12),0 0 22px rgba(0,0,0,.34);}' +
          '#previewContent table{width:100%;border-collapse:collapse;margin:1.5rem 0;background:rgba(15,23,42,.82);}' +
          '#previewContent th,#previewContent td{border:1px solid rgba(51,65,85,.8);padding:12px 14px;text-align:left;vertical-align:top;}' +
          '#previewContent th{background:rgba(30,41,59,.55);}' +
          '@media (max-width: 760px){#previewContent .content-grid-two{grid-template-columns:1fr;}}' +
        '</style>' +
        '<div class="preview-article">' +
          '<div class="mb-4"><span class="preview-chip">' + categoriaNome + '</span></div>' +
          '<h1 class="preview-title">' + titulo + '</h1>' +
          '<div>' + (conteudo || "<p><em>Sem conteudo.</em></p>") + '</div>' +
        '</div>';

      initPreviewMedia(previewContent);
    }

    var modal = byId("previewModal");
    if (modal) modal.classList.add("active");

    document.body.style.overflow = "hidden";
  };

  window.fecharPreview = function fecharPreview(e) {
    var modal = byId("previewModal");
    if (!modal) return;

    if (!e || (e.target && e.target.id === "previewModal")) {
      modal.classList.remove("active");
      document.body.style.overflow = "";
    }
  };

  var formTracker = {
    initialState: "",
    submitting: false,
  };

  window.__postEditorAllowUnload = window.__postEditorAllowUnload === true;

  function serializeFormState(form) {
    if (!form) return "";

    window.atualizarTextarea();

    var data = new window.FormData(form);
    data.delete("_csrf_token");
    var pairs = [];
    data.forEach(function (value, key) {
      pairs.push([key, String(value)]);
    });

    pairs.sort(function (a, b) {
      if (a[0] === b[0]) {
        return a[1] < b[1] ? -1 : (a[1] > b[1] ? 1 : 0);
      }
      return a[0] < b[0] ? -1 : 1;
    });

    return pairs.map(function (pair) {
      return pair[0] + "=" + pair[1];
    }).join("&");
  }

  function hasUnsavedChanges(form) {
    if (!form) return false;
    return serializeFormState(form) !== formTracker.initialState;
  }

  function confirmUnsavedLeave() {
    var title = "Sair sem salvar";
    var message = "Existem alteracoes nao salvas. Deseja sair mesmo assim?";

    if (window.adminUi && typeof window.adminUi.confirm === "function") {
      return window.adminUi.confirm({
        title: title,
        subtitle: "Editor de post",
        message: '<p class="text-sm leading-6 text-slate-300">' + escapeHtml(message) + "</p>",
        submitLabel: "Sair sem salvar",
        cancelLabel: "Continuar editando",
        destructive: true
      });
    }

    return new Promise(function (resolve) {
      var host = document.createElement("div");
      host.className = "fixed inset-0 z-[10020] flex items-center justify-center px-4 py-8";
      host.innerHTML =
        '<div class="absolute inset-0 bg-slate-950/80 backdrop-blur-sm"></div>' +
        '<div class="relative w-full max-w-lg rounded-3xl border border-cyan-500/20 bg-slate-950 shadow-2xl shadow-cyan-500/10">' +
          '<div class="border-b border-slate-800/70 px-6 py-5">' +
            '<div class="font-orbitron text-lg font-black text-white">' + escapeHtml(title) + "</div>" +
          "</div>" +
          '<div class="px-6 py-5 text-sm leading-6 text-slate-300">' + escapeHtml(message) + "</div>" +
          '<div class="flex items-center justify-end gap-3 border-t border-slate-800/70 px-6 py-5">' +
            '<button type="button" data-inline-cancel class="admin-btn admin-btn-secondary">Continuar editando</button>' +
            '<button type="button" data-inline-confirm class="admin-btn admin-btn-primary !border-rose-500/40 !text-rose-200 hover:!bg-rose-500/10">Sair sem salvar</button>' +
          "</div>" +
        "</div>";

      var close = function (accepted) {
        host.remove();
        document.body.style.overflow = "";
        resolve(accepted);
      };

      document.body.appendChild(host);
      document.body.style.overflow = "hidden";

      var confirmButton = host.querySelector("[data-inline-confirm]");
      var cancelButton = host.querySelector("[data-inline-cancel]");
      var overlay = host.firstElementChild;

      if (confirmButton) confirmButton.addEventListener("click", function () { close(true); });
      if (cancelButton) cancelButton.addEventListener("click", function () { close(false); });
      if (overlay) overlay.addEventListener("click", function () { close(false); });
    });
  }

  function initUnsavedChangesGuard() {
    var form = byId("postForm");
    if (!form) return;

    var captureInitialState = function () {
      formTracker.initialState = serializeFormState(form);
    };

    captureInitialState();
    window.setTimeout(captureInitialState, 150);

    window.addEventListener("beforeunload", function (event) {
      if (window.__postEditorAllowUnload) return;
      if (formTracker.submitting || !hasUnsavedChanges(form)) return;
      event.preventDefault();
      event.returnValue = "";
    });

    document.querySelectorAll("[data-confirm-leave]").forEach(function (link) {
      link.addEventListener("click", function (event) {
        if (window.__postEditorAllowUnload) return;
        if (!hasUnsavedChanges(form) || formTracker.submitting) return;

        event.preventDefault();
        confirmUnsavedLeave().then(function (shouldLeave) {
          if (!shouldLeave) return;
          window.__postEditorAllowUnload = true;
          var href = link.getAttribute("href");
          if (!href) return;
          window.location.href = href;
        });
      });
    });
  }

  // -------------------------
  // Submit
  // -------------------------
  function initSubmitValidation() {
    var form = byId("postForm");
    if (!form) return;

    form.addEventListener("submit", function (event) {
      var submitter = event.submitter || null;
      if (submitter && submitter.name === "cleanup_orphan_images") {
        formTracker.submitting = true;
        window.__postEditorAllowUnload = true;
        return true;
      }

      if (form.dataset.optimized === "1") {
        window.atualizarTextarea();
        formTracker.submitting = true;

        var submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) {
          submitBtn.disabled = true;
          submitBtn.innerHTML = "Salvando...";
        }

        return true;
      }

      event.preventDefault();
      window.atualizarTextarea();

      var btn = form.querySelector('button[type="submit"]');
      if (btn) {
        btn.disabled = true;
        btn.innerHTML = "Otimizando...";
      }

      optimizePostMediaInputs(form)
        .catch(function () {
        })
        .then(function () {
          formTracker.submitting = true;
          form.dataset.optimized = "1";
          form.submit();
        });

      return false;
    });
  }

  // -------------------------
  // Boot
  // -------------------------
  function boot() {
    if (!existsEditor()) return;

    var ajuda = byId("editor-ajuda");
    if (ajuda) ajuda.textContent = "Use a barra acima para formatar.";
    initCounts();
    initMediaHelpers();
    initSubmitValidation();
    initUnsavedChangesGuard();
    syncDestaqueToggle();
    syncCategoriaIndicator();
    window.setTimeout(syncDestaqueToggle, 0);
    window.setTimeout(syncCategoriaIndicator, 0);
    window.setTimeout(syncDestaqueToggle, 150);
    window.setTimeout(syncCategoriaIndicator, 150);

    var destaqueInput = document.querySelector('input[name="destaque"]');
    if (destaqueInput) destaqueInput.addEventListener("change", syncDestaqueToggle);

    var categoriaSelect = byId("categoria_post_id");
    if (categoriaSelect) categoriaSelect.addEventListener("change", syncCategoriaIndicator);

    var editorUpload = byId("editorImageUpload");
    if (editorUpload) {
      editorUpload.addEventListener("change", function () {
        var file = editorUpload.files && editorUpload.files[0] ? editorUpload.files[0] : null;
        uploadInlineEditorImage(file);
        editorUpload.value = "";
      });
    }

    window.addEventListener("pageshow", function () {
      syncCategoriaIndicator();
      syncMediaPreview("imagem_capa");
      syncMediaPreview("imagem_thumb");
    });

    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape") {
        window.fecharPreview();
        if (window.fecharPreviewMidiaPost) window.fecharPreviewMidiaPost();
      }
    });

    window.atualizarTextarea();
    window.switchTab("visual");
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
  } else {
    boot();
  }
})();














