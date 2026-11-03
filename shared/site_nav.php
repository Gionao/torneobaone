<?php
$siteNavConfig = $siteNavConfig ?? [];
$siteNavId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($siteNavConfig['id'] ?? 'site-navigation'));
$siteNavIsDense = !empty($siteNavConfig['dense']);
$siteNavBrandHref = (string)($siteNavConfig['brand_href'] ?? SITE_URL . '/');
$siteNavBrandName = (string)($siteNavConfig['brand_name'] ?? 'Torneo Baone');
$siteNavBrandYear = (string)($siteNavConfig['brand_year'] ?? '2026');
$siteNavBrandLabel = (string)($siteNavConfig['brand_label'] ?? ($siteNavBrandName . ' ' . $siteNavBrandYear));
$siteNavPrimary = is_array($siteNavConfig['primary'] ?? null) ? $siteNavConfig['primary'] : [];
$siteNavActions = is_array($siteNavConfig['actions'] ?? null) ? $siteNavConfig['actions'] : [];
$siteNavContext = trim((string)($siteNavConfig['context'] ?? ''));
$siteNavLabel = (string)($siteNavConfig['label'] ?? 'Navigazione principale');
$siteNavRenderLink = static function (array $item, bool $action = false): void {
    $href = (string)($item['href'] ?? '#');
    $label = (string)($item['label'] ?? '');
    $current = !empty($item['current']);
    $class = $action
        ? 'nav-action' . (!empty($item['primary']) ? ' nav-action--primary' : '') . (!empty($item['danger']) ? ' nav-action--danger' : '')
        : 'nav-link';
    if ($current && !$action) {
        $class .= ' is-current';
    }
    ?>
    <a class="<?= $class ?>" href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>"<?= $current ? ' aria-current="page"' : '' ?><?= !empty($item['target']) ? ' target="' . htmlspecialchars((string)$item['target'], ENT_QUOTES, 'UTF-8') . '" rel="noopener"' : '' ?>>
      <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
      <?php if ($action && !empty($item['primary'])): ?>
        <svg viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M3 8h9M8 3l5 5-5 5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
      <?php endif; ?>
    </a>
    <?php
};
?>
<header class="site-header<?= $siteNavIsDense ? ' site-header--dense' : '' ?>" data-site-nav data-nav-breakpoint="<?= $siteNavIsDense ? '1060' : '820' ?>">
  <div class="nav-shell">
    <a class="brand" href="<?= htmlspecialchars($siteNavBrandHref, ENT_QUOTES, 'UTF-8') ?>" aria-label="<?= htmlspecialchars($siteNavBrandLabel, ENT_QUOTES, 'UTF-8') ?>">
      <img class="brand-logo" src="<?= SITE_URL ?>/assets/brand/01_loghi/png/logo_principale.png" alt="" width="48" height="48">
      <span class="brand-copy">
        <span class="brand-name"><?= htmlspecialchars($siteNavBrandName, ENT_QUOTES, 'UTF-8') ?></span>
        <span class="brand-year"><?= htmlspecialchars($siteNavBrandYear, ENT_QUOTES, 'UTF-8') ?></span>
      </span>
    </a>

    <div class="nav-area">
      <nav aria-label="<?= htmlspecialchars($siteNavLabel, ENT_QUOTES, 'UTF-8') ?>">
        <ul class="nav-links">
          <?php foreach ($siteNavPrimary as $siteNavItem): ?>
            <li><?php $siteNavRenderLink($siteNavItem); ?></li>
          <?php endforeach; ?>
        </ul>
      </nav>
      <?php if ($siteNavContext !== '' || $siteNavActions !== []): ?>
      <div class="nav-actions">
        <?php if ($siteNavContext !== ''): ?><span class="nav-context"><?= htmlspecialchars($siteNavContext, ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
        <?php foreach ($siteNavActions as $siteNavItem): $siteNavRenderLink($siteNavItem, true); endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <button class="menu-toggle" type="button" aria-label="Apri il menu" aria-expanded="false" aria-controls="<?= $siteNavId ?>-mobile">
      <svg class="menu-icon menu-icon--open" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
      <svg class="menu-icon menu-icon--close" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
    </button>
  </div>

  <nav class="mobile-panel" id="<?= $siteNavId ?>-mobile" aria-label="Navigazione mobile" data-open="false" aria-hidden="true" inert>
    <ul class="mobile-links">
      <?php foreach ($siteNavPrimary as $siteNavItem): ?>
        <li><?php $siteNavRenderLink($siteNavItem); ?></li>
      <?php endforeach; ?>
    </ul>
    <?php if ($siteNavContext !== '' || $siteNavActions !== []): ?>
    <div class="mobile-actions">
      <?php if ($siteNavContext !== ''): ?><span class="nav-context"><?= htmlspecialchars($siteNavContext, ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
      <?php foreach ($siteNavActions as $siteNavItem): $siteNavRenderLink($siteNavItem, true); endforeach; ?>
    </div>
    <?php endif; ?>
  </nav>
</header>
<script src="<?= SITE_URL ?>/assets/js/site-nav.js?v=<?= @filemtime(__DIR__ . '/../assets/js/site-nav.js') ?: '1' ?>"></script>
