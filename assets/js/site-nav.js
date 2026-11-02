'use strict';

(function () {
  const navs = document.querySelectorAll('[data-site-nav]');
  if (!navs.length) return;

  navs.forEach(nav => {
    const button = nav.querySelector('.menu-toggle');
    const panel = nav.querySelector('.mobile-panel');
    if (!button || !panel) return;

    const breakpoint = Number(nav.dataset.navBreakpoint) || 820;
    const mobileQuery = window.matchMedia(`(max-width: ${breakpoint}px)`);

    function setOpen(open, restoreFocus = false) {
      const wasOpen = button.getAttribute('aria-expanded') === 'true';
      const shouldOpen = open && mobileQuery.matches;
      button.setAttribute('aria-expanded', String(shouldOpen));
      button.setAttribute('aria-label', shouldOpen ? 'Chiudi il menu' : 'Apri il menu');
      panel.dataset.open = String(shouldOpen);
      panel.setAttribute('aria-hidden', String(!shouldOpen));
      panel.inert = !shouldOpen;
      document.body.classList.toggle('site-nav-open', shouldOpen);

      if (shouldOpen && !wasOpen) {
        panel.querySelector('a')?.focus({ preventScroll: true });
      } else if (!shouldOpen && (restoreFocus || (wasOpen && panel.contains(document.activeElement)))) {
        button.focus({ preventScroll: true });
      }
    }

    button.addEventListener('click', () => {
      setOpen(button.getAttribute('aria-expanded') !== 'true');
    });

    panel.addEventListener('click', event => {
      if (event.target.closest('a')) setOpen(false);
    });

    document.addEventListener('click', event => {
      if (button.getAttribute('aria-expanded') === 'true' && !nav.contains(event.target)) {
        setOpen(false);
      }
    });

    document.addEventListener('keydown', event => {
      if (event.key === 'Escape' && button.getAttribute('aria-expanded') === 'true') {
        setOpen(false, true);
      }
    });

    mobileQuery.addEventListener('change', event => {
      if (!event.matches) setOpen(false);
    });
  });
})();
