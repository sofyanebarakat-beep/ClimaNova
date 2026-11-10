<?php
declare(strict_types=1);
require __DIR__ . '/_inc/auth.php';
require __DIR__ . '/_inc/seo.php';
require __DIR__ . '/_inc/seo_agent.php';
require __DIR__ . '/_inc/seo_layout.php';
require_login();

// Actions (POST + CSRF), then redirect so a reload never repeats them.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid($_POST['csrf'] ?? null)) {
        $_SESSION['flash'] = ['error', 'Your session expired — please try again.'];
    } elseif (($_POST['action'] ?? '') === 'sync_offers') {
        try {
            $r = seo_sync_offers(dirname(__DIR__));
            $_SESSION['flash'] = ['success', "Offers synced: {$r['synced']} service(s)" . ($r['deactivated'] ? ", {$r['deactivated']} marked inactive." : '.')];
        } catch (Throwable $e) {
            $_SESSION['flash'] = ['error', admin_error($e, 'sync the offers')];
        }
    }
    header('Location: /admin/seo');
    exit;
}
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$data = null;
$error = '';
try {
    $data = seo_dashboard_data();
} catch (Throwable $e) {
    $error = admin_error($e, 'load the SEO dashboard');
}

$LOG_BADGE = ['success' => 'ok', 'failed' => 'bad', 'error' => 'bad', 'running' => 'warn', 'skipped' => 'muted-badge', 'info' => 'muted-badge'];

seo_header('dashboard', 'SEO Dashboard', 'Campaigns, articles, automation and API spend for ClimaNova Energie at a glance.');
?>
<div class="toolbar">
  <form method="post" action="/admin/seo">
    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="action" value="sync_offers">
    <button type="submit" class="btn btn-primary">Sync offers</button>
  </form>
  <a href="/admin/seo" class="btn btn-ghost">↻ Refresh</a>
</div>

<?php if ($flash): ?><div class="<?= $flash[0] === 'success' ? 'success' : 'alert' ?>"><?= h($flash[1]) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert"><?= h($error) ?></div><?php endif; ?>

<?php if ($data): $c = $data['counts']; $b = $data['budget']; ?>
<section class="tiles tiles-6">
  <?php foreach ([
      ['Active campaigns', $c['active_campaigns'], '/admin/seo-campaigns'],
      ['Total articles', $c['total_articles'], '/admin/seo-articles'],
      ['Drafts', $c['drafts'], '/admin/seo-articles'],
      ['Generated', $c['generated'], '/admin/seo-articles'],
      ['Scheduled', $c['scheduled'], '/admin/seo-calendar'],
      ['Published', $c['published'], '/admin/seo-articles'],
      ['Failed automation jobs', $c['failed_jobs'], '/admin/seo-logs'],
      ['API spend this month', seo_money($b['recorded']), '/admin/seo-budget'],
      ['Total campaigns', $c['total_campaigns'], '/admin/seo-campaigns'],
      ['SEO offers', $c['offers'], '/admin/seo-offers'],
      ['Scheduled this week', $c['scheduled_week'], '/admin/seo-calendar'],
      ['Conversions this week', $c['conversions_week'], '/admin/seo-analytics'],
  ] as [$label, $value, $href]): ?>
    <a class="tile tile-link <?= $label === 'Failed automation jobs' && $value > 0 ? 'tile-bad' : '' ?>" href="<?= h($href) ?>">
      <div class="tile-label"><?= h($label) ?></div>
      <div class="tile-value"><?= h((string) $value) ?></div>
    </a>
  <?php endforeach; ?>
</section>
<p class="muted small">This week: <?= h($data['week'][0]->format('D j M')) ?> – <?= h($data['week'][1]->modify('-1 day')->format('D j M')) ?> (Paris time).</p>

<div class="grid-2">
  <section class="card">
    <div class="card-head"><h2>API Budget</h2><a href="/admin/seo-budget" class="small">Manage →</a></div>
    <?php if ($b['state'] === 'exhausted'): ?>
      <div class="alert">Spending plus open reservations have used the whole monthly allowance<?= $b['hard_stop'] ? ' — new AI requests are blocked until next month or until you raise the allowance.' : '.' ?></div>
    <?php elseif ($b['state'] === 'warning'): ?>
      <div class="warning-note"><?= h((string) $b['used_pct']) ?>% of the monthly allowance is used, past the <?= h((string) $b['warning_pct']) ?>% warning threshold.</div>
    <?php endif; ?>
    <div class="meter <?= $b['state'] ?>"><span style="width: <?= min(100, $b['used_pct']) ?>%"></span></div>
    <dl class="kv">
      <dt>Monthly allowance</dt><dd><?= seo_money($b['allowance']) ?></dd>
      <dt>Recorded spend</dt><dd><?= seo_money($b['recorded']) ?> <span class="muted small">(<?= $b['requests'] ?> request<?= $b['requests'] === 1 ? '' : 's' ?>)</span></dd>
      <dt>Spent + reserved</dt><dd><?= seo_money($b['committed']) ?></dd>
      <dt>Remaining</dt><dd><strong><?= seo_money($b['remaining']) ?></strong></dd>
      <dt>Hard stop</dt><dd><?= $b['hard_stop'] ? '<span class="badge ok">On</span> requests stop at the allowance' : '<span class="badge warn">Off</span> requests may exceed the allowance' ?></dd>
    </dl>
    <p class="muted small">This allowance is a spending limit set here in the admin. It is separate from your OpenAI account balance: OpenAI can still refuse requests if that balance runs out, and topping it up does not raise this limit.</p>
  </section>

  <section class="card">
    <div class="card-head"><h2>Recent automation logs</h2><a href="/admin/seo-logs" class="small">All logs →</a></div>
    <?php if (!$data['logs']): ?>
      <p class="muted">No automation has run yet.</p>
    <?php else: ?>
      <table>
        <tbody>
        <?php foreach ($data['logs'] as $l): ?>
          <tr>
            <td class="nowrap muted small"><?= h(date('d/m H:i', strtotime($l['created_at']))) ?></td>
            <td><span class="badge <?= $LOG_BADGE[$l['status']] ?? 'muted-badge' ?>"><?= h($l['status']) ?></span></td>
            <td><strong class="small"><?= h($l['job']) ?></strong><div class="small"><?= h($l['message']) ?></div></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </section>
</div>
<?php endif; ?>
<?php admin_footer();
