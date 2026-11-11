<?php
declare(strict_types=1);
require __DIR__ . '/_inc/auth.php';
require __DIR__ . '/_inc/seo.php';
require __DIR__ . '/_inc/seo_articles.php';
require __DIR__ . '/_inc/seo_layout.php';
require_login();

$results = null;
$flash = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid($_POST['csrf'] ?? null)) {
        $flash = ['error', 'Your session expired — please try again.'];
    } elseif (($_POST['action'] ?? '') === 'approve') {
        $ids = array_map('intval', (array) ($_POST['ids'] ?? []));
        if (!$ids) {
            $flash = ['error', 'Tick at least one draft to approve.'];
        } else {
            @set_time_limit(120);
            $results = seo_weekly_approve($ids);
            $ok = count(array_filter($results, static fn ($r) => $r === null));
            $fail = count($results) - $ok;
            $flash = [$fail ? 'error' : 'success', "$ok approved and scheduled" . ($fail ? ", $fail could not be approved — see the reasons below." : '.')];
            // Successful ones leave the list; failures stay with their reason (shown on this response, no redirect).
        }
    }
}

$loadError = '';
$drafts = [];
try {
    $drafts = seo_list_articles('draft,generated,review');
} catch (Throwable $e) {
    $loadError = admin_error($e, 'load the drafts');
}
$needPicture = count(array_filter($drafts, static fn ($a) => empty($a['content']['featuredImage']['url'])));
$STATUS_BADGE = ['draft' => 'muted-badge', 'generated' => 'muted-badge', 'review' => 'warn'];

seo_header('weekly', 'Weekly Check', 'Review the week’s drafts and approve them together.');
?>
<?php if ($flash): ?><div class="<?= $flash[0] === 'success' ? 'success' : 'alert' ?>"><?= h($flash[1]) ?></div><?php endif; ?>
<?php if ($loadError): ?><div class="alert"><?= h($loadError) ?></div><?php endif; ?>

<form method="post" id="wk-form">
  <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
  <input type="hidden" name="action" value="approve">
  <div class="wk-head">
    <strong><span id="n-wait"><?= count($drafts) ?></span> waiting · <span><?= $needPicture ?></span> need a picture · <span id="n-sel">0</span> selected</strong>
    <button class="btn btn-ok" id="wk-approve" <?= $drafts ? '' : 'disabled' ?>>Approve selected</button>
  </div>
  <p class="muted small">Approving runs “Submit for review” then “Approve” for each ticked draft, and gives each the next free publishing slot. Drafts without a featured image cannot be ticked.</p>

  <?php if (!$drafts && !$loadError): ?>
    <section class="card empty"><p><strong>Nothing waiting.</strong></p><p class="muted">New drafts appear here after generation in <a href="/admin/seo-articles">Articles &amp; Approval</a>.</p></section>
  <?php endif; ?>
  <div class="art-list">
  <?php foreach ($drafts as $a):
      $fi = $a['content']['featuredImage'] ?? null;
      $hasImg = !empty($fi['url']);
      $err = $results[$a['id']] ?? null;
      $checked = $hasImg && ($results === null || $err === null);
  ?>
    <div class="wk-row <?= $hasImg ? '' : 'is-blocked' ?>" id="article-<?= $a['id'] ?>">
      <input type="checkbox" name="ids[]" value="<?= $a['id'] ?>" <?= $checked ? 'checked' : '' ?> <?= $hasImg ? '' : 'disabled' ?> aria-label="Select <?= h($a['title']) ?>">
      <?php if ($hasImg): ?><img class="art-thumb" src="<?= h($fi['url']) ?>" alt="<?= h($fi['alt'] ?? '') ?>" loading="lazy"><?php else: ?><span class="badge warn" style="margin-top:4px">Needs a picture</span><?php endif; ?>
      <div>
        <h3 style="margin:0 0 4px;font-size:16px"><?= h($a['title']) ?></h3>
        <div class="art-meta">
          <span class="badge <?= $STATUS_BADGE[$a['status']] ?? 'muted-badge' ?>"><?= h(SEO_ARTICLE_STATUSES[$a['status']]) ?></span>
          <span>Offer: <?= h($a['offer_name'] ?? '—') ?></span>
          <span><?= (int) $a['word_count'] ?> words</span>
          <?php if ($a['quality_score'] !== null): ?><span>Quality <?= (int) $a['quality_score'] ?>%</span><?php endif; ?>
        </div>
        <?php if ($err): ?><p class="error-line"><strong>Not approved:</strong> <?= h($err) ?></p><?php endif; ?>
        <?php if ($a['quality_notes']): ?><ul class="notes"><?php foreach ($a['quality_notes'] as $n): ?><li><?= h($n) ?></li><?php endforeach; ?></ul><?php endif; ?>
        <div class="art-actions">
          <a class="btn btn-sm <?= $hasImg ? 'btn-ghost' : 'btn-primary' ?>" href="/admin/seo-articles?edit=<?= $a['id'] ?>">Add pictures</a>
          <button type="button" class="btn btn-sm btn-ghost" data-read="<?= $a['id'] ?>" aria-expanded="false">Read article</button>
        </div>
        <div class="wk-read preview-article" id="read-<?= $a['id'] ?>" hidden>
          <?php if ($hasImg): ?><img class="preview-featured" src="<?= h($fi['url']) ?>" alt="<?= h($fi['alt'] ?? '') ?>" loading="lazy"><?php endif; ?>
          <h2 style="margin-top:0"><?= h($a['title']) ?></h2>
          <?= seo_article_body_html($a) ?>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
  </div>
</form>
<script>
(() => {
  const form = document.getElementById('wk-form');
  const boxes = [...form.querySelectorAll('input[name="ids[]"]')];
  const count = () => {
    const n = boxes.filter((b) => b.checked).length;
    document.getElementById('n-sel').textContent = n;
    document.getElementById('wk-approve').disabled = n === 0;
  };
  boxes.forEach((b) => b.addEventListener('change', count));
  count();
  form.addEventListener('submit', () => {
    const b = document.getElementById('wk-approve');
    setTimeout(() => { b.disabled = true; b.textContent = 'Approving…'; });
  });
  document.querySelectorAll('[data-read]').forEach((btn) => btn.addEventListener('click', () => {
    const el = document.getElementById('read-' + btn.dataset.read);
    el.hidden = !el.hidden;
    btn.setAttribute('aria-expanded', String(!el.hidden));
    btn.textContent = el.hidden ? 'Read article' : 'Hide article';
  }));
})();
</script>
<?php admin_footer();
