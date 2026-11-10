<?php
declare(strict_types=1);
require __DIR__ . '/_inc/auth.php';
require __DIR__ . '/_inc/seo.php';
require __DIR__ . '/_inc/seo_budget.php';
require __DIR__ . '/_inc/seo_layout.php';
require_login();

$errors = [];
$form = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid($_POST['csrf'] ?? null)) {
        $errors = ['Your session expired — please try again.'];
    } else {
        try {
            $errors = seo_save_budget((string) ($_POST['allowance'] ?? ''), (string) ($_POST['thresholds'] ?? ''), isset($_POST['hard_stop']));
            if (!$errors) {
                $_SESSION['flash'] = ['success', 'API budget saved.'];
                header('Location: /admin/seo-budget');
                exit;
            }
        } catch (Throwable $e) {
            $errors = [admin_error($e, 'save the API budget')];
        }
    }
    $form = ['allowance' => (string) ($_POST['allowance'] ?? ''), 'thresholds' => (string) ($_POST['thresholds'] ?? ''), 'hard_stop' => isset($_POST['hard_stop'])];
}
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$data = null;
$loadError = '';
try {
    $data = seo_budget_page_data();
} catch (Throwable $e) {
    $loadError = admin_error($e, 'load the API budget');
}
if ($data && $form === null) {
    $form = ['allowance' => rtrim(rtrim(number_format($data['settings']['allowance'], 2, '.', ''), '0'), '.'),
        'thresholds' => implode(', ', array_map(static fn ($t) => rtrim(rtrim(number_format($t, 2, '.', ''), '0'), '.'), $data['settings']['thresholds'])),
        'hard_stop' => $data['settings']['hard_stop']];
}
$money4 = static fn ($v) => '$' . number_format((float) $v, 4);
$STATUS_BADGE = ['succeeded' => 'ok', 'recorded' => 'ok', 'reserved' => 'warn', 'failed' => 'bad', 'released' => 'muted-badge'];

seo_header('budget', 'API Budget', 'An internal monthly limit on AI spending, checked before every AI request.');
?>
<?php if ($flash): ?><div class="<?= $flash[0] === 'success' ? 'success' : 'alert' ?>"><?= h($flash[1]) ?></div><?php endif; ?>
<?php if ($loadError): ?><div class="alert"><?= h($loadError) ?></div><?php endif; ?>

<?php if ($data): $b = $data['budget']; ?>
<div class="grid-2">
  <section class="card">
    <div class="card-head"><h2>This month</h2><span class="muted small"><?= h((new DateTimeImmutable('now', new DateTimeZone('Europe/Paris')))->format('F Y')) ?> · Paris time</span></div>
    <?php if ($b['state'] === 'exhausted'): ?>
      <div class="alert">The allowance is used up: spend plus open reservations have reached <?= seo_money($b['allowance']) ?><?= $b['hard_stop'] ? ' — new AI requests are refused until next month or until you raise it.' : ', but the hard stop is off, so requests continue.' ?></div>
    <?php elseif ($b['threshold_passed'] !== null): ?>
      <div class="warning-note"><?= h((string) $b['used_pct']) ?>% of the allowance is used — past the <?= h((string) $b['warning_pct']) ?>% warning threshold.</div>
    <?php endif; ?>
    <div class="meter-wrap">
      <div class="meter <?= $b['state'] ?>"><span style="width: <?= min(100, $b['used_pct']) ?>%"></span></div>
      <?php foreach ($b['thresholds'] as $t): ?><i class="tick" style="left: <?= $t * 100 ?>%" title="<?= round($t * 100) ?>% warning"></i><?php endforeach; ?>
    </div>
    <dl class="kv">
      <dt>Recorded spend</dt><dd><?= $money4($b['recorded']) ?> <span class="muted small">(<?= $b['requests'] ?> answered request<?= $b['requests'] === 1 ? '' : 's' ?>)</span></dd>
      <dt>Reserved (in flight)</dt><dd><?= $money4($b['reserved']) ?></dd>
      <dt>Spent + reserved</dt><dd><strong><?= $money4($b['committed']) ?></strong> <span class="muted small">— this is what counts toward the limit</span></dd>
      <dt>Remaining</dt><dd><strong><?= $money4($b['remaining']) ?></strong> of <?= seo_money($b['allowance']) ?></dd>
      <dt>Failed / unusable</dt><dd><?= (int) ($data['usage']['failed_requests'] ?? 0) ?> failed (not billed) · <?= (int) ($data['usage']['invalid_outputs'] ?? 0) ?> unusable answer(s) (billed)</dd>
      <dt>Tokens</dt><dd><?= number_format((int) ($data['usage']['input_tokens'] ?? 0), 0, '.', ' ') ?> in · <?= number_format((int) ($data['usage']['output_tokens'] ?? 0), 0, '.', ' ') ?> out</dd>
    </dl>
  </section>

  <section class="card">
    <h2>Settings</h2>
    <?php if ($errors): ?><div class="alert" role="alert"><ul class="error-list"><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <form method="post" action="/admin/seo-budget" class="stack-form">
      <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
      <label>Internal monthly allowance (USD)
        <input type="text" name="allowance" inputmode="decimal" value="<?= h($form['allowance']) ?>" placeholder="1">
      </label>
      <label>Warning thresholds
        <input type="text" name="thresholds" value="<?= h($form['thresholds']) ?>" placeholder="0.5, 0.8, 0.95">
        <span class="muted small">Shares of the allowance, comma separated (0.8 = 80%). A warning appears on the dashboard when spend + reservations pass one.</span>
      </label>
      <label class="switch"><input type="checkbox" name="hard_stop" value="1" <?= $form['hard_stop'] ? 'checked' : '' ?>>
        <span><strong>Enforce a hard stop when the allowance is used up</strong>
        <span class="muted small">The budget counts as used up when spend <em>plus reservations</em> reach the limit — not only spend. Every AI request is then refused until next month.</span></span></label>
      <button type="submit" class="btn btn-primary">Save budget</button>
    </form>
  </section>
</div>

<section class="card notice-card">
  <h2>This is an internal limit</h2>
  <p>This allowance is checked by your admin before each AI request. It <strong>does not cap what your AI provider can bill</strong>:
    it cannot stop requests made with your key elsewhere, and provider prices can change. Always set a limit at the provider too:</p>
  <ul>
    <li><strong>OpenAI:</strong> platform.openai.com → Settings → <em>Limits</em> → set a monthly budget, and use prepaid credits without auto-recharge.</li>
    <li><strong>Google Gemini:</strong> the free tier is not billed. If you enable billing, add a budget alert in Google Cloud → Billing → <em>Budgets &amp; alerts</em>.</li>
    <li><strong>Groq:</strong> the free tier has fixed rate limits; on a paid plan, set the spend limit in the Groq console.</li>
  </ul>
</section>

<div class="grid-2">
  <section class="card">
    <h2>Spend by feature this month</h2>
    <?php if (!$data['by_purpose']): ?><p class="muted">No AI requests this month.</p>
    <?php else: ?>
      <table><thead><tr><th>Feature</th><th class="num">Answered</th><th class="num">Failed</th><th class="num">Unusable</th><th class="num">Cost</th></tr></thead><tbody>
      <?php foreach ($data['by_purpose'] as $p): ?>
        <tr><td><?= h(ucfirst(str_replace('_', ' ', $p['purpose']))) ?></td><td class="num"><?= (int) $p['answered'] ?></td><td class="num"><?= (int) $p['failed'] ?></td><td class="num"><?= (int) $p['invalid'] ?></td><td class="num"><?= $money4($p['cost']) ?></td></tr>
      <?php endforeach; ?></tbody></table>
    <?php endif; ?>
  </section>
  <section class="card">
    <h2>Latest AI requests</h2>
    <?php if (!$data['recent']): ?><p class="muted">No AI requests yet.</p>
    <?php else: ?>
      <div class="table-wrap"><table><thead><tr><th>When</th><th>Feature</th><th>Status</th><th class="num">Cost</th></tr></thead><tbody>
      <?php foreach ($data['recent'] as $r): ?>
        <tr><td class="nowrap small"><?= h(date('d/m H:i', strtotime($r['created_at']))) ?></td>
          <td class="small"><?= h(str_replace('_', ' ', $r['purpose'])) ?><div class="muted"><?= h($r['model']) ?></div></td>
          <td><span class="badge <?= $STATUS_BADGE[$r['status']] ?? 'muted-badge' ?>"><?= h($r['status']) ?></span><?php if ($r['outcome'] === 'invalid_output'): ?><span class="badge warn">unusable</span><?php endif; ?></td>
          <td class="num small"><?= $r['status'] === 'reserved' ? '≤ ' . $money4($r['reserved_usd']) : $money4($r['cost_usd']) ?></td></tr>
      <?php endforeach; ?></tbody></table></div>
    <?php endif; ?>
  </section>
</div>
<?php endif; ?>
<?php admin_footer();
