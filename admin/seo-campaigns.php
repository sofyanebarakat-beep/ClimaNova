<?php
declare(strict_types=1);
require __DIR__ . '/_inc/auth.php';
require __DIR__ . '/_inc/seo.php';
require __DIR__ . '/_inc/seo_agent.php';
require __DIR__ . '/_inc/seo_layout.php';
require_login();

$errors = [];
$form = null;              // values shown in the form (posted values after a validation error)
$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if (!csrf_valid($_POST['csrf'] ?? null)) {
        $_SESSION['flash'] = ['error', 'Your session expired — please try again.'];
        header('Location: /admin/seo-campaigns');
        exit;
    }
    if ($action === 'toggle') {
        try {
            $s = seo_toggle_campaign((int) ($_POST['id'] ?? 0));
            $_SESSION['flash'] = ['success', $s === 'paused' ? 'Campaign paused.' : 'Campaign resumed.'];
        } catch (Throwable $e) {
            $_SESSION['flash'] = ['error', admin_error($e, 'pause or resume the campaign')];
        }
        header('Location: /admin/seo-campaigns#list');
        exit;
    }
    if ($action === 'save') {
        $editId = ($_POST['id'] ?? '') !== '' ? (int) $_POST['id'] : null;
        try {
            $r = seo_save_campaign($editId, $_POST);
            if (isset($r['id'])) {
                $_SESSION['flash'] = ['success', $editId ? 'Campaign updated.' : 'Campaign created.'];
                header('Location: /admin/seo-campaigns#campaign-' . $r['id']);
                exit;
            }
            $errors = $r['errors'];
        } catch (Throwable $e) {
            $errors = [admin_error($e, 'save the campaign')];
        }
        $form = [
            'name' => $_POST['name'] ?? '', 'goal' => $_POST['goal'] ?? '', 'language' => $_POST['language'] ?? 'fr',
            'countries' => $_POST['countries'] ?? '', 'weekly_target' => $_POST['weekly_target'] ?? '',
            'start_date' => $_POST['start_date'] ?? '', 'end_date' => $_POST['end_date'] ?? '',
            'days' => array_map('intval', (array) ($_POST['days'] ?? [])), 'times' => $_POST['times'] ?? '',
            'time_zone' => $_POST['time_zone'] ?? '', 'status' => $_POST['status'] ?? 'draft',
            'offers' => array_map('intval', (array) ($_POST['offers'] ?? [])),
            'automation_enabled' => !empty($_POST['automation_enabled']), 'auto_publish' => !empty($_POST['auto_publish']),
        ];
    }
}
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$campaigns = [];
$usable = [];
$loadError = '';
try {
    $campaigns = seo_list_campaigns();
    $usable = seo_usable_offers();
} catch (Throwable $e) {
    $loadError = admin_error($e, 'load the campaigns');
}

$editing = null;
if ($editId) {
    foreach ($campaigns as $c) if ((int) $c['id'] === $editId) $editing = $c;
    if (!$editing && !$errors) { $loadError = $loadError ?: 'That campaign could not be found.'; $editId = null; }
}
if ($form === null) {
    $form = $editing ? [
        'name' => $editing['name'], 'goal' => (string) $editing['goal'], 'language' => $editing['language'],
        'countries' => implode(', ', $editing['target_countries']), 'weekly_target' => (string) $editing['weekly_target'],
        'start_date' => (string) $editing['start_date'], 'end_date' => (string) $editing['end_date'],
        'days' => $editing['publish_days'], 'times' => implode(', ', $editing['publish_times']),
        'time_zone' => $editing['time_zone'], 'status' => $editing['status'],
        'offers' => array_map(static fn ($o) => (int) $o['id'], $editing['offers']),
        'automation_enabled' => $editing['automation_enabled'], 'auto_publish' => $editing['auto_publish'],
    ] : [
        'name' => '', 'goal' => '', 'language' => 'fr', 'countries' => 'FR', 'weekly_target' => '3',
        'start_date' => date('Y-m-d'), 'end_date' => '', 'days' => [1, 3, 5], 'times' => '09:00',
        'time_zone' => 'Europe/Paris', 'status' => 'draft', 'offers' => [], 'automation_enabled' => false, 'auto_publish' => false,
    ];
}
// Offers linked to this campaign that are no longer enabled: shown, but they will be removed on save.
$usableIds = array_map('intval', array_column($usable, 'id'));
$staleOffers = $editing ? array_filter($editing['offers'], static fn ($o) => !in_array((int) $o['id'], $usableIds, true)) : [];
$STATUS_BADGE = ['active' => 'ok', 'paused' => 'warn', 'draft' => 'muted-badge', 'completed' => 'muted-badge'];
$fmtDate = static fn (?string $d) => $d ? date('d/m/Y', strtotime($d)) : '';

seo_header('campaigns', 'Campaigns', 'Campaigns promote one or more enabled offers with a weekly publishing schedule.');
?>
<?php if ($flash): ?><div class="<?= $flash[0] === 'success' ? 'success' : 'alert' ?>"><?= h($flash[1]) ?></div><?php endif; ?>
<?php if ($loadError): ?><div class="alert"><?= h($loadError) ?></div><?php endif; ?>

<section class="card" id="campaign-form">
  <div class="card-head">
    <h2><?= $editId ? 'Edit campaign' : 'New campaign' ?></h2>
    <?php if ($editId): ?><a href="/admin/seo-campaigns" class="small">Cancel editing</a><?php endif; ?>
  </div>

  <?php if ($errors): ?>
    <div class="alert" role="alert"><strong>Please fix the following:</strong>
      <ul class="error-list"><?php foreach ($errors as $err): ?><li><?= h($err) ?></li><?php endforeach; ?></ul>
    </div>
  <?php endif; ?>

  <?php if (!$usable): ?>
    <div class="notice">No offer is enabled for promotion yet. <a href="/admin/seo-offers">Enable at least one offer</a> before creating a campaign.</div>
  <?php endif; ?>

  <form method="post" action="/admin/seo-campaigns<?= $editId ? '?edit=' . $editId : '' ?>" class="campaign-form" novalidate>
    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= $editId ?: '' ?>">

    <div class="form-grid">
      <label class="field span-2">Campaign name
        <input type="text" name="name" minlength="5" maxlength="120" required value="<?= h($form['name']) ?>" placeholder="Climatisation Nice — été 2027">
        <span class="muted small">5–120 characters.</span>
      </label>
      <label class="field span-2">Campaign goal
        <textarea name="goal" rows="2" maxlength="500" placeholder="Rank on the first page for “climatisation nice” and get more quote requests."><?= h($form['goal']) ?></textarea>
      </label>
      <label class="field">Language
        <select name="language">
          <?php foreach (SEO_LANGUAGES as $code => $label): ?><option value="<?= $code ?>" <?= $form['language'] === $code ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?>
        </select>
      </label>
      <label class="field">Target country codes
        <input type="text" name="countries" value="<?= h($form['countries']) ?>" placeholder="FR, MC">
        <span class="muted small">Comma separated, 2 letters each.</span>
      </label>
      <label class="field">Weekly article target
        <input type="number" name="weekly_target" min="1" max="12" step="1" value="<?= h((string) $form['weekly_target']) ?>">
        <span class="muted small">1–12 articles per week.</span>
      </label>
      <label class="field">Status
        <select name="status">
          <?php foreach (SEO_CAMPAIGN_STATUSES as $k => $label): ?><option value="<?= $k ?>" <?= $form['status'] === $k ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?>
        </select>
      </label>
      <label class="field">Starts on <input type="date" name="start_date" value="<?= h($form['start_date']) ?>"></label>
      <label class="field">Ends on <input type="date" name="end_date" value="<?= h($form['end_date']) ?>"><span class="muted small">Leave empty for no end date.</span></label>

      <fieldset class="field span-2">
        <legend>Publishing days</legend>
        <div class="day-picks">
          <?php foreach (SEO_WEEKDAYS as $n => $label): ?>
            <label class="day"><input type="checkbox" name="days[]" value="<?= $n ?>" <?= in_array($n, $form['days'], true) ? 'checked' : '' ?>><span><?= $label ?></span></label>
          <?php endforeach; ?>
        </div>
      </fieldset>
      <label class="field">Publishing times
        <input type="text" name="times" value="<?= h($form['times']) ?>" placeholder="09:00, 17:00">
        <span class="muted small">Up to 4, in HH:mm.</span>
      </label>
      <label class="field">Time zone
        <input type="text" name="time_zone" list="tz-list" value="<?= h($form['time_zone']) ?>" placeholder="Europe/Paris">
        <datalist id="tz-list"><?php foreach (DateTimeZone::listIdentifiers() as $tz): ?><option value="<?= h($tz) ?>"><?php endforeach; ?></datalist>
      </label>

      <fieldset class="field span-2">
        <legend>Promoted offers</legend>
        <?php if ($usable): ?>
          <div class="offer-picks">
            <?php foreach ($usable as $o): ?>
              <label class="check"><input type="checkbox" name="offers[]" value="<?= (int) $o['id'] ?>" <?= in_array((int) $o['id'], $form['offers'], true) ? 'checked' : '' ?>>
                <span><?= h($o['name']) ?> <span class="muted small nowrap">· priority <?= (int) $o['seo_priority'] ?></span></span></label>
            <?php endforeach; ?>
          </div>
        <?php else: ?><p class="muted small">No enabled offers.</p><?php endif; ?>
        <?php foreach ($staleOffers as $o): ?>
          <p class="muted small">“<?= h($o['name']) ?>” is linked but no longer enabled — it will be removed from this campaign when you save.</p>
        <?php endforeach; ?>
      </fieldset>

      <div class="span-2 switches">
        <label class="switch"><input type="checkbox" name="automation_enabled" value="1" <?= $form['automation_enabled'] ? 'checked' : '' ?>>
          <span><strong>Write drafts automatically from approved topics</strong>
          <span class="muted small">The agent writes drafts on its own. Every draft still needs your approval before it can be published.</span></span></label>
        <label class="switch"><input type="checkbox" name="auto_publish" value="1" <?= $form['auto_publish'] ? 'checked' : '' ?>>
          <span><strong>Publish approved articles automatically at the campaign's days and times</strong>
          <span class="muted small">Only articles you approved, with a featured image you uploaded, are ever published.</span></span></label>
      </div>
    </div>

    <div class="form-actions">
      <button type="submit" class="btn btn-primary"><?= $editId ? 'Save changes' : 'Create campaign' ?></button>
    </div>
    <p class="tip">💡 For 12 posts a week, choose 6 days and 2 times.</p>
  </form>
</section>

<section class="card" id="list">
  <div class="card-head"><h2>Campaigns</h2><span class="muted small"><?= count($campaigns) ?> campaign(s)</span></div>
  <?php if (!$campaigns): ?>
    <p class="muted">No campaigns yet — create your first one above.</p>
  <?php else: ?>
    <div class="table-wrap">
      <table class="campaign-table">
        <thead><tr><th>Name</th><th>Status</th><th>Offers</th><th>Countries</th><th>Dates</th><th>Schedule</th><th>Automation</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($campaigns as $c): ?>
          <tr id="campaign-<?= (int) $c['id'] ?>" class="<?= (int) $c['id'] === $editId ? 'is-editing' : '' ?>">
            <td><strong><?= h($c['name']) ?></strong><?php if ($c['goal']): ?><div class="muted small clamp"><?= h($c['goal']) ?></div><?php endif; ?></td>
            <td><span class="badge <?= $STATUS_BADGE[$c['status']] ?? 'muted-badge' ?>"><?= h(SEO_CAMPAIGN_STATUSES[$c['status']] ?? $c['status']) ?></span></td>
            <td><?php foreach ($c['offers'] as $o): ?><span class="badge <?= $o['usable'] ? 'muted-badge' : 'bad' ?>" title="<?= $o['usable'] ? '' : 'No longer enabled' ?>"><?= h($o['name']) ?></span><?php endforeach; ?></td>
            <td class="nowrap"><?= h(implode(', ', $c['target_countries'])) ?></td>
            <td class="nowrap small"><?= h($fmtDate($c['start_date'])) ?> → <?= $c['end_date'] ? h($fmtDate($c['end_date'])) : '<span class="muted">no end</span>' ?></td>
            <td class="small"><?= h(seo_days_label($c['publish_days'])) ?> · <?= h(implode(', ', $c['publish_times'])) ?>
              <div class="muted"><?= (int) $c['weekly_target'] ?>/week · <?= h($c['time_zone']) ?></div></td>
            <td class="small nowrap">Drafts: <?= $c['automation_enabled'] ? '<span class="badge ok">auto</span>' : '<span class="badge muted-badge">manual</span>' ?><br>
              Publish: <?= $c['auto_publish'] ? '<span class="badge ok">auto</span>' : '<span class="badge muted-badge">manual</span>' ?></td>
            <td class="nowrap actions">
              <a class="btn btn-ghost btn-sm" href="/admin/seo-campaigns?edit=<?= (int) $c['id'] ?>#campaign-form">Edit</a>
              <?php if (in_array($c['status'], ['active', 'paused'], true)): ?>
                <form method="post" action="/admin/seo-campaigns">
                  <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                  <input type="hidden" name="action" value="toggle">
                  <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                  <button type="submit" class="btn btn-ghost btn-sm"><?= $c['status'] === 'active' ? 'Pause' : 'Resume' ?></button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
<?php admin_footer();
