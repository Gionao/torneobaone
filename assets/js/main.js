// assets/js/main.js
// ============================================================
//  JavaScript front-end — Torneo Calcio a 5 Baone 2026
// ============================================================

'use strict';

/* ── Countdown ───────────────────────────────────────────── */
(function () {
  const el = document.getElementById('countdown');
  if (!el) return;

  const target = new Date(el.dataset.target).getTime();

  function pad(n) { return String(n).padStart(2, '0'); }

  function tick() {
    const diff = target - Date.now();

    if (diff <= 0) {
      el.innerHTML = '<span style="color:var(--gold);font-family:\'Barlow Condensed\',sans-serif;font-size:1.4rem;letter-spacing:.1em">🏆 TORNEO IN CORSO!</span>';
      return;
    }

    const days  = Math.floor(diff / 86400000);
    const hours = Math.floor((diff % 86400000) / 3600000);
    const mins  = Math.floor((diff % 3600000)  / 60000);
    const secs  = Math.floor((diff % 60000)    / 1000);

    document.getElementById('cd-days').textContent  = pad(days);
    document.getElementById('cd-hours').textContent = pad(hours);
    document.getElementById('cd-mins').textContent  = pad(mins);
    document.getElementById('cd-secs').textContent  = pad(secs);
  }

  tick();
  setInterval(tick, 1000);
})();


/* ── Navbar scroll effect ─────────────────────────────────── */
(function () {
  const nav = document.getElementById('navbar');
  if (!nav) return;

  window.addEventListener('scroll', () => {
    nav.style.background = window.scrollY > 40
      ? 'rgba(13,27,42,.98)'
      : 'rgba(13,27,42,.92)';
  }, { passive: true });
})();

/* ── Menu mobile ─────────────────────────────────────────── */
(function () {
  const nav = document.getElementById('navbar');
  const button = document.getElementById('navMenuToggle');
  const menu = document.getElementById('navLinks');
  const backdrop = document.getElementById('navBackdrop');
  if (!nav || !button || !menu || !backdrop) return;

  function setOpen(open, moveFocus = true) {
    const wasOpen = button.getAttribute('aria-expanded') === 'true';
    nav.classList.toggle('menu-open', open);
    document.body.classList.toggle('nav-menu-open', open);
    button.setAttribute('aria-expanded', String(open));
    button.setAttribute('aria-label', open ? 'Chiudi il menu' : 'Apri il menu');
    menu.setAttribute('aria-hidden', String(!open));
    menu.inert = !open;
    backdrop.setAttribute('aria-hidden', String(!open));
    if (open) {
      const firstLink = menu.querySelector('a');
      if (firstLink) firstLink.focus({ preventScroll: true });
    } else if (wasOpen && moveFocus && window.innerWidth <= 768) {
      button.focus({ preventScroll: true });
    }
  }

  const initiallyOpen = window.innerWidth > 768;
  button.setAttribute('aria-expanded', 'false');
  button.setAttribute('aria-label', 'Apri il menu');
  setOpen(false, false);
  if (initiallyOpen) {
    menu.setAttribute('aria-hidden', 'false');
    menu.inert = false;
  }

  button.addEventListener('click', () => {
    setOpen(button.getAttribute('aria-expanded') !== 'true');
  });
  backdrop.addEventListener('click', () => setOpen(false));
  menu.addEventListener('click', event => {
    if (event.target.closest('a')) setOpen(false);
  });
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && button.getAttribute('aria-expanded') === 'true') {
      setOpen(false);
    }
  });
  window.addEventListener('resize', () => {
    if (window.innerWidth > 768 && button.getAttribute('aria-expanded') === 'true') {
      setOpen(false);
    }
  }, { passive: true });
})();
