<?php
declare(strict_types=1);
require __DIR__ . '/_inc/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_valid($_POST['csrf'] ?? null)) {
    log_admin_event('logout', (string) ($_SESSION['admin_email'] ?? ''));
    logout();
}
header('Location: /admin/');
exit;
