<?php
declare(strict_types=1);
require __DIR__ . '/_inc/auth.php';
require __DIR__ . '/_inc/seo.php';
require __DIR__ . '/_inc/seo_analytics.php';
require __DIR__ . '/_inc/seo_layout.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $isJson = ($_POST['format'] ?? '') === 'json';
    if (!csrf_valid($_POST['csrf'] ?? null)) {
        $r = ['ok' => false, 'message' => 'Your session expired — reload the page.'];
    } elseif ($action === 'sync') {
        try {
            $r = seo_analytics_sync($isJson ? 'auto' : 'manual');
        } catch (Throwable $e) {
            $r = ['ok' => false, 'message' => admin_error($e, 'sync Google data')];
        }
    } elseif ($action === 'connect') {
        try {
            seo_save_google_connection((string) ($_POST['key_json'] ?? ''), (string) ($_POST['gsc_site'] ?? ''), (string) ($_POST['ga4_property'] ?? ''));
            $r = ['ok' => true, 'message' => 'Google connection saved. Click “Sync Google data” to test it.'];
        } catch (Throwable $e) {
            $r = ['ok' => false, 'message' => $e instanceof InvalidArgumentException ? $e->getMessage() : admin_error($e, 'save the Google connection')];
        }
    } else {
        $r = ['ok' => false, 'message' => 'Unknown action.'];
    }
    if ($isJson) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($r);
        exit;
    }
    $_SESSION['flash'] = [$r['ok'] ? 'success' : 'error', $r['message']];
    header('Location: /admin/seo-analytics');
    exit;
}
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$cfg = google_config();
$data = null;
$loadError = '';
try {
    $data = seo_analytics_page();
} catch (Throwable $e) {
    $loadError = admin_error($e, 'load the analytics');
}
$state = $data['state'] ?? [];
$autoSync = $data && $cfg['sa'] && seo_analytics_stale($state) && empty($state['running']);
$n = static fn ($v) => number_format((int) $v, 0, ',', ' ');
$gsc = $data['gsc'] ?? null;
$ga4 = $data['ga4'] ?? null;
$ctr = $gsc && $gsc['impressions'] > 0 ? round($gsc['clicks'] / $gsc['impressions'] * 100, 1) : null;
$csrf = '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">';

seo_header('analytics', 'Analytics', 'Impressions, clicks and visits from Google — last 30 complete days.');
?>
<?php if ($flash): ?><div class="<?= $flash[0] === 'success' ? 'success' : 'alert' ?>"><?= h($flash[1]) ?></div><?php endif; ?>
<?php if ($loadError): ?><div class="alert"><?= h($loadError) ?></div><?php endif; ?>
<div class="notice" id="auto-sync" hidden>Syncing Google data (the last sync is older than <?= SEO_SYNC_STALE_HOURS ?> hours)…</div>

<section class="card">
  <div class="card-head">
    <h2>Google connection</h2>
    <form method="post"><?= $csrf ?><input type="hidden" name="action" value="sync"><button class="btn btn-primary" <?= $cfg['sa'] ? '' : 'disabled' ?>>Sync Google data</button></form>
  </div>
  <dl class="kv">
    <dt>Service account</dt><dd><?= $cfg['email'] ? '<code>' . h($cfg['email']) . '</code>' : '<span class="conn-bad">Not connected</span>' ?></dd>
    <dt>Search Console site</dt><dd><?= $cfg['gsc_site'] ? '<code>' . h($cfg['gsc_site']) . '</code>' : '<span class="conn-bad">Missing GSC_SITE_URL</span>' ?></dd>
    <dt>GA4 property</dt><dd><?= $cfg['ga4_property'] ? '<code>' . h($cfg['ga4_property']) . '</code>' : '<span class="conn-bad">Missing GA4_PROPERTY_ID</span>' ?></dd>
    <dt>Last sync</dt><dd><?php if (!empty($state['last_finished_at'])): ?>
      <span class="<?= !empty($state['last_ok']) ? 'conn-ok' : 'conn-bad' ?>"><?= !empty($state['last_ok']) ? 'OK' : 'Problem' ?></span>
      · <?= h(date('d/m/Y H:i', strtotime($state['last_finished_at']))) ?> · <?= h($state['range'] ?? '') ?><br><span class="small"><?= h($state['last_message'] ?? '') ?></span>
      <?php else: ?>Never<?php endif; ?></dd>
  </dl>
  <details <?= $cfg['sa'] ? '' : 'open' ?>><summary><?= $cfg['sa'] ? 'Change the Google connection' : 'Connect Google' ?></summary>
    <ol class="steps small">
      <li>In Google Cloud, create a service account and download its JSON key. Enable the <em>Google Search Console API</em> and <em>Google Analytics Data API</em>.</li>
      <li>Search Console → Settings → Users and permissions → add the service account email (Restricted is enough).</li>
      <li>Google Analytics → Admin → Property access management → add the same email as Viewer.</li>
    </ol>
    <form method="post" class="stack-form"><?= $csrf ?><input type="hidden" name="action" value="connect">
      <label>GOOGLE_SERVICE_ACCOUNT_JSON — the whole key file <?= $cfg['sa'] ? '(leave empty to keep the saved key)' : '' ?>
        <textarea name="key_json" rows="5" placeholder='{"type": "service_account", …}' autocomplete="off" spellcheck="false"></textarea></label>
      <label>GSC_SITE_URL <span class="muted small">"https://climanova-energie.fr/" for a URL-prefix property (keep the last slash) or "sc-domain:climanova-energie.fr" for a domain property</span>
        <input type="text" name="gsc_site" value="<?= h($cfg['gsc_site'] ?? 'sc-domain:climanova-energie.fr') ?>" required></label>
      <label>GA4_PROPERTY_ID <span class="muted small">numbers only — Admin → Property details (optional)</span>
        <input type="text" name="ga4_property" value="<?= h($cfg['ga4_property'] ?? '') ?>" inputmode="numeric" pattern="\d{4,15}" placeholder="optional — leave empty until GA4 is ready"></label>
      <button class="btn btn-primary">Save connection</button>
      <p class="muted small">Stored on the server outside the website folder (never in the public repository).</p>
    </form>
  </details>
</section>

<div class="tiles tiles-5">
  <div class="tile"><div class="tile-label">Impressions</div><div class="tile-value"><?= $gsc ? $n($gsc['impressions']) : '—' ?></div><div class="tile-sub">Search Console</div></div>
  <div class="tile"><div class="tile-label">Clicks</div><div class="tile-value"><?= $gsc ? $n($gsc['clicks']) : '—' ?></div><div class="tile-sub"><?= $ctr !== null ? "CTR $ctr %" : 'Search Console' ?></div></div>
  <div class="tile"><div class="tile-label">GA4 sessions</div><div class="tile-value"><?= $ga4 ? $n($ga4['sessions']) : '—' ?></div><div class="tile-sub"><?= $ga4 ? $n($ga4['engaged']) . ' engaged' : 'Google Analytics' ?></div></div>
  <div class="tile"><div class="tile-label">Consultation requests</div><div class="tile-value"><?= $data ? $n($data['conversions']['consultation']) : '—' ?></div><div class="tile-sub">GA4 key events + recorded</div></div>
  <div class="tile"><div class="tile-label">Offer registrations</div><div class="tile-value"><?= $data ? $n($data['conversions']['registration']) : '—' ?></div><div class="tile-sub">GA4 key events + recorded</div></div>
</div>

<section class="card">
  <div class="card-head"><h2>Top SEO articles and offers</h2><span class="muted small">URL variants merged · position weighted by impressions</span></div>
  <?php if (empty($data['top'])): ?><p class="muted">No data yet. Published SEO articles (/blog/…) and offer pages appear here after a sync.</p><?php else: ?>
  <div class="table-wrap"><table>
    <thead><tr><th>Page</th><th>Type</th><th class="num">Impressions</th><th class="num">Clicks</th><th class="num">CTR</th><th class="num">Position</th><th class="num">Sessions</th></tr></thead>
    <tbody>
    <?php foreach ($data['top'] as $t): $imp = (int) $t['impressions']; ?>
      <tr>
        <td class="page-cell"><a href="<?= h(str_starts_with((string) $t['url'], 'http') ? $t['url'] : SEO_SITE_URL . $t['url']) ?>" target="_blank" rel="noopener"><?= h($t['name']) ?></a><div class="path muted"><?= h(preg_replace('~^https://[^/]+~', '', (string) $t['url'])) ?></div></td>
        <td><span class="badge muted-badge"><?= h($t['kind']) ?></span></td>
        <td class="num"><?= $n($imp) ?></td>
        <td class="num"><?= $n($t['clicks']) ?></td>
        <td class="num"><?= $imp > 0 ? round((int) $t['clicks'] / $imp * 100, 1) . ' %' : '—' ?></td>
        <td class="num"><?= $t['position'] !== null ? h($t['position']) : '—' ?></td>
        <td class="num"><?= $n($t['sessions']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</section>

<section class="card">
  <div class="card-head"><h2>Recent conversions</h2><span class="muted small">GA4 key events named like lead / contact / devis (consultations) or sign_up / register (registrations), plus recorded conversions</span></div>
  <?php if (empty($data['recent'])): ?><p class="muted">No conversions in the last 30 days.</p><?php else: ?>
  <div class="table-wrap"><table>
    <thead><tr><th>When</th><th>Event</th><th>Type</th><th class="num">Count</th><th>Source</th></tr></thead>
    <tbody>
    <?php foreach ($data['recent'] as $c): ?>
      <tr><td class="nowrap"><?= h($c['when']) ?></td><td><?= h($c['what']) ?><?= $c['page'] ? ' <span class="path muted">' . h($c['page']) . '</span>' : '' ?></td>
        <td><?= $c['kind'] === 'consultation' ? 'Consultation request' : 'Offer registration' ?></td><td class="num"><?= (int) $c['count'] ?></td><td><?= h($c['source']) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</section>
<?php if ($autoSync): ?>
<script>
(async () => {
  const box = document.getElementById('auto-sync');
  box.hidden = false;
  try {
    const body = new URLSearchParams({ action: 'sync', format: 'json', csrf: <?= json_encode(csrf_token()) ?> });
    const r = await fetch(location.pathname, { method: 'POST', body, credentials: 'same-origin' }).then((x) => x.json());
    box.textContent = r.message + ' Reloading…';
    setTimeout(() => location.reload(), 1200);
  } catch (e) { box.textContent = 'The automatic sync could not run — use “Sync Google data”.'; }
})();
</script>
<?php endif; ?>
<?php admin_footer();
