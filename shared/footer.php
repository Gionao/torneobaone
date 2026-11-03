<?php require_once __DIR__ . '/../includes/config.php'; ?>
<footer class="site-footer<?= !empty($isPublicRedesign) ? ' public-footer' : '' ?>">
  <div class="container">
    <div class="footer-main">
      <section class="footer-brand-block" aria-labelledby="footer-brand-title">
        <a class="footer-brand" href="<?= SITE_URL ?>/" aria-label="Torneo Baone, torna alla home">
          <img src="<?= SITE_URL ?>/<?= (!empty($isPublicRedesign) || !empty($isRegistrationInfoLayout) || !empty($isCaptainAuthLayout)) ? 'assets/brand/01_loghi/png/logo_scudetto_compatto.png' : 'assets/img/logo_torneo.png' ?>" alt="" class="footer-logo" width="48" height="48">
          <span id="footer-brand-title">Torneo Baone</span>
        </a>
        <p class="footer-tagline">Calcio a 5 · Baone</p>
        <p class="footer-organizer">Organizzato dal Patronato San Lorenzo.</p>
        <div class="footer-socials" aria-label="Seguici sui social">
          <a href="https://www.instagram.com/torneodibaone" target="_blank" rel="noopener noreferrer" aria-label="Instagram — si apre in una nuova scheda">
            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M7.5 2h9A5.5 5.5 0 0 1 22 7.5v9a5.5 5.5 0 0 1-5.5 5.5h-9A5.5 5.5 0 0 1 2 16.5v-9A5.5 5.5 0 0 1 7.5 2Zm0 2A3.5 3.5 0 0 0 4 7.5v9A3.5 3.5 0 0 0 7.5 20h9a3.5 3.5 0 0 0 3.5-3.5v-9A3.5 3.5 0 0 0 16.5 4h-9ZM12 7a5 5 0 1 1 0 10 5 5 0 0 1 0-10Zm0 2a3 3 0 1 0 0 6 3 3 0 0 0 0-6Zm5.25-3.5a1.25 1.25 0 1 1 0 2.5 1.25 1.25 0 0 1 0-2.5Z"/></svg>
            <span>Instagram</span>
          </a>
          <a href="https://www.facebook.com/share/17aeXbMUpy/?mibextid=wwXIfr" target="_blank" rel="noopener noreferrer" aria-label="Facebook — si apre in una nuova scheda">
            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
            <span>Facebook</span>
          </a>
        </div>
      </section>

      <nav class="footer-column" aria-label="Esplora il torneo">
        <h2>Esplora</h2>
        <ul>
          <li><a href="<?= SITE_URL ?>/calendario">Calendario</a></li>
          <li><a href="<?= SITE_URL ?>/classifica">Classifica</a></li>
          <li><a href="<?= SITE_URL ?>/squadre">Squadre</a></li>
        </ul>
      </nav>

      <nav class="footer-column" aria-label="Informazioni e documenti">
        <h2>Informazioni</h2>
        <ul>
          <li><a href="<?= SITE_URL ?>/shared/documentazione/2026_v1/torneoBaone2026-regolamento.pdf" target="_blank" rel="noopener">Regolamento <span class="footer-link-note">PDF</span></a></li>
          <li><a href="<?= SITE_URL ?>/shared/documentazione/2026_v1/torneoBaone2026-privacy.pdf" target="_blank" rel="noopener">Informativa privacy <span class="footer-link-note">PDF</span></a></li>
        </ul>
      </nav>

      <div class="footer-column footer-join">
        <h2>Partecipa</h2>
        <p>Vuoi partecipare al torneo o gestire la tua squadra?</p>
        <a class="footer-cta" href="<?= SITE_URL ?>/iscrizione">Vai all’iscrizione <span aria-hidden="true">→</span></a>
        <a class="footer-login" href="<?= SITE_URL ?>/capitano/login">Area capitani</a>
      </div>
    </div>

    <div class="footer-bottom">
      <p>&copy; <?= date('Y') ?> Patronato San Lorenzo · Baone</p>
      <p class="footer-credit">Sito realizzato da Giona Davide</p>
    </div>
  </div>
</footer>
<script src="<?= SITE_URL ?>/assets/js/main.js?v=<?= @filemtime(__DIR__ . '/../assets/js/main.js') ?: '1' ?>"></script>
</body>
</html>
