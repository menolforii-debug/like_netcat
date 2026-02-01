// Global refresh context (used by data-refresh-url-template)
window.adminRefreshContext = window.adminRefreshContext || {};

function initAdminRefreshContextFromUrl() {
  try {
    const url = new URL(window.location.href);
    const layout = url.searchParams.get('layout');
    const keyword = url.searchParams.get('keyword');
    const tab = url.searchParams.get('tab');
    if (layout !== null) window.adminRefreshContext.layout = layout;
    if (keyword !== null) window.adminRefreshContext.keyword = keyword;
    if (tab !== null) window.adminRefreshContext.tab = tab;
  } catch (e) {}
}

function applyRefreshUrlTemplate(template) {
  const ctx = window.adminRefreshContext || {};
  return (template || '')
    .replaceAll('{layout}', encodeURIComponent(ctx.layout || ''))
    .replaceAll('{keyword}', encodeURIComponent(ctx.keyword || ''))
    .replaceAll('{tab}', encodeURIComponent(ctx.tab || ''));
}

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

function slugifyInfoblockKey(value) {
  if (!value) return '';
  const map = {
    а: 'a', б: 'b', в: 'v', г: 'g', д: 'd', е: 'e', ё: 'e', ж: 'zh', з: 'z', и: 'i', й: 'y',
    к: 'k', л: 'l', м: 'm', н: 'n', о: 'o', п: 'p', р: 'r', с: 's', т: 't', у: 'u', ф: 'f',
    х: 'h', ц: 'c', ч: 'ch', ш: 'sh', щ: 'sch', ъ: '', ы: 'y', ь: '', э: 'e', ю: 'yu', я: 'ya'
  };
  const normalized = value
    .toLowerCase()
    .split('')
    .map((char) => (map[char] !== undefined ? map[char] : char))
    .join('');
  return normalized
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/-+/g, '-')
    .replace(/^-+|-+$/g, '');
}

function initInfoblockKeyAutofill(scope) {
  const root = scope || document;
  root.querySelectorAll('form').forEach((form) => {
    const nameInput = form.querySelector('.js-infoblock-name');
    const keyInput = form.querySelector('.js-infoblock-key');
    const componentSelect = form.querySelector('.js-infoblock-component');
    if (!nameInput || !keyInput) return;

    if (keyInput.value.trim() !== '') {
      keyInput.dataset.manual = '1';
    } else {
      delete keyInput.dataset.manual;
    }

    const isManual = () => keyInput.dataset.manual === '1';
    const getSourceValue = () => {
      if (componentSelect) {
        const selectedOption = componentSelect.selectedOptions[0];
        const componentTitle = selectedOption ? selectedOption.textContent.trim() : '';
        if (componentTitle) {
          return componentTitle;
        }
      }
      return nameInput.value.trim();
    };
    const setKey = () => {
      if (isManual()) return;
      const slug = slugifyInfoblockKey(getSourceValue());
      keyInput.value = slug;
    };

    if (!keyInput.value.trim()) {
      setKey();
    }

    keyInput.addEventListener('input', () => {
      if (keyInput.value.trim() === '') {
        delete keyInput.dataset.manual;
        setKey();
        return;
      }
      keyInput.dataset.manual = '1';
    });
    nameInput.addEventListener('input', () => {
      setKey();
    });
    if (componentSelect) {
      componentSelect.addEventListener('change', () => {
        setKey();
      });
    }
  });
}

function initCodeEditorFullscreen(scope) {
  const root = scope || document;
  const wrappers = root.querySelectorAll('.js-code-editor-wrapper');
  if (!wrappers.length) return;

  const setFullscreen = (wrapper, enabled) => {
    wrapper.classList.toggle('is-fullscreen', enabled);
    const expandButton = wrapper.querySelector('.js-code-editor-expand');
    const collapseButton = wrapper.querySelector('.js-code-editor-collapse');
    if (expandButton) expandButton.classList.toggle('d-none', enabled);
    if (collapseButton) collapseButton.classList.toggle('d-none', !enabled);

    const textarea = wrapper.querySelector('textarea.code-editor');
    const editor = textarea ? textarea._codeMirror : null;
    if (editor) {
      if (enabled) {
        const height = Math.max(240, window.innerHeight - 180);
        editor.setSize(null, height);
      } else {
        editor.setSize(null, 160);
      }
      editor.refresh();
    }

    const hasFullscreen = document.querySelector('.js-code-editor-wrapper.is-fullscreen');
    document.body.classList.toggle('code-editor-fullscreen-open', !!hasFullscreen);
  };

  wrappers.forEach((wrapper) => {
    const expandButton = wrapper.querySelector('.js-code-editor-expand');
    const collapseButton = wrapper.querySelector('.js-code-editor-collapse');
    if (expandButton) {
      expandButton.addEventListener('click', () => setFullscreen(wrapper, true));
    }
    if (collapseButton) {
      collapseButton.addEventListener('click', () => setFullscreen(wrapper, false));
    }
  });

  window.addEventListener('resize', () => {
    document.querySelectorAll('.js-code-editor-wrapper.is-fullscreen').forEach((wrapper) => {
      const textarea = wrapper.querySelector('textarea.code-editor');
      const editor = textarea ? textarea._codeMirror : null;
      if (editor) {
        const height = Math.max(240, window.innerHeight - 180);
        editor.setSize(null, height);
        editor.refresh();
      }
    });
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
  initInfoblockKeyAutofill(document);
  initCodeEditorFullscreen(document);
  initAdminRefreshContextFromUrl();
});

const ADMIN_SNACKBAR_DURATION = 3500;
const ADMIN_SNACKBAR_ID = 'adminSnackbar';
let adminSnackbarTimer = null;

function ensureAdminSnackbarStyles() {
  if (document.getElementById('adminSnackbarStyles')) return;
  const style = document.createElement('style');
  style.id = 'adminSnackbarStyles';
  style.textContent = `
    .admin-snackbar {
      position: fixed;
      top: 20px;
      left: 50%;
      transform: translate(-50%, -10px);
      opacity: 0;
      z-index: 1080;
      padding: 12px 16px;
      border-radius: 8px;
      color: #fff;
      font-weight: 600;
      box-shadow: 0 8px 24px rgba(0, 0, 0, 0.18);
      transition: opacity 180ms ease, transform 180ms ease;
      max-width: min(90vw, 480px);
      text-align: center;
      pointer-events: none;
    }
    .admin-snackbar.is-visible {
      opacity: 1;
      transform: translate(-50%, 0);
    }
    .admin-snackbar.is-success { background: #198754; }
    .admin-snackbar.is-danger { background: #dc3545; }
    .admin-snackbar.is-info { background: #0d6efd; }
  `;
  document.head.appendChild(style);
}

function getAdminSnackbar() {
  let snackbar = document.getElementById(ADMIN_SNACKBAR_ID);
  if (!snackbar) {
    ensureAdminSnackbarStyles();
    snackbar = document.createElement('div');
    snackbar.id = ADMIN_SNACKBAR_ID;
    snackbar.className = 'admin-snackbar';
    document.body.appendChild(snackbar);
  }
  return snackbar;
}

function isAdminModalOpen() {
  const modalEl = document.getElementById('adminModal');
  return !!(modalEl && modalEl.classList.contains('show'));
}

function showGlobalSnackbar(type, message) {
  if (!message) return;
  if (isAdminModalOpen()) return;
  const snackbar = getAdminSnackbar();
  snackbar.textContent = message;
  snackbar.classList.remove('is-success', 'is-danger', 'is-info', 'is-visible');
  if (type === 'danger' || type === 'error') {
    snackbar.classList.add('is-danger');
  } else if (type === 'success') {
    snackbar.classList.add('is-success');
  } else {
    snackbar.classList.add('is-info');
  }
  if (adminSnackbarTimer) {
    clearTimeout(adminSnackbarTimer);
  }
  requestAnimationFrame(() => {
    snackbar.classList.add('is-visible');
    adminSnackbarTimer = setTimeout(() => {
      snackbar.classList.remove('is-visible');
    }, ADMIN_SNACKBAR_DURATION);
  });
}

function getAdminModalParts() {
  const modalEl = document.getElementById('adminModal');
  if (!modalEl) return null;
  const modalBody = modalEl.querySelector('.modal-body');
  if (!modalBody) return null;
  let errorEl = modalBody.querySelector('.admin-modal-error');
  let contentEl = modalBody.querySelector('.admin-modal-content');
  if (!errorEl || !contentEl) {
    modalBody.innerHTML = '';
    errorEl = document.createElement('div');
    errorEl.className = 'admin-modal-error d-none text-danger fw-semibold mb-3';
    errorEl.setAttribute('role', 'alert');
    contentEl = document.createElement('div');
    contentEl.className = 'admin-modal-content';
    modalBody.appendChild(errorEl);
    modalBody.appendChild(contentEl);
  }
  return { modalEl, modalBody, errorEl, contentEl };
}

function showModalError(message) {
  const parts = getAdminModalParts();
  if (!parts || !message) return;
  parts.errorEl.textContent = message;
  parts.errorEl.classList.remove('d-none');
}

function clearModalError() {
  const parts = getAdminModalParts();
  if (!parts) return;
  parts.errorEl.textContent = '';
  parts.errorEl.classList.add('d-none');
}

function refreshAdminBlocks(selectors) {
  if (!Array.isArray(selectors)) return;
  selectors.forEach((selector) => {
    const target = document.querySelector(selector);
    if (!target) return;
    let url = target.getAttribute('data-refresh-url');
    const tpl = target.getAttribute('data-refresh-url-template');
    if (tpl) {
      url = applyRefreshUrlTemplate(tpl);
    }
    if (!url) return;
    fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then((res) => res.text())
      .then((html) => {
        target.innerHTML = html;
        initVisualInheritToggles(target);
        initInfoblockViewSelects(target);
        initInfoblockKeyAutofill(target);
        initCodeEditorFullscreen(target);
        if (window.initCodeEditors) window.initCodeEditors(target);
      });
  });
}

function handleAjaxResponse(payload, context) {
  const isModalForm = context && context.isModalForm;
  const modalEl = context && context.modalEl;
  if (!payload || typeof payload !== 'object') return;
  if (payload.error) {
    if (isModalForm) {
      showModalError(payload.error);
    } else {
      showGlobalSnackbar('danger', payload.error);
    }
    return;
  }
  if (payload.ok) {
    // Support focus.layout and focus.keyword (snippets) in addition to existing focus.section_id/component_id
    if (payload.focus && payload.focus.layout !== undefined) {
      const url = new URL(window.location.href);
      url.searchParams.set('layout', payload.focus.layout);
      if (payload.focus.tab) {
        url.searchParams.set('tab', payload.focus.tab);
      }
      window.history.replaceState({}, '', url);
      window.adminRefreshContext.layout = payload.focus.layout;
      if (payload.focus.tab) window.adminRefreshContext.tab = payload.focus.tab;
    }
    if (payload.focus && payload.focus.keyword !== undefined) {
      const url = new URL(window.location.href);
      url.searchParams.set('keyword', payload.focus.keyword);
      url.searchParams.delete('new');
      window.history.replaceState({}, '', url);
      window.adminRefreshContext.keyword = payload.focus.keyword;
    }

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
    // Optional hard redirect if backend asks
    if (payload.redirect) {
      if (isModalForm && modalEl) {
        // modal will be closed below; redirect after close
        const modalInstance = window.bootstrap ? window.bootstrap.Modal.getOrCreateInstance(modalEl) : null;
        if (modalInstance) modalInstance.hide();
      }
      window.location.href = payload.redirect;
      return;
    }
    if (isModalForm && modalEl) {
      const modalInstance = window.bootstrap ? window.bootstrap.Modal.getOrCreateInstance(modalEl) : null;
      if (payload.message && modalInstance) {
        modalEl.addEventListener(
          'hidden.bs.modal',
          () => {
            showGlobalSnackbar('success', payload.message);
          },
          { once: true }
        );
      }
      if (modalInstance) {
        modalInstance.hide();
      }
    } else if (payload.message) {
      showGlobalSnackbar('success', payload.message);
    }
  }
}

function openAdminModal(url) {
  const parts = getAdminModalParts();
  if (!parts) return;
  const modal = window.bootstrap ? window.bootstrap.Modal.getOrCreateInstance(parts.modalEl) : null;
  const modalTitle = parts.modalEl.querySelector('.modal-title');
  clearModalError();
  parts.contentEl.innerHTML = '<div class="text-muted">Загрузка...</div>';
  if (modalTitle) modalTitle.textContent = 'Загрузка...';

  fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
    .then((res) => res.text())
    .then((html) => {
      parts.contentEl.innerHTML = html;
      const title = parts.contentEl.querySelector('[data-modal-title]');
      if (modalTitle && title) {
        modalTitle.textContent = title.getAttribute('data-modal-title') || 'Редактирование';
        title.remove();
      }
      initInfoblockViewSelects(parts.contentEl || document);
      initInfoblockKeyAutofill(parts.contentEl || document);
      initVisualInheritToggles(parts.contentEl || document);
      initCodeEditorFullscreen(parts.contentEl || document);
      if (window.initCodeEditors) {
        window.initCodeEditors(parts.contentEl || document);
      }
    })
    .catch(() => {
      showModalError('Не удалось загрузить форму.');
      parts.contentEl.innerHTML = '';
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
  const modalEl = document.getElementById('adminModal');
  const isModalForm = !!(modalEl && modalEl.contains(form));
  if (isModalForm) {
    clearModalError();
  }
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
      handleAjaxResponse(payload, { isModalForm, modalEl });
    })
    .catch(() => {
      if (isModalForm) {
        showModalError('Ошибка запроса. Попробуйте еще раз.');
      } else {
        showGlobalSnackbar('danger', 'Ошибка запроса. Попробуйте еще раз.');
      }
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

  const isLink = btn.tagName === 'A';
  if (!isLink) {
    e.preventDefault();
    e.stopPropagation();
  }

  const compId = btn.getAttribute('data-component-id');
  if (!compId) return;

  const item = document.querySelector('.component-tree-item[data-component-id="' + compId + '"]');
  if (!item) return;

  item.classList.toggle('is-open');
});
