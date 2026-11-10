<?php
declare(strict_types=1);
require __DIR__ . '/_inc/auth.php';
require __DIR__ . '/_inc/seo.php';
require __DIR__ . '/_inc/seo_agent.php';
require __DIR__ . '/_inc/seo_layout.php';
require_login();

$anchor = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if (!csrf_valid($_POST['csrf'] ?? null)) {
        $_SESSION['flash'] = ['error', 'Your session expired — please try again.'];
    } elseif ($action === 'sync_offers') {
        try {
            $r = seo_sync_offers(dirname(__DIR__));
            $_SESSION['flash'] = ['success', "Catalog synced: {$r['synced']} service(s)" . ($r['deactivated'] ? ", {$r['deactivated']} no longer in the catalog." : '.') . ' Your settings were kept.'];
        } catch (Throwable $e) {
            $_SESSION['flash'] = ['error', admin_error($e, 'sync the catalog')];
        }
    } elseif ($action === 'save_offer') {
        $id = (int) ($_POST['id'] ?? 0);
        $anchor = '#offer-' . $id;
        try {
            $r = seo_save_offer($id, isset($_POST['promotion_enabled']), (string) ($_POST['target_keywords'] ?? ''), $_POST['seo_priority'] ?? '');
            $_SESSION['flash'] = ['success', "Saved “{$r['slug']}”.", $id];
        } catch (Throwable $e) {
            // Keep what the admin typed so a validation error does not lose their edits.
            $_SESSION['offer_form'][$id] = ['promotion_enabled' => isset($_POST['promotion_enabled']),
                'target_keywords' => (string) ($_POST['target_keywords'] ?? ''), 'seo_priority' => (string) ($_POST['seo_priority'] ?? '')];
            $_SESSION['flash'] = ['error', admin_error($e, 'save this offer'), $id];
        }
    }
    header('Location: /admin/seo-offers' . $anchor);
    exit;
}
$flash = $_SESSION['flash'] ?? null;
$draft = $_SESSION['offer_form'] ?? [];
unset($_SESSION['flash'], $_SESSION['offer_form']);
$cardFlash = $flash && isset($flash[2]) ? $flash : null;   // shown inside the card that was saved
$pageFlash = $flash && !isset($flash[2]) ? $flash : null;

$offers = [];
$error = '';
try {
    $offers = seo_list_offers();
} catch (Throwable $e) {
    $error = admin_error($e, 'load the offers');
}
$enabled = count(array_filter($offers, static fn ($o) => $o['is_active'] && $o['promotion_enabled']));

seo_header('offers', 'Offers', 'The ClimaNova Energie services the articles promote — only enabled offers can be used in campaigns and articles.');
?>
<div class="toolbar">
  <form method="post" action="/admin/seo-offers">
    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="action" value="sync_offers">
    <button type="submit" class="btn btn-primary">Sync offers</button>
  </form>
  <?php if ($offers): ?><span class="muted small"><?= $enabled ?> of <?= count($offers) ?> offer(s) enabled for promotion.</span><?php endif; ?>
</div>

<?php if ($pageFlash): ?><div class="<?= $pageFlash[0] === 'success' ? 'success' : 'alert' ?>"><?= h($pageFlash[1]) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert"><?= h($error) ?></div><?php endif; ?>

<?php if (!$error && !$offers): ?>
  <section class="card empty"><p><strong>Sync the catalog to get started.</strong></p>
    <p class="muted">“Sync offers” imports every service page under /services/ from the sitemap.</p></section>
<?php endif; ?>

<div class="offer-grid">
<?php foreach ($offers as $o):
    $d = $draft[$o['id']] ?? null;
    $isEnabled = $d ? $d['promotion_enabled'] : $o['promotion_enabled'];
    $kw = $d ? $d['target_keywords'] : implode(', ', $o['target_keywords']);
    $prio = $d ? $d['seo_priority'] : (string) $o['seo_priority'];
    $usable = $o['is_active'] && $o['promotion_enabled']; ?>
  <form method="post" action="/admin/seo-offers" class="card offer-card <?= $usable ? '' : 'is-off' ?>" id="offer-<?= (int) $o['id'] ?>">
    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="action" value="save_offer">
    <input type="hidden" name="id" value="<?= (int) $o['id'] ?>">

    <div class="offer-top">
      <div>
        <h2><?= h($o['name']) ?></h2>
        <div class="muted small">ID: <code><?= h($o['slug']) ?></code></div>
      </div>
      <?php if (!$o['is_active']): ?><span class="badge bad">Not in catalog</span>
      <?php elseif ($o['promotion_enabled']): ?><span class="badge ok">Enabled</span>
      <?php else: ?><span class="badge muted-badge">Disabled</span><?php endif; ?>
    </div>
    <a class="small" href="<?= h(SITE . $o['path']) ?>" target="_blank" rel="noopener"><?= h($o['path']) ?> ↗</a>

    <?php if ($cardFlash && (int) $cardFlash[2] === (int) $o['id']): ?>
      <div class="<?= $cardFlash[0] === 'success' ? 'success' : 'alert' ?>"><?= h($cardFlash[1]) ?></div>
    <?php endif; ?>

    <label class="check">
      <input type="checkbox" name="promotion_enabled" value="1" <?= $isEnabled ? 'checked' : '' ?> <?= $o['is_active'] ? '' : 'disabled' ?>>
      Promotion enabled
    </label>
    <label class="field">Target keywords
      <input type="text" name="target_keywords" value="<?= h($kw) ?>" placeholder="ex. <?= h(mb_strtolower($o['name'])) ?>, devis <?= h(str_replace('-', ' ', $o['slug'])) ?> nice">
      <span class="muted small">Comma separated.</span>
    </label>
    <label class="field">SEO priority (1-100)
      <input type="number" name="seo_priority" min="1" max="100" step="1" required value="<?= h($prio) ?>">
    </label>
    <div class="offer-foot">
      <button type="submit" class="btn btn-primary">Save</button>
      <?php if ($o['synced_at']): ?><span class="muted small">Synced <?= h(date('d/m/Y H:i', strtotime($o['synced_at']))) ?></span><?php endif; ?>
    </div>
  </form>
<?php endforeach; ?>
</div>
<?php admin_footer();
