/**
 * File: /assets/js/criar-post.js
 * Purpose: Editor (abas/toolbar/sync), preview e Gerador Nerd para /admin/pages/criar-post.php
 *
 * Notes:
 * - Este arquivo expõe funções globais (switchTab, formatar, etc.) porque o HTML usa onclick="...".
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

    // Esconde todos os painéis
    document.querySelectorAll(".editor-panel").forEach(function (p) {
      p.classList.add("hidden");
    });

    var panel = byId("panel-" + tabName);
    if (panel) panel.classList.remove("hidden");

    // Botões de aba
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
      html: "Edite diretamente o código HTML.",
      gerador: "Preencha os campos e gere conteúdo automaticamente.",
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
    var titulo = (tituloEl && tituloEl.value) ? tituloEl.value : "Sem título";

    var visual = byId("editor-visual");
    var conteudo = visual ? visual.innerHTML : "";

    var catRadio = document.querySelector('input[name="categoria_post_id"]:checked');
    var categoriaNome = "Geral";
    if (catRadio && catRadio.parentElement) {
      var span = catRadio.parentElement.querySelector("span");
      if (span) categoriaNome = span.textContent;
    }

    var previewContent = byId("previewContent");
    if (previewContent) {
      previewContent.innerHTML =
        '<div class="mb-4">' +
        '<span class="px-3 py-1 bg-cyan-500 text-slate-900 text-xs font-bold rounded-full uppercase">' +
        categoriaNome +
        "</span></div>" +
        '<h1 class="font-orbitron text-4xl font-bold text-white mb-6">' +
        titulo +
        "</h1>" +
        '<div class="prose prose-invert max-w-none text-gray-300 leading-relaxed">' +
        (conteudo || "<p><em>Sem conteúdo.</em></p>") +
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
  // Gerador Nerd (compatível com seu HTML do criar-post)
  // -------------------------
  var geradorIniciado = false;
  var geradorHtmlAtual = "";

  var TEMPLATES = {
    comparativo: {
      fields: [
        { id: "produto_a", label: "Produto A", placeholder: "Ex.: RTX 5070" },
        { id: "produto_b", label: "Produto B", placeholder: "Ex.: RX 9070" },
        { id: "contexto", label: "Contexto / objetivo", placeholder: "Ex.: 1440p, custo/benefício..." },
      ],
      build: function (v) {
        var a = v.produto_a || "Produto A";
        var b = v.produto_b || "Produto B";
        var ctx = v.contexto || "";
        return (
          "<h2>" + a + " vs " + b + ": qual vale mais a pena?</h2>" +
          "<p>" + ctx + "</p>" +
          "<h3>Resumo rápido</h3>" +
          "<ul><li><b>" + a + "</b>: pontos fortes …</li><li><b>" + b + "</b>: pontos fortes …</li></ul>" +
          "<h3>Comparativo em tópicos</h3>" +
          "<ul><li><b>Performance:</b> …</li><li><b>Consumo/temperatura:</b> …</li><li><b>Preço:</b> …</li><li><b>Para quem é:</b> …</li></ul>" +
          "<h3>Veredito</h3><p>…</p>"
        );
      },
    },
    review: {
      fields: [
        { id: "produto", label: "Produto", placeholder: "Ex.: Nintendo Switch 2" },
        { id: "pontos", label: "Pontos (vírgula)", placeholder: "Design, bateria, tela..." },
      ],
      build: function (v) {
        var p = v.produto || "Produto";
        var pts = (v.pontos || "")
          .split(",")
          .map(function (s) { return s.trim(); })
          .filter(Boolean)
          .map(function (s) { return "<li>" + s + " …</li>"; })
          .join("");
        return (
          "<h2>Review: " + p + "</h2>" +
          "<p>Visão geral do produto e para quem ele faz sentido.</p>" +
          "<h3>Pontos principais</h3><ul>" + (pts || "<li>…</li>") + "</ul>" +
          "<h3>Prós e contras</h3><ul><li><b>Prós:</b> …</li><li><b>Contras:</b> …</li></ul>" +
          "<h3>Conclusão</h3><p>…</p>"
        );
      },
    },
    guia: {
      fields: [
        { id: "tema", label: "Tema do guia", placeholder: "Ex.: Como montar um PC gamer barato" },
        { id: "nivel", label: "Nível", placeholder: "Iniciante / Intermediário / Avançado" },
      ],
      build: function (v) {
        return (
          "<h2>" + (v.tema || "Guia") + "</h2>" +
          "<p><b>Nível:</b> " + (v.nivel || "") + "</p>" +
          "<h3>O que você vai aprender</h3><ul><li>…</li></ul>" +
          "<h3>Passo a passo</h3><ol><li>…</li><li>…</li><li>…</li></ol>" +
          "<h3>Dicas finais</h3><ul><li>…</li></ul>"
        );
      },
    },
    noticia: {
      fields: [
        { id: "assunto", label: "Assunto", placeholder: "Ex.: Lançamento do iPhone X" },
        { id: "pontos", label: "Fatos-chave (vírgula)", placeholder: "Preço, data, novidades..." },
      ],
      build: function (v) {
        var a = v.assunto || "Notícia";
        var pts = (v.pontos || "")
          .split(",")
          .map(function (s) { return s.trim(); })
          .filter(Boolean)
          .map(function (s) { return "<li>" + s + "</li>"; })
          .join("");
        return (
          "<h2>" + a + "</h2>" +
          "<p>Contexto rápido do que aconteceu.</p>" +
          "<h3>O que foi anunciado</h3><ul>" + (pts || "<li>…</li>") + "</ul>" +
          "<h3>Por que isso importa</h3><p>…</p>" +
          "<h3>O que esperar agora</h3><p>…</p>"
        );
      },
    },
    lista: {
      fields: [
        { id: "titulo_lista", label: "Título da lista", placeholder: "Ex.: Top 7 teclados custo/benefício" },
        { id: "qtd", label: "Quantidade", placeholder: "Ex.: 7" },
      ],
      build: function (v) {
        var t = v.titulo_lista || "Lista";
        var qtd = parseInt(v.qtd || "5", 10);
        if (!qtd || qtd < 1) qtd = 5;

        var items = "";
        for (var i = 1; i <= qtd; i += 1) {
          items += "<li><b>#" + i + "</b> — …</li>";
        }

        return (
          "<h2>" + t + "</h2>" +
          "<p>Critérios usados e para quem a lista é.</p>" +
          "<ol>" + items + "</ol>" +
          "<h3>Como escolher</h3><ul><li>…</li></ul>"
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

  function renderGeradorFields() {
    var select = byId("gerador-template");
    var campos = byId("gerador-campos");
    if (!select || !campos) return;

    var key = select.value || "comparativo";
    var tpl = TEMPLATES[key] || TEMPLATES.comparativo;

    var html = "";
    tpl.fields.forEach(function (f) {
      html +=
        '<div>' +
        '<label class="block text-gray-400 text-xs mb-1">' +
        f.label +
        "</label>" +
        '<input id="gerador-' + f.id + '" type="text" class="w-full px-3 py-2 bg-slate-800 border border-cyan-500/30 rounded-lg text-sm text-gray-300 focus:border-cyan-400 focus:outline-none" placeholder="' +
        (f.placeholder || "") +
        '">' +
        "</div>";
    });

    campos.innerHTML = html;
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
  // Submit validation (igual ao editar-post)
  // -------------------------
  function initSubmitValidation() {
    var form = byId("postForm");
    if (!form) return;

    form.addEventListener("submit", function (e) {
      window.atualizarTextarea();

      var tituloEl = byId("titulo");
      var titulo = tituloEl ? tituloEl.value.trim() : "";

      var hidden = byId("conteudoHidden");
      var conteudo = hidden ? hidden.value.trim() : "";

      var categoria = document.querySelector('input[name="categoria_post_id"]:checked');

      var erros = [];
      if (!titulo) erros.push("Título é obrigatório");
      if (!conteudo || conteudo === "<br>") erros.push("Conteúdo é obrigatório");
      if (!categoria) erros.push("Selecione uma categoria");

      if (erros.length > 0) {
        e.preventDefault();
        window.alert(erros.join("\n"));
        return false;
      }

      var btn = form.querySelector('button[type="submit"]');
      if (btn) {
        btn.disabled = true;
        btn.innerHTML = "💾 Salvando...";
      }

      return true;
    });
  }

  // -------------------------
  // Boot
  // -------------------------
  function boot() {
    if (!existsEditor()) return;

    // marcador visual (sem console)
    var ajuda = byId("editor-ajuda");
    if (ajuda) ajuda.textContent = "✅ criar-post.js carregado";
    window.__CRIAR_POST_JS_OK__ = true;


    initCounts();
    initSubmitValidation();

    // ESC fecha modal
    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape") window.fecharPreview();
    });

    // Sync inicial
    window.atualizarTextarea();

    // Garante que a aba visual está ativa no load
    window.switchTab("visual");
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
  } else {
    boot();
  }
})();
