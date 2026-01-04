document.addEventListener('DOMContentLoaded', () => {
  if (!window.CodeMirror) {
    return;
  }

  const editors = document.querySelectorAll('textarea.code-editor');
  editors.forEach((textarea) => {
    if (textarea.dataset.codemirror === '1') {
      return;
    }

    window.CodeMirror.fromTextArea(textarea, {
      lineNumbers: true,
      lineWrapping: true,
      mode: 'application/x-httpd-php',
    });

    textarea.dataset.codemirror = '1';
    textarea.style.display = 'none';
  });
});
