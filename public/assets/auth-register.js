(() => {
  const forms = document.querySelectorAll('form.js-auth-register');
  if (!forms.length) return;

  const debounce = (fn, ms) => {
    let t;
    return (...args) => {
      clearTimeout(t);
      t = setTimeout(() => fn(...args), ms);
    };
  };

  const setMsg = (el, text, isError) => {
    if (!el) return;
    if (!text) {
      el.hidden = true;
      el.textContent = '';
      return;
    }
    el.hidden = false;
    el.textContent = text;
    el.classList.toggle('form-error', Boolean(isError));
  };

  const check = async (email, nickname) => {
    const params = new URLSearchParams();
    if (email !== undefined) params.set('email', email);
    if (nickname !== undefined) params.set('nickname', nickname);
    const res = await fetch(`/api/v1/auth/availability?${params.toString()}`, {
      credentials: 'same-origin',
    });
    if (!res.ok) return null;
    return res.json();
  };

  forms.forEach((form) => {
    const emailInput = form.querySelector('[data-availability="email"]');
    const nickInput = form.querySelector('[data-availability="nickname"]');
    const emailMsg = form.querySelector('.js-reg-email-msg');
    const nickMsg = form.querySelector('.js-reg-nick-msg');

    const run = debounce(async () => {
      const email = emailInput ? String(emailInput.value || '').trim() : '';
      const nickname = nickInput ? String(nickInput.value || '').trim() : '';
      const data = await check(email, nickname);
      if (!data) return;
      if (data.email_error) {
        setMsg(emailMsg, data.email_error, true);
      } else {
        setMsg(emailMsg, '', false);
      }
      if (data.nickname_error) {
        setMsg(nickMsg, data.nickname_error, true);
      } else {
        setMsg(nickMsg, '', false);
      }
    }, 320);

    if (emailInput) emailInput.addEventListener('input', run);
    if (emailInput) emailInput.addEventListener('blur', run);
    if (nickInput) nickInput.addEventListener('input', run);
    if (nickInput) nickInput.addEventListener('blur', run);

    form.addEventListener('submit', async (e) => {
      const email = emailInput ? String(emailInput.value || '').trim() : '';
      const nickname = nickInput ? String(nickInput.value || '').trim() : '';
      const data = await check(email, nickname);
      if (!data) return;
      if (!data.email_available || data.email_error || !data.nickname_available || data.nickname_error) {
        e.preventDefault();
        if (data.email_error) setMsg(emailMsg, data.email_error, true);
        if (data.nickname_error) setMsg(nickMsg, data.nickname_error, true);
        return;
      }
    });
  });
})();
