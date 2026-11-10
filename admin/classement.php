<?php
declare(strict_types=1);
require __DIR__ . '/_inc/auth.php';
require __DIR__ . '/_inc/seo.php';
require __DIR__ . '/_inc/gsc.php';
require __DIR__ . '/_inc/layout.php';
require_login();

$root = dirname(__DIR__);
$cat = array_key_exists($_GET['cat'] ?? '', CATEGORIES) ? $_GET['cat'] : '';
$SORTS = ['score' => 'Score SEO', 'position' => 'Position Google', 'clicks' => 'Clics', 'impressions' => 'Impressions'];
$sort = array_key_exists($_GET['sort'] ?? '', $SORTS) ? $_GET['sort'] : 'score';
$refresh = $_SERVER['REQUEST_METHOD'] === 'POST' && csrf_valid($_POST['csrf'] ?? null);

// On-page scores are always computed live from the files (fast, local).
$pages = analyse_site($root);

// Google figures: cached once a day in Neon (seo_snapshots), refreshed on demand.
$stats = [];
$gscError = '';
$dbError = '';
$cachedAt = null;
$dbOk = db_configured();
if ($dbOk) {
    try {
        if (!$refresh) {
            $rows = db_query('select path, position, clicks, impressions, top_query, created_at from seo_snapshots where snapshot_date = current_date');
            $hasGoogle = (bool) array_filter($rows, static fn ($r) => $r['position'] !== null);
            if ($rows && ($hasGoogle || !gsc_config())) {
                foreach ($rows as $r) {
                    if ($r['position'] !== null) $stats[$r['path']] = ['position' => (float) $r['position'], 'clicks' => (int) $r['clicks'], 'impressions' => (int) $r['impressions'], 'top_query' => $r['top_query']];
                }
                $cachedAt = $rows[0]['created_at'];
            }
        }
    } catch (DbError $e) {
        $dbError = $e->getMessage();
        $dbOk = false;
    }
}
if ($cachedAt === null) {
    if (gsc_config()) {
        try {
            $stats = gsc_page_stats();
        } catch (Throwable $e) {
            $gscError = $e->getMessage();
        }
    }
    if ($dbOk && $pages) {
        // One upsert for all pages: $1..$7 per row.
        $values = [];
        $params = [];
        foreach ($pages as $i => $p) {
            $s = $stats[$p['path']] ?? null;
            $b = $i * 7;
            $values[] = sprintf('($%d,$%d,$%d::smallint,$%d::real,$%d::int,$%d::int,$%d)', $b + 1, $b + 2, $b + 3, $b + 4, $b + 5, $b + 6, $b + 7);
            array_push($params, $p['path'], $p['category'], $p['score'], $s['position'] ?? null, $s['clicks'] ?? null, $s['impressions'] ?? null, $s['top_query'] ?? null);
        }
        try {
            db_query('insert into seo_snapshots (path, category, score, position, clicks, impressions, top_query) values ' . implode(',', $values) .
                ' on conflict (snapshot_date, path) do update set category = excluded.category, score = excluded.score, position = excluded.position,
                  clicks = excluded.clicks, impressions = excluded.impressions, top_query = excluded.top_query, created_at = now()', $params);
            $cachedAt = date('c');
        } catch (DbError $e) {
            $dbError = $e->getMessage();
        }
    }
    if ($refresh) {
        header('Location: /admin/classement.php?' . http_build_query(array_filter(['cat' => $cat, 'sort' => $sort])));
        exit;
    }
}

// Trend: compare with the latest snapshot at least 7 days old.
$before = [];
if ($dbOk && !$dbError) {
    try {
        foreach (db_query('select distinct on (path) path, score, position, snapshot_date from seo_snapshots
                           where snapshot_date <= current_date - 7 order by path, snapshot_date desc') as $r) {
            $before[$r['path']] = $r;
        }
    } catch (DbError $e) {
        $dbError = $e->getMessage();
    }
}

foreach ($pages as &$p) {
    $p['g'] = $stats[$p['path']] ?? null;
    $p['before'] = $before[$p['path']] ?? null;
}
unset($p);

$counts = array_count_values(array_column($pages, 'category'));
$list = array_values(array_filter($pages, static fn ($p) => $cat === '' || $p['category'] === $cat));
usort($list, static function ($a, $b) use ($sort) {
    return match ($sort) {
        'position' => ($a['g']['position'] ?? 999) <=> ($b['g']['position'] ?? 999),
        'clicks' => ($b['g']['clicks'] ?? -1) <=> ($a['g']['clicks'] ?? -1),
        'impressions' => ($b['g']['impressions'] ?? -1) <=> ($a['g']['impressions'] ?? -1),
        default => [$b['score'], $a['path']] <=> [$a['score'], $b['path']],
    };
});

$avgScore = $list ? (int) round(array_sum(array_column($list, 'score')) / count($list)) : 0;
$imp = array_sum(array_map(static fn ($p) => $p['g']['impressions'] ?? 0, $list));
$avgPos = $imp ? array_sum(array_map(static fn ($p) => ($p['g']['position'] ?? 0) * ($p['g']['impressions'] ?? 0), $list)) / $imp : null;
$clicks = array_sum(array_map(static fn ($p) => $p['g']['clicks'] ?? 0, $list));
$onPage1 = count(array_filter($list, static fn ($p) => isset($p['g']) && $p['g']['position'] <= 10));
$qs = static fn (array $over) => '/admin/classement.php?' . http_build_query(array_filter(array_merge(['cat' => $cat, 'sort' => $sort], $over)));
$pos1 = static fn (float $x) => number_format($x, 1, ',', ' ');

admin_header('Classement SEO', 'classement:' . $cat);
?>
<div class="page-head">
  <div>
    <h1>Classement SEO<?= $cat ? ' · ' . h(CATEGORIES[$cat]) : '' ?></h1>
    <p class="muted">Score SEO calculé sur le contenu de chaque page<?= gsc_config() ? ' · positions Google des 28 derniers jours' : '' ?>.</p>
  </div>
  <form method="post" action="<?= h($qs([])) ?>">
    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
    <button class="btn btn-ghost" type="submit">↻ Actualiser</button>
  </form>
</div>

<?php if (!gsc_config()): ?>
  <div class="notice">Les positions Google apparaîtront ici après avoir <a href="/admin/parametres.php#search-console">connecté Search Console</a>. Le score SEO fonctionne déjà.</div>
<?php endif; ?>
<?php if ($gscError): ?><div class="alert">Search Console : <?= h($gscError) ?></div><?php endif; ?>
<?php if ($dbError): ?><div class="alert">Base de données : <?= h($dbError) ?></div><?php endif; ?>

<nav class="tabs">
  <a href="<?= h($qs(['cat' => ''])) ?>" class="<?= $cat === '' ? 'active' : '' ?>">Toutes <span><?= count($pages) ?></span></a>
  <?php foreach (CATEGORIES as $k => $label): if (empty($counts[$k])) continue; ?>
    <a href="<?= h($qs(['cat' => $k])) ?>" class="<?= $cat === $k ? 'active' : '' ?>"><?= h($label) ?> <span><?= $counts[$k] ?></span></a>
  <?php endforeach; ?>
</nav>

<section class="tiles">
  <div class="tile"><div class="tile-label">Score SEO moyen</div><div class="tile-value <?= score_class($avgScore) ?>-text"><?= $avgScore ?><small>/100</small></div><div class="tile-sub"><?= count($list) ?> pages</div></div>
  <div class="tile"><div class="tile-label">Position moyenne</div><div class="tile-value"><?= $avgPos ? h($pos1($avgPos)) : '—' ?></div><div class="tile-sub">pondérée par les impressions</div></div>
  <div class="tile"><div class="tile-label">Pages en 1re page</div><div class="tile-value"><?= gsc_config() ? $onPage1 : '—' ?></div><div class="tile-sub">position ≤ 10</div></div>
  <div class="tile"><div class="tile-label">Clics Google</div><div class="tile-value"><?= gsc_config() ? number_format($clicks, 0, ',', ' ') : '—' ?></div><div class="tile-sub">28 derniers jours</div></div>
</section>

<section class="card">
  <div class="card-head">
    <h2>Classement par : <?= h($SORTS[$sort]) ?></h2>
    <div class="seg">
      <?php foreach ($SORTS as $k => $label): ?>
        <a href="<?= h($qs(['sort' => $k])) ?>" class="<?= $sort === $k ? 'active' : '' ?>"><?= h($label) ?></a>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="table-wrap">
    <table class="rank-table">
      <thead><tr><th>#</th><th>Page</th><th>Score</th><th>Position</th><th class="num">Clics</th><th class="num">Impr.</th><th>À corriger</th></tr></thead>
      <tbody>
      <?php foreach ($list as $i => $p): $g = $p['g']; $bf = $p['before']; ?>
        <tr>
          <td class="rank"><?= $i + 1 ?></td>
          <td class="page-cell">
            <a href="<?= h($p['url']) ?>" target="_blank" rel="noopener"><?= h($p['title'] ?: $p['path']) ?></a>
            <div class="muted path"><span class="badge muted-badge"><?= h(CATEGORIES[$p['category']]) ?></span> <?= h($p['path']) ?></div>
            <?php if (!empty($g['top_query'])): ?><div class="muted path">🔎 <?= h($g['top_query']) ?></div><?php endif; ?>
          </td>
          <td class="nowrap">
            <div class="score <?= score_class($p['score']) ?>"><span style="width: <?= $p['score'] ?>%"></span></div>
            <strong><?= $p['score'] ?></strong>
            <?php if ($bf && (int) $bf['score'] !== $p['score']): $d = $p['score'] - (int) $bf['score']; ?>
              <span class="delta <?= $d > 0 ? 'up' : 'down' ?>"><?= $d > 0 ? '+' : '' ?><?= $d ?></span>
            <?php endif; ?>
          </td>
          <td class="nowrap">
            <?php if ($g): ?>
              <strong><?= h($pos1($g['position'])) ?></strong>
              <?php if ($bf && $bf['position'] !== null): $d = (float) $bf['position'] - $g['position']; if (abs($d) >= 0.1): ?>
                <span class="delta <?= $d > 0 ? 'up' : 'down' ?>"><?= $d > 0 ? '▲' : '▼' ?> <?= h($pos1(abs($d))) ?></span>
              <?php endif; endif; ?>
            <?php else: ?><span class="muted">—</span><?php endif; ?>
          </td>
          <td class="num"><?= $g ? number_format($g['clicks'], 0, ',', ' ') : '—' ?></td>
          <td class="num"><?= $g ? number_format($g['impressions'], 0, ',', ' ') : '—' ?></td>
          <td class="issues">
            <?php if (!$p['issues']): ?><span class="badge ok">Parfait</span>
            <?php else: ?>
              <?php foreach (array_slice($p['issues'], 0, 2) as [$label, $pts]): ?><span class="badge warn" title="−<?= $pts ?> pts"><?= h($label) ?></span><?php endforeach; ?>
              <?php if (count($p['issues']) > 2): ?>
                <details><summary><?= count($p['issues']) - 2 ?> de plus</summary>
                  <?php foreach (array_slice($p['issues'], 2) as [$label, $pts]): ?><span class="badge warn" title="−<?= $pts ?> pts"><?= h($label) ?></span><?php endforeach; ?>
                </details>
              <?php endif; ?>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <p class="muted small">
    <?= $cachedAt ? 'Données enregistrées le ' . h(date('d/m/Y à H:i', strtotime($cachedAt))) . ' · ' : '' ?>
    Tendances comparées à il y a 7 jours (après une semaine d’historique).
  </p>
</section>
<?php admin_footer();
