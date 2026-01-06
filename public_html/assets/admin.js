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

function showAdminToast(type, message) {
  try {
    if (window.jQuery && window.jQuery.SOW && window.jQuery.SOW.core && window.jQuery.SOW.core.toast) {
      window.jQuery.SOW.core.toast.show(type, '', message, 'top-center', 3500, true);
      return;
    }
  } catch (e) {}
  alert(message);
}

function refreshAdminBlocks(selectors) {
  if (!Array.isArray(selectors)) return;
  selectors.forEach((selector) => {
    const target = document.querySelector(selector);
    if (!target) return;
    const url = target.getAttribute('data-refresh-url');
    if (!url) return;
    fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then((res) => res.text())
      .then((html) => {
        target.innerHTML = html;
      });
  });
}

function handleAjaxResponse(payload) {
  if (!payload || typeof payload !== 'object') return;
  if (payload.ok) {
    if (payload.refresh) {
      refreshAdminBlocks(payload.refresh);
    }
    if (payload.focus && payload.focus.section_id) {
      const url = new URL(window.location.href);
      url.searchParams.set('section_id', payload.focus.section_id);
      window.history.replaceState({}, '', url);
    }
    if (payload.focus && payload.focus.component_id) {
      const url = new URL(window.location.href);
      url.searchParams.set('component_id', payload.focus.component_id);
      window.history.replaceState({}, '', url);
    }
  } else if (payload.error) {
    showAdminToast('danger', payload.error);
  }
}

function openAdminModal(url) {
  const modalEl = document.getElementById('adminModal');
  if (!modalEl) return;
  const modal = window.bootstrap ? window.bootstrap.Modal.getOrCreateInstance(modalEl) : null;
  const modalBody = modalEl.querySelector('.modal-body');
  const modalTitle = modalEl.querySelector('.modal-title');
  if (modalBody) modalBody.innerHTML = '<div class="text-muted">Загрузка...</div>';
  if (modalTitle) modalTitle.textContent = 'Загрузка...';

  fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
    .then((res) => res.text())
    .then((html) => {
      if (modalBody) modalBody.innerHTML = html;
      const title = modalBody ? modalBody.querySelector('[data-modal-title]') : null;
      if (modalTitle && title) {
        modalTitle.textContent = title.getAttribute('data-modal-title') || 'Редактирование';
        title.remove();
      }
    })
    .catch(() => {
      if (modalBody) modalBody.innerHTML = '<div class="text-danger">Не удалось загрузить форму.</div>';
    });

  if (modal) modal.show();
}

document.addEventListener('click', (e) => {
  const trigger = e.target.closest('[data-modal-url]');
  if (!trigger) return;
  e.preventDefault();
  const url = trigger.getAttribute('data-modal-url');
  if (url) {
    openAdminModal(url);
  }
});

document.addEventListener('submit', (e) => {
  const form = e.target;
  if (!(form instanceof HTMLFormElement)) return;
  if (!form.dataset.ajax) return;

  e.preventDefault();
  const confirmMessage = form.getAttribute('data-confirm');
  if (confirmMessage) {
    const confirmModal = document.getElementById('adminConfirmModal');
    if (!confirmModal) return;
    const confirmBody = confirmModal.querySelector('.modal-body');
    if (confirmBody) {
      confirmBody.textContent = confirmMessage;
    }
    const confirmBtn = confirmModal.querySelector('[data-confirm-action="true"]');
    if (confirmBtn) {
      confirmBtn.onclick = () => {
        const modalInstance = window.bootstrap ? window.bootstrap.Modal.getOrCreateInstance(confirmModal) : null;
        if (modalInstance) modalInstance.hide();
        submitAjaxForm(form);
      };
    }
    const modalInstance = window.bootstrap ? window.bootstrap.Modal.getOrCreateInstance(confirmModal) : null;
    if (modalInstance) modalInstance.show();
    return;
  }

  submitAjaxForm(form);
});

function submitAjaxForm(form) {
  const action = form.getAttribute('action') || window.location.href;
  const method = (form.getAttribute('method') || 'POST').toUpperCase();
  const formData = new FormData(form);
  fetch(action, {
    method,
    body: formData,
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
  })
    .then((res) => res.json())
    .then((payload) => {
      handleAjaxResponse(payload);
      if (payload && payload.ok) {
        const modalEl = document.getElementById('adminModal');
        if (modalEl && modalEl.classList.contains('show')) {
          const modalInstance = window.bootstrap ? window.bootstrap.Modal.getOrCreateInstance(modalEl) : null;
          if (modalInstance) modalInstance.hide();
        }
      }
    })
    .catch(() => {
      showAdminToast('danger', 'Ошибка запроса. Попробуйте еще раз.');
    });
}

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
