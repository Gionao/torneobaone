<?php
/** Shared visual shell for authentication pages. Callers keep their own auth/session handlers. */
$auth = $authPage ?? [];
$authEscape = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$authTitle = (string) ($auth['title'] ?? 'Accesso');
$authSubtitle = (string) ($auth['subtitle'] ?? 'Inserisci le tue credenziali per continuare.');
$authLogo = (string) ($auth['logo'] ?? '');
$authLogoAlt = (string) ($auth['logo_alt'] ?? '');
$authFields = is_array($auth['fields'] ?? null) ? $auth['fields'] : [];
$authHidden = is_array($auth['hidden'] ?? null) ? $auth['hidden'] : [];
$authLinks = is_array($auth['links'] ?? null) ? $auth['links'] : [];
$authError = (string) ($auth['error'] ?? '');
$authNotice = (string) ($auth['notice'] ?? '');
$authFormAction = (string) ($auth['action'] ?? '');
$authFormMethod = strtoupper((string) ($auth['method'] ?? 'POST')) === 'GET' ? 'get' : 'post';
$authPageTitle = (string) ($auth['page_title'] ?? ($authTitle . ' — ' . SITE_NAME));
$authDescription = (string) ($auth['description'] ?? '');
$authSubmit = (string) ($auth['submit'] ?? 'Accedi');
$authShowPasswordToggle = !empty($auth['show_password_toggle']);
$authNoIndex = !empty($auth['noindex']);
$authNoValidate = !empty($auth['novalidate']);
$authFooter = (string) ($auth['footer'] ?? '');
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php if ($authNoIndex): ?><meta name="robots" content="noindex, nofollow"><?php endif; ?>
  <link rel="icon" type="image/x-icon" href="<?= $authEscape(SITE_URL) ?>/assets/brand/02_favicon/favicon.ico">
  <title><?= $authEscape($authPageTitle) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700;800&family=Barlow:wght@400;500;600;700&display=swap" rel="stylesheet">
  <?php if (!empty($auth['navigation'])): ?><link rel="stylesheet" href="<?= $authEscape(SITE_URL) ?>/assets/css/style.css?v=public-menu-7"><?php endif; ?>
  <link rel="stylesheet" href="<?= $authEscape(SITE_URL) ?>/assets/css/baone-ui.css?v=phase04">
  <link rel="stylesheet" href="<?= $authEscape(SITE_URL) ?>/assets/css/components/auth.css">
</head>
<body class="tb-ui tb-auth-page<?= !empty($auth['navigation']) ? ' tb-auth-page--with-nav' : '' ?>">
  <?php if (!empty($auth['navigation']) && is_array($auth['navigation'])): $siteNavConfig = $auth['navigation']; require __DIR__ . '/site_nav.php'; endif; ?>
  <a class="tb-skip-link" href="#auth-form">Vai al modulo di accesso</a>
  <main class="tb-auth-layout">
    <section class="tb-auth-card" aria-labelledby="auth-title">
      <?php if ($authLogo !== ''): ?>
        <div class="tb-auth-brand"><img src="<?= $authEscape($authLogo) ?>" alt="<?= $authEscape($authLogoAlt) ?>"></div>
      <?php endif; ?>
      <?php if (!empty($auth['eyebrow'])): ?><p class="tb-auth-eyebrow"><?= $authEscape($auth['eyebrow']) ?></p><?php endif; ?>
      <h1 id="auth-title" class="tb-auth-title"><?= $authEscape($authTitle) ?></h1>
      <?php if ($authSubtitle !== ''): ?><p class="tb-auth-subtitle"><?= $authEscape($authSubtitle) ?></p><?php endif; ?>
      <?php if ($authDescription !== ''): ?><p class="tb-auth-description"><?= $authEscape($authDescription) ?></p><?php endif; ?>

      <?php if ($authNotice !== ''): ?><div class="tb-alert tb-alert--success" role="status"><span class="tb-alert__body"><?= $authEscape($authNotice) ?></span></div><?php endif; ?>
      <?php if ($authError !== ''): ?><div class="tb-alert tb-alert--error" role="alert" id="auth-error"><span class="tb-alert__body"><?= $authEscape($authError) ?></span></div><?php endif; ?>

      <form id="auth-form" class="tb-auth-form" method="<?= $authFormMethod ?>" action="<?= $authEscape($authFormAction) ?>"<?= $authError !== '' ? ' aria-describedby="auth-error"' : '' ?><?= $authNoValidate ? ' novalidate' : '' ?>>
        <?php foreach ($authHidden as $hidden): ?>
          <input type="hidden" name="<?= $authEscape($hidden['name'] ?? '') ?>" value="<?= $authEscape($hidden['value'] ?? '') ?>">
        <?php endforeach; ?>
        <?php foreach ($authFields as $field):
          $name = (string) ($field['name'] ?? '');
          $id = (string) ($field['id'] ?? $name);
          $type = (string) ($field['type'] ?? 'text');
          $error = (string) ($field['error'] ?? '');
          $describedBy = trim(implode(' ', array_filter([
              (string) ($field['describedby'] ?? ''),
              !empty($field['hint']) ? $id . '-hint' : '',
              $error !== '' ? $id . '-error' : '',
          ])));
        ?>
          <div class="tb-field">
            <label class="tb-label" for="<?= $authEscape($id) ?>"><?= $authEscape($field['label'] ?? '') ?><?php if (!empty($field['required'])): ?> <span aria-hidden="true">*</span><?php endif; ?></label>
            <?php if (!empty($field['hint'])): ?><span class="tb-field__hint" id="<?= $authEscape($id) ?>-hint"><?= $authEscape($field['hint']) ?></span><?php endif; ?>
            <div class="tb-auth-input-wrap<?= $type === 'password' && $authShowPasswordToggle ? ' tb-auth-input-wrap--password' : '' ?>">
              <input class="tb-input" type="<?= $authEscape($type) ?>" id="<?= $authEscape($id) ?>" name="<?= $authEscape($name) ?>" value="<?= $type === 'password' ? '' : $authEscape($field['value'] ?? '') ?>"<?= !empty($field['required']) ? ' required' : '' ?><?= !empty($field['autofocus']) ? ' autofocus' : '' ?><?= isset($field['autocomplete']) ? ' autocomplete="' . $authEscape($field['autocomplete']) . '"' : '' ?><?= isset($field['maxlength']) ? ' maxlength="' . (int) $field['maxlength'] . '"' : '' ?><?= $error !== '' ? ' aria-invalid="true"' : '' ?><?= $describedBy !== '' ? ' aria-describedby="' . $authEscape($describedBy) . '"' : '' ?>>
              <?php if ($type === 'password' && $authShowPasswordToggle): ?><button class="tb-auth-password-toggle" type="button" data-password-toggle="<?= $authEscape($id) ?>" aria-label="Mostra password" aria-pressed="false">Mostra</button><?php endif; ?>
            </div>
            <?php if ($error !== ''): ?><span class="tb-field__error" id="<?= $authEscape($id) ?>-error"><?= $authEscape($error) ?></span><?php endif; ?>
          </div>
        <?php endforeach; ?>
        <button class="tb-button tb-button--full tb-button--large" type="submit"><?= $authEscape($authSubmit) ?></button>
      </form>

      <?php if ($authLinks !== []): ?>
        <nav class="tb-auth-links" aria-label="Link utili">
          <?php foreach ($authLinks as $link): ?><a href="<?= $authEscape($link['href'] ?? '#') ?>"><?= $authEscape($link['label'] ?? '') ?></a><?php endforeach; ?>
        </nav>
      <?php endif; ?>
      <?php if ($authFooter !== ''): ?><p class="tb-auth-footer"><?= $authEscape($authFooter) ?></p><?php endif; ?>
    </section>
  </main>
  <script>
  document.querySelectorAll('[data-password-toggle]').forEach(function (button) {
    button.addEventListener('click', function () {
      const input = document.getElementById(button.dataset.passwordToggle);
      if (!input) return;
      const visible = input.type === 'password';
      input.type = visible ? 'text' : 'password';
      button.textContent = visible ? 'Nascondi' : 'Mostra';
      button.setAttribute('aria-label', visible ? 'Nascondi password' : 'Mostra password');
      button.setAttribute('aria-pressed', visible ? 'true' : 'false');
    });
  });
  </script>
</body>
</html>
