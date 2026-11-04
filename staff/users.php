<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';

$principal = staff_require_authenticated();
if (!$principal['is_super_admin']) {
    http_response_code(403);
    exit('Accesso riservato al Super Admin.');
}
$pdo = db();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
$messaggio = '';
$errore = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        if (!csrf_verify()) {
            throw new RuntimeException('Token di sicurezza non valido.');
        }
        $action = (string) ($_POST['action'] ?? '');
        $targetId = (int) ($_POST['user_id'] ?? 0);
        if ($action === 'create') {
            $sector = (string) ($_POST['sector'] ?? '');
            $role = (string) ($_POST['role'] ?? '');
            if (!staff_validate_sector_role($sector, $role)) {
                throw new RuntimeException('Settore o ruolo non valido.');
            }
            $username = trim((string) ($_POST['username'] ?? ''));
            $lookup = $pdo->prepare('SELECT id FROM admin WHERE username=? AND attivo=1 AND is_super_admin=0 LIMIT 1');
            $lookup->execute([$username]);
            $existing = (int) ($lookup->fetchColumn() ?: 0);
            if ($existing > 0) {
                staff_assign_sector_role($existing, $sector, $role, (string) ($_POST['station_name'] ?? '') ?: null);
                $messaggio = 'Accesso assegnato all’account esistente. Nessuna password è stata modificata.';
            } else {
                $created = staff_create_account_with_temporary_password($username, $sector, $role, (string) ($_POST['station_name'] ?? '') ?: null);
                $messaggio = 'Account creato. Password temporanea da consegnare in modo protetto; non verrà mostrata di nuovo: ' . $created['temporary_password'];
            }
        } elseif ($action === 'create_super') {
            $temporaryPassword = staff_generate_temporary_password();
            $createdId = staff_create_super_admin(trim((string) ($_POST['username'] ?? '')), $temporaryPassword);
            $messaggio = 'Super Admin creato (ID ' . $createdId . '). Password temporanea da consegnare in modo protetto; non verrà mostrata di nuovo: ' . $temporaryPassword;
        } elseif ($action === 'assign') {
            staff_assign_sector_role($targetId, (string) ($_POST['sector'] ?? ''), (string) ($_POST['role'] ?? ''), (string) ($_POST['station_name'] ?? '') ?: null);
            $messaggio = 'Assegnazione aggiornata.';
        } elseif ($action === 'revoke') {
            staff_revoke_sector_role($targetId, (string) ($_POST['sector'] ?? ''));
            $messaggio = 'Assegnazione revocata.';
        } elseif ($action === 'assignment_status') {
            staff_set_sector_assignment_active($targetId, (string) ($_POST['sector'] ?? ''), (int) ($_POST['active'] ?? 0) === 1);
            $messaggio = 'Stato assegnazione aggiornato.';
        } elseif ($action === 'status') {
            staff_set_global_status($targetId, (int) ($_POST['active'] ?? 0) === 1);
            $messaggio = 'Stato account aggiornato.';
        } elseif ($action === 'super') {
            staff_set_super_admin($targetId, (int) ($_POST['enabled'] ?? 0) === 1);
            $messaggio = 'Privilegio Super Admin aggiornato.';
        } elseif ($action === 'username') {
            staff_update_username($targetId, trim((string) ($_POST['username'] ?? '')));
            $messaggio = 'Username aggiornato.';
        } else {
            throw new RuntimeException('Operazione non riconosciuta.');
        }
    } catch (Throwable $e) {
        $errore = $e->getMessage();
    }
}

$search = trim((string) ($_GET['q'] ?? ''));
$statusFilter = (string) ($_GET['stato'] ?? '');
$sectorFilter = (string) ($_GET['settore'] ?? '');
if (!in_array($statusFilter, ['', 'attivi', 'disattivi', 'cambio'], true)) $statusFilter = '';
if ($sectorFilter !== '' && !isset(STAFF_SECTOR_ROLES[$sectorFilter])) $sectorFilter = '';
$where = [];
$params = [];
if ($search !== '') {
    $where[] = 'u.username LIKE ?';
    $params[] = '%' . addcslashes($search, '%_\\') . '%';
}
if ($statusFilter === 'attivi') $where[] = 'u.attivo=1';
if ($statusFilter === 'disattivi') $where[] = 'u.attivo=0';
if ($statusFilter === 'cambio') $where[] = 'u.must_change_password=1';
if ($sectorFilter !== '') {
    $where[] = 'EXISTS (SELECT 1 FROM staff_assegnazioni sf WHERE sf.id_admin=u.id AND sf.area=? AND sf.attivo=1)';
    $params[] = $sectorFilter;
}
$sql = 'SELECT u.id,u.username,u.attivo,u.is_super_admin,u.must_change_password FROM admin u'
    . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY u.is_super_admin DESC,u.username LIMIT 250';
$usersStmt = $pdo->prepare($sql);
$usersStmt->execute($params);
$users = $usersStmt->fetchAll();
$assignmentStmt = $pdo->query('SELECT id_admin,area,ruolo,station_name,attivo FROM staff_assegnazioni');
$byUser = [];
foreach ($assignmentStmt->fetchAll() as $a) $byUser[(int) $a['id_admin']][(string) $a['area']] = $a;
$csrf = csrf_token();
$roleLabels = [
    'amministratore' => 'amministratore', 'segreteria' => 'segreteria',
    'gestore' => 'gestore', 'telecronista' => 'telecronista',
    'operatore' => 'operatore', 'bar' => 'bar', 'cucina' => 'cucina',
    'editor' => 'editor', 'uploader' => 'uploader',
];
?>
<!DOCTYPE html><html lang="it"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Gestione staff — <?= htmlspecialchars(SITE_NAME, ENT_QUOTES, 'UTF-8') ?></title>
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css"><link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/admin.css"><link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/baone-ui.css"><link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/admin-workspace.css?v=1"><link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/staff-users.css">
</head><body class="tb-ui admin-body admin-workspace"><main class="admin-main"><div class="admin-container">
<header class="staff-users-header">
  <div class="staff-users-heading">
    <div><span class="staff-users-eyebrow">Amministrazione</span><h1>Gestione staff</h1></div>
    <p>Account, assegnazioni e stato globale dello staff.</p>
  </div>
  <a class="btn btn-gold staff-users-add" href="#nuovo-account">+ Aggiungi Utente</a>
</header>
<?php if ($messaggio !== ''): ?><div class="alert alert-success"><?= htmlspecialchars($messaggio, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<?php if ($errore !== ''): ?><div class="alert alert-error"><?= htmlspecialchars($errore, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<section class="staff-users-panel staff-users-filters" aria-labelledby="filtri-titolo">
  <div class="staff-users-panel-heading"><div><span class="staff-users-eyebrow">Elenco account</span><h2 id="filtri-titolo">Cerca e filtra</h2></div><span class="staff-users-count"><?= count($users) ?> risultati<?= count($users) === 250 ? ' · limite 250' : '' ?></span></div>
  <form method="get" class="staff-users-filter-form">
    <div class="form-group staff-users-search"><label for="users-search">Cerca username</label><input id="users-search" type="search" name="q" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>" placeholder="Es. mario.rossi"></div>
    <div class="form-group"><label for="users-status">Stato account</label><select id="users-status" name="stato"><option value="">Tutti gli stati</option><option value="attivi" <?= $statusFilter==='attivi'?'selected':'' ?>>Attivi</option><option value="disattivi" <?= $statusFilter==='disattivi'?'selected':'' ?>>Disattivi</option><option value="cambio" <?= $statusFilter==='cambio'?'selected':'' ?>>Cambio password richiesto</option></select></div>
    <div class="form-group"><label for="users-sector">Settore</label><select id="users-sector" name="settore"><option value="">Tutti i settori</option><?php foreach (STAFF_SECTOR_ROLES as $s => $_): ?><option value="<?= htmlspecialchars($s, ENT_QUOTES, 'UTF-8') ?>" <?= $sectorFilter===$s?'selected':'' ?>><?= htmlspecialchars(ucfirst($s), ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></div>
    <div class="staff-users-filter-actions"><button class="btn btn-outline" type="submit">Applica filtri</button><?php if ($search !== '' || $statusFilter !== '' || $sectorFilter !== ''): ?><a class="staff-users-reset" href="<?= htmlspecialchars((string) strtok($_SERVER['REQUEST_URI'] ?? '', '?'), ENT_QUOTES, 'UTF-8') ?>">Azzera</a><?php endif; ?></div>
  </form>
</section>
<div class="staff-users-create-grid" id="nuovo-account">
  <section class="staff-users-panel staff-users-create">
    <div class="staff-users-panel-heading"><div><span class="staff-users-eyebrow">Nuovo accesso</span><h2>Crea account o assegna un settore</h2></div></div>
    <form method="post" class="staff-users-create-form"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="action" value="create">
      <div class="form-group"><label for="create-username">Username account</label><input id="create-username" name="username" required maxlength="60" pattern="[A-Za-z0-9._-]+"></div>
      <div class="form-group"><label for="create-sector">Settore</label><select id="create-sector" name="sector" required><?php foreach (STAFF_SECTOR_ROLES as $s => $rs): ?><option value="<?= htmlspecialchars($s, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(ucfirst($s), ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></div>
      <div class="form-group"><label for="create-role">Ruolo</label><select id="create-role" name="role" required><?php foreach (STAFF_SECTOR_ROLES as $s => $rs): foreach ($rs as $r): ?><option data-sector="<?= htmlspecialchars($s, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars($r, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($s . ' — ' . $roleLabels[$r], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; endforeach; ?></select></div>
      <div class="form-group"><label for="create-station">Postazione POS <span>(se prevista)</span></label><input id="create-station" name="station_name" maxlength="80"></div>
      <div class="staff-users-form-submit"><button class="btn btn-gold" type="submit">Crea o assegna</button><p>Un nuovo account riceve una password temporanea; gli account esistenti mantengono la password corrente.</p></div>
    </form>
  </section>
  <section class="staff-users-panel staff-users-super">
    <div class="staff-users-panel-heading"><div><span class="staff-users-eyebrow">Privilegi elevati</span><h2>Nuovo Super Admin</h2></div></div>
    <p class="staff-users-help">Crea un account con accesso completo alla gestione staff e alle assegnazioni.</p>
    <form method="post" class="staff-users-super-form"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="action" value="create_super"><div class="form-group"><label for="super-username">Username</label><input id="super-username" name="username" required maxlength="60" pattern="[A-Za-z0-9._-]+"></div><button class="btn btn-outline" type="submit" onclick="return confirm('Creare un nuovo Super Admin?')">Crea Super Admin</button></form>
    <small>La password temporanea viene generata dal server e consegnata una sola volta.</small>
  </section>
</div>
<section class="staff-users-panel staff-users-table-panel" aria-label="Account staff">
  <div class="staff-users-table-wrap"><table class="admin-table staff-users-table"><thead><tr><th scope="col">Utente</th><?php foreach (array_keys(STAFF_SECTOR_ROLES) as $sector): ?><th scope="col"><?= htmlspecialchars(ucfirst($sector), ENT_QUOTES, 'UTF-8') ?></th><?php endforeach; ?><th scope="col">Stato account</th><th scope="col" class="staff-users-actions-heading">Azioni</th></tr></thead><tbody>
  <?php if ($users === []): ?><tr><td class="staff-users-empty" colspan="<?= count(STAFF_SECTOR_ROLES) + 3 ?>">Nessun account corrisponde ai filtri selezionati.</td></tr><?php endif; ?>
  <?php foreach ($users as $u): $id=(int)$u['id']; $super=(int)$u['is_super_admin']===1; ?><tr class="<?= $super ? 'is-super-admin' : '' ?>">
    <td><div class="staff-users-identity"><span class="staff-users-avatar" aria-hidden="true"><?= htmlspecialchars(strtoupper(substr((string)$u['username'], 0, 1)), ENT_QUOTES, 'UTF-8') ?></span><div class="staff-users-identity-copy"><strong><?= htmlspecialchars($u['username'], ENT_QUOTES, 'UTF-8') ?></strong><?php if ($super): ?><span class="staff-users-role staff-users-role--super">Super Admin</span><?php endif; ?><?php if ((int)$u['must_change_password']): ?><span class="staff-users-note">Cambio password richiesto</span><?php endif; ?></div></div></td>
    <?php foreach (array_keys(STAFF_SECTOR_ROLES) as $sector): $a=$byUser[$id][$sector]??null; ?><td><?php if ($a): ?><div class="staff-users-assignment"><div class="staff-users-assignment-label"><span class="staff-users-role"><?= htmlspecialchars($roleLabels[$a['ruolo']] ?? $a['ruolo'], ENT_QUOTES, 'UTF-8') ?></span><span class="staff-users-status <?= (int)$a['attivo'] ? 'is-active' : 'is-inactive' ?>"><?= (int)$a['attivo']?'Attiva':'Disattiva' ?></span></div><?php if ($sector==='pos' && $a['station_name']): ?><small><?= htmlspecialchars($a['station_name'], ENT_QUOTES, 'UTF-8') ?></small><?php endif; ?><div class="staff-users-inline-actions">
      <form method="post"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="action" value="assignment_status"><input type="hidden" name="user_id" value="<?= $id ?>"><input type="hidden" name="sector" value="<?= htmlspecialchars($sector, ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="active" value="<?= (int)$a['attivo']?0:1 ?>"><button class="btn btn-sm btn-outline" type="submit"><?= (int)$a['attivo']?'Disattiva':'Riattiva' ?></button></form>
      <form method="post" onsubmit="return confirm('Revocare solo questa assegnazione?')"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="action" value="revoke"><input type="hidden" name="user_id" value="<?= $id ?>"><input type="hidden" name="sector" value="<?= htmlspecialchars($sector, ENT_QUOTES, 'UTF-8') ?>"><button class="btn btn-sm btn-red" type="submit">Revoca</button></form>
    </div></div><?php else: ?><span class="staff-users-unassigned">Non assegnato</span><?php endif; ?></td><?php endforeach; ?>
    <td><span class="staff-users-status <?= (int)$u['attivo'] ? 'is-active' : 'is-inactive' ?>"><?= (int)$u['attivo']?'Attivo':'Disattivo' ?></span></td>
    <td><div class="staff-users-row-actions">
      <form method="post" class="staff-users-username-form"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="action" value="username"><input type="hidden" name="user_id" value="<?= $id ?>"><label class="staff-users-sr-only" for="username-<?= $id ?>">Username per <?= htmlspecialchars($u['username'], ENT_QUOTES, 'UTF-8') ?></label><input id="username-<?= $id ?>" name="username" value="<?= htmlspecialchars($u['username'], ENT_QUOTES, 'UTF-8') ?>" required maxlength="60"><button class="btn btn-sm btn-outline" type="submit">Salva</button></form>
      <form method="post" onsubmit="return confirm('<?= (int)$u['attivo']?'Disattivare':'Riattivare' ?> questo account?')"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="action" value="status"><input type="hidden" name="user_id" value="<?= $id ?>"><input type="hidden" name="active" value="<?= (int)$u['attivo']?0:1 ?>"><button class="btn btn-sm btn-outline" type="submit"><?= (int)$u['attivo']?'Disattiva':'Attiva' ?></button></form>
      <form method="post" onsubmit="return confirm('Modificare lo stato Super Admin? Il sistema protegge l’ultimo Super Admin attivo.')"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="action" value="super"><input type="hidden" name="user_id" value="<?= $id ?>"><input type="hidden" name="enabled" value="<?= $super?0:1 ?>"><button class="btn btn-sm btn-outline" type="submit"><?= $super?'Rimuovi Super Admin':'Nomina Super Admin' ?></button></form>
    </div></td>
  </tr><?php endforeach; ?>
  </tbody></table></div>
</section>
<p class="staff-users-back"><a href="<?= STAFF_URL ?>/index.php">Torna alla selezione area</a></p></div></main>
<script>
document.querySelectorAll('form').forEach(form=>{const s=form.querySelector('select[name="sector"]'),r=form.querySelector('select[name="role"]');if(!s||!r)return;const filter=()=>{[...r.options].forEach(o=>o.hidden=o.dataset.sector!==s.value);const first=[...r.options].find(o=>!o.hidden);if(first)r.value=first.value;};s.addEventListener('change',filter);filter();});
</script></body></html>
