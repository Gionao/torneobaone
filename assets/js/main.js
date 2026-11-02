// assets/js/main.js
// ============================================================
//  JavaScript front-end — Torneo Calcio a 5 Baone 2026
// ============================================================

'use strict';

/* ── Scroll reveals (public pages) ───────────────────────── */
(function () {
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (reduceMotion || !('IntersectionObserver' in window)) return;

  const elements = document.querySelectorAll(
    '.match-card, .info-card, .cal-fase-block, .cal-card, .standings-table tbody tr, .social-btn, .featured-match-card, .team-directory-card, .team-match-row'
  );
  if (!elements.length) return;

  elements.forEach((element, index) => {
    element.dataset.reveal = index % 2 === 0 ? 'from-left' : 'from-right';
    element.style.setProperty('--reveal-delay', `${Math.min(index % 4, 3) * 65}ms`);
  });
  document.documentElement.classList.add('motion-ready');

  const observer = new IntersectionObserver((entries, currentObserver) => {
    entries.forEach(entry => {
      if (!entry.isIntersecting) return;
      entry.target.classList.add('is-visible');
      currentObserver.unobserve(entry.target);
    });
  }, { threshold: 0.12, rootMargin: '0px 0px -32px 0px' });

  elements.forEach(element => observer.observe(element));
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
    const seconds = document.getElementById('cd-secs');
    const nextSeconds = pad(secs);
    if (seconds.textContent !== nextSeconds) {
      seconds.textContent = nextSeconds;
      seconds.classList.remove('is-ticking');
      requestAnimationFrame(() => seconds.classList.add('is-ticking'));
    }
  }

  tick();
  setInterval(tick, 1000);
})();

/* ── Featured next match countdown ───────────────────────── */
(function () {
  const el = document.getElementById('next-match-countdown');
  if (!el) return;

  const target = new Date(el.dataset.target).getTime();
  if (Number.isNaN(target)) {
    el.textContent = 'Orario della partita da confermare';
    return;
  }

  const pad = value => String(value).padStart(2, '0');
  function tick() {
    const remaining = target - Date.now();
    if (remaining <= 0) {
      el.textContent = 'Orario d’inizio raggiunto';
      return false;
    }

    const days = Math.floor(remaining / 86400000);
    const hours = Math.floor((remaining % 86400000) / 3600000);
    const minutes = Math.floor((remaining % 3600000) / 60000);
    const seconds = Math.floor((remaining % 60000) / 1000);
    el.textContent = days > 0
      ? `Al fischio d’inizio: ${days}g ${pad(hours)}:${pad(minutes)}:${pad(seconds)}`
      : `Al fischio d’inizio: ${pad(hours)}:${pad(minutes)}:${pad(seconds)}`;
    return true;
  }

  if (tick()) {
    const interval = window.setInterval(() => {
      if (!tick()) window.clearInterval(interval);
    }, 1000);
  }
})();
