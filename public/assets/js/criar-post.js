/**
 * File: /assets/js/criar-post.js
 * Purpose: Editor (abas/toolbar/sync), preview e Gerador Nerd para /admin/pages/criar-post.php
 *
 * Notes:
 * - Este arquivo expÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Âµe funÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â§ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Âµes globais (switchTab, formatar, etc.) porque o HTML usa onclick="...".
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

    // Esconde todos os painÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â©is
    document.querySelectorAll(".editor-panel").forEach(function (p) {
      p.classList.add("hidden");
    });

    var panel = byId("panel-" + tabName);
    if (panel) panel.classList.remove("hidden");

    // BotÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Âµes de aba
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
      html: "Edite diretamente o cÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â³digo HTML.",
      gerador: "Preencha os campos e gere conteÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Âºdo automaticamente.",
    };

    var ajudaEl = byId("editor-ajuda");
    if (ajudaEl) ajudaEl.textContent = ajudas[tabName] || "";

    // Sync visual <-> html
    titulo = renderHighlightedTitle(titulo);

    var visual = byId("editor-visual");
    var htmlArea = byId("editor-html");

    if (tabName === "html" && visual && htmlArea) {
      htmlArea.value = visual.innerHTML;
    } else if (tabName === "visual" && visual && htmlArea) {
      visual.innerHTML = htmlArea.value;
      window.atualizarTextarea();
    }

    // Inicializa gerador quando entrar
    if (tabName === "gerador") {
      initGerador();
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
  // Categoria
  // -------------------------
  window.syncCategoriaIndicator = function syncCategoriaIndicator() {
    var categoriaSelect = byId("categoria_post_id");
    var dot = byId("categoria-indicator-dot");
    var label = byId("categoria-indicator-label");
    if (!categoriaSelect || !dot || !label) return;

    var option = categoriaSelect.options[categoriaSelect.selectedIndex] || null;
    var color = option && option.getAttribute("data-category-color") ? option.getAttribute("data-category-color") : "#475569";
    var text = option && option.value && option.value !== "0" ? option.textContent.trim() : "Nenhuma categoria selecionada";

    dot.style.background = color || "#475569";
    label.textContent = text;
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
    var preview = byId(targetId + "_preview");
    var empty = byId(targetId + "_preview_empty");
    if (!input || !preview) return;

    var url = normalizeMediaUrl(input.value);
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
    var titulo = (tituloEl && tituloEl.value) ? tituloEl.value : "Sem tÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â­tulo";

    var visual = byId("editor-visual");
    var conteudo = visual ? visual.innerHTML : "";

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
        '<style>.post-preview-title-accent{background:linear-gradient(90deg,#38bdf8 0%,#60a5fa 34%,#8b5cf6 68%,#22d3ee 100%);-webkit-background-clip:text;background-clip:text;color:transparent;}</style>' +
        '<div class="mb-4">' +
        '<span class="px-3 py-1 bg-cyan-500 text-slate-900 text-xs font-bold rounded-full uppercase">' +
        categoriaNome +
        "</span></div>" +
        '<h1 class="font-orbitron text-4xl font-bold text-white mb-6">' +
        titulo +
        "</h1>" +
        '<div class="prose prose-invert max-w-none text-gray-300 leading-relaxed">' +
        (conteudo || "<p><em>Sem conteÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Âºdo.</em></p>") +
        "</div>";
    }

    var modal = byId("previewModal");
    if (modal) modal.classList.add("active"); // IMPORTANT: usa 'active' como no editar-post

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

  // -------------------------
  // Gerador Nerd (compatÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â­vel com seu HTML do criar-post)
  // -------------------------
  var geradorIniciado = false;
  var geradorHtmlAtual = "";

  var TEMPLATES = {
    comparativo: {
      fields: [
        { id: "produto_a", label: "Produto A", placeholder: "Ex.: RTX 5070" },
        { id: "produto_b", label: "Produto B", placeholder: "Ex.: RX 9070" },
        { id: "contexto", label: "Contexto / objetivo", placeholder: "Ex.: 1440p, custo/beneficio..." },
        { id: "linhas", label: "Linhas da tabela", placeholder: "" },
        { id: "saida", label: "Formato", placeholder: "" },
      ],
      build: function (v) {
        var a = v.produto_a || "Produto A";
        var b = v.produto_b || "Produto B";
        var ctx = v.contexto || "";
        var formato = (v.saida || "ambos").toLowerCase();
        var rows = Array.isArray(v.linhas) ? v.linhas : [];
        rows = rows
          .map(function (row) {
            return {
              label: (row && row.label ? row.label : "").trim(),
              left: (row && row.left ? row.left : "").trim(),
              right: (row && row.right ? row.right : "").trim(),
            };
          })
          .filter(function (row) {
            return row.label !== "" || row.left !== "" || row.right !== "";
          });

        if (rows.length === 0) {
          rows = [
            { label: "Performance", left: "...", right: "..." },
            { label: "Consumo", left: "...", right: "..." },
            { label: "Preco", left: "...", right: "..." },
            { label: "Perfil ideal", left: "...", right: "..." },
          ];
        }

        var tableRows = rows.map(function (row) {
          return '' +
            '<tr>' +
            '<th style="border:1px solid rgba(51,65,85,0.8);padding:12px 14px;text-align:left;vertical-align:top;background:rgba(30,41,59,0.55);">' + row.label + '</th>' +
            '<td style="border:1px solid rgba(51,65,85,0.8);padding:12px 14px;vertical-align:top;">' + row.left + '</td>' +
            '<td style="border:1px solid rgba(51,65,85,0.8);padding:12px 14px;vertical-align:top;">' + row.right + '</td>' +
            '</tr>';
        }).join("");

        var topicRows = rows.map(function (row) {
          return "<li><b>" + row.label + ":</b> " + a + " - " + row.left + " | " + b + " - " + row.right + "</li>";
        }).join("");

        var compareTable =
          '<div class="content-block content-block-table">' +
          '<div class="content-block-label">Tabela comparativa</div>' +
          '<table class="nerd-comparison-table" style="width:100%;border-collapse:collapse;border:1px solid rgba(0,212,255,0.28);margin:1rem 0;background:rgba(15,23,42,0.82);">' +
          '<thead>' +
          '<tr>' +
          '<th style="border:1px solid rgba(0,212,255,0.22);padding:12px 14px;background:rgba(0,212,255,0.12);text-align:left;">Criterio</th>' +
          '<th style="border:1px solid rgba(0,212,255,0.22);padding:12px 14px;background:rgba(0,212,255,0.12);text-align:left;">' + a + '</th>' +
          '<th style="border:1px solid rgba(0,212,255,0.22);padding:12px 14px;background:rgba(0,212,255,0.12);text-align:left;">' + b + '</th>' +
          '</tr>' +
          '</thead>' +
          '<tbody>' + tableRows + '</tbody>' +
          '</table>' +
          '</div>';

        var quickSummary =
          '<div class="content-block content-block-note">' +
          '<div class="content-block-label">Resumo rapido</div>' +
          '<p>' + ctx + '</p>' +
          '</div>';

        var prosCons =
          '<div class="content-grid-two">' +
          '<div class="content-block content-block-success"><div class="content-block-label">Pontos fortes de ' + a + '</div><ul><li>...</li><li>...</li></ul></div>' +
          '<div class="content-block content-block-warning"><div class="content-block-label">Pontos fortes de ' + b + '</div><ul><li>...</li><li>...</li></ul></div>' +
          '</div>';

        var faq =
          '<div class="content-block content-block-faq">' +
          '<div class="content-block-label">FAQ rapido</div>' +
          '<h3>Qual entrega melhor custo-beneficio?</h3><p>...</p>' +
          '<h3>Qual faz mais sentido para o seu perfil?</h3><p>...</p>' +
          '</div>';

        var parts = [
          '<h2>' + a + ' vs ' + b + ': qual vale mais a pena?</h2>',
          ctx ? '<p>' + ctx + '</p>' : '',
          quickSummary,
        ];

        if (formato === 'tabela' || formato === 'ambos' || formato === '') {
          parts.push(compareTable);
        }

        if (formato === 'topicos' || formato === 'ambos' || formato === '') {
          parts.push('<h3>Comparativo em topicos</h3><ul>' + topicRows + '</ul>');
        }

        parts.push(prosCons);
        parts.push('<div class="content-block content-block-highlight"><div class="content-block-label">Veredito</div><p>Explique aqui qual vence no seu criterio principal e em qual cenario o outro ainda faz mais sentido.</p></div>');
        parts.push(faq);

        return parts.join('');
      },
    },
    review: {
      fields: [
        { id: "produto", label: "Produto", placeholder: "Ex.: Nintendo Switch 2" },
        { id: "pontos", label: "Pontos (vÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â­rgula)", placeholder: "Design, bateria, tela..." },
      ],
      build: function (v) {
        var p = v.produto || "Produto";
        var pts = (v.pontos || "")
          .split(",")
          .map(function (s) { return s.trim(); })
          .filter(Boolean)
          .map(function (s) { return "<li>" + s + " ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¦</li>"; })
          .join("");
        return (
          "<h2>Review: " + p + "</h2>" +
          "<p>VisÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â£o geral do produto e para quem ele faz sentido.</p>" +
          "<h3>Pontos principais</h3><ul>" + (pts || "<li>ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¦</li>") + "</ul>" +
          "<h3>PrÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â³s e contras</h3><ul><li><b>PrÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â³s:</b> ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¦</li><li><b>Contras:</b> ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¦</li></ul>" +
          "<h3>ConclusÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â£o</h3><p>ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¦</p>"
        );
      },
    },
    pros_contras: {
      fields: [
        { id: "produto", label: "Produto / tema", placeholder: "Ex.: RTX 5070" },
        { id: "contexto", label: "Contexto", placeholder: "Ex.: Para 1440p e ray tracing" },
        { id: "pros", label: "Pros (um por linha)", placeholder: "Bom desempenho\nDLSS forte\nConsumo equilibrado", type: "textarea", rows: 5 },
        { id: "contras", label: "Contras (um por linha)", placeholder: "Preco alto\nPouca VRAM\nEstoque limitado", type: "textarea", rows: 5 },
      ],
      build: function (v) {
        var produto = v.produto || "Produto";
        var contexto = v.contexto || "";
        var pros = (v.pros || "")
          .split(/\r?\n/)
          .map(function (s) { return s.trim(); })
          .filter(Boolean)
          .map(function (s) { return "<li>" + s + "</li>"; })
          .join("");
        var contras = (v.contras || "")
          .split(/\r?\n/)
          .map(function (s) { return s.trim(); })
          .filter(Boolean)
          .map(function (s) { return "<li>" + s + "</li>"; })
          .join("");

        return (
          "<h2>" + produto + ": pros e contras</h2>" +
          (contexto ? "<p>" + contexto + "</p>" : "") +
          '<div class="content-grid-two">' +
          '<div class="content-block content-block-success"><div class="content-block-label">Pros</div><ul>' + (pros || "<li>...</li>") + "</ul></div>" +
          '<div class="content-block content-block-warning"><div class="content-block-label">Contras</div><ul>' + (contras || "<li>...</li>") + "</ul></div>" +
          "</div>" +
          '<div class="content-block content-block-highlight"><div class="content-block-label">Vale a pena?</div><p>Explique aqui em qual cenario esse produto ou tema compensa mais.</p></div>'
        );
      },
    },
    faq: {
      fields: [
        { id: "tema", label: "Tema do FAQ", placeholder: "Ex.: PS5 Pro" },
        { id: "pergunta_1", label: "Pergunta 1", placeholder: "Ex.: Vale a pena comprar agora?" },
        { id: "pergunta_2", label: "Pergunta 2", placeholder: "Ex.: Qual o publico ideal?" },
        { id: "pergunta_3", label: "Pergunta 3", placeholder: "Ex.: O que muda em relacao ao modelo anterior?" },
      ],
      build: function (v) {
        var tema = v.tema || "Tema";
        var perguntas = [v.pergunta_1, v.pergunta_2, v.pergunta_3]
          .map(function (s) { return (s || "").trim(); })
          .filter(Boolean);

        if (perguntas.length === 0) {
          perguntas = [
            "Vale a pena?",
            "Para quem faz mais sentido?",
            "O que considerar antes de comprar?",
          ];
        }

        return (
          "<h2>FAQ rapido: " + tema + "</h2>" +
          '<div class="content-block content-block-faq">' +
          '<div class="content-block-label">Perguntas frequentes</div>' +
          perguntas.map(function (pergunta) {
            return "<h3>" + pergunta + "</h3><p>...</p>";
          }).join("") +
          "</div>"
        );
      },
    },
    ficha_tecnica: {
      fields: [
        { id: "produto", label: "Produto / assunto", placeholder: "Ex.: Steam Deck OLED" },
        { id: "fabricante", label: "Fabricante / marca", placeholder: "Ex.: Valve" },
        { id: "faixa_preco", label: "Faixa de preco", placeholder: "Ex.: R$ 4.000 a R$ 5.000" },
        { id: "itens", label: "Itens da ficha (um por linha, formato Campo: valor)", placeholder: "Tela: OLED 7,4\nArmazenamento: 512 GB\nBateria: 50 Wh", type: "textarea", rows: 6 },
      ],
      build: function (v) {
        var produto = v.produto || "Produto";
        var fabricante = v.fabricante || "...";
        var faixaPreco = v.faixa_preco || "...";
        var itens = (v.itens || "")
          .split(/\r?\n/)
          .map(function (s) { return s.trim(); })
          .filter(Boolean)
          .map(function (line) {
            var parts = line.split(":");
            var label = (parts.shift() || "").trim();
            var value = parts.join(":").trim();
            if (!label) return "";
            return "<li><b>" + label + ":</b> " + (value || "...") + "</li>";
          })
          .filter(Boolean)
          .join("");

        return (
          "<h2>Ficha tecnica: " + produto + "</h2>" +
          '<div class="content-block content-block-note">' +
          '<div class="content-block-label">Visao geral</div>' +
          "<p><b>Marca:</b> " + fabricante + "<br><b>Faixa de preco:</b> " + faixaPreco + "</p>" +
          "</div>" +
          '<div class="content-block">' +
          '<div class="content-block-label">Especificacoes principais</div>' +
          "<ul>" + (itens || "<li><b>Especificacao:</b> ...</li>") + "</ul>" +
          "</div>"
        );
      },
    },
    guia: {
      fields: [
        { id: "tema", label: "Tema do guia", placeholder: "Ex.: Como montar um PC gamer barato" },
        { id: "nivel", label: "NÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â­vel", placeholder: "Iniciante / IntermediÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¡rio / AvanÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â§ado" },
      ],
      build: function (v) {
        return (
          "<h2>" + (v.tema || "Guia") + "</h2>" +
          "<p><b>NÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â­vel:</b> " + (v.nivel || "") + "</p>" +
          "<h3>O que vocÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Âª vai aprender</h3><ul><li>ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¦</li></ul>" +
          "<h3>Passo a passo</h3><ol><li>ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¦</li><li>ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¦</li><li>ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¦</li></ol>" +
          "<h3>Dicas finais</h3><ul><li>ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¦</li></ul>"
        );
      },
    },
    noticia: {
      fields: [
        { id: "assunto", label: "Assunto", placeholder: "Ex.: LanÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â§amento do iPhone X" },
        { id: "pontos", label: "Fatos-chave (vÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â­rgula)", placeholder: "PreÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â§o, data, novidades..." },
      ],
      build: function (v) {
        var a = v.assunto || "NotÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â­cia";
        var pts = (v.pontos || "")
          .split(",")
          .map(function (s) { return s.trim(); })
          .filter(Boolean)
          .map(function (s) { return "<li>" + s + "</li>"; })
          .join("");
        return (
          "<h2>" + a + "</h2>" +
          "<p>Contexto rÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¡pido do que aconteceu.</p>" +
          "<h3>O que foi anunciado</h3><ul>" + (pts || "<li>ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¦</li>") + "</ul>" +
          "<h3>Por que isso importa</h3><p>ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¦</p>" +
          "<h3>O que esperar agora</h3><p>ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¦</p>"
        );
      },
    },
    lista: {
      fields: [
        { id: "titulo_lista", label: "TÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â­tulo da lista", placeholder: "Ex.: Top 7 teclados custo/benefÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â­cio" },
        { id: "qtd", label: "Quantidade", placeholder: "Ex.: 7" },
      ],
      build: function (v) {
        var t = v.titulo_lista || "Lista";
        var qtd = parseInt(v.qtd || "5", 10);
        if (!qtd || qtd < 1) qtd = 5;

        var items = "";
        for (var i = 1; i <= qtd; i += 1) {
          items += "<li><b>#" + i + "</b> ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¦</li>";
        }

        return (
          "<h2>" + t + "</h2>" +
          "<p>CritÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â©rios usados e para quem a lista ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â©.</p>" +
          "<ol>" + items + "</ol>" +
          "<h3>Como escolher</h3><ul><li>ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¦</li></ul>"
        );
      },
    },
  };

  function hideGeradorPreview() {
    var wrap = byId("gerador-preview");
    if (wrap) wrap.classList.add("hidden");
  }

  function showGeradorPreview(html) {
    var wrap = byId("gerador-preview");
    var content = byId("gerador-preview-content");
    if (!wrap || !content) return;

    content.textContent = html;
    wrap.classList.remove("hidden");
  }

  function disableAplicar() {
    var btn = byId("btn-aplicar");
    if (!btn) return;
    btn.disabled = true;
    btn.classList.add("opacity-50", "cursor-not-allowed");
  }

  function enableAplicar() {
    var btn = byId("btn-aplicar");
    if (!btn) return;
    btn.disabled = false;
    btn.classList.remove("opacity-50", "cursor-not-allowed");
  }

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

        var shouldLeave = window.confirm("Existem alteracoes nao salvas. Deseja sair mesmo assim?");
        if (!shouldLeave) {
          event.preventDefault();
        }
      });
    });
  }

  function getDefaultComparativoRows() {
    return [
      { label: "Performance", left: "", right: "" },
      { label: "Consumo", left: "", right: "" },
      { label: "Preco", left: "", right: "" },
      { label: "Perfil ideal", left: "", right: "" },
    ];
  }

  function renderComparativoRows() {
    var wrap = byId("gerador-linhas-wrap");
    if (!wrap) return;

    var items = wrap.querySelectorAll("[data-row-item]");
    if (!items.length) {
      getDefaultComparativoRows().forEach(function (row) {
        appendComparativoRow(row);
      });
    }
  }

  function appendComparativoRow(data) {
    var wrap = byId("gerador-linhas-wrap");
    if (!wrap) return;

    var row = data || { label: "", left: "", right: "" };
    var item = document.createElement("div");
    item.setAttribute("data-row-item", "1");
    item.className = "grid grid-cols-1 md:grid-cols-[1.1fr_1fr_1fr_auto] gap-3 items-end rounded-xl border border-slate-700/80 bg-slate-900/60 p-3";
    item.innerHTML = '' +
      '<div><label class="block text-[11px] text-slate-400 mb-1">Criterio</label><input type="text" data-row-field="label" class="w-full px-3 py-2 bg-slate-800 border border-cyan-500/30 rounded-lg text-sm text-gray-300 focus:border-cyan-400 focus:outline-none" value="' + escapeHtml(row.label || "") + '" placeholder="Ex.: Performance"></div>' +
      '<div><label class="block text-[11px] text-slate-400 mb-1">Produto A</label><input type="text" data-row-field="left" class="w-full px-3 py-2 bg-slate-800 border border-cyan-500/30 rounded-lg text-sm text-gray-300 focus:border-cyan-400 focus:outline-none" value="' + escapeHtml(row.left || "") + '" placeholder="Ex.: 220 FPS"></div>' +
      '<div><label class="block text-[11px] text-slate-400 mb-1">Produto B</label><input type="text" data-row-field="right" class="w-full px-3 py-2 bg-slate-800 border border-cyan-500/30 rounded-lg text-sm text-gray-300 focus:border-cyan-400 focus:outline-none" value="' + escapeHtml(row.right || "") + '" placeholder="Ex.: 205 FPS"></div>' +
      '<button type="button" class="px-3 py-2 rounded-lg border border-rose-500/30 text-rose-300 text-xs font-bold hover:bg-rose-500/10" data-row-remove>Remover</button>';

    wrap.appendChild(item);

    var removeButton = item.querySelector("[data-row-remove]");
    if (removeButton) {
      removeButton.addEventListener("click", function () {
        item.remove();
      });
    }
  }

  function escapeHtml(value) {
    return String(value || "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/\"/g, "&quot;");
  }

  function renderGeradorFields() {
    var select = byId("gerador-template");
    var campos = byId("gerador-campos");
    if (!select || !campos) return;

    var key = select.value || "comparativo";
    var tpl = TEMPLATES[key] || TEMPLATES.comparativo;

    var html = "";
    tpl.fields.forEach(function (f) {
      var fieldHtml = "";
      if (key === "comparativo" && f.id === "linhas") {
        fieldHtml = '' +
          '<div class="space-y-3">' +
          '<div id="gerador-linhas-wrap" class="space-y-3"></div>' +
          '<button type="button" id="gerador-add-row" class="px-3 py-2 rounded-lg border border-cyan-500/30 text-cyan-200 text-xs font-bold hover:bg-cyan-500/10">Adicionar linha</button>' +
          '</div>';
      } else if (key === "comparativo" && f.id === "saida") {
        fieldHtml = '' +
          '<select id="gerador-' + f.id + '" class="w-full px-3 py-2 bg-slate-800 border border-cyan-500/30 rounded-lg text-sm text-gray-300 focus:border-cyan-400 focus:outline-none">' +
          '<option value="ambos">Tabela + topicos</option>' +
          '<option value="tabela">Somente tabela</option>' +
          '<option value="topicos">Somente topicos</option>' +
          '</select>';
      } else if (f.type === "textarea") {
        fieldHtml = '<textarea id="gerador-' + f.id + '" rows="' + (f.rows || 4) + '" class="w-full px-3 py-2 bg-slate-800 border border-cyan-500/30 rounded-lg text-sm text-gray-300 focus:border-cyan-400 focus:outline-none" placeholder="' + (f.placeholder || "") + '"></textarea>';
      } else {
        fieldHtml = '<input id="gerador-' + f.id + '" type="text" class="w-full px-3 py-2 bg-slate-800 border border-cyan-500/30 rounded-lg text-sm text-gray-300 focus:border-cyan-400 focus:outline-none" placeholder="' + (f.placeholder || "") + '">';
      }

      html +=
        '<div>' +
        '<label class="block text-gray-400 text-xs mb-1">' +
        f.label +
        "</label>" +
        fieldHtml +
        "</div>";
    });

    campos.innerHTML = html;

    if (key === "comparativo") {
      renderComparativoRows();
      var addButton = byId("gerador-add-row");
      if (addButton) {
        addButton.addEventListener("click", function () {
          appendComparativoRow();
        });
      }
    }
    geradorHtmlAtual = "";
    disableAplicar();
    hideGeradorPreview();
  }

  function getGeradorValues() {
    var select = byId("gerador-template");
    var key = (select && select.value) || "comparativo";
    var tpl = TEMPLATES[key] || TEMPLATES.comparativo;

    var values = {};
    tpl.fields.forEach(function (f) {
      if (key === "comparativo" && f.id === "linhas") {
        values[f.id] = Array.prototype.slice.call(document.querySelectorAll("#gerador-linhas-wrap [data-row-item]"))
          .map(function (item) {
            var label = item.querySelector('[data-row-field="label"]');
            var left = item.querySelector('[data-row-field="left"]');
            var right = item.querySelector('[data-row-field="right"]');
            return {
              label: label ? (label.value || "") : "",
              left: left ? (left.value || "") : "",
              right: right ? (right.value || "") : "",
            };
          });
        return;
      }

      var el = byId("gerador-" + f.id);
      values[f.id] = el ? (el.value || "") : "";
    });

    return { key: key, values: values };
  }

  function initGerador() {
    if (geradorIniciado) return;

    var select = byId("gerador-template");
    var campos = byId("gerador-campos");
    if (!select || !campos) return;

    select.addEventListener("change", renderGeradorFields);
    renderGeradorFields();

    geradorIniciado = true;
  }

  window.gerarConteudo = function gerarConteudo() {
    if (!existsEditor()) return;

    initGerador();
    var data = getGeradorValues();
    var tpl = TEMPLATES[data.key] || TEMPLATES.comparativo;

    geradorHtmlAtual = tpl.build(data.values);
    showGeradorPreview(geradorHtmlAtual);
    enableAplicar();
  };

  window.aplicarGerador = function aplicarGerador() {
    if (!existsEditor()) return;
    if (!geradorHtmlAtual) return;

    var visual = byId("editor-visual");
    var htmlArea = byId("editor-html");

    if (visual) visual.innerHTML = geradorHtmlAtual;
    if (htmlArea) htmlArea.value = geradorHtmlAtual;

    window.atualizarTextarea();
    window.switchTab("visual");
  };

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
    if (ajuda) ajuda.textContent = "Editor do post";

    initCounts();
    initMediaHelpers();
    initSubmitValidation();
    initUnsavedChangesGuard();
    syncCategoriaIndicator();
    window.setTimeout(syncCategoriaIndicator, 0);
    window.setTimeout(syncCategoriaIndicator, 150);

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
      if (e.key === "Escape") window.fecharPreview();
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














