<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';

if (is_admin_logged()) {
    staff_continue_after_login();
}

// The established admin login view is the single staff credential form.
header('Location: ' . ADMIN_PATH . '/login.php');
exit;
