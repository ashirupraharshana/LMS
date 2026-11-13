(() => {
  const sidebar = document.getElementById('portalSidebar');
  const toggle = document.getElementById('portalMenuToggle');
  const backdrop = document.getElementById('portalBackdrop');
  if (!sidebar || !toggle || !backdrop) return;
  const close = () => { sidebar.classList.remove('is-open'); backdrop.hidden = true;
    toggle.setAttribute('aria-expanded', 'false'); toggle.setAttribute('aria-label','Open sidebar'); };
  toggle.addEventListener('click', () => { const active = sidebar.classList.toggle('is-open');
    backdrop.hidden = !active; toggle.setAttribute('aria-expanded', String(active));
    toggle.setAttribute('aria-label', active ? 'Close sidebar' : 'Open sidebar'); });
  backdrop.addEventListener('click', close);
  document.addEventListener('keydown', event => { if (event.key === 'Escape') close(); });
  sidebar.querySelectorAll('nav a').forEach(link => link.addEventListener('click', close));
  window.addEventListener('resize', () => { if (window.innerWidth > 980) close(); });
})();
