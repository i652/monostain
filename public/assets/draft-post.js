(() => {
  const form = document.querySelector('form[data-post-draft-form]');
  if (!form) return;

  const key = form.getAttribute('data-draft-key') || 'new';
  const STORAGE_KEY = `stain_draft_post_${key}`;

  const syncEditorsToHidden = () => {
    form.querySelectorAll('.editor-area[data-field]').forEach((ed) => {
      const field = ed.getAttribute('data-field');
      const input = form.querySelector(`input[type="hidden"][name="${field}"]`);
      if (input) input.value = ed.innerHTML;
    });
  };

  const gatherState = () => {
    syncEditorsToHidden();
    const state = { v: 2, savedAt: Date.now() };
    const pageUpdated = form.dataset.postUpdatedAt || '';
    if (pageUpdated) state.serverPostUpdatedAt = pageUpdated;
    const els = form.querySelectorAll('input, select, textarea');
    els.forEach((el) => {
      const n = el.name;
      if (!n || el.type === 'file' || el.type === 'submit' || el.type === 'button') return;
      if (el.type === 'checkbox' || el.type === 'radio') {
        if (el.checked) state[n] = el.value;
        return;
      }
      state[n] = el.value;
    });
    form.querySelectorAll('.editor-area[data-field]').forEach((ed) => {
      const f = ed.getAttribute('data-field');
      if (f) state[`__html_${f}`] = ed.innerHTML;
    });
    return state;
  };

  const applyState = (state) => {
    if (!state || (state.v !== 1 && state.v !== 2)) return;
    Object.keys(state).forEach((k) => {
      if (k.startsWith('__html_') || k === 'v' || k === 'savedAt' || k === 'serverPostUpdatedAt') return;
      const el = form.elements.namedItem(k);
      if (el && !el.classList?.contains('editor-area')) {
        const node = Array.isArray(el) ? el[0] : el;
        if (node && node.type !== 'hidden') node.value = state[k];
      }
    });
    form.querySelectorAll('.editor-area[data-field]').forEach((ed) => {
      const f = ed.getAttribute('data-field');
      const html = state[`__html_${f}`];
      if (html != null) ed.innerHTML = html;
    });
    syncEditorsToHidden();
    form.dispatchEvent(new CustomEvent('stain-draft-restored', { bubbles: true }));
  };

  const hasMeaningfulContent = (state) => {
    if (!state) return false;
    const title = String(state.title || '').trim();
    const full = String(state.full_text || '').replace(/<[^>]+>/g, '').trim();
    const fullMedia = /<(img|video|picture)\b/i.test(String(state.full_text || ''));
    const prev = String(state.preview_text || '').replace(/<[^>]+>/g, '').trim();
    const prevMedia = /<(img|video|picture)\b/i.test(String(state.preview_text || ''));
    return title.length > 0 || full.length > 0 || fullMedia || prev.length > 0 || prevMedia;
  };

  const tryRestore = async () => {
    let raw;
    try {
      raw = localStorage.getItem(STORAGE_KEY);
    } catch {
      return;
    }
    if (!raw) return;
    let state;
    try {
      state = JSON.parse(raw);
    } catch {
      localStorage.removeItem(STORAGE_KEY);
      return;
    }
    if (!hasMeaningfulContent(state)) {
      localStorage.removeItem(STORAGE_KEY);
      return;
    }
    const pageUpdatedAt = form.dataset.postUpdatedAt || '';
    let msg =
      'Найден локальный черновик поста (несохранённый в браузере). Восстановить?';
    if (pageUpdatedAt) {
      const draftAt = state.serverPostUpdatedAt || '';
      if (draftAt !== pageUpdatedAt) {
        msg =
          'Пост на сервере изменён после сохранения этого черновика (или черновик устарел). Восстановить несохранённую копию из браузера?';
      }
    }
    const ok = window.uiConfirm
      ? await window.uiConfirm(msg)
      : window.confirm(msg);
    if (ok) {
      applyState(state);
    } else {
      try {
        localStorage.removeItem(STORAGE_KEY);
      } catch {
        /* ignore */
      }
    }
  };

  tryRestore();

  let saveTimer = null;
  const scheduleSave = () => {
    window.clearTimeout(saveTimer);
    saveTimer = window.setTimeout(() => {
      try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(gatherState()));
      } catch {
        /* quota */
      }
    }, 800);
  };

  form.addEventListener('input', scheduleSave);
  form.addEventListener('change', scheduleSave);
  form.querySelectorAll('.editor-area[data-field]').forEach((ed) => {
    ed.addEventListener('blur', scheduleSave);
  });

  window.stainClearPostDraft = () => {
    try {
      localStorage.removeItem(STORAGE_KEY);
    } catch {
      /* ignore */
    }
  };

  window.stainSavePostDraftNow = () => {
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(gatherState()));
    } catch {
      /* ignore */
    }
  };
})();
