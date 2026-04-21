(() => {
  const burgerButton = document.getElementById('burger-btn');
  const topNav = document.getElementById('top-nav');
  if (burgerButton && topNav) {
    burgerButton.addEventListener('click', () => {
      topNav.classList.toggle('open');
      burgerButton.setAttribute('aria-expanded', topNav.classList.contains('open') ? 'true' : 'false');
    });
  }

  // auth segmented (each .auth-wrap has its own radio group name)
  document.querySelectorAll('.auth-wrap').forEach((wrap) => {
    const radios = wrap.querySelectorAll('input[type="radio"][name^="auth_section"]');
    if (!radios.length) return;
    const apply = () => {
      const checked = wrap.querySelector('input[type="radio"][name^="auth_section"]:checked');
      const selected = checked?.value || 'login';
      wrap.querySelectorAll('.auth-panel').forEach((p) => {
        p.hidden = p.getAttribute('data-panel') !== selected;
      });
    };
    radios.forEach((r) => r.addEventListener('change', apply));
    apply();
  });

  // panel segmented (posts/pages/users)
  const panelSections = document.querySelectorAll('.panel-section');
  const panelRadios = document.querySelectorAll('input[name="panel_section"]');
  if (panelRadios.length) {
    const apply = () => {
      const selected = document.querySelector('input[name="panel_section"]:checked')?.value || 'posts';
      if (selected === 'users') {
        if (!window.location.pathname.startsWith('/panel/users')) {
          window.location.href = '/panel/users';
        }
        return;
      }
      if (selected === 'categories') {
        if (!window.location.pathname.startsWith('/panel/categories')) {
          window.location.href = '/panel/categories';
        }
        return;
      }
      if (selected === 'media') {
        if (!window.location.pathname.startsWith('/panel/media')) {
          window.location.href = '/panel/media';
        }
        return;
      }
      if (window.location.pathname.startsWith('/panel/users')) {
        window.location.href = `/panel/${encodeURIComponent(selected)}`;
        return;
      }
      if (window.location.pathname.startsWith('/panel/categories')) {
        window.location.href = `/panel/${encodeURIComponent(selected)}`;
        return;
      }
      if (window.location.pathname.startsWith('/panel/media')) {
        window.location.href = `/panel/${encodeURIComponent(selected)}`;
        return;
      }
      panelSections.forEach((p) => {
        const panel = p.getAttribute('data-panel');
        p.hidden = panel !== selected;
      });
      loadAdminLists();
    };
    panelRadios.forEach((r) => r.addEventListener('change', apply));
    apply();
  }

  const button = document.getElementById('load-more');
  const list = document.getElementById('post-list');
  const listCategorySlug = list ? list.getAttribute('data-category-slug') : null;
  let offset = list ? list.querySelectorAll('.post-card').length : 0;

  const formatDate = (raw) => {
    const date = new Date(raw);
    if (Number.isNaN(date.getTime())) return raw || '';
    const dd = String(date.getDate()).padStart(2, '0');
    const mm = String(date.getMonth() + 1).padStart(2, '0');
    const yyyy = date.getFullYear();
    const hh = String(date.getHours()).padStart(2, '0');
    const min = String(date.getMinutes()).padStart(2, '0');
    return `${dd}.${mm}.${yyyy} в ${hh}:${min}`;
  };

  if (button && list) {
    button.addEventListener('click', async () => {
      button.disabled = true;
      button.setAttribute('aria-busy', 'true');
      try {
        const catQ = listCategorySlug ? `&category=${encodeURIComponent(listCategorySlug)}` : '';
        const response = await fetch(`/api/v1/public/posts?offset=${offset}&limit=6${catQ}`);
        const payload = await response.json();
        const items = payload.items || [];

        for (let i = 0; i < items.length; i++) {
          const item = items[i];
          const globalIndex = offset + i;
          const mod = globalIndex % 3;
          const card = document.createElement('article');
          card.className = mod === 0 ? 'post-card post-card--wide' : 'post-card post-card--narrow';
          const date = formatDate(item.published_at || '');
          const cat = String(item.category_slug || 'news');
          const catName = String(item.category_name || '');
          const href = `/${cat}/${String(item.slug)}`;
          const prev = item.preview_text || item.seo_description || '';
          card.innerHTML = `
          <div class="post-card-head">
            <h2 class="post-title"><a class="post-link" href="${href}">${escapeHtml(item.title)}</a></h2>
          </div>
          <div class="post-preview"></div>
          <div class="post-card-foot">
            <small class="date-pill">${escapeHtml(date)}</small>
            <a class="post-category-pill" href="/${escapeHtml(cat)}">${escapeHtml(catName)}</a>
          </div>
        `;
          const prevEl = card.querySelector('.post-preview');
          if (prevEl) prevEl.innerHTML = prev;
          card.setAttribute('data-post-id', item.id);
          card.setAttribute('data-post-slug', item.slug);
          card.setAttribute('data-category-slug', cat);
          list.appendChild(card);
        }

        offset += items.length;
        if (!payload.has_more || items.length < 6) {
          button.style.display = 'none';
        }
      } finally {
        button.disabled = false;
        button.setAttribute('aria-busy', 'false');
      }
    });
  }

  // share buttons
  document.addEventListener('click', async (e) => {
    const share = e.target && e.target.closest && e.target.closest('.js-share');
    if (!share) return;
    let url = window.location.href;
    const card = share.closest('[data-post-slug]');
    if (card) {
      const slug = card.getAttribute('data-post-slug') || '';
      const cat = card.getAttribute('data-category-slug') || '';
      if (slug && cat) {
        url = `${window.location.origin}/${cat}/${slug}`;
      }
    }
    try {
      await navigator.clipboard.writeText(url);
      toast('Ссылка на пост скопирована');
    } catch {
      try {
        const ta = document.createElement('textarea');
        ta.value = url;
        ta.style.position = 'fixed';
        ta.style.left = '-9999px';
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        ta.remove();
        toast('Ссылка на пост скопирована');
      } catch {
        toast('Не удалось скопировать ссылку');
      }
    }
  });

  const postsAdminState = {
    offset: 0,
    loaded: false,
  };

  async function loadAdminLists() {
    const postsWrap = document.getElementById('posts-panel-list');
    const pagesWrap = document.getElementById('pages-panel-list');
    if (!postsWrap || !pagesWrap) return;
    const selected = document.querySelector('input[name="panel_section"]:checked')?.value || 'posts';

    if (!postsAdminState.loaded) {
      postsAdminState.loaded = true;
      await appendAdminPosts(postsWrap, true);
    }

    if (selected === 'pages' && !pagesWrap.dataset.loaded) {
      const pagesRes = await fetch('/api/v1/pages', { credentials: 'same-origin' });
      const pages = await pagesRes.json();
      pagesWrap.innerHTML = (pages.items || []).map((p) => {
        return `<div class="panel-list-item"><div class="panel-list-item-line"><div class="panel-item-main"><a href="/${escapeHtml(p.slug)}">${escapeHtml(p.title)}</a></div><div class="panel-list-actions"><a class="btn btn-outline" href="/panel/pages/${Number(p.id)}/edit">Редактировать</a><form method="post" action="/panel/pages/${Number(p.id)}/delete" data-confirm="Удалить страницу?"><button class="btn btn-danger" type="submit">Удалить</button></form></div></div></div>`;
      }).join('');
      pagesWrap.dataset.loaded = '1';
    }
  }

  async function appendAdminPosts(postsWrap, reset = false) {
    const moreButton = document.getElementById('posts-panel-more');
    if (reset) {
      postsAdminState.offset = 0;
      postsWrap.innerHTML = '';
      if (moreButton) moreButton.style.display = '';
    }
    const res = await fetch(`/api/v1/posts?offset=${postsAdminState.offset}&limit=10`, { credentials: 'same-origin' });
    const posts = await res.json();
    const items = posts.items || [];
    const html = items.map((p) => {
      const scheduled = p.status === 'draft' && p.published_at && new Date(p.published_at).getTime() > Date.now();
      const statusText = p.status === 'published' ? 'Опубликовано' : 'Черновик';
      const publishedLabel = p.published_at || p.created_at ? formatDate(p.published_at || p.created_at) : '';
      const cat = String(p.category_slug || 'news');
      const href = `/${cat}/${String(p.slug)}`;
      return `<div class="panel-list-item"><div class="panel-list-item-line"><div class="panel-item-main"><a href="${href}">${escapeHtml(p.title)}</a>${publishedLabel ? `<span class="meta">${escapeHtml(publishedLabel)}</span>` : ''}${scheduled ? `<span class="meta">Запланирован: ${escapeHtml(formatDate(p.published_at))}</span>` : ''}</div><div class="panel-list-actions"><span class="status-badge">${statusText}</span><a class="btn btn-outline" href="/panel/posts/${Number(p.id)}/edit">Редактировать</a><form method="post" action="/panel/posts/${Number(p.id)}/delete" data-confirm="Удалить пост?"><button class="btn btn-danger" type="submit">Удалить</button></form></div></div></div>`;
    }).join('');
    postsWrap.insertAdjacentHTML('beforeend', html);
    postsAdminState.offset += items.length;
    if (moreButton && (!posts.has_more || items.length < 10)) {
      moreButton.style.display = 'none';
    }
  }

  const postsAdminMore = document.getElementById('posts-panel-more');
  if (postsAdminMore) {
    postsAdminMore.addEventListener('click', async () => {
      const postsWrap = document.getElementById('posts-panel-list');
      if (!postsWrap) return;
      postsAdminMore.disabled = true;
      await appendAdminPosts(postsWrap, false);
      postsAdminMore.disabled = false;
    });
  }

  if (document.getElementById('posts-panel-list')) {
    loadAdminLists();
  }

  function toast(text) {
    let el = document.querySelector('.toast');
    if (!el) {
      el = document.createElement('div');
      el.className = 'toast';
      document.body.appendChild(el);
    }
    el.textContent = text;
    el.classList.add('show');
    window.setTimeout(() => el.classList.remove('show'), 2000);
  }

  const params = new URLSearchParams(window.location.search);
  const notice = params.get('notice');
  const error = params.get('error');
  if (notice) {
    toast(notice);
    params.delete('notice');
  }
  if (error) {
    toast(error);
    params.delete('error');
  }
  if (notice || error) {
    const qs = params.toString();
    const clean = `${window.location.pathname}${qs ? `?${qs}` : ''}${window.location.hash || ''}`;
    window.history.replaceState({}, '', clean);
  }

  function escapeHtml(value) {
    return String(value)
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');
  }

  const mediaBtn = document.getElementById('media-load-more');
  const mediaGrid = document.getElementById('media-photostream');
  if (mediaBtn && mediaGrid) {
    let mediaOffset = mediaGrid.querySelectorAll('.media-photostream__cell').length;
    mediaBtn.addEventListener('click', async () => {
      mediaBtn.disabled = true;
      let res;
      let data;
      try {
        res = await fetch(`/api/v1/media?offset=${mediaOffset}&limit=10`, { credentials: 'same-origin' });
        data = await res.json();
      } catch {
        mediaBtn.disabled = false;
        return;
      }
      if (!res.ok) {
        mediaBtn.disabled = false;
        return;
      }
      const items = data.items || [];
      for (let i = 0; i < items.length; i++) {
        const m = items[i];
        const id = Number(m.id);
        const kind = String(m.kind || '');
        const url = `/media/${id}`;
        const cell = document.createElement('div');
        cell.className = 'media-photostream__cell';
        cell.dataset.mediaId = String(id);
        const thumb = document.createElement('div');
        thumb.className = 'media-photostream__thumb';
        const inner = document.createElement('div');
        inner.className = 'media-photostream__inner';
        if (kind === 'video') {
          const v = document.createElement('video');
          v.src = url;
          v.preload = 'metadata';
          v.muted = true;
          v.playsInline = true;
          inner.appendChild(v);
        } else if (kind === 'file') {
          const f = document.createElement('div');
          f.className = 'media-thumb-file';
          f.setAttribute('aria-hidden', 'true');
          f.textContent = 'PDF';
          inner.appendChild(f);
        } else {
          const img = document.createElement('img');
          img.src = url;
          img.alt = 'Превью';
          img.loading = 'lazy';
          img.decoding = 'async';
          inner.appendChild(img);
        }
        const del = document.createElement('button');
        del.type = 'button';
        del.className = 'media-photostream__delete';
        del.dataset.mediaDelete = String(id);
        del.textContent = 'Удалить';
        thumb.appendChild(inner);
        thumb.appendChild(del);
        cell.appendChild(thumb);
        const title = String(m.source_title || '').trim();
        let display = String(m.source_title_display || '').trim();
        if (!display && title) {
          const chars = [...title];
          display = chars.length <= 20 ? title : `${chars.slice(0, 20).join('')}...`;
        }
        const srcUrl = String(m.source_url || '').trim();
        const extra = Number(m.source_extra_count) || 0;
        if (display && srcUrl) {
          const a = document.createElement('a');
          a.className = 'media-photostream__caption';
          a.href = srcUrl;
          a.title = extra > 0 ? `${title} (ещё материалов: ${extra})` : title;
          const span = document.createElement('span');
          span.className = 'media-photostream__caption-text';
          span.textContent = display;
          a.appendChild(span);
          if (extra > 0) {
            const more = document.createElement('span');
            more.className = 'media-photostream__caption-more';
            more.setAttribute('aria-hidden', 'true');
            more.textContent = `+${extra}`;
            a.appendChild(more);
          }
          cell.appendChild(a);
        }
        mediaGrid.appendChild(cell);
      }
      mediaOffset += items.length;
      if (!data.has_more) mediaBtn.style.display = 'none';
      mediaBtn.disabled = false;
    });
  }
})();
