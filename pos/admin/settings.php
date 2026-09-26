<?php
// pos/api/settings.php — Lettura e aggiornamento impostazioni POS
// GET  → restituisce tutte le impostazioni
// POST → aggiorna una o più chiavi (solo admin)
declare(strict_types=1);

define('POS_API', true);
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/role_check.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Lettura pubblica per operatori (serve alla cassa per mostrare le tariffe)
    require_role(['admin', 'operatore', 'bar', 'cucina']);
    try {
        $rows = $pdo->query("SELECT `key`, `value`, `label` FROM pos_settings")->fetchAll();
        $out  = [];
        foreach ($rows as $r) {
            $out[$r['key']] = ['value' => $r['value'], 'label' => $r['label']];
        }
        pos_json(true, $out);
    } catch (Throwable $e) {
        pos_json(false, null, 'Errore lettura impostazioni.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Scrittura solo admin
    require_role(['admin']);
    $raw   = file_get_contents('php://input');
    $input = json_decode($raw ?: '', true);
    if (!is_array($input)) {
        pos_json(false, null, 'Payload non valido.');
    }
    $allowed = ['cover_fee', 'takeaway_fee'];
    try {
        $stmt = $pdo->prepare("UPDATE pos_settings SET `value` = ? WHERE `key` = ?");
        $updated = 0;
        foreach ($allowed as $key) {
            if (array_key_exists($key, $input)) {
                $val = max(0.0, (float) $input[$key]);
                $stmt->execute([number_format($val, 2, '.', ''), $key]);
                $updated++;
            }
        }
        pos_json(true, ['updated' => $updated]);
    } catch (Throwable $e) {
        pos_json(false, null, 'Errore salvataggio impostazioni.');
    }
}

pos_json(false, null, 'Metodo non consentito.');
