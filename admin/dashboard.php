<?php
declare(strict_types=1);
require __DIR__ . '/_inc/auth.php';
require __DIR__ . '/_inc/seo.php';
require __DIR__ . '/_inc/gsc.php';
require __DIR__ . '/_inc/layout.php';
require_login();

$root = dirname(__DIR__);
$pages = analyse_site($root);
$articles = array_values(array_filter($pages, static fn ($p) => $p['category'] === 'blog'));
usort($articles, static fn ($a, $b) => strcmp($b['published'] ?? '', $a['published'] ?? ''));
$avgScore = $pages ? (int) round(array_sum(array_column($pages, 'score')) / count($pages)) : 0;
$toFix = $pages;
usort($toFix, static fn ($a, $b) => $a['score'] <=> $b['score']);
$toFix = array_slice($toFix, 0, 5);

$activity = [];
$dbError = '';
if (db_configured()) {
    try {
        $activity = db_query('select created_at, email, event, host(ip) as ip from admin_activity order by created_at desc limit 8');
    } catch (DbError $e) {
        $dbError = $e->getMessage();
    }
}
$EVENT_LABELS = ['login' => ['Connexion', 'ok'], 'login_failed' => ['Échec de connexion', 'warn'], 'logout' => ['Déconnexion', 'muted-badge']];

$links = [
    ['Google Search Console', 'https://search.google.com/search-console?resource_id=sc-domain:climanova-energie.fr', 'Clics, positions, indexation'],
    ['Fiche Google Business', 'https://business.google.com/', 'Avis, photos, posts Maps'],
    ['Bing Webmaster Tools', 'https://www.bing.com/webmasters', 'Indexation Bing'],
    ['Meta Business Suite', 'https://business.facebook.com/', 'Facebook, Instagram, pixel'],
    ['PageSpeed Insights', 'https://pagespeed.web.dev/analysis?url=' . rawurlencode(SITE . '/'), 'Vitesse mobile du site'],
    ['Voir le site', SITE . '/', 'Le site public'],
];

admin_header('Tableau de bord', 'dashboard');
?>
<h1>Bonjour 👋</h1>
<p class="muted">Vue d’ensemble de climanova-energie.fr.</p>

<section class="tiles">
  <div class="tile"><div class="tile-label">Pages dans le sitemap</div><div class="tile-value"><?= count($pages) ?></div></div>
  <div class="tile"><div class="tile-label">Articles de blog</div><div class="tile-value"><?= count($articles) ?></div><div class="tile-sub">Dernier : <?= h(fr_date($articles[0]['published'] ?? '')) ?></div></div>
  <div class="tile"><div class="tile-label">Score SEO moyen</div><div class="tile-value <?= score_class($avgScore) ?>-text"><?= $avgScore ?><small>/100</small></div><div class="tile-sub"><a href="/admin/classement.php">Voir le classement →</a></div></div>
  <div class="tile"><div class="tile-label">Search Console</div><div class="tile-value small"><?= gsc_config() ? 'Connecté' : 'Non connecté' ?></div><div class="tile-sub"><a href="/admin/parametres.php#search-console"><?= gsc_config() ? 'Paramètres' : 'Connecter →' ?></a></div></div>
</section>

<div class="grid-2">
  <section class="card">
    <div class="card-head"><h2>Pages à améliorer en priorité</h2><a href="/admin/classement.php?sort=score" class="small">Tout voir →</a></div>
    <table>
      <tbody>
      <?php foreach ($toFix as $p): ?>
        <tr>
          <td><a href="<?= h($p['url']) ?>" target="_blank" rel="noopener"><?= h($p['path']) ?></a>
            <div class="muted path"><?= h($p['issues'][0][0] ?? '') ?></div></td>
          <td class="nowrap num"><span class="badge <?= score_class($p['score']) ?>"><?= $p['score'] ?>/100</span></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </section>

  <section class="card">
    <div class="card-head"><h2>Historique des connexions</h2><a href="/admin/parametres.php#base-de-donnees" class="small">Base de données →</a></div>
    <?php if ($dbError): ?><p><span class="badge warn">Erreur</span> <?= h($dbError) ?></p>
    <?php elseif (!db_configured()): ?><p class="muted">Base de données non connectée.</p>
    <?php elseif (!$activity): ?><p class="muted">Aucune activité enregistrée.</p>
    <?php else: ?>
      <table>
        <tbody>
        <?php foreach ($activity as $a): [$label, $cls] = $EVENT_LABELS[$a['event']] ?? [$a['event'], 'muted-badge']; ?>
          <tr>
            <td class="nowrap"><?= h(date('d/m H:i', strtotime($a['created_at']))) ?></td>
            <td><span class="badge <?= $cls ?>"><?= h($label) ?></span></td>
            <td class="muted path"><?= h($a['ip'] ?? '—') ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </section>
</div>

<section class="card">
  <h2>Raccourcis</h2>
  <div class="links">
    <?php foreach ($links as [$label, $href, $sub]): ?>
      <a class="link" href="<?= h($href) ?>" target="_blank" rel="noopener"><strong><?= h($label) ?> ↗</strong><span class="muted"><?= h($sub) ?></span></a>
    <?php endforeach; ?>
  </div>
</section>
<?php admin_footer();
