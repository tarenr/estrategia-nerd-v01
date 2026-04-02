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
  }
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

  var formTracker = {
    initialState: "",
    submitting: false,
  };

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
      if (formTracker.submitting || !hasUnsavedChanges(form)) return;
      event.preventDefault();
      event.returnValue = "";
    });

    document.querySelectorAll("[data-confirm-leave]").forEach(function (link) {
      link.addEventListener("click", function (event) {
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

    form.addEventListener("submit", function () {
      window.atualizarTextarea();
      formTracker.submitting = true;

      var btn = form.querySelector('button[type="submit"]');
      if (btn) {
        btn.disabled = true;
        btn.innerHTML = "Salvando...";
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
    initUnsavedChangesGuard();
    syncCategoriaIndicator();
    window.setTimeout(syncCategoriaIndicator, 0);
    window.setTimeout(syncCategoriaIndicator, 150);

    var categoriaSelect = byId("categoria_post_id");
    if (categoriaSelect) categoriaSelect.addEventListener("change", syncCategoriaIndicator);

    window.addEventListener("pageshow", syncCategoriaIndicator);

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











