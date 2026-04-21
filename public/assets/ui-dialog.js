(() => {
  const ensure = () => {
    let root = document.getElementById('ui-dialog');
    if (root) return root;
    root = document.createElement('div');
    root.id = 'ui-dialog';
    root.className = 'ui-dialog';
    root.setAttribute('hidden', '');
    root.setAttribute('aria-hidden', 'true');
    root.innerHTML = `
      <div class="ui-dialog__backdrop" role="presentation"></div>
      <div class="ui-dialog__box" role="dialog" aria-modal="true" aria-labelledby="ui-dialog-msg">
        <p id="ui-dialog-msg" class="ui-dialog__msg"></p>
        <input type="url" id="ui-dialog-input" class="ui-dialog__input" autocomplete="off" hidden />
        <div class="ui-dialog__actions">
          <button type="button" class="btn btn-outline" id="ui-dialog-cancel">Отмена</button>
          <button type="button" class="btn btn-outline" id="ui-dialog-ok">OK</button>
        </div>
      </div>
    `;
    document.body.appendChild(root);
    return root;
  };

  let resolvePromise = null;
  let mode = 'alert';

  const hide = () => {
    const root = document.getElementById('ui-dialog');
    if (!root) return;
    root.setAttribute('hidden', '');
    root.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('ui-dialog-open');
    resolvePromise = null;
  };

  const show = () => {
    const root = ensure();
    root.removeAttribute('hidden');
    root.setAttribute('aria-hidden', 'false');
    document.body.classList.add('ui-dialog-open');
  };

  const wireOnce = () => {
    const root = ensure();
    if (root.dataset.wired) return;
    root.dataset.wired = '1';
    const msgEl = root.querySelector('#ui-dialog-msg');
    const inputEl = root.querySelector('#ui-dialog-input');
    const okBtn = root.querySelector('#ui-dialog-ok');
    const cancelBtn = root.querySelector('#ui-dialog-cancel');
    const backdrop = root.querySelector('.ui-dialog__backdrop');

    const finish = (value) => {
      const r = resolvePromise;
      hide();
      if (r) r(value);
    };

    okBtn.addEventListener('click', () => {
      if (mode === 'prompt') {
        finish(inputEl.value.trim());
      } else if (mode === 'confirm') {
        finish(true);
      } else {
        finish(undefined);
      }
    });

    cancelBtn.addEventListener('click', () => {
      if (mode === 'confirm') finish(false);
      if (mode === 'prompt') finish(null);
    });

    backdrop.addEventListener('click', () => {
      if (mode === 'confirm') finish(false);
      if (mode === 'prompt') finish(null);
      if (mode === 'alert') finish(undefined);
    });

    root.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        if (mode === 'confirm') finish(false);
        if (mode === 'prompt') finish(null);
        if (mode === 'alert') finish(undefined);
      }
    });
  };

  wireOnce();

  window.uiAlert = (message) =>
    new Promise((resolve) => {
      ensure();
      const root = document.getElementById('ui-dialog');
      const msgEl = root.querySelector('#ui-dialog-msg');
      const inputEl = root.querySelector('#ui-dialog-input');
      const okBtn = root.querySelector('#ui-dialog-ok');
      const cancelBtn = root.querySelector('#ui-dialog-cancel');
      mode = 'alert';
      msgEl.textContent = String(message ?? '');
      inputEl.hidden = true;
      cancelBtn.hidden = true;
      okBtn.textContent = 'OK';
      resolvePromise = resolve;
      show();
      okBtn.focus();
    });

  window.uiConfirm = (message) =>
    new Promise((resolve) => {
      ensure();
      const root = document.getElementById('ui-dialog');
      const msgEl = root.querySelector('#ui-dialog-msg');
      const inputEl = root.querySelector('#ui-dialog-input');
      const okBtn = root.querySelector('#ui-dialog-ok');
      const cancelBtn = root.querySelector('#ui-dialog-cancel');
      mode = 'confirm';
      msgEl.textContent = String(message ?? '');
      inputEl.hidden = true;
      cancelBtn.hidden = false;
      okBtn.textContent = 'OK';
      cancelBtn.textContent = 'Отмена';
      resolvePromise = resolve;
      show();
      okBtn.focus();
    });

  window.uiPrompt = (message, defaultValue = '', inputType = 'text') =>
    new Promise((resolve) => {
      ensure();
      const root = document.getElementById('ui-dialog');
      const msgEl = root.querySelector('#ui-dialog-msg');
      const inputEl = root.querySelector('#ui-dialog-input');
      const okBtn = root.querySelector('#ui-dialog-ok');
      const cancelBtn = root.querySelector('#ui-dialog-cancel');
      mode = 'prompt';
      msgEl.textContent = String(message ?? '');
      inputEl.type = inputType === 'url' ? 'url' : 'text';
      inputEl.hidden = false;
      inputEl.value = defaultValue;
      cancelBtn.hidden = false;
      okBtn.textContent = 'OK';
      cancelBtn.textContent = 'Отмена';
      resolvePromise = resolve;
      show();
      inputEl.focus();
      inputEl.select();
    });

  document.addEventListener('submit', async (e) => {
    const form = e.target;
    if (!(form instanceof HTMLFormElement)) return;
    const msg = form.getAttribute('data-confirm');
    if (!msg) return;
    e.preventDefault();
    const ok = await window.uiConfirm(msg);
    if (ok) {
      form.removeAttribute('data-confirm');
      form.submit();
    }
  });
})();
