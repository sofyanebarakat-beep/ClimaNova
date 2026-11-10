<?php
declare(strict_types=1);
require __DIR__ . '/_inc/auth.php';
require __DIR__ . '/_inc/seo.php';
require __DIR__ . '/_inc/seo_keywords.php';
require __DIR__ . '/_inc/seo_layout.php';
require_login();

$root = dirname(__DIR__);
$cid = (int) ($_GET['campaign'] ?? $_POST['campaign'] ?? 0);
$nextMonday = (new DateTimeImmutable('now', new DateTimeZone('Europe/Paris')))->modify('next monday')->format('Y-m-d');
$week = (string) ($_GET['week'] ?? $_POST['week'] ?? $nextMonday);
if (!seo_valid_date($week)) $week = $nextMonday;
$week = seo_monday($week);
$back = static fn (string $anchor = '') => '/admin/seo-keywords-topics?' . http_build_query(['campaign' => $GLOBALS['cid'], 'week' => $GLOBALS['week']]) . $anchor;
$intOrNull = static fn ($v) => ($v === null || $v === '') ? null : (int) $v;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $anchor = str_contains($action, 'topic') || $action === 'generate_week' ? '#weekly-plan' : '#keywords';
    if (!csrf_valid($_POST['csrf'] ?? null)) {
        $_SESSION['flash'] = ['error', 'Your session expired — please try again.'];
    } else {
        try {
            switch ($action) {
                case 'add_keyword':
                    $cid = (int) ($_POST['campaign'] ?? 0);
                    seo_add_keyword($cid, $intOrNull($_POST['offer'] ?? null), (string) ($_POST['keyword'] ?? ''));
                    $_SESSION['flash'] = ['success', 'Keyword added.'];
                    break;
                case 'ai_keywords':
                    $r = seo_ai_keyword_ideas($root, $cid);
                    $_SESSION['flash'] = ['success', "AI keyword ideas: {$r['added']} added" . ($r['dropped'] ? ", {$r['dropped']} dropped as duplicates or not matching an offer." : '.')];
                    break;
                case 'gsc_ideas':
                    $_SESSION['gsc_ideas'][$cid] = seo_gsc_keyword_ideas($cid);
                    $_SESSION['flash'] = ['success', count($_SESSION['gsc_ideas'][$cid]) . ' Search Console idea(s) found — add the ones you want.'];
                    break;
                case 'add_gsc_keyword':
                    seo_add_keyword($cid, $intOrNull($_POST['offer'] ?? null), (string) ($_POST['keyword'] ?? ''), ['source' => 'search_console',
                        'search_intent' => 'informational', 'gsc_impressions' => (int) ($_POST['impressions'] ?? 0),
                        'gsc_clicks' => (int) ($_POST['clicks'] ?? 0), 'gsc_position' => (float) ($_POST['position'] ?? 0),
                        'rationale' => 'Real Search Console query (last 90 days).']);
                    $_SESSION['gsc_ideas'][$cid] = array_values(array_filter($_SESSION['gsc_ideas'][$cid] ?? [], static fn ($i) => $i['query'] !== ($_POST['keyword'] ?? '')));
                    $_SESSION['flash'] = ['success', 'Search Console keyword added.'];
                    break;
                case 'generate_week':
                    $r = seo_generate_week($root, $cid, $week);
                    $_SESSION['flash'] = [$r['created'] ? 'success' : 'error', "Weekly plan: {$r['created']} topic(s) created"
                        . ($r['rejected'] ? ", {$r['rejected']} rejected as duplicates — click “Generate weekly plan” again to fill the remaining slots." : '.')];
                    break;
                case 'edit_topic':
                    seo_update_topic($root, (int) $_POST['topic'], $_POST);
                    $week = seo_monday((string) $_POST['planned_for']);
                    $_SESSION['flash'] = ['success', 'Topic updated.'];
                    break;
                case 'approve_topic':
                    seo_approve_topic((int) $_POST['topic']);
                    $_SESSION['flash'] = ['success', 'Topic approved.'];
                    break;
                case 'regenerate_topic':
                    seo_regenerate_topic($root, (int) $_POST['topic']);
                    $_SESSION['flash'] = ['success', 'Topic regenerated — review the new title and angle.'];
                    break;
                case 'add_topic':
                    $cid = (int) ($_POST['campaign'] ?? 0);
                    seo_add_topic($root, $cid, $intOrNull($_POST['offer'] ?? null), $intOrNull($_POST['keyword_id'] ?? null), $_POST);
                    $week = seo_monday((string) $_POST['planned_for']);
                    $_SESSION['flash'] = ['success', 'Topic added.'];
                    break;
            }
        } catch (AiError $e) {
            $_SESSION['flash'] = ['error', $e->getMessage()];
        } catch (Throwable $e) {
            $_SESSION['flash'] = ['error', admin_error($e, 'complete this action')];
        }
    }
    header('Location: ' . $back($anchor));
    exit;
}
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$campaigns = [];
$ctx = null;
$keywords = [];
$topics = [];
$loadError = '';
try {
    $campaigns = seo_campaign_options();
    if (!$cid && $campaigns) $cid = (int) $campaigns[0]['id'];
    if ($cid) {
        $ctx = seo_campaign_context($cid);
        $keywords = seo_list_keywords($cid);
        $topics = seo_list_topics($cid, $week);
    }
} catch (Throwable $e) {
    $loadError = admin_error($e, 'load keywords and topics');
}
$gscIdeas = $_SESSION['gsc_ideas'][$cid] ?? null;
$unused = array_filter($keywords, static fn ($k) => $k['used_at'] === null);
$TOPIC_BADGE = ['proposed' => 'warn', 'approved' => 'ok', 'used' => 'muted-badge', 'rejected' => 'bad'];
$weekEnd = (new DateTimeImmutable($week))->modify('+6 days');
$fmt = static fn (string $d) => (new DateTimeImmutable($d))->format('D j M');

/** Offer <select> for this campaign. */
function offer_select(array $ctx, string $name, ?int $selected = null, bool $allowWide = true): string
{
    $html = '<select name="' . h($name) . '">';
    if ($allowWide) $html .= '<option value="">Campaign-wide</option>';
    foreach ($ctx['offers'] as $o) $html .= '<option value="' . $o['id'] . '"' . ($selected === $o['id'] ? ' selected' : '') . '>' . h($o['name']) . '</option>';
    return $html . '</select>';
}

seo_header('keywords', 'Keywords & Topics', 'Find keywords with AI and free Search Console ideas, then plan each week’s article topics.');
?>
<?php if ($flash): ?><div class="<?= $flash[0] === 'success' ? 'success' : 'alert' ?>"><?= h($flash[1]) ?></div><?php endif; ?>
<?php if ($loadError): ?><div class="alert"><?= h($loadError) ?></div><?php endif; ?>

<?php if (!$campaigns && !$loadError): ?>
  <section class="card empty"><p><strong>Create a campaign first.</strong></p>
    <p class="muted">Keywords and topics belong to a campaign. <a href="/admin/seo-campaigns">Create one in the Campaigns tab</a>.</p></section>
<?php elseif ($ctx): ?>

<form method="get" action="/admin/seo-keywords-topics" class="toolbar">
  <label class="field inline">Campaign
    <select name="campaign" onchange="this.form.submit()">
      <?php foreach ($campaigns as $c): ?><option value="<?= (int) $c['id'] ?>" <?= (int) $c['id'] === $cid ? 'selected' : '' ?>><?= h($c['name']) ?> (<?= h($c['status']) ?>)</option><?php endforeach; ?>
    </select>
  </label>
  <input type="hidden" name="week" value="<?= h($week) ?>">
  <noscript><button class="btn btn-ghost">Show</button></noscript>
  <span class="muted small">Offers: <?= $ctx['offers'] ? h(implode(', ', array_column($ctx['offers'], 'name'))) : 'none enabled' ?> · <?= (int) $ctx['weekly_target'] ?>/week</span>
</form>
<?php if (!$ctx['offers']): ?><div class="notice">This campaign has no enabled offer. <a href="/admin/seo-offers">Enable an offer</a> or <a href="/admin/seo-campaigns?edit=<?= $cid ?>#campaign-form">edit the campaign</a>.</div><?php endif; ?>

<!-- ============ Section 1: Keyword research ============ -->
<section class="card" id="keywords">
  <div class="card-head"><h2>1 · Keyword research</h2><span class="muted small"><?= count($keywords) ?> keyword(s), <?= count($unused) ?> unused</span></div>

  <div class="kw-actions">
    <form method="post" action="<?= h($back()) ?>" class="kw-add">
      <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="action" value="add_keyword">
      <label class="field">Keyword <input type="text" name="keyword" required minlength="2" maxlength="120" placeholder="climatisation réversible nice prix"></label>
      <label class="field">Campaign
        <select name="campaign"><?php foreach ($campaigns as $c): ?><option value="<?= (int) $c['id'] ?>" <?= (int) $c['id'] === $cid ? 'selected' : '' ?>><?= h($c['name']) ?></option><?php endforeach; ?></select>
      </label>
      <label class="field">Offer <?= offer_select($ctx, 'offer') ?></label>
      <button type="submit" class="btn btn-ghost">Add</button>
    </form>
    <div class="kw-gen">
      <form method="post" action="<?= h($back()) ?>">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="action" value="ai_keywords">
        <button type="submit" class="btn btn-primary" <?= $ctx['offers'] ? '' : 'disabled' ?>>✦ Generate AI keyword ideas</button>
      </form>
      <form method="post" action="<?= h($back()) ?>">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="action" value="gsc_ideas">
        <button type="submit" class="btn btn-ghost">Get free ideas from Search Console</button>
      </form>
      <p class="muted small">AI ideas: 8–12 keywords across this campaign’s offers (one AI request, checked against your budget). No search volume is invented — real figures only come from Search Console.</p>
    </div>
  </div>

  <?php if ($gscIdeas !== null): ?>
    <h3>Search Console ideas · last 90 days</h3>
    <?php if (!$gscIdeas): ?><p class="muted">No new queries — everything relevant is already a keyword, or the site has no impressions yet.</p>
    <?php else: ?>
      <div class="table-wrap"><table>
        <thead><tr><th>Query</th><th class="num">Impr.</th><th class="num">Clicks</th><th class="num">Pos.</th><th>Offer</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($gscIdeas as $g): ?>
          <tr><td><?= h($g['query']) ?></td><td class="num"><?= (int) $g['impressions'] ?></td><td class="num"><?= (int) $g['clicks'] ?></td><td class="num"><?= h((string) $g['position']) ?></td>
            <td colspan="2"><form method="post" action="<?= h($back()) ?>" class="row-form">
              <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="add_gsc_keyword">
              <input type="hidden" name="keyword" value="<?= h($g['query']) ?>"><input type="hidden" name="impressions" value="<?= (int) $g['impressions'] ?>">
              <input type="hidden" name="clicks" value="<?= (int) $g['clicks'] ?>"><input type="hidden" name="position" value="<?= h((string) $g['position']) ?>">
              <?= offer_select($ctx, 'offer') ?> <button class="btn btn-ghost btn-sm">Add</button></form></td></tr>
        <?php endforeach; ?>
        </tbody></table></div>
    <?php endif; ?>
  <?php endif; ?>

  <h3>Campaign keywords</h3>
  <?php if (!$keywords): ?><p class="muted">No keywords yet — add one above or generate ideas.</p>
  <?php else: ?>
    <div class="table-wrap"><table class="kw-table">
      <thead><tr><th>Keyword</th><th>Intent</th><th class="num">Priority</th><th>Offer</th><th>Rationale</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach ($keywords as $k): ?>
        <tr class="<?= $k['used_at'] ? 'is-used' : '' ?>">
          <td><strong><?= h($k['keyword']) ?></strong><div class="muted small"><?= h(['manual' => 'Manual', 'ai' => 'AI idea', 'search_console' => 'Search Console'][$k['source']] ?? $k['source']) ?><?= $k['cluster'] ? ' · ' . h($k['cluster']) : '' ?>
            <?php if ($k['gsc_impressions'] !== null): ?> · <?= (int) $k['gsc_impressions'] ?> impr., <?= (int) $k['gsc_clicks'] ?> clicks, pos. <?= h((string) round((float) $k['gsc_position'], 1)) ?><?php endif; ?></div></td>
          <td><span class="badge muted-badge"><?= h(SEO_INTENTS[$k['search_intent']] ?? $k['search_intent']) ?></span></td>
          <td class="num"><?= (int) $k['priority'] ?></td>
          <td class="small"><?= $k['offer_name'] ? h($k['offer_name']) : '<span class="muted">Campaign-wide</span>' ?></td>
          <td class="small"><?= h((string) $k['rationale']) ?></td>
          <td><?= $k['used_at'] ? '<span class="badge muted-badge">Used</span>' : '<span class="badge ok">Unused</span>' ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody></table></div>
  <?php endif; ?>
</section>

<!-- ============ Section 2: Weekly plan ============ -->
<section class="card" id="weekly-plan">
  <div class="card-head"><h2>2 · Weekly plan</h2><span class="muted small">Week of <?= h($fmt($week)) ?> – <?= h($weekEnd->format('D j M Y')) ?> · <?= count($topics) ?>/<?= (int) $ctx['weekly_target'] ?> topics</span></div>
  <div class="toolbar">
    <form method="get" action="/admin/seo-keywords-topics#weekly-plan" class="row-form">
      <input type="hidden" name="campaign" value="<?= $cid ?>">
      <label class="field inline">Monday <input type="date" name="week" value="<?= h($week) ?>" step="7"></label>
      <button class="btn btn-ghost btn-sm">Show week</button>
    </form>
    <form method="post" action="<?= h($back()) ?>">
      <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="action" value="generate_week">
      <button type="submit" class="btn btn-primary" <?= $ctx['offers'] && count($topics) < (int) $ctx['weekly_target'] ? '' : 'disabled' ?>>✦ Generate weekly plan</button>
    </form>
    <span class="muted small">Fills up to <?= (int) $ctx['weekly_target'] ?> topics, rotating offers week to week and using each unused keyword once (<?= count($unused) ?> left).</span>
  </div>

  <?php if (!$topics): ?><p class="muted">No topics for this week yet.</p>
  <?php else: ?>
    <div class="topic-list">
    <?php foreach ($topics as $t): ?>
      <article class="topic" id="topic-<?= (int) $t['id'] ?>">
        <div class="topic-main">
          <div class="topic-meta"><span class="badge <?= $TOPIC_BADGE[$t['status']] ?? 'muted-badge' ?>"><?= h(ucfirst($t['status'])) ?></span>
            <span class="small"><?= h($fmt($t['planned_for'])) ?></span> · <span class="small"><?= h((string) $t['offer_name']) ?></span>
            <?php if ($t['keyword']): ?> · <span class="small muted">🔑 <?= h($t['keyword']) ?></span><?php endif; ?></div>
          <h3><?= h($t['title']) ?></h3>
          <?php if ($t['angle']): ?><p class="small"><?= h($t['angle']) ?></p><?php endif; ?>
        </div>
        <div class="topic-actions">
          <?php if ($t['status'] === 'proposed'): ?>
            <form method="post" action="<?= h($back('#topic-' . $t['id'])) ?>"><input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="approve_topic"><input type="hidden" name="topic" value="<?= (int) $t['id'] ?>"><button class="btn btn-primary btn-sm">Approve</button></form>
          <?php endif; ?>
          <form method="post" action="<?= h($back('#topic-' . $t['id'])) ?>"><input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="regenerate_topic"><input type="hidden" name="topic" value="<?= (int) $t['id'] ?>"><button class="btn btn-ghost btn-sm">↻ Regenerate</button></form>
          <a class="btn btn-ghost btn-sm" href="/admin/seo-articles?topic=<?= (int) $t['id'] ?>">Open in article generator →</a>
          <details class="topic-edit"><summary class="btn btn-ghost btn-sm">Edit</summary>
            <form method="post" action="<?= h($back()) ?>" class="stack-form">
              <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="edit_topic"><input type="hidden" name="topic" value="<?= (int) $t['id'] ?>">
              <label>Date <input type="date" name="planned_for" value="<?= h($t['planned_for']) ?>" required></label>
              <label>Title <input type="text" name="title" value="<?= h($t['title']) ?>" minlength="10" maxlength="160" required></label>
              <label>Angle <textarea name="angle" rows="3" maxlength="600"><?= h((string) $t['angle']) ?></textarea></label>
              <button class="btn btn-primary btn-sm">Save topic</button>
            </form>
          </details>
        </div>
      </article>
    <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <details class="manual-topic">
    <summary>+ Add a topic manually</summary>
    <form method="post" action="<?= h($back()) ?>" class="form-grid">
      <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="add_topic">
      <label class="field span-2">Title <input type="text" name="title" required minlength="10" maxlength="160"></label>
      <label class="field span-2">Angle <textarea name="angle" rows="2" maxlength="600"></textarea></label>
      <label class="field">Campaign <select name="campaign"><?php foreach ($campaigns as $c): ?><option value="<?= (int) $c['id'] ?>" <?= (int) $c['id'] === $cid ? 'selected' : '' ?>><?= h($c['name']) ?></option><?php endforeach; ?></select></label>
      <label class="field">Offer <?= offer_select($ctx, 'offer', null, false) ?></label>
      <label class="field">Keyword
        <select name="keyword_id"><option value="">None</option>
          <?php foreach ($keywords as $k): ?><option value="<?= (int) $k['id'] ?>"><?= h($k['keyword']) ?><?= $k['used_at'] ? ' (used)' : '' ?></option><?php endforeach; ?></select>
      </label>
      <label class="field">Date <input type="date" name="planned_for" value="<?= h($week) ?>" required></label>
      <div class="span-2"><button class="btn btn-primary">Add topic</button></div>
    </form>
  </details>
</section>
<?php endif; ?>
<?php admin_footer();
