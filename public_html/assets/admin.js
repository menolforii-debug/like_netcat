function initVisualInheritToggles(scope) {
  const root = scope || document;
  root.querySelectorAll('.js-visual-inherit').forEach((checkbox) => {
    const targetId = checkbox.getAttribute('data-target');
    if (!targetId) return;
    const target = root.getElementById ? root.getElementById(targetId) : document.getElementById(targetId);
    if (!target) return;
    const inputs = target.querySelectorAll('[data-visual-input]');
    const applyState = () => {
      const disabled = checkbox.checked;
      inputs.forEach((input) => {
        input.disabled = disabled;
      });
    };
    checkbox.addEventListener('change', applyState);
    applyState();
  });
}

function initInfoblockViewSelects(scope) {
  const root = scope || document;
  root.querySelectorAll('.js-infoblock-component').forEach((componentSelect) => {
    const form = componentSelect.closest('form');
    const viewSelect = form ? form.querySelector('.js-infoblock-view') : null;
    if (!viewSelect) return;

    const updateOptions = () => {
      const selectedOption = componentSelect.selectedOptions[0];
      let views = [];
      if (selectedOption) {
        const rawViews = selectedOption.getAttribute('data-views');
        if (rawViews) {
          try {
            const parsed = JSON.parse(rawViews);
            if (Array.isArray(parsed)) {
              views = parsed;
            }
          } catch (e) {}
        }
      }
      if (!Array.isArray(views) || views.length === 0) {
        views = ['list'];
      }

      const currentValue = viewSelect.value;
      const fallbackValue = viewSelect.dataset.current || '';
      viewSelect.innerHTML = '';
      views.forEach((view) => {
        const option = document.createElement('option');
        option.value = view;
        option.textContent = view;
        viewSelect.appendChild(option);
      });

      if (currentValue && views.includes(currentValue)) {
        viewSelect.value = currentValue;
      } else if (fallbackValue && views.includes(fallbackValue)) {
        viewSelect.value = fallbackValue;
      } else if (views.length > 0) {
        viewSelect.value = views[0];
      }
    };

    componentSelect.addEventListener('change', updateOptions);
    updateOptions();
  });
}

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

  initVisualInheritToggles(document);
  initInfoblockViewSelects(document);
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
        initVisualInheritToggles(target);
        initInfoblockViewSelects(target);
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
      if (payload.focus.tab) {
        url.searchParams.set('tab', payload.focus.tab);
        if (payload.focus.tab !== 'infoblocks') {
          url.searchParams.delete('infoblock_tab');
        }
      }
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

// Обрабатываем ответ AJAX-загрузки и сохраняем путь в скрытое поле.
function handleAdminFileUpload(data, input) {
  let payload = data;
  if (typeof data === 'string') {
    try {
      payload = JSON.parse(data);
    } catch (e) {
      payload = null;
    }
  }
  if (!payload || !payload.stored_path || !input) return;

  const hiddenSelector = input.getAttribute('data-upload-hidden');
  if (hiddenSelector) {
    const hidden = document.querySelector(hiddenSelector);
    if (hidden) {
      hidden.value = payload.stored_path;
    }
  }

  const clearSelector = input.getAttribute('data-file-btn-clear');
  if (clearSelector) {
    const clearBtn = document.querySelector(clearSelector);
    if (clearBtn && !clearBtn.dataset.fileUploadBound) {
      clearBtn.dataset.fileUploadBound = 'true';
      clearBtn.addEventListener('click', () => {
        if (hiddenSelector) {
          const hidden = document.querySelector(hiddenSelector);
          if (hidden) hidden.value = '';
        }
        input.disabled = false;
      });
    }
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
      initInfoblockViewSelects(modalBody || document);
      initVisualInheritToggles(modalBody || document);
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

document.addEventListener('click', (e) => {
  const trigger = e.target.closest('.js-file-upload-ajax');
  if (!trigger) return;
  e.preventDefault();
  const inputSelector = trigger.getAttribute('data-file-input');
  const input = inputSelector ? document.querySelector(inputSelector) : null;
  if (!input) return;
  const identifier = input.getAttribute('data-js-advanced-identifier');
  if (!identifier || !window.jQuery || !window.jQuery.SOW || !window.jQuery.SOW.core) {
    return;
  }

  window.jQuery.SOW.core.file_upload.file_upload__ajax_upload(identifier, '');
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
