(() => {
  const editors = document.querySelectorAll('.editor-area[data-field]');
  if (!editors.length) return;

  const form = editors[0].closest('form');
  if (!form) return;

  const alertUi = (msg) => {
    if (window.uiAlert) return window.uiAlert(msg);
    window.alert(msg);
    return Promise.resolve();
  };

  let activeEditor = editors[0];
  editors.forEach((ed) => {
    ed.addEventListener('focus', () => {
      activeEditor = ed;
    });
  });

  document.addEventListener(
    'mousedown',
    (e) => {
      const btn = e.target && e.target.closest && e.target.closest('.editor-toolbar .js-ed, .editor-toolbar .js-link');
      if (!btn || !form.contains(btn)) return;
      e.preventDefault();
    },
    true
  );

  const cloneRangeInEditor = (editor) => {
    const sel = window.getSelection();
    if (!sel || sel.rangeCount === 0) return null;
    const r = sel.getRangeAt(0);
    if (!editor.contains(r.commonAncestorContainer)) return null;
    return r.cloneRange();
  };

  const applySelectionRange = (editor, range) => {
    editor.focus();
    const sel = window.getSelection();
    sel.removeAllRanges();
    if (range) {
      try {
        sel.addRange(range);
        return;
      } catch {
        /* fall through */
      }
    }
    const end = document.createRange();
    end.selectNodeContents(editor);
    end.collapse(false);
    sel.addRange(end);
  };

  const syncHidden = () => {
    editors.forEach((ed) => {
      const field = ed.getAttribute('data-field');
      const input = form.querySelector(`input[type="hidden"][name="${field}"]`);
      if (input) input.value = ed.innerHTML;
    });
  };

  syncHidden();
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    syncHidden();
    const titleEl = form.querySelector('[name="title"]');
    const titleOk = titleEl && String(titleEl.value || '').trim() !== '';
    const fullHidden = form.querySelector('[name="full_text"]');
    const fullHtml = fullHidden ? String(fullHidden.value || '') : '';
    const fullPlain = fullHtml.replace(/<[^>]+>/g, '').trim();
    const fullMedia = /<(img|video|picture)\b/i.test(fullHtml);
    if (!titleOk || (!fullPlain && !fullMedia)) {
      await alertUi('Заполните заголовок и полный текст поста (текст или изображение/видео).');
      return;
    }
    const prevHidden = form.querySelector('[name="preview_text"]');
    if (prevHidden && form.querySelector('[name="category_id"]')) {
      const prevHtml = String(prevHidden.value || '');
      const prevPlain = prevHtml.replace(/<[^>]+>/g, '').trim();
      const prevMedia = /<(img|video|picture)\b/i.test(prevHtml);
      if (!prevPlain && !prevMedia) {
        await alertUi('Заполните превью: текст или изображение/видео.');
        return;
      }
    }
    if (typeof window.stainClearPostDraft === 'function') {
      window.stainClearPostDraft();
    }
    form.submit();
  });

  document.addEventListener('click', async (e) => {
    const btn = e.target && e.target.closest && e.target.closest('.js-ed');
    if (!btn) return;
    e.preventDefault();
    const cmd = btn.getAttribute('data-cmd');
    const arg = btn.getAttribute('data-arg') || null;
    document.execCommand(cmd, false, arg);
    syncHidden();
  });

  document.addEventListener('click', async (e) => {
    const btn = e.target && e.target.closest && e.target.closest('.js-link');
    if (!btn || !form.contains(btn)) return;
    e.preventDefault();
    const toolbar = btn.closest('.editor-toolbar');
    const field = toolbar?.getAttribute('data-editor');
    const editor = field ? form.querySelector(`.editor-area[data-field="${field}"]`) : activeEditor;
    if (!editor) return;

    const rangeBefore = cloneRangeInEditor(editor);
    const promptFn = window.uiPrompt || ((msg, def) => Promise.resolve(window.prompt(msg, def)));
    const raw = await promptFn('Адрес ссылки (URL или путь, например /page)', '', 'text');
    if (raw === null || raw === undefined) return;
    let url = String(raw).trim();
    if (url === '') return;

    if (!/^https?:\/\//i.test(url) && !url.startsWith('/') && !url.startsWith('#') && !url.startsWith('mailto:')) {
      url = `https://${url}`;
    }

    applySelectionRange(editor, rangeBefore);
    try {
      document.execCommand('createLink', false, url);
    } catch {
      await alertUi('Не удалось вставить ссылку. Выделите текст и попробуйте снова.');
      return;
    }
    syncHidden();
  });

  const mediaCards = new Set();

  const mediaIdFromUrl = (url) => {
    const m = String(url || '').match(/\/media\/([0-9]+)/);
    return m ? Number(m[1]) : null;
  };

  const removeMediaNodesFromEditors = (mediaId) => {
    editors.forEach((ed) => {
      ed.querySelectorAll(`img[src="/media/${mediaId}"]`).forEach((node) => node.remove());
      ed.querySelectorAll(`a[href="/media/${mediaId}"]`).forEach((node) => node.remove());
      ed.querySelectorAll(`video[src="/media/${mediaId}"]`).forEach((node) => node.remove());
      ed.querySelectorAll(`video source[src="/media/${mediaId}"]`).forEach((node) => {
        const video = node.closest('video');
        if (video) {
          video.remove();
        } else {
          node.remove();
        }
      });
    });
  };

  const buildCard = (field, mediaId, url, kind, label) => {
    const card = document.createElement('div');
    card.className = 'media-item';
    card.dataset.mediaId = String(mediaId);
    card.dataset.field = field;

    if (kind === 'video') {
      const video = document.createElement('video');
      video.src = url;
      video.controls = true;
      video.preload = 'metadata';
      card.appendChild(video);
    } else if (kind === 'file') {
      const box = document.createElement('div');
      box.className = 'media-item-file';
      box.textContent = label || 'PDF';
      card.appendChild(box);
    } else {
      const img = document.createElement('img');
      img.src = url;
      img.alt = 'preview';
      card.appendChild(img);
    }

    const removeBtn = document.createElement('button');
    removeBtn.type = 'button';
    removeBtn.className = 'media-delete';
    removeBtn.textContent = 'Удалить';
    removeBtn.dataset.mediaDelete = String(mediaId);
    card.appendChild(removeBtn);

    return card;
  };

  const addMediaCard = (field, mediaId, url, kind, label) => {
    const key = `${field}:${mediaId}`;
    if (mediaCards.has(key)) return;
    const list = form.querySelector(`.media-preview[data-media-list-for="${field}"]`);
    if (!list) return;
    list.appendChild(buildCard(field, mediaId, url, kind, label));
    mediaCards.add(key);
  };

  const appendMediaToEditorBottom = (editor, url, kind, linkLabel) => {
    editor.focus();
    if (kind === 'video') {
      editor.insertAdjacentHTML('beforeend', `<p><video controls src="${url}"></video></p>`);
    } else if (kind === 'file') {
      const safe =
        String(linkLabel || 'Файл')
          .replace(/&/g, '&amp;')
          .replace(/</g, '&lt;')
          .replace(/"/g, '&quot;');
      editor.insertAdjacentHTML('beforeend', `<p><a href="${url}">${safe}</a></p>`);
    } else {
      editor.insertAdjacentHTML('beforeend', `<p><img src="${url}" alt="Изображение"></p>`);
    }
  };

  const rescanMediaFromEditors = () => {
    mediaCards.clear();
    form.querySelectorAll('.media-preview').forEach((list) => {
      list.innerHTML = '';
    });

    editors.forEach((ed) => {
      const field = ed.getAttribute('data-field');
      if (!field) return;

      ed.querySelectorAll('img[src^="/media/"]').forEach((img) => {
        const mediaId = mediaIdFromUrl(img.getAttribute('src'));
        if (mediaId) {
          addMediaCard(field, mediaId, `/media/${mediaId}`, 'image');
        }
      });

      ed.querySelectorAll('video[src^="/media/"]').forEach((video) => {
        const mediaId = mediaIdFromUrl(video.getAttribute('src'));
        if (mediaId) {
          addMediaCard(field, mediaId, `/media/${mediaId}`, 'video');
        }
      });

      ed.querySelectorAll('video source[src^="/media/"]').forEach((source) => {
        const mediaId = mediaIdFromUrl(source.getAttribute('src'));
        if (mediaId) {
          addMediaCard(field, mediaId, `/media/${mediaId}`, 'video');
        }
      });

      ed.querySelectorAll('a[href^="/media/"]').forEach((aEl) => {
        const mediaId = mediaIdFromUrl(aEl.getAttribute('href'));
        if (mediaId) {
          addMediaCard(field, mediaId, `/media/${mediaId}`, 'file', (aEl.textContent || 'PDF').trim());
        }
      });
    });
  };

  rescanMediaFromEditors();
  form.addEventListener('stain-draft-restored', rescanMediaFromEditors);

  document.querySelectorAll('.js-img').forEach((input) => {
    input.addEventListener('change', async () => {
      const file = input.files && input.files[0];
      if (!file) return;

      const toolbar = input.closest('.editor-toolbar');
      const field = toolbar?.getAttribute('data-editor') || activeEditor.getAttribute('data-field');
      if (!field) {
        input.value = '';
        return;
      }
      const targetEditor = form.querySelector(`.editor-area[data-field="${field}"]`) || activeEditor;

      const fd = new FormData();
      fd.append('file', file);
      const res = await fetch('/api/v1/media', { method: 'POST', body: fd, credentials: 'same-origin' });
      const data = await res.json();
      if (!res.ok || !data.url || !data.id) {
        await alertUi(data.error || 'Не удалось загрузить файл');
        input.value = '';
        return;
      }

      let kind = data.kind === 'video' ? 'video' : 'image';
      if (data.kind === 'file') kind = 'file';
      const label = kind === 'file' ? String(data.original_name || '').trim() || 'PDF' : '';
      appendMediaToEditorBottom(targetEditor, data.url, kind, label);
      addMediaCard(field, Number(data.id), data.url, kind, label);
      syncHidden();
      input.value = '';
    });
  });

  document.addEventListener('click', async (e) => {
    const btn = e.target && e.target.closest && e.target.closest('[data-media-delete]');
    if (!btn) return;
    e.preventDefault();
    const mediaId = Number(btn.getAttribute('data-media-delete'));
    const ok = window.uiConfirm ? await window.uiConfirm('Удалить файл?') : window.confirm('Удалить файл?');
    if (!mediaId || !ok) return;

    const res = await fetch(`/api/v1/media/${mediaId}`, { method: 'DELETE', credentials: 'same-origin' });
    const data = await res.json().catch(() => ({}));
    if (res.status === 409 && data.code === 'MEDIA_IN_USE') {
      const refs = data.references || [];
      const lines = refs.map((r) => {
        const kind = r.content_type === 'page' ? 'Страница' : 'Пост';
        return `${kind}: ${r.title}`;
      });
      const msgParts = [data.error];
      if (lines.length) {
        msgParts.push('', ...lines);
      }
      await alertUi(msgParts.join('\n'));
      return;
    }
    if (!res.ok || !data.ok) {
      await alertUi(data.error || 'Не удалось удалить файл');
      return;
    }

    removeMediaNodesFromEditors(mediaId);
    form.querySelectorAll(`.media-item[data-media-id="${mediaId}"]`).forEach((node) => {
      const key = `${node.getAttribute('data-field')}:${mediaId}`;
      mediaCards.delete(key);
      node.remove();
    });
    syncHidden();
  });
})();
