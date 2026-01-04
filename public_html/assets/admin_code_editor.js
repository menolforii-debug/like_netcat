document.addEventListener('DOMContentLoaded', () => {
  if (!window.CodeMirror) {
    return;
  }

  const editors = document.querySelectorAll('textarea.code-editor');
  editors.forEach((textarea) => {
    window.CodeMirror.fromTextArea(textarea, {
      lineNumbers: true,
      lineWrapping: true,
      mode: 'application/x-httpd-php',
    });
  });
});
