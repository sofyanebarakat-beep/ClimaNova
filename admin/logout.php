<?php
declare(strict_types=1);
require __DIR__ . '/_inc/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_valid($_POST['csrf'] ?? null)) {
    logout();
}
header('Location: /admin/');
exit;
