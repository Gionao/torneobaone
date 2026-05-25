<?php
// iscrizione.php
// ============================================================
//  Iscrizione & Regolamento — Torneo Calcio a 5 Baone 2026
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Iscrizione & Regolamento — <?= htmlspecialchars(SITE_NAME) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:ital,wght@0,400;0,600;0,700;0,800;1,700&family=Barlow:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar">
  <div class="nav-container">
    <a href="<?= SITE_URL ?>" class="nav-brand">
      <img src="<?= SITE_URL ?>/assets/img/logo_torneo.png" alt="Logo" class="nav-logo">
      <span>Torneo <em>Baone</em> 2026</span>
    </a>
    <button class="nav-toggle" id="navToggle" aria-label="Apri menu">
      <span></span><span></span><span></span>
    </button>
    <ul class="nav-links" id="navLinks">
      <li><a href="<?= SITE_URL ?>">Home</a></li>
      <li><a href="<?= SITE_URL ?>/calendario.php">Calendario</a></li>
      <li><a href="<?= SITE_URL ?>/classifica.php">Classifica</a></li>
      <li><a href="<?= SITE_URL ?>/iscrizione.php" class="active">Iscrizione</a></li>
    </ul>
  </div>
</nav>

<!-- HERO -->
<section class="iscr-hero">
  <div class="container">
    <h1 class="iscr-hero-title">Iscrizione <em>al Torneo</em></h1>
    <p class="iscr-hero-sub">
      Per iscrivere la tua squadra contatta direttamente gli organizzatori.<br>
      Scarica il regolamento e il modulo di iscrizione qui sotto.
    </p>
  </div>
</section>

<!-- DOCUMENTI -->
<section class="section">
  <div class="container">
    <h2 class="section-title">📄 Documenti</h2>

    <div class="docs-grid">

      <div class="doc-card">
        <div class="doc-icon">📋</div>
        <div class="doc-body">
          <h3>Regolamento Ufficiale</h3>
          <p>Tutte le regole del torneo: formato, gironi, playoff, fair play e premi.</p>
        </div>
        <a href="<?= SITE_URL ?>/assets/docs/regolamento.pdf"
           download class="btn btn-gold btn-sm">
          ⬇ Scarica PDF
        </a>
      </div>

      <div class="doc-card">
        <div class="doc-icon">📝</div>
        <div class="doc-body">
          <h3>Modulo di Iscrizione</h3>
          <p>Compila e consegna (o invia via WhatsApp) entro la scadenza indicata.</p>
        </div>
        <a href="<?= SITE_URL ?>/assets/docs/modulo_iscrizione.pdf"
           download class="btn btn-gold btn-sm">
          ⬇ Scarica PDF
        </a>
      </div>

    </div>
  </div>
</section>

<!-- CONTATTI ORGANIZZATORI -->
<section class="section partite-strip">
  <div class="container">
    <h2 class="section-title">📞 Contatta gli Organizzatori</h2>
    <p style="color:var(--muted);margin-bottom:2rem;font-size:.92rem;">
      Per informazioni, iscrizioni e qualsiasi necessità scrivi o chiama direttamente:
    </p>

    <div class="organizers-grid">

      <!-- Organizzatore 1 -->
      <div class="organizer-card">
        <div class="org-avatar">MR</div>
        <div class="org-info">
          <h3 class="org-name">Mario Rossi</h3>
          <span class="org-role">Organizzatore</span>
        </div>
        <div class="org-contacts">
          <a href="tel:+393331234567" class="org-contact-btn org-phone">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07A19.5 19.5 0 013.07 9.81 19.79 19.79 0 01.02 1.18 2 2 0 012 0h3a2 2 0 012 1.72 12.84 12.84 0 00.7 2.81 2 2 0 01-.45 2.11L6.09 7.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45 12.84 12.84 0 002.81.7A2 2 0 0122 14v2.92z"/></svg>
            +39 333 123 4567
          </a>
          <a href="https://wa.me/393331234567" target="_blank" rel="noopener" class="org-contact-btn org-whatsapp">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M12 0C5.373 0 0 5.373 0 12c0 2.127.558 4.126 1.533 5.858L.057 23.5l5.797-1.517A11.933 11.933 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 21.894a9.877 9.877 0 01-5.031-1.376l-.361-.214-3.44.902.919-3.352-.235-.374A9.861 9.861 0 012.106 12C2.106 6.58 6.58 2.106 12 2.106S21.894 6.58 21.894 12 17.42 21.894 12 21.894z"/></svg>
            WhatsApp
          </a>
          <a href="mailto:mario.rossi@email.it" class="org-contact-btn org-email">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
            mario.rossi@email.it
          </a>
        </div>
      </div>

      <!-- Organizzatore 2 -->
      <div class="organizer-card">
        <div class="org-avatar">LB</div>
        <div class="org-info">
          <h3 class="org-name">Luca Bianchi</h3>
          <span class="org-role">Organizzatore</span>
        </div>
        <div class="org-contacts">
          <a href="tel:+393337654321" class="org-contact-btn org-phone">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07A19.5 19.5 0 013.07 9.81 19.79 19.79 0 01.02 1.18 2 2 0 012 0h3a2 2 0 012 1.72 12.84 12.84 0 00.7 2.81 2 2 0 01-.45 2.11L6.09 7.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45 12.84 12.84 0 002.81.7A2 2 0 0122 14v2.92z"/></svg>
            +39 333 765 4321
          </a>
          <a href="https://wa.me/393337654321" target="_blank" rel="noopener" class="org-contact-btn org-whatsapp">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M12 0C5.373 0 0 5.373 0 12c0 2.127.558 4.126 1.533 5.858L.057 23.5l5.797-1.517A11.933 11.933 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 21.894a9.877 9.877 0 01-5.031-1.376l-.361-.214-3.44.902.919-3.352-.235-.374A9.861 9.861 0 012.106 12C2.106 6.58 6.58 2.106 12 2.106S21.894 6.58 21.894 12 17.42 21.894 12 21.894z"/></svg>
            WhatsApp
          </a>
          <a href="mailto:luca.bianchi@email.it" class="org-contact-btn org-email">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
            luca.bianchi@email.it
          </a>
        </div>
      </div>

    </div>
  </div>
</section>

<!-- INFO ISCRIZIONE -->
<section class="section">
  <div class="container">
    <h2 class="section-title">ℹ️ Come Iscriversi</h2>
    <div class="steps-grid">
      <div class="step-card">
        <div class="step-num">1</div>
        <h4>Scarica il modulo</h4>
        <p>Scarica il PDF qui sopra e compilalo con i dati della tua squadra e dei giocatori.</p>
      </div>
      <div class="step-card">
        <div class="step-num">2</div>
        <h4>Contatta un organizzatore</h4>
        <p>Invia il modulo compilato via WhatsApp o email a uno degli organizzatori.</p>
      </div>
      <div class="step-card">
        <div class="step-num">3</div>
        <h4>Conferma l'iscrizione</h4>
        <p>Riceverai conferma con il girone assegnato e il calendario delle partite.</p>
      </div>
    </div>
  </div>
</section>

<!-- FOOTER -->
<footer class="site-footer">
  <img src="<?= SITE_URL ?>/assets/img/logo_torneo.png" alt="Logo" class="footer-logo">
  <p>&copy; <?= date('Y') ?> <?= htmlspecialchars(SITE_NAME) ?></p>
  <p class="footer-small">
    <a href="<?= SITE_URL ?>">Home</a> &middot;
    <a href="<?= SITE_URL ?>/calendario.php">Calendario</a> &middot;
    <a href="<?= SITE_URL ?>/classifica.php">Classifica</a>
  </p>
</footer>

<style>
/* Hero */
.iscr-hero {
  padding: 4rem 0 3rem;
  text-align: center;
  background: var(--navy-mid);
  border-bottom: 1px solid rgba(201,168,76,.1);
}
.iscr-hero-title {
  font-family: 'Barlow Condensed', sans-serif;
  font-size: clamp(2rem, 6vw, 3.5rem);
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: .05em;
  color: var(--white);
  line-height: 1;
  margin-bottom: .8rem;
}
.iscr-hero-title em { color: var(--gold); font-style: normal; }
.iscr-hero-sub { color: var(--muted); font-size: .95rem; line-height: 1.7; }

/* Documenti */
.docs-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 1.2rem;
}
.doc-card {
  display: flex;
  align-items: center;
  gap: 1.2rem;
  background: var(--navy-mid);
  border: 1px solid rgba(201,168,76,.15);
  border-radius: var(--radius);
  padding: 1.4rem 1.6rem;
  transition: var(--transition);
}
.doc-card:hover { border-color: rgba(201,168,76,.4); transform: translateY(-2px); }
.doc-icon { font-size: 2.2rem; flex-shrink: 0; }
.doc-body { flex: 1; }
.doc-body h3 {
  font-family: 'Barlow Condensed', sans-serif;
  font-size: 1.05rem;
  font-weight: 700;
  color: var(--white);
  text-transform: uppercase;
  letter-spacing: .05em;
  margin-bottom: .3rem;
}
.doc-body p { font-size: .82rem; color: var(--muted); }

/* Organizzatori */
.organizers-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 1.5rem;
}
.organizer-card {
  background: var(--navy-mid);
  border: 1px solid rgba(201,168,76,.12);
  border-radius: var(--radius);
  padding: 1.8rem;
  transition: var(--transition);
}
.organizer-card:hover { border-color: rgba(201,168,76,.3); }

.org-avatar {
  width: 56px; height: 56px;
  border-radius: 50%;
  background: linear-gradient(135deg, var(--gold-dim), var(--gold));
  color: var(--navy);
  font-family: 'Barlow Condensed', sans-serif;
  font-size: 1.3rem;
  font-weight: 800;
  display: flex; align-items: center; justify-content: center;
  margin-bottom: .8rem;
}
.org-info { margin-bottom: 1.2rem; }
.org-name {
  font-family: 'Barlow Condensed', sans-serif;
  font-size: 1.3rem;
  font-weight: 700;
  color: var(--white);
  text-transform: uppercase;
  letter-spacing: .05em;
}
.org-role { font-size: .78rem; color: var(--gold); text-transform: uppercase; letter-spacing: .07em; }

.org-contacts { display: flex; flex-direction: column; gap: .6rem; }
.org-contact-btn {
  display: flex;
  align-items: center;
  gap: .7rem;
  padding: .6rem .9rem;
  border-radius: 7px;
  font-size: .88rem;
  font-weight: 500;
  transition: var(--transition);
  color: var(--white);
  text-decoration: none;
}
.org-contact-btn svg { width: 17px; height: 17px; flex-shrink: 0; }
.org-phone     { background: rgba(76,143,201,.12); border: 1px solid rgba(76,143,201,.25); }
.org-phone:hover { background: rgba(76,143,201,.25); color: #7bb8e8; }
.org-whatsapp  { background: rgba(37,211,102,.1);  border: 1px solid rgba(37,211,102,.25); }
.org-whatsapp:hover { background: rgba(37,211,102,.22); color: #4ddb7a; }
.org-email     { background: rgba(201,168,76,.08); border: 1px solid rgba(201,168,76,.2); }
.org-email:hover { background: rgba(201,168,76,.18); color: var(--gold); }

/* Steps */
.steps-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1.5rem;
  counter-reset: steps;
}
.step-card {
  background: var(--navy-mid);
  border: 1px solid rgba(201,168,76,.1);
  border-radius: var(--radius);
  padding: 1.8rem 1.5rem;
  text-align: center;
  position: relative;
}
.step-num {
  width: 44px; height: 44px;
  border-radius: 50%;
  border: 2px solid var(--gold);
  color: var(--gold);
  font-family: 'Barlow Condensed', sans-serif;
  font-size: 1.4rem;
  font-weight: 800;
  display: flex; align-items: center; justify-content: center;
  margin: 0 auto 1rem;
}
.step-card h4 {
  font-family: 'Barlow Condensed', sans-serif;
  font-size: 1rem;
  font-weight: 700;
  color: var(--white);
  text-transform: uppercase;
  letter-spacing: .05em;
  margin-bottom: .5rem;
}
.step-card p { font-size: .85rem; color: var(--muted); line-height: 1.6; }
</style>

<script>
const toggle = document.getElementById('navToggle');
const links  = document.getElementById('navLinks');
toggle?.addEventListener('click', () => links.classList.toggle('open'));
</script>

</body>
</html>