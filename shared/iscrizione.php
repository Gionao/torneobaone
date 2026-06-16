<?php
// iscrizione.php
// ============================================================
//  Iscrizione & Regolamento — Torneo Calcio a 5 Baone 2026
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';

// !! Inserisci qui il link al tuo Google Form !!
define('GOOGLE_FORM_URL', 'https://forms.gle/zg6k4K98rFSQs4Zn9');
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
<nav class="navbar" id="navbar">
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
    <div class="iscr-hero-inner">
      <div class="iscr-hero-text">
        <p class="iscr-label">⚽ Estate 2026 · Baone</p>
        <h1 class="iscr-hero-title">Iscriviti <em>al Torneo</em></h1>
        <p class="iscr-hero-sub">
          Porta la tua squadra al Torneo di Calcio a 5 di Baone.<br>
          Compila il form online — ci pensiamo noi al resto.
        </p>
        <a href="<?= GOOGLE_FORM_URL ?>" target="_blank" rel="noopener" class="btn-iscriviti">
          <span class="btn-iscriviti-icon">
            <!-- Google Forms icon -->
            <svg viewBox="0 0 24 24" fill="currentColor" width="22" height="22">
              <path d="M19.5 3h-15A1.5 1.5 0 003 4.5v15A1.5 1.5 0 004.5 21h15a1.5 1.5 0 001.5-1.5v-15A1.5 1.5 0 0019.5 3zm-1.5 15H6V6h12v12zm-9-9h6v1.5H9V9zm0 3h6v1.5H9V12zm0 3h4.5v1.5H9V15z"/>
            </svg>
          </span>
          Compila il Form di Iscrizione
          <span class="btn-iscriviti-arrow">→</span>
        </a>
        <p class="iscr-hero-note">
          🔒 Il form è gestito tramite Google Forms &bull; Dati al sicuro
        </p>
      </div>
      <div class="iscr-hero-badge">
        <div class="badge-inner">
          <div class="badge-num">10</div>
          <div class="badge-lbl">Squadre<br>al torneo</div>
        </div>
        <div class="badge-inner">
          <div class="badge-num">€180</div>
          <div class="badge-lbl">Contributo<br>d'iscrizione</div>
        </div>
        <div class="badge-inner">
          <div class="badge-num">8–10</div>
          <div class="badge-lbl">Giocatori<br>per squadra</div>
        </div>
        <div class="badge-inner">
          <div class="badge-num">18-23</div>
          <div class="badge-lbl">Orari<br>delle partite</div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- COME FUNZIONA — 4 STEP -->
<section class="section">
  <div class="container">
    <h2 class="section-title centered">Come Iscriversi</h2>

    <div class="steps-flow">

      <div class="step-flow-card">
        <div class="step-flow-num">1</div>
        <div class="step-flow-icon">📋</div>
        <h4>Compila il Form</h4>
        <p>Il capitano compila il <strong>form Google</strong> con il nome della squadra e i propri contatti. Basta un minuto.</p>
        <a href="<?= GOOGLE_FORM_URL ?>" target="_blank" rel="noopener" class="step-cta">Vai al Form →</a>
      </div>

      <div class="step-flow-arrow">›</div>

      <div class="step-flow-card">
        <div class="step-flow-num">2</div>
        <div class="step-flow-icon">📞</div>
        <h4>Ti Contattiamo Noi</h4>
        <p>Gli organizzatori ricontatteranno il capitano per raccogliere la <strong>lista completa dei giocatori</strong> e tutti i dettagli.</p>
      </div>

      <div class="step-flow-arrow">›</div>

      <div class="step-flow-card">
        <div class="step-flow-num">3</div>
        <div class="step-flow-icon">📄</div>
        <h4>Moduli e Pagamento</h4>
        <p>Compilazione e invio dei <strong>moduli ufficiali</strong> e pagamento del contributo di iscrizione (€ 180 a squadra).</p>
      </div>

      <div class="step-flow-arrow">›</div>

      <div class="step-flow-card step-flow-card--last">
        <div class="step-flow-num">4</div>
        <div class="step-flow-icon">🛡️</div>
        <h4>Logo della Squadra</h4>
        <p>Puoi inviare un <strong>logo o stemma</strong> della tua squadra — verrà visualizzato sul sito e nelle classifiche.</p>
        <span class="step-optional">Facoltativo</span>
      </div>

    </div>

    <!-- CTA centrata -->
    <div class="cta-center">
      <a href="<?= GOOGLE_FORM_URL ?>" target="_blank" rel="noopener" class="btn-iscriviti btn-iscriviti--center">
        <span class="btn-iscriviti-icon">
          <svg viewBox="0 0 24 24" fill="currentColor" width="20" height="20">
            <path d="M19.5 3h-15A1.5 1.5 0 003 4.5v15A1.5 1.5 0 004.5 21h15a1.5 1.5 0 001.5-1.5v-15A1.5 1.5 0 0019.5 3zm-1.5 15H6V6h12v12zm-9-9h6v1.5H9V9zm0 3h6v1.5H9V12zm0 3h4.5v1.5H9V15z"/>
          </svg>
        </span>
        Iscriviti Ora — È Gratuito e Veloce
        <span class="btn-iscriviti-arrow">→</span>
      </a>
      <p style="color:var(--muted);font-size:.8rem;margin-top:.8rem">Dopo l'invio del form verrai ricontattato entro 48 ore.</p>
    </div>

  </div>
</section>

<!-- DOCUMENTI -->
<section class="section partite-strip">
  <div class="container">
    <h2 class="section-title">📄 Documenti Utili</h2>
    <div class="docs-grid">

      <div class="doc-card">
        <div class="doc-icon">📋</div>
        <div class="doc-body">
          <h3>Regolamento Ufficiale</h3>
        </div>
        <a href="<?= SITE_URL ?>/shared/documentazione/torneoBaone2026-regolamento.pdf" download class="btn btn-gold btn-sm">
          ⬇ Scarica PDF
        </a>
      </div>

      <div class="doc-card">
        <div class="doc-icon">📝</div>
        <div class="doc-body">
          <h3>Liberatoria</h3>
        </div>
        <a href="<?= SITE_URL ?>/shared/documentazione/torneoBaone2026-libertoria.pdf" download class="btn btn-gold btn-sm">
          ⬇ Scarica PDF
        </a>
      </div>
      
      <div class="doc-card">
        <div class="doc-icon">📋</div>
        <div class="doc-body">
          <h3>Informativa privacy</p>
        </div>
        <a href="<?= SITE_URL ?>/shared/documentazione/torneoBaone2026-privacy.pdf" download class="btn btn-gold btn-sm">
          ⬇ Scarica PDF
        </a>
      </div>

    </div>
  </div>
</section>

<!-- CONTATTI ORGANIZZATORI -->
<section class="section">
  <div class="container">
    <h2 class="section-title">📞 Contatta gli Organizzatori</h2>
    <p style="color:var(--muted);margin-bottom:2rem;font-size:.92rem;">
      Hai dubbi o domande? Scrivi o chiama direttamente:
    </p>

    <div class="organizers-grid">

      <div class="organizer-card">
        <div class="org-avatar">DG</div>
        <div class="org-info">
          <h3 class="org-name">Davide Giona</h3>
          <span class="org-role">Organizzatore</span>
        </div>
        <div class="org-contacts">
          <a href="tel:+393395447030" class="org-contact-btn org-phone">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07A19.5 19.5 0 013.07 9.81 19.79 19.79 0 01.02 1.18 2 2 0 012 0h3a2 2 0 012 1.72 12.84 12.84 0 00.7 2.81 2 2 0 01-.45 2.11L6.09 7.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45 12.84 12.84 0 002.81.7A2 2 0 0122 14v2.92z"/></svg>
            +39 339 544 7030
          </a>
          <a href="https://wa.me/3395447030" target="_blank" rel="noopener" class="org-contact-btn org-whatsapp">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M12 0C5.373 0 0 5.373 0 12c0 2.127.558 4.126 1.533 5.858L.057 23.5l5.797-1.517A11.933 11.933 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 21.894a9.877 9.877 0 01-5.031-1.376l-.361-.214-3.44.902.919-3.352-.235-.374A9.861 9.861 0 012.106 12C2.106 6.58 6.58 2.106 12 2.106S21.894 6.58 21.894 12 17.42 21.894 12 21.894z"/></svg>
            WhatsApp
          </a>
          <a href="mailto:torneobaone@gmail.com" class="org-contact-btn org-email">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
            torneobaone@gmail.com
          </a>
        </div>
      </div>

      <div class="organizer-card">
        <div class="org-avatar">AT</div>
        <div class="org-info">
          <h3 class="org-name">Alberto Tobaldo</h3>
          <span class="org-role">Organizzatore</span>
        </div>
        <div class="org-contacts">
          <a href="tel:+393714941448" class="org-contact-btn org-phone">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07A19.5 19.5 0 013.07 9.81 19.79 19.79 0 01.02 1.18 2 2 0 012 0h3a2 2 0 012 1.72 12.84 12.84 0 00.7 2.81 2 2 0 01-.45 2.11L6.09 7.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45 12.84 12.84 0 002.81.7A2 2 0 0122 14v2.92z"/></svg>
            +39 3714941448
          </a>
          <a href="https://wa.me/3714941448" target="_blank" rel="noopener" class="org-contact-btn org-whatsapp">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M12 0C5.373 0 0 5.373 0 12c0 2.127.558 4.126 1.533 5.858L.057 23.5l5.797-1.517A11.933 11.933 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 21.894a9.877 9.877 0 01-5.031-1.376l-.361-.214-3.44.902.919-3.352-.235-.374A9.861 9.861 0 012.106 12C2.106 6.58 6.58 2.106 12 2.106S21.894 6.58 21.894 12 17.42 21.894 12 21.894z"/></svg>
            WhatsApp
          </a>
          <a href="mailto:torneobaone@gmail.com" class="org-contact-btn org-email">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
            torneobaone@gmail.com
          </a>
        </div>
      </div>

    </div>
  </div>
</section>

<!-- FOOTER -->
<footer class="site-footer">
  <div class="container">
    <img src="<?= SITE_URL ?>/assets/img/logo_torneo.png" alt="Logo" class="footer-logo">
    <p>&copy; <?= date('Y') ?> Torneo Calcio a 5 Baone &mdash; Patronato San Lorenzo</p>
    <p class="footer-small">
      <a href="<?= SITE_URL ?>/regolamento.php">Regolamento</a> &bull;
      <a href="<?= SITE_URL ?>/admin/login.php">Admin</a>
    </p>
  </div>
</footer>

<style>
/* ── Hero iscrizione ──────────────────────────────────────── */
.iscr-hero {
  padding: 4rem 0 3.5rem;
  background: var(--navy-mid);
  border-bottom: 1px solid rgba(201,168,76,.1);
}
.iscr-hero-inner {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 3rem;
  flex-wrap: wrap;
}
.iscr-hero-text { flex: 1; min-width: 280px; }

.iscr-label {
  font-size: .78rem;
  text-transform: uppercase;
  letter-spacing: .12em;
  color: var(--gold);
  margin-bottom: .6rem;
  font-weight: 600;
}
.iscr-hero-title {
  font-family: 'Barlow Condensed', sans-serif;
  font-size: clamp(2.2rem, 6vw, 3.8rem);
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: .04em;
  color: var(--white);
  line-height: 1;
  margin-bottom: .9rem;
}
.iscr-hero-title em { color: var(--gold); font-style: normal; }
.iscr-hero-sub {
  color: var(--muted);
  font-size: .95rem;
  line-height: 1.7;
  margin-bottom: 1.8rem;
}
.iscr-hero-note {
  margin-top: .8rem;
  font-size: .75rem;
  color: rgba(240,244,248,.3);
  letter-spacing: .03em;
}

/* Badge statistiche */
.iscr-hero-badge {
  display: flex;
  gap: 1rem;
  flex-wrap: wrap;
}
.badge-inner {
  background: rgba(201,168,76,.06);
  border: 1px solid rgba(201,168,76,.2);
  border-radius: 12px;
  padding: 1.2rem 1.5rem;
  text-align: center;
  min-width: 90px;
}
.badge-num {
  font-family: 'Barlow Condensed', sans-serif;
  font-size: 2rem;
  font-weight: 800;
  color: var(--gold);
  line-height: 1;
}
.badge-lbl {
  font-size: .7rem;
  color: var(--muted);
  text-transform: uppercase;
  letter-spacing: .07em;
  margin-top: .3rem;
  line-height: 1.3;
}

/* ── Bottone iscrizione principale ───────────────────────── */
.btn-iscriviti {
  display: inline-flex;
  align-items: center;
  gap: .7rem;
  padding: .9rem 2rem;
  background: var(--gold);
  color: var(--navy);
  border-radius: 8px;
  font-family: 'Barlow Condensed', sans-serif;
  font-size: 1.15rem;
  font-weight: 800;
  letter-spacing: .07em;
  text-transform: uppercase;
  text-decoration: none;
  transition: var(--transition);
  box-shadow: 0 4px 24px rgba(201,168,76,.3);
  position: relative;
  overflow: hidden;
}
.btn-iscriviti::before {
  content: '';
  position: absolute;
  inset: 0;
  background: linear-gradient(120deg, transparent 0%, rgba(255,255,255,.15) 50%, transparent 100%);
  transform: translateX(-100%);
  transition: transform .5s ease;
}
.btn-iscriviti:hover::before { transform: translateX(100%); }
.btn-iscriviti:hover {
  background: var(--gold-bright);
  color: var(--navy);
  transform: translateY(-2px);
  box-shadow: 0 8px 32px rgba(201,168,76,.45);
}
.btn-iscriviti-icon { display: flex; align-items: center; }
.btn-iscriviti-arrow {
  font-size: 1.2rem;
  transition: transform .2s;
}
.btn-iscriviti:hover .btn-iscriviti-arrow { transform: translateX(4px); }
.btn-iscriviti--center { font-size: 1.05rem; }

.cta-center { text-align: center; margin-top: 3rem; }

/* ── Steps flow ──────────────────────────────────────────── */
.steps-flow {
  display: flex;
  align-items: flex-start;
  gap: 0;
  flex-wrap: wrap;
  justify-content: center;
}

.step-flow-card {
  flex: 1;
  min-width: 190px;
  max-width: 240px;
  background: var(--navy-mid);
  border: 1px solid rgba(201,168,76,.12);
  border-radius: var(--radius);
  padding: 1.8rem 1.4rem;
  text-align: center;
  position: relative;
  transition: var(--transition);
}
.step-flow-card:hover {
  border-color: rgba(201,168,76,.35);
  transform: translateY(-3px);
}
.step-flow-card--last { border-color: rgba(201,168,76,.08); }

.step-flow-num {
  position: absolute;
  top: -14px;
  left: 50%;
  transform: translateX(-50%);
  width: 28px; height: 28px;
  border-radius: 50%;
  background: var(--gold);
  color: var(--navy);
  font-family: 'Barlow Condensed', sans-serif;
  font-size: .95rem;
  font-weight: 800;
  display: flex; align-items: center; justify-content: center;
}

.step-flow-icon { font-size: 2rem; margin: .5rem 0 .8rem; }

.step-flow-card h4 {
  font-family: 'Barlow Condensed', sans-serif;
  font-size: 1rem;
  font-weight: 700;
  color: var(--white);
  text-transform: uppercase;
  letter-spacing: .05em;
  margin-bottom: .6rem;
}
.step-flow-card p {
  font-size: .82rem;
  color: var(--muted);
  line-height: 1.6;
  margin-bottom: .8rem;
}
.step-flow-card strong { color: var(--white); }

.step-cta {
  display: inline-block;
  font-size: .8rem;
  font-family: 'Barlow Condensed', sans-serif;
  font-weight: 700;
  letter-spacing: .06em;
  text-transform: uppercase;
  color: var(--gold);
  text-decoration: none;
  border-bottom: 1px solid rgba(201,168,76,.3);
  padding-bottom: 1px;
  transition: var(--transition);
}
.step-cta:hover { color: var(--gold-bright); border-color: var(--gold-bright); }

.step-optional {
  display: inline-block;
  font-size: .7rem;
  color: var(--muted);
  background: rgba(255,255,255,.05);
  border: 1px solid rgba(255,255,255,.1);
  border-radius: 20px;
  padding: .15rem .6rem;
  letter-spacing: .06em;
  text-transform: uppercase;
}

/* Frecce tra step */
.step-flow-arrow {
  font-size: 2rem;
  color: var(--gold-dim);
  padding: 2.5rem .3rem 0;
  flex-shrink: 0;
  align-self: flex-start;
}

/* ── Doc cards ───────────────────────────────────────────── */
.docs-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 1.2rem;
}
.doc-card {
  display: flex;
  align-items: center;
  gap: 1.2rem;
  background: var(--navy);
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

/* ── Organizzatori ───────────────────────────────────────── */
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

/* ── Responsive ──────────────────────────────────────────── */
@media (max-width: 700px) {
  .steps-flow { flex-direction: column; align-items: center; gap: 2rem; }
  .step-flow-arrow { padding: 0; transform: rotate(90deg); }
  .step-flow-card { max-width: 100%; width: 100%; }
  .iscr-hero-badge { justify-content: center; }
}
</style>

<script>
// Navbar mobile toggle
const toggle = document.getElementById('navToggle');
const links  = document.getElementById('navLinks');
toggle?.addEventListener('click', () => links.classList.toggle('open'));

// Navbar scroll
window.addEventListener('scroll', () => {
  document.getElementById('navbar').style.background =
    window.scrollY > 40 ? 'rgba(13,27,42,.98)' : 'rgba(13,27,42,.92)';
}, { passive: true });
</script>

</body>
</html>