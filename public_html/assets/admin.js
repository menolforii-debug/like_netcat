document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('textarea.code-editor').forEach((textarea) => {
    textarea.addEventListener('keydown', (event) => {
      if (event.key === 'Tab') {
        event.preventDefault();
        const start = textarea.selectionStart;
        const end = textarea.selectionEnd;
        const value = textarea.value;
        textarea.value = value.substring(0, start) + '    ' + value.substring(end);
        textarea.selectionStart = textarea.selectionEnd = start + 4;
      }
    });
  });
});

// SectionTree: toggle expand/collapse by chevron (no navigation)
document.addEventListener('click', (e) => {
  const btn = e.target.closest('.js-tree-toggle');
  if (!btn) return;

  e.preventDefault();
  e.stopPropagation();

  const nodeId = btn.getAttribute('data-node-id');
  if (!nodeId) return;

  const item = document.querySelector('.section-tree-item[data-node-id="' + nodeId + '"]');
  if (!item) return;

  item.classList.toggle('is-open');
});

// ComponentTree: toggle views expand/collapse by chevron (no navigation)
document.addEventListener('click', (e) => {
  const btn = e.target.closest('.js-component-toggle');
  if (!btn) return;

  e.preventDefault();
  e.stopPropagation();

  const compId = btn.getAttribute('data-component-id');
  if (!compId) return;

  const item = document.querySelector('.component-tree-item[data-component-id="' + compId + '"]');
  if (!item) return;

  item.classList.toggle('is-open');
});
