<?php
declare(strict_types=1);
require __DIR__ . '/_inc/auth.php';
require_login();

// Site figures are read from the site's own files; login history comes from Neon Postgres.
$root = dirname(__DIR__);
const SITE = 'https://climanova-energie.fr';

// One-time setup: store the Neon connection string on the server, outside public_html.
$dbNotice = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_db') {
    $url = trim((string) ($_POST['database_url'] ?? ''));
    if (!csrf_valid($_POST['csrf'] ?? null)) {
        $dbNotice = 'Session expirée, veuillez réessayer.';
    } elseif (!db_valid_url($url)) {
        $dbNotice = 'Ce n’est pas une adresse de connexion Neon valide (postgresql://…neon.tech/…).';
    } else {
        try {
            db_save_url($url);
            header('Location: /admin/dashboard.php#base-de-donnees');
            exit;
        } catch (DbError $e) {
            $dbNotice = $e->getMessage();
        }
    }
}

$db = ['configured' => db_configured(), 'ok' => false, 'error' => '', 'version' => '', 'ms' => 0, 'activity' => []];
if ($db['configured']) {
    try {
        $t = microtime(true);
        $v = db_query("select split_part(version(), ' ', 2) as v");
        $db['ms'] = (int) round((microtime(true) - $t) * 1000);
        $db['version'] = $v[0]['v'] ?? '';
        $db['activity'] = db_query(
            'select created_at, email, event, host(ip) as ip from admin_activity order by created_at desc limit 15'
        );
        $db['ok'] = true;
    } catch (DbError $e) {
        $db['error'] = $e->getMessage();
    }
}
$EVENT_LABELS = ['login' => ['Connexion', 'ok'], 'login_failed' => ['Échec de connexion', 'warn'], 'logout' => ['Déconnexion', 'muted-badge']];

function page_info(string $root, string $url): array
{
    $path = parse_url($url, PHP_URL_PATH) ?: '/';
    $file = $root . rtrim($path, '/') . (str_ends_with($path, '.html') ? '' : '/index.html');
    $info = ['url' => $url, 'path' => $path, 'exists' => is_file($file), 'issues' => []];
    if (!$info['exists']) {
        $info['issues'][] = 'Fichier introuvable';
        return $info;
    }
    $html = (string) file_get_contents($file);
    $get = static function (string $re) use ($html): string {
        return preg_match($re, $html, $m) ? html_entity_decode(trim($m[1]), ENT_QUOTES, 'UTF-8') : '';
    };
    $info['title'] = $get('~<title>(.*?)</title>~is');
    $info['description'] = $get('~<meta\s+name=["\']description["\']\s+content=["\'](.*?)["\']~is');
    $info['published'] = $get('~article:published_time["\']\s+content=["\'](.*?)["\']~i')
        ?: $get('~"datePublished"\s*:\s*"(.*?)"~');
    $info['modified'] = date('Y-m-d', (int) filemtime($file));
    $h1 = preg_match_all('~<h1[\s>]~i', $html);
    $tl = mb_strlen($info['title']);
    $dl = mb_strlen($info['description']);

    if ($tl === 0) $info['issues'][] = 'Titre manquant';
    elseif ($tl > 65) $info['issues'][] = "Titre long ($tl car.)";
    if ($dl === 0) $info['issues'][] = 'Meta description manquante';
    elseif ($dl < 70 || $dl > 165) $info['issues'][] = "Description $dl car.";
    if ($h1 !== 1) $info['issues'][] = "$h1 balise(s) H1";
    if (!preg_match('~<link\s+rel=["\']canonical~i', $html)) $info['issues'][] = 'Canonical manquante';
    if (preg_match('~<meta\s+name=["\']robots["\']\s+content=["\'][^"\']*noindex~i', $html)) $info['issues'][] = 'noindex !';
    if (!preg_match('~property=["\']og:image["\']~i', $html)) $info['issues'][] = 'Image de partage manquante';
    return $info;
}

$urls = [];
$sitemap = @file_get_contents($root . '/sitemap.xml');
if ($sitemap && preg_match_all('~<loc>\s*(.*?)\s*</loc>~', $sitemap, $m)) {
    $urls = array_unique($m[1]);
}
$pages = array_map(static fn ($u) => page_info($root, $u), $urls);
$articles = array_values(array_filter($pages, static fn ($p) => preg_match('~^/blog/.+~', $p['path'])));
$others = array_values(array_filter($pages, static fn ($p) => !preg_match('~^/blog/.+~', $p['path'])));
usort($articles, static fn ($a, $b) => strcmp($b['published'] ?? '', $a['published'] ?? ''));

$withIssues = count(array_filter($pages, static fn ($p) => $p['issues']));
$lastArticle = $articles[0]['published'] ?? '';
$lastUpdate = max(array_map(static fn ($p) => $p['modified'] ?? '', $pages) ?: ['']);

function fr_date(string $d): string
{
    if (!$d || !($t = strtotime($d))) return '—';
    $months = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
    return date('j', $t) . ' ' . $months[(int) date('n', $t) - 1] . ' ' . date('Y', $t);
}

$links = [
    ['Voir le site', SITE . '/', 'Le site public'],
    ['Google Search Console', 'https://search.google.com/search-console?resource_id=sc-domain:climanova-energie.fr', 'Clics, positions, indexation'],
    ['Fiche Google Business', 'https://business.google.com/', 'Avis, photos, posts Maps'],
    ['Bing Webmaster Tools', 'https://www.bing.com/webmasters', 'Indexation Bing'],
    ['Meta Business Suite', 'https://business.facebook.com/', 'Facebook, Instagram, pixel'],
    ['PageSpeed Insights', 'https://pagespeed.web.dev/analysis?url=' . rawurlencode(SITE . '/'), 'Vitesse mobile du site'],
];

function issues_cell(array $p): string
{
    if (!$p['issues']) return '<span class="badge ok">OK</span>';
    return implode(' ', array_map(static fn ($i) => '<span class="badge warn">' . h($i) . '</span>', $p['issues']));
}
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Tableau de bord — Admin ClimaNova</title>
<link rel="icon" href="/images/69787fd4f36671416d6263df_ClimaNova Energie-favicon.png">
<link rel="stylesheet" href="/admin/admin.css">
</head>
<body>
<header class="topbar">
  <div class="topbar-inner">
    <a href="/admin/dashboard.php" class="brand">
      <img src="/images/climanova-logo-premium.svg" alt="ClimaNova Énergie" width="150" height="34">
      <span>Admin</span>
    </a>
    <div class="topbar-right">
      <span class="muted user"><?= h($_SESSION['admin_email']) ?></span>
      <form method="post" action="/admin/logout.php">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <button type="submit" class="btn btn-ghost">Déconnexion</button>
      </form>
    </div>
  </div>
</header>

<main class="wrap">
  <h1>Bonjour 👋</h1>
  <p class="muted">Vue d’ensemble du site climanova-energie.fr, calculée à partir des fichiers en ligne.</p>

  <section class="tiles">
    <div class="tile"><div class="tile-label">Pages dans le sitemap</div><div class="tile-value"><?= count($pages) ?></div></div>
    <div class="tile"><div class="tile-label">Articles de blog</div><div class="tile-value"><?= count($articles) ?></div><div class="tile-sub">Dernier : <?= h(fr_date($lastArticle)) ?></div></div>
    <div class="tile"><div class="tile-label">Pages à corriger (SEO)</div><div class="tile-value <?= $withIssues ? 'warn-text' : 'ok-text' ?>"><?= $withIssues ?></div><div class="tile-sub">sur <?= count($pages) ?></div></div>
    <div class="tile"><div class="tile-label">Dernière mise à jour</div><div class="tile-value small"><?= h(fr_date($lastUpdate)) ?></div></div>
  </section>

  <section class="card">
    <h2>Raccourcis</h2>
    <div class="links">
      <?php foreach ($links as [$label, $href, $sub]): ?>
        <a class="link" href="<?= h($href) ?>" target="_blank" rel="noopener">
          <strong><?= h($label) ?> ↗</strong><span class="muted"><?= h($sub) ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="card" id="base-de-donnees">
    <h2>Base de données (Neon)</h2>
    <?php if ($dbNotice): ?><div class="alert"><?= h($dbNotice) ?></div><?php endif; ?>

    <?php if (!$db['configured']): ?>
      <p class="muted">Pas encore connectée. Collez l’adresse de connexion Neon (<code>DATABASE_URL</code>, version « pooled »).
        Elle est enregistrée sur le serveur, hors du dossier public, et n’est jamais affichée ni envoyée sur GitHub.</p>
      <form method="post" action="/admin/dashboard.php#base-de-donnees" class="db-form">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="action" value="save_db">
        <input type="password" name="database_url" required placeholder="postgresql://…@….neon.tech/neondb?sslmode=require" autocomplete="off">
        <button type="submit" class="btn btn-primary">Connecter</button>
      </form>
    <?php elseif (!$db['ok']): ?>
      <p><span class="badge warn">Erreur</span> <?= h($db['error']) ?></p>
    <?php else: ?>
      <p class="db-status"><span class="badge ok">Connectée</span>
        <span class="muted">PostgreSQL <?= h($db['version']) ?> · réponse en <?= $db['ms'] ?> ms</span></p>
      <h3>Historique des connexions</h3>
      <?php if (!$db['activity']): ?>
        <p class="muted">Aucune activité enregistrée pour l’instant.</p>
      <?php else: ?>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Date</th><th>Événement</th><th>E-mail</th><th>Adresse IP</th></tr></thead>
            <tbody>
            <?php foreach ($db['activity'] as $a): [$label, $cls] = $EVENT_LABELS[$a['event']] ?? [$a['event'], 'muted-badge']; ?>
              <tr>
                <td class="nowrap"><?= h(date('d/m/Y H:i', strtotime($a['created_at']))) ?></td>
                <td><span class="badge <?= $cls ?>"><?= h($label) ?></span></td>
                <td><?= h($a['email']) ?></td>
                <td class="muted"><?= h($a['ip'] ?? '—') ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </section>

  <section class="card">
    <h2>Articles de blog</h2>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Article</th><th>Publié</th><th>Contrôle SEO</th></tr></thead>
        <tbody>
        <?php foreach ($articles as $p): ?>
          <tr>
            <td><a href="<?= h($p['url']) ?>" target="_blank" rel="noopener"><?= h($p['title'] ?? $p['path']) ?></a><div class="muted path"><?= h($p['path']) ?></div></td>
            <td class="nowrap"><?= h(fr_date($p['published'] ?? '')) ?></td>
            <td><?= issues_cell($p) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>

  <section class="card">
    <h2>Pages principales</h2>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Page</th><th>Contrôle SEO</th></tr></thead>
        <tbody>
        <?php foreach ($others as $p): ?>
          <tr>
            <td><a href="<?= h($p['url']) ?>" target="_blank" rel="noopener"><?= h($p['path']) ?></a><div class="muted path"><?= h($p['title'] ?? '') ?></div></td>
            <td><?= issues_cell($p) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
</main>
</body>
</html>
