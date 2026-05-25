// assets/js/main.js
// ============================================================
//  JavaScript front-end — Torneo Calcio a 5 Baone 2026
// ============================================================

'use strict';

/* ── Navbar mobile toggle ─────────────────────────────────── */
(function () {
  const toggle = document.getElementById('navToggle');
  const links  = document.getElementById('navLinks');
  if (!toggle || !links) return;

  toggle.addEventListener('click', () => {
    const open = links.classList.toggle('open');
    toggle.setAttribute('aria-expanded', open);
  });

  // Chiudi menu cliccando su un link
  links.querySelectorAll('a').forEach(a =>
    a.addEventListener('click', () => links.classList.remove('open'))
  );
})();


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
