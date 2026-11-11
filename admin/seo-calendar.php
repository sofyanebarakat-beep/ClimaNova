<?php
declare(strict_types=1);
require __DIR__ . '/_inc/auth.php';
require __DIR__ . '/_inc/seo.php';
require __DIR__ . '/_inc/seo_articles.php';
require __DIR__ . '/_inc/seo_layout.php';
require_login();

$week = (string) ($_GET['week'] ?? '');
$monday = seo_valid_date($week) ? seo_monday($week) : seo_monday((new DateTimeImmutable('now', new DateTimeZone('Europe/Paris')))->format('Y-m-d'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    if (!csrf_valid($_POST['csrf'] ?? null)) {
        $_SESSION['flash'] = ['error', 'Your session expired — please try again.'];
    } else {
        try {
            if ($action === 'move_topic') {
                seo_move_topic((int) ($_POST['topic_id'] ?? 0), (string) ($_POST['date'] ?? ''));
                $_SESSION['flash'] = ['success', 'Topic date changed.'];
            } elseif ($action === 'reschedule') {
                $id = (int) ($_POST['id'] ?? 0);
                $when = trim((string) ($_POST['when'] ?? ''));
                $slot = seo_schedule_article($id, $when === '' ? null : $when);
                $tz = seo_article($id)['time_zone'];
                $_SESSION['flash'] = ['success', 'Scheduled for ' . $slot->setTimezone(new DateTimeZone($tz))->format('d/m/Y H:i') . " ($tz)."];
                $monday = seo_monday($slot->setTimezone(new DateTimeZone($tz))->format('Y-m-d'));
            } elseif ($action === 'publish') {
                $url = seo_publish_article((int) ($_POST['id'] ?? 0));
                $_SESSION['flash'] = ['success', "Published: $url"];
            }
        } catch (Throwable $e) {
            $_SESSION['flash'] = ['error', $e instanceof InvalidArgumentException ? $e->getMessage() : admin_error($e, 'update the calendar')];
        }
    }
    header('Location: /admin/seo-calendar?week=' . $monday);
    exit;
}
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$data = ['topics' => [], 'articles' => [], 'approved' => []];
$loadError = '';
try {
    $data = seo_calendar_week($monday);
} catch (Throwable $e) {
    $loadError = admin_error($e, 'load the calendar');
}
$start = new DateTimeImmutable($monday);
$today = (new DateTimeImmutable('now', new DateTimeZone('Europe/Paris')))->format('Y-m-d');
$days = [];
for ($i = 0; $i < 7; $i++) $days[] = $start->modify("+$i days")->format('Y-m-d');
$csrf = '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">';
$weekdays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

seo_header('calendar', 'Calendar', 'Topics by planned date and articles by publishing slot, in each campaign’s time zone.');
?>
<?php if ($flash): ?><div class="<?= $flash[0] === 'success' ? 'success' : 'alert' ?>"><?= h($flash[1]) ?></div><?php endif; ?>
<?php if ($loadError): ?><div class="alert"><?= h($loadError) ?></div><?php endif; ?>

<div class="toolbar">
  <a class="btn btn-ghost btn-sm" href="?week=<?= $start->modify('-7 days')->format('Y-m-d') ?>">← Previous week</a>
  <strong><?= h($start->format('d/m/Y')) ?> – <?= h($start->modify('+6 days')->format('d/m/Y')) ?></strong>
  <a class="btn btn-ghost btn-sm" href="?week=<?= $start->modify('+7 days')->format('Y-m-d') ?>">Next week →</a>
  <a class="btn btn-ghost btn-sm" href="/admin/seo-calendar">This week</a>
  <form method="get" class="row-form"><input type="date" name="week" value="<?= h($monday) ?>" aria-label="Go to week"><button class="btn btn-ghost btn-sm">Go</button></form>
</div>
<div class="cal-legend" style="margin-bottom:10px"><span class="l-topic">Planned topic</span><span class="l-sched">Scheduled article</span><span class="l-pub">Published</span><span class="l-fail">Failed</span></div>

<div class="cal-grid">
<?php foreach ($days as $i => $d): ?>
  <div class="cal-day <?= $d === $today ? 'today' : '' ?>">
    <h3><?= $weekdays[$i] ?> <?= h(date('d/m', strtotime($d))) ?></h3>
    <?php foreach ($data['articles'] as $a): if ($a['local_day'] !== $d) continue;
        $cls = $a['status'] === 'published' ? 'published' : ($a['status'] === 'failed' ? 'failed' : 'scheduled'); ?>
      <div class="cal-item <?= $cls ?>">
        <strong><?= h($a['local_time']) ?></strong> <?= h($a['title']) ?>
        <div class="muted small"><?= h($a['offer_name'] ?? '') ?> · <?= h(SEO_ARTICLE_STATUSES[$a['status']]) ?><?= $a['time_zone'] !== 'Europe/Paris' ? ' · ' . h($a['time_zone']) : '' ?></div>
        <?php if ($a['status'] === 'failed' && $a['last_error']): ?><div class="error-line"><?= h($a['last_error']) ?></div><?php endif; ?>
        <?php if ($a['status'] === 'published'): ?><a class="small" href="/blog/<?= h($a['slug']) ?>/" target="_blank" rel="noopener">View ↗</a><?php endif; ?>
        <?php if (in_array($a['status'], ['scheduled', 'failed'], true)): ?>
          <details><summary>Reschedule</summary>
            <form method="post"><?= $csrf ?><input type="hidden" name="action" value="reschedule"><input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
              <input type="datetime-local" name="when" value="<?= h($a['local_input']) ?>" required><button class="btn btn-sm btn-primary">Move</button></form>
            <form method="post" onsubmit="return confirm('Publish this article now?')"><?= $csrf ?><input type="hidden" name="action" value="publish"><input type="hidden" name="id" value="<?= (int) $a['id'] ?>"><button class="btn btn-sm btn-ghost">Publish now</button></form>
          </details>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
    <?php foreach ($data['topics'] as $t): if ($t['planned_for'] !== $d) continue; ?>
      <div class="cal-item topic">
        <?= h($t['title']) ?>
        <div class="muted small"><?= h($t['campaign_name']) ?> · <?= h($t['offer_name'] ?? '') ?> · <?= h($t['status']) ?></div>
        <?php if ($t['article_id']): ?><a class="small" href="/admin/seo-articles#article-<?= (int) $t['article_id'] ?>">Article #<?= (int) $t['article_id'] ?></a>
        <?php elseif ($t['status'] !== 'used'): ?><a class="small" href="/admin/seo-articles?topic=<?= (int) $t['id'] ?>#generator">Write article</a><?php endif; ?>
        <details><summary>Change date</summary>
          <form method="post"><?= $csrf ?><input type="hidden" name="action" value="move_topic"><input type="hidden" name="topic_id" value="<?= (int) $t['id'] ?>">
            <input type="date" name="date" value="<?= h($t['planned_for']) ?>" required><button class="btn btn-sm btn-primary">Move</button></form>
        </details>
      </div>
    <?php endforeach; ?>
  </div>
<?php endforeach; ?>
</div>

<section class="card" style="margin-top:16px">
  <div class="card-head"><h2>Approved, not yet scheduled</h2><span class="muted small">Pick a time, or leave it empty for the next free slot (max 12 per week).</span></div>
  <?php if (!$data['approved']): ?><p class="muted">None.</p><?php endif; ?>
  <?php foreach ($data['approved'] as $a): ?>
    <form method="post" class="row-form" style="margin-bottom:8px"><?= $csrf ?>
      <input type="hidden" name="action" value="reschedule"><input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
      <span style="min-width:240px"><?= h($a['title']) ?> <span class="muted small">(<?= h($a['time_zone']) ?>)</span></span>
      <input type="datetime-local" name="when"><button class="btn btn-sm btn-primary">Schedule</button>
    </form>
  <?php endforeach; ?>
</section>
<?php admin_footer();
