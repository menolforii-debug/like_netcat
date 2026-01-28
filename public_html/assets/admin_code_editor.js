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
    if (textarea.dataset.codemirror === '1') {
      return;
    }

    const editor = window.CodeMirror.fromTextArea(textarea, {
      lineNumbers: true,
      lineWrapping: true,
      mode: 'application/x-httpd-php',
      indentUnit: 2,
      tabSize: 2,
      indentWithTabs: false,
    });

    editor.setSize(null, 320);

    textarea.dataset.codemirror = '1';
    textarea._codeMirror = editor;
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

document.addEventListener('DOMContentLoaded', () => {
  initCodeEditors(document);
});
