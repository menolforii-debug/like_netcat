document.addEventListener('DOMContentLoaded', () => {
  const editors = document.querySelectorAll('textarea.code-editor');
  editors.forEach((textarea) => {
    const wrapper = document.createElement('div');
    wrapper.className = 'code-editor-wrap';

    const gutter = document.createElement('div');
    gutter.className = 'code-editor-gutter';

    const editor = document.createElement('div');
    editor.className = 'code-editor-area';

    const pre = document.createElement('pre');
    pre.className = 'code-editor-highlight';

    const code = document.createElement('code');
    code.className = 'language-php';
    pre.appendChild(code);

    const parent = textarea.parentNode;
    if (!parent) {
      return;
    }

    parent.insertBefore(wrapper, textarea);
    wrapper.appendChild(gutter);
    wrapper.appendChild(editor);
    editor.appendChild(pre);
    editor.appendChild(textarea);

    const syncHighlight = () => {
      code.textContent = textarea.value;
      if (window.hljs && typeof window.hljs.highlightElement === 'function') {
        window.hljs.highlightElement(code);
      }
    };

    const syncGutter = () => {
      const lines = textarea.value.split('\n').length || 1;
      const currentLines = gutter.childElementCount;
      if (lines !== currentLines) {
        gutter.innerHTML = '';
        for (let i = 1; i <= lines; i += 1) {
          const line = document.createElement('div');
          line.textContent = String(i);
          gutter.appendChild(line);
        }
      }
    };

    const syncScroll = () => {
      pre.scrollTop = textarea.scrollTop;
      pre.scrollLeft = textarea.scrollLeft;
      gutter.scrollTop = textarea.scrollTop;
    };

    textarea.addEventListener('input', () => {
      syncHighlight();
      syncGutter();
    });
    textarea.addEventListener('scroll', syncScroll);

    syncHighlight();
    syncGutter();
    syncScroll();
  });
});
