(() => {
  const STORAGE_KEY = 'stain-theme-mode';
  const toggles = document.querySelectorAll('[data-theme-mode]');
  const root = document.documentElement;

  const paintToggle = (mode) => {
    toggles.forEach((btn) => {
      btn.classList.toggle('is-active', btn.getAttribute('data-theme-mode') === mode);
    });
  };

  const applyThemeMode = (mode) => {
    root.setAttribute('data-theme', mode);
    paintToggle(mode);
  };

  const stored = localStorage.getItem(STORAGE_KEY);
  const initial = stored === 'day' || stored === 'night' ? stored : 'day';
  applyThemeMode(initial);
  if (toggles.length) {
    toggles.forEach((btn) => {
      btn.addEventListener('click', () => {
        const mode = btn.getAttribute('data-theme-mode') || 'day';
        if (!['day', 'night'].includes(mode)) return;
        localStorage.setItem(STORAGE_KEY, mode);
        applyThemeMode(mode);
      });
    });
  }
})();
