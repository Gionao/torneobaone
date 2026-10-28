<?php
// photo/admin/_403.php
$pageTitle = 'Accesso non consentito';
$paginaAttiva = '';
if (function_exists('media_is_logged') && media_is_logged()) {
    require __DIR__ . '/_header.php';
} else {
    http_response_code(403);
}
?>
<div class="admin-page-header">
  <h1>Accesso non consentito</h1>
  <p>Il tuo ruolo non permette questa operazione.</p>
</div>
<p><a class="btn btn-outline" href="<?= media_h(function_exists('media_is_logged') && media_is_logged() ? ARCHIVIO_URL . '/index.php' : SITE_URL) ?>">Torna indietro</a></p>
<?php if (function_exists('media_is_logged') && media_is_logged()): ?>
<?php require __DIR__ . '/_footer.php'; ?>
<?php else: ?>
</body>
</html>
<?php endif; ?>
