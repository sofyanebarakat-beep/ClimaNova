<?php
declare(strict_types=1);
require __DIR__ . '/_inc/auth.php';
require_login();

// Everything here is read from the site's own files: no database.
$root = dirname(__DIR__);
const SITE = 'https://climanova-energie.fr';

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
