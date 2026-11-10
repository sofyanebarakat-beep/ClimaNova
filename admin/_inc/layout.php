<?php
// Shared admin chrome: sidebar navigation + top bar. Call admin_header() then admin_footer().
declare(strict_types=1);

function admin_nav(): array
{
    return [
        ['Tableau de bord', '/admin/dashboard.php', 'dashboard', '▦'],
        ['Classement SEO', '/admin/classement.php', 'classement', '▲', [
            ['Toutes les pages', '/admin/classement.php', 'classement:'],
            ['Services', '/admin/classement.php?cat=services', 'classement:services'],
            ['Villes', '/admin/classement.php?cat=villes', 'classement:villes'],
            ['Blog', '/admin/classement.php?cat=blog', 'classement:blog'],
        ]],
        ['SEO Agent', '/admin/seo', 'seo', '✦'],
        ['Paramètres', '/admin/parametres.php', 'parametres', '⚙'],
    ];
}

function admin_header(string $title, string $active): void
{
    $section = explode(':', $active)[0];
    ?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= h($title) ?> — Admin ClimaNova</title>
<link rel="icon" href="/images/69787fd4f36671416d6263df_ClimaNova Energie-favicon.png">
<link rel="stylesheet" href="/admin/admin.css?v=5">
</head>
<body class="has-sidebar">
<input type="checkbox" id="nav-toggle" class="nav-toggle" aria-hidden="true">
<aside class="sidebar" aria-label="Navigation administrateur">
  <a href="/admin/dashboard.php" class="brand">
    <img src="/images/climanova-logo-premium.svg" alt="ClimaNova Énergie" width="150" height="34">
    <span>Admin</span>
  </a>
  <nav>
    <?php foreach (admin_nav() as $item): [$label, $href, $key, $icon] = $item; $children = $item[4] ?? []; ?>
      <a href="<?= h($href) ?>" class="nav-item <?= $section === $key ? 'active' : '' ?>"><span class="nav-icon"><?= $icon ?></span><?= h($label) ?></a>
      <?php if ($children && $section === $key): ?>
        <div class="nav-sub">
          <?php foreach ($children as [$cl, $ch, $ck]): ?>
            <a href="<?= h($ch) ?>" class="<?= $active === $ck ? 'active' : '' ?>"><?= h($cl) ?></a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    <?php endforeach; ?>
  </nav>
  <div class="sidebar-foot">
    <a href="<?= h(SITE) ?>/" target="_blank" rel="noopener" class="nav-item"><span class="nav-icon">↗</span>Voir le site</a>
    <div class="muted user"><?= h($_SESSION['admin_email'] ?? '') ?></div>
    <form method="post" action="/admin/logout.php">
      <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
      <button type="submit" class="btn btn-ghost full">Déconnexion</button>
    </form>
  </div>
</aside>
<label for="nav-toggle" class="nav-backdrop" aria-hidden="true"></label>
<div class="main">
  <header class="mobile-bar">
    <label for="nav-toggle" class="burger" aria-label="Menu">☰</label>
    <img src="/images/climanova-logo-premium.svg" alt="ClimaNova Énergie" height="28">
  </header>
  <main class="wrap">
<?php
}

function admin_footer(): void
{
    echo "  </main>\n</div>\n</body>\n</html>\n";
}
