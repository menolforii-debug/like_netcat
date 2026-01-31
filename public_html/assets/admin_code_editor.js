function createEditor(textarea, mode) {
  if (!window.CodeMirror) {
    return null;
  }

  if (textarea.dataset.codemirror === '1') {
    return textarea._codeMirror || null;
  }

  const editor = window.CodeMirror.fromTextArea(textarea, {
    lineNumbers: true,
    lineWrapping: true,
    mode: mode || 'application/x-httpd-php',
    theme: 'eclipse',
    indentUnit: 2,
    tabSize: 2,
    indentWithTabs: false,
  });

  editor.setSize(null, 160);

  textarea.dataset.codemirror = '1';
  textarea._codeMirror = editor;

  return editor;
}

function initCodeEditors(scope) {
  if (!window.CodeMirror) {
    return;
  }

  const root = scope || document;
  const textareas = root.querySelectorAll('textarea.code-editor');
  if (!textareas.length) {
    return;
  }

  const state = (window.__adminCodeEditors = window.__adminCodeEditors || {
    editors: [],
    listenersBound: false,
  });

  textareas.forEach((textarea) => {
    const mode = textarea.dataset.mode || 'application/x-httpd-php';
    const editor = createEditor(textarea, mode);
    if (!editor) {
      return;
    }

    state.editors.push(editor);

    const form = textarea.closest('form');
    if (form && !form.dataset.codemirrorBound) {
      form.addEventListener('submit', () => {
        state.editors.forEach((ed) => ed.save());
      });
      form.dataset.codemirrorBound = '1';
    }
  });

  window.setTimeout(() => {
    state.editors.forEach((ed) => ed.refresh());
  }, 0);

  if (!state.listenersBound) {
    const refreshAll = () => state.editors.forEach((ed) => ed.refresh());

    window.addEventListener('resize', refreshAll);
    document.addEventListener('shown.bs.tab', refreshAll);
    document.addEventListener('shown.bs.collapse', refreshAll);

    state.listenersBound = true;
  }
}

window.initCodeEditors = initCodeEditors;
window.initCodeEditor = function initCodeEditor(textarea, mode) {
  if (!textarea) {
    return null;
  }

  const state = (window.__adminCodeEditors = window.__adminCodeEditors || {
    editors: [],
    listenersBound: false,
  });

  const editor = createEditor(textarea, mode);
  if (!editor) {
    return null;
  }

  if (!state.editors.includes(editor)) {
    state.editors.push(editor);
  }

  const form = textarea.closest('form');
  if (form && !form.dataset.codemirrorBound) {
    form.addEventListener('submit', () => {
      state.editors.forEach((ed) => ed.save());
    });
    form.dataset.codemirrorBound = '1';
  }

  if (!state.listenersBound) {
    const refreshAll = () => state.editors.forEach((ed) => ed.refresh());

    window.addEventListener('resize', refreshAll);
    document.addEventListener('shown.bs.tab', refreshAll);
    document.addEventListener('shown.bs.collapse', refreshAll);

    state.listenersBound = true;
  }

  return editor;
};

document.addEventListener('DOMContentLoaded', () => {
  initCodeEditors(document);
});
