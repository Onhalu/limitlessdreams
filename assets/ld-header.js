/* Mobile drawer: open/close, Escape, backdrop, focus. Markup ids match the hub header. */
(function () {
  var drawer = document.getElementById('mobile-drawer');
  var backdrop = document.getElementById('mobile-backdrop');
  var toggle = document.getElementById('menu-toggle');
  var closeBtn = document.getElementById('menu-close');
  var iconOpen = document.getElementById('icon-open');
  var iconClose = document.getElementById('icon-close');
  var lastFocus = null;

  if (!drawer || !toggle) return;

  function openMenu() {
    lastFocus = document.activeElement;
    drawer.hidden = false;
    backdrop.hidden = false;
    requestAnimationFrame(function () {
      drawer.setAttribute('data-open', 'true');
      backdrop.setAttribute('data-open', 'true');
    });
    toggle.setAttribute('aria-expanded', 'true');
    document.body.classList.add('menu-open');
    iconOpen.classList.add('hidden');
    iconClose.classList.remove('hidden');
    closeBtn.focus();
  }

  function closeMenu() {
    drawer.setAttribute('data-open', 'false');
    backdrop.setAttribute('data-open', 'false');
    toggle.setAttribute('aria-expanded', 'false');
    document.body.classList.remove('menu-open');
    iconOpen.classList.remove('hidden');
    iconClose.classList.add('hidden');
    setTimeout(function () {
      if (drawer.getAttribute('data-open') === 'false') {
        drawer.hidden = true;
        backdrop.hidden = true;
      }
    }, 250);
    if (lastFocus && lastFocus.focus) lastFocus.focus();
  }

  toggle.addEventListener('click', function () {
    if (drawer.getAttribute('data-open') === 'true') closeMenu();
    else openMenu();
  });
  if (closeBtn) closeBtn.addEventListener('click', closeMenu);
  if (backdrop) backdrop.addEventListener('click', closeMenu);
  document.querySelectorAll('.mobile-nav-link').forEach(function (a) {
    a.addEventListener('click', closeMenu);
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && drawer.getAttribute('data-open') === 'true') closeMenu();
  });
})();
