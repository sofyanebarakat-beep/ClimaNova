<?php
declare(strict_types=1);
require __DIR__ . '/_inc/auth.php';

if (is_logged_in()) {
    header('Location: /admin/dashboard.php');
    exit;
}

$error = '';
$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = (string) ($_POST['email'] ?? '');
    if (!csrf_valid($_POST['csrf'] ?? null)) {
        $error = 'Session expirée, veuillez réessayer.';
    } elseif (is_locked_out()) {
        $error = 'Trop de tentatives. Réessayez dans 15 minutes.';
    } elseif (attempt_login($email, (string) ($_POST['password'] ?? ''))) {
        header('Location: /admin/dashboard.php');
        exit;
    } else {
        usleep(400000); // slows down guessing
        $error = 'E-mail ou mot de passe incorrect.';
    }
}
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Connexion — Admin ClimaNova</title>
<link rel="icon" href="/images/69787fd4f36671416d6263df_ClimaNova Energie-favicon.png">
<link rel="stylesheet" href="/admin/admin.css">
</head>
<body class="login-page">
  <main class="login-card">
    <img src="/images/climanova-logo-premium.svg" alt="ClimaNova Énergie" class="login-logo" width="180" height="40">
    <h1>Espace administrateur</h1>
    <p class="muted">Connectez-vous pour gérer le site.</p>

    <?php if ($error): ?>
      <div class="alert" role="alert"><?= h($error) ?></div>
    <?php endif; ?>

    <form method="post" action="/admin/" autocomplete="on">
      <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
      <label for="email">E-mail</label>
      <input id="email" name="email" type="email" required autocomplete="username" value="<?= h($email) ?>" autofocus>
      <label for="password">Mot de passe</label>
      <div class="pw-wrap">
        <input id="password" name="password" type="password" required autocomplete="current-password">
        <button type="button" class="pw-toggle" aria-label="Afficher le mot de passe"
          onclick="const i=document.getElementById('password');i.type=i.type==='password'?'text':'password';">👁</button>
      </div>
      <button type="submit" class="btn btn-primary">Se connecter</button>
    </form>
  </main>
</body>
</html>
