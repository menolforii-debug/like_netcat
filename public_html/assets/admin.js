document.addEventListener('DOMContentLoaded', () => {
  const editors = [];
  const targets = [
    'textarea[name="list_tpl"]',
    'textarea[name="single_tpl"]',
  ];

  targets.forEach((selector) => {
    const textarea = document.querySelector(selector);
    if (!textarea || typeof CodeMirror === 'undefined') {
      return;
    }

    const editor = CodeMirror.fromTextArea(textarea, {
      mode: 'application/x-httpd-php',
      theme: 'material-darker',
      lineNumbers: true,
      lineWrapping: true,
      indentUnit: 4,
      tabSize: 4,
      indentWithTabs: false,
    });

    editor.setSize('100%', 300);
    editors.push(editor);
  });

  if (editors.length === 0) {
    return;
  }

  document.querySelectorAll('form').forEach((form) => {
    form.addEventListener('submit', () => {
      editors.forEach((editor) => editor.save());
    });
  });
});
