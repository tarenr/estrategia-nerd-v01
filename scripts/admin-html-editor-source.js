import { Compartment, EditorState } from '@codemirror/state';
import { EditorView, lineNumbers } from '@codemirror/view';
import { basicSetup } from 'codemirror';
import { defaultHighlightStyle, syntaxHighlighting } from '@codemirror/language';
import { html } from '@codemirror/lang-html';
import { oneDark } from '@codemirror/theme-one-dark';
import * as prettier from 'prettier/standalone';
import prettierHtmlPlugin from 'prettier/plugins/html';

function buildTheme() {
  return EditorView.theme({
    '&': {
      minHeight: '480px',
      height: '480px',
      borderRadius: '18px',
      border: '1px solid rgba(34, 211, 238, 0.18)',
      overflow: 'hidden',
      backgroundColor: '#0f172a',
      fontSize: '14px',
    },
    '.cm-scroller': {
      overflow: 'auto',
      fontFamily: 'Consolas, Monaco, "Courier New", monospace',
      lineHeight: '1.65',
    },
    '.cm-content': {
      padding: '18px 0',
    },
    '.cm-line': {
      padding: '0 16px',
    },
    '.cm-gutters': {
      backgroundColor: '#020617',
      borderRight: '1px solid rgba(34, 211, 238, 0.12)',
      color: '#64748b',
    },
    '.cm-activeLineGutter': {
      backgroundColor: 'rgba(34, 211, 238, 0.08)',
      color: '#bae6fd',
    },
    '.cm-activeLine': {
      backgroundColor: 'rgba(15, 23, 42, 0.88)',
    },
    '.cm-selectionBackground, ::selection': {
      backgroundColor: 'rgba(14, 165, 233, 0.28) !important',
    },
  });
}

function syncValue(view, textarea, options = {}) {
  const value = view.state.doc.toString();
  textarea.value = value;
  if (options.hiddenField) {
    options.hiddenField.value = value;
  }
  if (options.mirror) {
    options.mirror.textContent = value;
  }
  if (options.syncFromHtml && typeof window.syncFromHtml === 'function') {
    window.syncFromHtml();
  }
}

function findForm(root, textarea) {
  if (textarea && textarea.form) return textarea.form;
  const explicit = root.getAttribute('data-html-editor-form');
  if (explicit) {
    return document.querySelector(explicit);
  }
  return root.closest('form');
}

function resolveLinkedNode(root, attrName) {
  const selector = root.getAttribute(attrName);
  if (!selector) return null;
  return document.querySelector(selector);
}

function createHtmlEditor(root) {
  const textarea = root.querySelector('[data-html-editor-textarea]');
  const mount = root.querySelector('[data-html-editor-mount]');
  const mirror = root.querySelector('[data-html-editor-output]');
  const form = findForm(root, textarea);
  const wrapToggle = root.querySelector('[data-html-editor-wrap]');
  const formatButton = root.querySelector('[data-html-editor-format]');
  const status = root.querySelector('[data-html-editor-status]');
  const hiddenField = resolveLinkedNode(root, 'data-html-editor-hidden');
  const syncFromHtml = root.getAttribute('data-html-editor-sync-from-html') === '1';
  const preventSubmit = root.getAttribute('data-html-editor-prevent-submit') === '1';

  if (!textarea || !mount) return null;

  const wrapCompartment = new Compartment();

  const syncOptions = {
    mirror,
    hiddenField,
    syncFromHtml,
  };

  function createExtensions(wrapEnabled) {
    return [
      basicSetup,
      lineNumbers(),
      html(),
      oneDark,
      syntaxHighlighting(defaultHighlightStyle, { fallback: true }),
      buildTheme(),
      wrapCompartment.of(wrapEnabled ? EditorView.lineWrapping : []),
      EditorView.updateListener.of((update) => {
        if (!update.docChanged) return;
        syncValue(update.view, textarea, syncOptions);
        if (status) {
          status.textContent = 'Sincronizado com o textarea';
        }
      }),
    ];
  }

  const view = new EditorView({
    state: EditorState.create({
      doc: textarea.value || '',
      extensions: createExtensions(false),
    }),
    parent: mount,
  });

  syncValue(view, textarea, syncOptions);

  function setWrap(enabled) {
    view.dispatch({
      effects: wrapCompartment.reconfigure(enabled ? EditorView.lineWrapping : []),
    });
    syncValue(view, textarea, syncOptions);
  }

  async function formatHtml() {
    const rawValue = view.state.doc.toString();
    try {
      const formatted = await prettier.format(rawValue, {
        parser: 'html',
        plugins: [prettierHtmlPlugin],
        printWidth: 100,
        tabWidth: 2,
        useTabs: false,
        htmlWhitespaceSensitivity: 'css',
      });
      view.dispatch({
        changes: {
          from: 0,
          to: view.state.doc.length,
          insert: String(formatted || ''),
        },
      });
      syncValue(view, textarea, syncOptions);
      if (status) {
        status.textContent = 'HTML formatado com sucesso';
      }
      view.focus();
    } catch (error) {
      if (status) {
        status.textContent = 'Falha ao formatar o HTML';
      }
      if (window.console && typeof window.console.error === 'function') {
        window.console.error('Falha ao formatar HTML no CodeMirror:', error);
      }
    }
  }

  if (wrapToggle) {
    wrapToggle.addEventListener('change', () => {
      setWrap(wrapToggle.checked);
    });
  }

  if (formatButton) {
    formatButton.addEventListener('click', () => {
      if (status) {
        status.textContent = 'Formatando HTML...';
      }
      formatHtml();
    });
  }

  if (form) {
    form.addEventListener('submit', (event) => {
      syncValue(view, textarea, syncOptions);
      if (status) {
        status.textContent = 'Submit de teste sincronizado';
      }
      if (preventSubmit) {
        event.preventDefault();
      }
    });
  }

  return {
    view,
    textareaId: textarea.id || '',
    sync() {
      syncValue(view, textarea, syncOptions);
    },
    setValue(value) {
      view.dispatch({
        changes: {
          from: 0,
          to: view.state.doc.length,
          insert: String(value || ''),
        },
      });
      syncValue(view, textarea, syncOptions);
    },
    replaceFromTextarea() {
      const nextValue = textarea.value || '';
      view.dispatch({
        changes: {
          from: 0,
          to: view.state.doc.length,
          insert: nextValue,
        },
      });
      syncValue(view, textarea, syncOptions);
    },
    formatHtml,
  };
}

const htmlEditorRegistry = new Map();

function initHtmlEditorTests() {
  document.querySelectorAll('[data-html-editor-root]').forEach((root) => {
    if (root.__htmlEditorTestReady) return;
    root.__htmlEditorTestReady = true;
    const instance = createHtmlEditor(root);
    if (instance && instance.textareaId) {
      htmlEditorRegistry.set(instance.textareaId, instance);
    }
  });
}

window.AdminHtmlEditor = {
  getByTextareaId(textareaId) {
    return htmlEditorRegistry.get(String(textareaId || '')) || null;
  },
  syncByTextareaId(textareaId) {
    const instance = htmlEditorRegistry.get(String(textareaId || ''));
    if (!instance) return false;
    instance.sync();
    return true;
  },
  setValueByTextareaId(textareaId, value) {
    const instance = htmlEditorRegistry.get(String(textareaId || ''));
    if (!instance) return false;
    instance.setValue(value);
    return true;
  },
  refreshFromTextareaById(textareaId) {
    const instance = htmlEditorRegistry.get(String(textareaId || ''));
    if (!instance) return false;
    instance.replaceFromTextarea();
    return true;
  },
};

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initHtmlEditorTests);
} else {
  initHtmlEditorTests();
}
