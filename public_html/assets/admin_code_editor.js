document.addEventListener('DOMContentLoaded', () => {
  const editors = document.querySelectorAll('textarea.code-editor');
  editors.forEach((textarea) => {
    const pre = document.createElement('pre');
    pre.className = 'code-editor-preview';

    const code = document.createElement('code');
    code.className = 'language-php';
    pre.appendChild(code);

    textarea.insertAdjacentElement('afterend', pre);

    const sync = () => {
      code.textContent = textarea.value;
      if (window.hljs && typeof window.hljs.highlightElement === 'function') {
        window.hljs.highlightElement(code);
      }
    };

    textarea.addEventListener('input', sync);
    sync();
  });
});
