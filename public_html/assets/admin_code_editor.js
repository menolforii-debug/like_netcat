document.addEventListener('DOMContentLoaded', () => {
  if (!window.CodeMirror) {
    return;
  }

  const textareas = document.querySelectorAll('textarea.code-editor');
  if (!textareas.length) {
    return;
  }

  // Храним созданные редакторы, чтобы:
  // - перед submit делать save()
  // - дергать refresh() при показе/изменении размеров
  const editors = [];

  textareas.forEach((textarea) => {
    if (textarea.dataset.codemirror === '1') {
      return;
    }

    // ВАЖНО:
    // - НЕ прячем textarea вручную. CodeMirror.fromTextArea сделает это сам.
    // - Настроим нормальный режим и удобства.
    const editor = window.CodeMirror.fromTextArea(textarea, {
      lineNumbers: true,
      lineWrapping: true,
      mode: 'application/x-httpd-php',
      indentUnit: 2,
      tabSize: 2,
      indentWithTabs: false,
    });

    // Адекватная высота (можно менять под себя)
    editor.setSize(null, 320);

    textarea.dataset.codemirror = '1';
    textarea._codeMirror = editor;
    editors.push(editor);
  });

  // На всякий случай: если DOM/шрифты догружаются — обновим рендер
  // (особенно актуально при heavy CSS/Bootstrap/SOW)
  window.setTimeout(() => {
    editors.forEach((ed) => ed.refresh());
  }, 0);

  // Перед отправкой формы обязательно сохранить актуальное содержимое
  const forms = new Set();
  textareas.forEach((ta) => {
    const form = ta.closest('form');
    if (form) {
      forms.add(form);
    }
  });

  forms.forEach((form) => {
    form.addEventListener('submit', () => {
      editors.forEach((ed) => ed.save());
    });
  });

  // Если редактор был создан в скрытом контейнере (табы/аккордеон/коллапс),
  // или меняется ширина (responsive), refresh помогает убрать "кривизну".
  const refreshAll = () => editors.forEach((ed) => ed.refresh());

  window.addEventListener('resize', () => {
    refreshAll();
  });

  // Bootstrap events (SOW использует bootstrap-подобные компоненты в vendor_bundle)
  document.addEventListener('shown.bs.tab', refreshAll);
  document.addEventListener('shown.bs.collapse', refreshAll);
});
