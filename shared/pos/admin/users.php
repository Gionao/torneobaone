<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/role_check.php';
require_role(['admin']);

$posNavActive = 'users';
$currentId    = (int) $_SESSION['pos_admin_id'];
$message      = null;
$messageType  = 'success';
$editUserId   = (int) ($_GET['edit_password'] ?? 0);

$roles = ['admin', 'operatore', 'bar', 'cucina'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'create_user') {
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $confirm  = $_POST['password_confirm'] ?? '';
            $role     = $_POST['role'] ?? 'operatore';
            $station  = trim($_POST['station_name'] ?? '');

            if ($username === '' || strlen($password) < 8) {
                throw new RuntimeException('Username e password (min 8 caratteri) obbligatori.');
            }
            if ($password !== $confirm) {
                throw new RuntimeException('Le password non coincidono.');
            }
            if (!in_array($role, $roles, true)) {
                throw new RuntimeException('Ruolo non valido.');
            }
            if ($role === 'operatore' && $station === '') {
                throw new RuntimeException('Nome cassa obbligatorio per gli operatori.');
            }
            if ($role !== 'operatore') {
                $station = null;
            }

            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            $pdo->prepare('
                INSERT INTO admin (username, role, station_name, password_hash)
                VALUES (?, ?, ?, ?)
            ')->execute([$username, $role, $station ?: null, $hash]);
            $message = 'Utente creato.';
        } elseif ($action === 'update_password') {
            $userId   = (int) ($_POST['user_id'] ?? 0);
            $password = $_POST['password'] ?? '';
            $confirm  = $_POST['password_confirm'] ?? '';

            if ($userId < 1 || strlen($password) < 8) {
                throw new RuntimeException('Password non valida (min 8 caratteri).');
            }
            if ($password !== $confirm) {
                throw new RuntimeException('Le password non coincidono.');
            }

            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            $pdo->prepare('UPDATE admin SET password_hash = ? WHERE id = ?')->execute([$hash, $userId]);
            $message = 'Password aggiornata.';
            $editUserId = 0;
        } elseif ($action === 'delete_user') {
            $userId = (int) ($_POST['user_id'] ?? 0);
            if ($userId === $currentId) {
                throw new RuntimeException('Non puoi eliminare il tuo account.');
            }
            $pdo->prepare('DELETE FROM admin WHERE id = ?')->execute([$userId]);
            $message = 'Utente eliminato.';
        }
    } catch (Throwable $e) {
        $messageType = 'error';
        $message = DEBUG_MODE ? $e->getMessage() : 'Operazione non riuscita.';
    }
}

$users = $pdo->query('
    SELECT id, username, role, station_name, created_at
    FROM admin ORDER BY role, username
')->fetchAll();

$pageTitle = 'Utenti POS';
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?> — <?= htmlspecialchars(SITE_NAME) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700;800&family=Barlow:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/admin.css">
  <link rel="stylesheet" href="<?= SITE_URL ?>/pos/assets/css/pos.css">
</head>
<body class="admin-body">
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
    <input type="hidden" name="action" value="update_password">
    <input type="hidden" name="user_id" value="<?= $editUserId ?>">
    <div class="form-group">
      <label>Nuova password</label>
      <input type="password" name="password" required minlength="8">
    </div>
    <div class="form-group">
      <label>Conferma password</label>
      <input type="password" name="password_confirm" required minlength="8">
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
document.getElementById('posNavToggle')?.addEventListener('click', () => {
  document.getElementById('posNavLinks')?.classList.toggle('open');
});
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
