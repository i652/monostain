(() => {
  document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape') return;
    if (window.location.hash !== '#auth-popup') return;
    e.preventDefault();
    const path = window.location.pathname + window.location.search;
    window.history.replaceState(null, '', path);
  });
})();
