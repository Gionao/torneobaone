<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once dirname(__DIR__, 2) . '/includes/staff_authorization.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/role_check.php';
require_role(['admin']);
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$posNavActive = 'users';
$currentId    = staff_id();
$message      = null;
$messageType  = 'success';
$editUserId   = (int) ($_GET['edit_password'] ?? 0);

$roles = ['admin', 'operatore', 'bar', 'cucina'];
$ruoliPos = $roles;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if (!csrf_verify()) {
            throw new RuntimeException('Sessione scaduta. Ricarica la pagina e riprova.');
        }
        if ($action === 'create_user') {
            $username = trim($_POST['username'] ?? '');
            $role     = $_POST['role'] ?? 'operatore';
            $station  = trim($_POST['station_name'] ?? '');

            if ($username === '') {
                throw new RuntimeException('Lo username è obbligatorio.');
            }
            $role = $role === 'admin' ? 'amministratore' : $role;
            if (!staff_validate_sector_role('pos', $role) || !can_assign_sector_role('pos', $role)) {
                throw new RuntimeException('Ruolo non valido.');
            }
            if ($role === 'operatore' && $station === '') {
                throw new RuntimeException('Nome cassa obbligatorio per gli operatori.');
            }
            if ($role !== 'operatore') {
                $station = null;
            }
            $lookup = $pdo->prepare('SELECT id FROM admin WHERE username=? AND attivo=1 AND is_super_admin=0 LIMIT 1');
            $lookup->execute([$username]);
            $existingId = (int) ($lookup->fetchColumn() ?: 0);
            if ($existingId > 0) {
                staff_assign_sector_role($existingId, 'pos', $role, $station ?: null);
                $message = 'Accesso POS assegnato.';
            } else {
                $created = staff_create_account_with_temporary_password($username, 'pos', $role, $station ?: null);
                $message = 'La password digitata non è stata utilizzata. Password temporanea generata (mostrata una sola volta): ' . $created['temporary_password'];
            }
        } elseif ($action === 'update_password') {
            throw new RuntimeException('La gestione POS non può reimpostare la password globale di un utente.');
        } elseif ($action === 'delete_user') {
            $userId = (int) ($_POST['user_id'] ?? 0);
            if ($userId === $currentId || $userId < 1) {
                throw new RuntimeException('Non puoi revocare la tua assegnazione amministratore.');
            }
            if (get_sector_role($userId, 'pos') === null) {
                throw new RuntimeException('Utente POS non trovato.');
            }
            staff_revoke_sector_role($userId, 'pos');
            $message = 'Accesso POS revocato. Gli altri accessi dell’account restano invariati.';
        }
    } catch (Throwable $e) {
        $messageType = 'error';
        $message = DEBUG_MODE ? $e->getMessage() : 'Operazione non riuscita.';
    }
}

$users = $pdo->prepare('SELECT u.id,u.username,a.ruolo AS role,a.station_name,u.created_at FROM admin u INNER JOIN staff_assegnazioni a ON a.id_admin=u.id AND a.area=? AND a.attivo=1 WHERE u.attivo=1 AND u.is_super_admin=0 ORDER BY a.ruolo,u.username');
$users->execute(['pos']);
$users = $users->fetchAll();
$csrfToken = csrf_token();

$pageTitle = 'Utenti POS';
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <link rel="icon" type="image/x-icon" href="<?= SITE_URL ?>/assets/brand/02_favicon/favicon.ico">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?> — <?= htmlspecialchars(SITE_NAME) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700;800&family=Barlow:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/baone-ui.css?v=phase04">
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/admin.css">
  <link rel="stylesheet" href="<?= POS_URL ?>/assets/css/pos.css?v=phase05">
</head>
<body class="admin-body tb-ui">
<?php require __DIR__ . '/../includes/navbar.php'; ?>
<main class="admin-main"><div class="admin-container">

<div class="admin-page-header">
  <h1>Gestione utenti</h1>
  <p>Account staff POS (tabella admin condivisa)</p>
</div>

<?php if ($message): ?>
  <div class="alert alert-<?= $messageType === 'error' ? 'error' : 'success' ?>"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<div class="admin-table-wrap" style="margin-bottom:2rem;">
  <table class="admin-table">
    <thead>
      <tr>
        <th>Username</th>
        <th>Ruolo</th>
        <th>Cassa</th>
        <th>Creato</th>
        <th>Azioni</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($users as $u): ?>
      <tr>
        <td><strong><?= htmlspecialchars($u['username']) ?></strong></td>
        <td><span class="role-badge role-badge--<?= htmlspecialchars($u['role']) ?>"><?= htmlspecialchars($u['role']) ?></span></td>
        <td style="color:var(--muted)"><?= htmlspecialchars($u['station_name'] ?? '—') ?></td>
        <td style="font-size:.82rem;color:var(--muted)"><?= htmlspecialchars($u['created_at']) ?></td>
        <td class="actions">
          <a href="?edit_password=<?= (int) $u['id'] ?>" class="btn btn-sm btn-outline">Modifica password</a>
          <?php if ((int) $u['id'] !== $currentId): ?>
          <form method="post" style="display:inline" onsubmit="return confirm('Eliminare questo utente?');">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="action" value="delete_user">
            <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
            <button type="submit" class="btn btn-sm btn-red">Elimina</button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php if ($editUserId > 0):
    $editUser = null;
    foreach ($users as $u) {
        if ((int) $u['id'] === $editUserId) { $editUser = $u; break; }
    }
?>
<?php if ($editUser): ?>
<section class="admin-form" style="margin-bottom:2rem;">
  <h2 style="font-family:'Barlow Condensed',sans-serif;color:var(--gold);text-transform:uppercase;margin-bottom:1rem;">
    Password — <?= htmlspecialchars($editUser['username']) ?>
  </h2>
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
    <input type="hidden" name="action" value="update_password">
    <input type="hidden" name="user_id" value="<?= $editUserId ?>">
    <div class="form-group">
      <label>Nuova password</label>
    <input type="password" name="password" minlength="8" autocomplete="new-password">
    </div>
    <div class="form-group">
      <label>Conferma password</label>
    <input type="password" name="password_confirm" minlength="8" autocomplete="new-password">
    </div>
    <div class="form-actions">
      <button type="submit" class="btn btn-gold">Salva password</button>
      <a href="users.php" class="btn btn-outline">Annulla</a>
    </div>
  </form>
</section>
<?php endif; ?>
<?php endif; ?>

<section class="admin-form">
  <h2 style="font-family:'Barlow Condensed',sans-serif;color:var(--gold);text-transform:uppercase;margin-bottom:1rem;">Aggiungi utente</h2>
  <form method="post" id="formCreateUser">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
    <input type="hidden" name="action" value="create_user">
    <div class="form-group">
      <label>Username</label>
      <input type="text" name="username" required maxlength="60" autocomplete="off">
    </div>
    <div class="form-group">
      <label>Password</label>
      <input type="password" name="password" required minlength="8">
    </div>
    <div class="form-group">
      <label>Conferma password</label>
      <input type="password" name="password_confirm" required minlength="8">
    </div>
    <div class="form-group">
      <label>Ruolo</label>
      <select name="role" id="userRole" required>
        <?php foreach ($roles as $r): ?>
        <option value="<?= $r ?>"><?= $r ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group" id="stationGroup">
      <label>Nome cassa</label>
      <input type="text" name="station_name" id="stationName" maxlength="80"
             placeholder="es. Cassa Campo, Cassa Stand Gastronomico">
    </div>
    <div class="form-actions">
      <button type="submit" class="btn btn-gold">Aggiungi utente</button>
    </div>
  </form>
</section>

</div></main>
<script>
const roleSel = document.getElementById('userRole');
const stationGrp = document.getElementById('stationGroup');
const stationInp = document.getElementById('stationName');
function toggleStation() {
  const isOp = roleSel.value === 'operatore';
  stationGrp.style.display = isOp ? 'block' : 'none';
  stationInp.required = isOp;
  if (!isOp) stationInp.value = '';
}
roleSel.addEventListener('change', toggleStation);
toggleStation();
</script>
</body>
</html>
