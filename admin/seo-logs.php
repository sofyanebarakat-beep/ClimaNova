<?php
declare(strict_types=1);
require __DIR__ . '/_inc/auth.php';
require __DIR__ . '/_inc/seo.php';
require __DIR__ . '/_inc/seo_budget.php';
require __DIR__ . '/_inc/seo_layout.php';
require_login();

$operation = isset(SEO_LOG_OPERATIONS[$_GET['operation'] ?? '']) ? $_GET['operation'] : '';
$status = isset(SEO_LOG_STATUSES[$_GET['status'] ?? '']) ? $_GET['status'] : '';
$before = isset($_GET['before']) ? max(0, (int) $_GET['before']) : null;

$logs = ['rows' => [], 'more' => false];
$error = '';
try {
    $logs = seo_list_logs($operation, $status, $before ?: null);
} catch (Throwable $e) {
    $error = admin_error($e, 'load the automation logs');
}
$BADGE = ['running' => 'warn', 'success' => 'ok', 'error' => 'bad', 'skipped' => 'muted-badge'];
$fmt = static fn (?string $t) => $t ? date('d/m/Y H:i:s', strtotime($t)) : '—';

seo_header('logs', 'Automation Logs', 'Every AI feature, sync, publish and scheduled job — what ran, when, and whether it worked.');
?>
<form method="get" action="/admin/seo-logs" class="toolbar">
  <label class="field inline">Operation
    <select name="operation" onchange="this.form.submit()">
      <option value="">All operations</option>
      <?php foreach (SEO_LOG_OPERATIONS as $k => $label): ?><option value="<?= $k ?>" <?= $operation === $k ? 'selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?>
    </select>
  </label>
  <label class="field inline">Status
    <select name="status" onchange="this.form.submit()">
      <option value="">All statuses</option>
      <?php foreach (SEO_LOG_STATUSES as $k => $label): ?><option value="<?= $k ?>" <?= $status === $k ? 'selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?>
    </select>
  </label>
  <noscript><button class="btn btn-ghost btn-sm">Filter</button></noscript>
  <?php if ($operation || $status || $before): ?><a href="/admin/seo-logs" class="small">Clear filters</a><?php endif; ?>
</form>

<?php if ($error): ?><div class="alert"><?= h($error) ?></div><?php endif; ?>

<section class="card">
  <?php if (!$logs['rows'] && !$error): ?>
    <p class="muted"><?= $operation || $status ? 'No log matches these filters.' : 'No automation has run yet.' ?></p>
  <?php else: ?>
    <div class="table-wrap"><table class="log-table">
      <thead><tr><th>Job</th><th>Status</th><th>Started</th><th>Finished</th><th>Message</th></tr></thead>
      <tbody>
      <?php foreach ($logs['rows'] as $l): $st = seo_log_status($l['status']); $op = $l['operation'] ?: seo_log_operation($l['job']); ?>
        <tr>
          <td><strong class="small"><?= h($l['job']) ?></strong><div class="muted small"><?= h(SEO_LOG_OPERATIONS[$op] ?? $op) ?></div></td>
          <td><span class="badge <?= $BADGE[$st] ?? 'muted-badge' ?>"><?= h(SEO_LOG_STATUSES[$st] ?? ucfirst($st)) ?></span></td>
          <td class="nowrap small"><?= h($fmt($l['started_at'] ?: $l['created_at'])) ?></td>
          <td class="nowrap small"><?= $st === 'running' ? '<span class="muted">still running</span>' : h($fmt($l['finished_at'] ?: $l['created_at'])) ?>
            <?php if ($d = seo_duration($l['started_at'], $l['finished_at'])): ?><div class="muted"><?= h($d) ?></div><?php endif; ?></td>
          <td class="small"><?= h($l['message']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody></table></div>
    <?php if ($logs['more']): $last = end($logs['rows']); ?>
      <p><a class="btn btn-ghost btn-sm" href="/admin/seo-logs?<?= h(http_build_query(array_filter(['operation' => $operation, 'status' => $status, 'before' => $last['id']]))) ?>">Older logs →</a></p>
    <?php endif; ?>
  <?php endif; ?>
</section>
<?php admin_footer();
