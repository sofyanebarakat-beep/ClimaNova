<?php
declare(strict_types=1);
require __DIR__ . '/_inc/auth.php';
require __DIR__ . '/_inc/seo.php';
require __DIR__ . '/_inc/seo_articles.php';
require __DIR__ . '/_inc/seo_layout.php';
require_login();

$self = '/admin/seo-articles';
$back = static function (string $query = '', string $hash = '') use ($self): never {
    header('Location: ' . $self . ($query !== '' ? '?' . $query : '') . $hash);
    exit;
};
$flash = static fn (string $type, string $msg) => $_SESSION['flash'] = [$type, $msg];

/* ---------- Actions ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);
    $ret = (string) ($_POST['return'] ?? '');
    $ret = preg_match('/^[a-z0-9=&_,-]*$/i', $ret) ? $ret : '';

    // Signed upload link (JSON, called by the editor's JavaScript).
    if ($action === 'upload_link') {
        header('Content-Type: application/json; charset=utf-8');
        if (!csrf_valid($_POST['csrf'] ?? null)) { http_response_code(403); echo json_encode(['error' => 'Your session expired — reload the page.']); exit; }
        try {
            echo json_encode(seo_create_upload_link($id, (string) ($_POST['kind'] ?? ''), (string) ($_POST['mime'] ?? ''), (int) ($_POST['size'] ?? 0)));
        } catch (Throwable $e) {
            http_response_code(400);
            echo json_encode(['error' => $e instanceof InvalidArgumentException ? $e->getMessage() : admin_error($e, 'prepare the upload')]);
        }
        exit;
    }
    // AI SEO assistant + image descriptions (JSON, called by the editor). Nothing is saved: the editor fills the fields.
    if ($action === 'ai_seo' || $action === 'ai_alt') {
        header('Content-Type: application/json; charset=utf-8');
        if (!csrf_valid($_POST['csrf'] ?? null)) { http_response_code(403); echo json_encode(['error' => 'Your session expired — reload the page.']); exit; }
        @set_time_limit(180);
        try {
            echo json_encode($action === 'ai_seo'
                ? seo_ai_seo_suggestions($id, ['title' => (string) ($_POST['title'] ?? ''), 'focus_keyword' => (string) ($_POST['focus_keyword'] ?? ''),
                    'markdown' => (string) ($_POST['markdown'] ?? ''), 'fi_url' => (string) ($_POST['fi_url'] ?? '')])
                : seo_ai_image_alt($id, (string) ($_POST['url'] ?? ''), (string) ($_POST['context'] ?? '')), JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            http_response_code(400);
            echo json_encode(['error' => $e instanceof InvalidArgumentException || $e instanceof AiError ? $e->getMessage() : admin_error($e, 'ask the AI')]);
        }
        exit;
    }
    if (!csrf_valid($_POST['csrf'] ?? null)) { $flash('error', 'Your session expired — please try again.'); $back(); }

    try {
        switch ($action) {
            case 'generate':
                $r = seo_generate_article($_POST);
                $_SESSION['gen_result'] = $r;
                $back('', '#article-' . $r['id']);
            case 'save':
                $r = seo_save_article($id, $_POST, (string) ($_POST['target'] ?? 'draft'));
                if (($_POST['target'] ?? '') === 'review' && $r['notes']) {
                    $flash('error', 'Saved as a draft. ' . seo_quality_message($r['notes']));
                    $back('edit=' . $id);
                }
                $flash('success', $r['status'] === 'review' ? 'Saved and sent for review.' : 'Saved as a draft. The previous version is in History.');
                $back(($_POST['stay'] ?? '') ? 'edit=' . $id : $ret, '#article-' . $id);
            case 'submit':
                seo_submit_for_review($id);
                $flash('success', 'Submitted for review.');
                break;
            case 'approve':
                $slot = seo_approve_article($id);
                $a = seo_article($id);
                $flash('success', $slot ? 'Approved and scheduled for ' . $slot->setTimezone(new DateTimeZone($a['time_zone']))->format('d/m/Y H:i') . '.' : 'Approved.');
                break;
            case 'reject':
                seo_reject_article($id, (string) ($_POST['reason'] ?? ''));
                $flash('success', 'Article rejected.');
                break;
            case 'regenerate':
                $r = seo_regenerate_article($id);
                $_SESSION['gen_result'] = $r;
                $flash('success', 'Article regenerated — the previous version is in History and the featured image was kept.');
                break;
            case 'rewrite':
                seo_rewrite_section($id, (string) ($_POST['heading'] ?? ''));
                $flash('success', 'Section rewritten — the previous version is in History.');
                break;
            case 'publish':
                $url = seo_publish_article($id);
                $flash('success', "Published: $url");
                break;
            case 'schedule':
                $t = trim((string) ($_POST['when'] ?? ''));
                $slot = seo_schedule_article($id, $t === '' ? null : $t);
                $a = seo_article($id);
                $flash('success', 'Scheduled for ' . $slot->setTimezone(new DateTimeZone($a['time_zone']))->format('d/m/Y H:i') . " ({$a['time_zone']}).");
                break;
            case 'unpublish':
                seo_unpublish_article($id);
                $flash('success', 'Removed from the blog. The article is approved again.');
                break;
            case 'restore':
                seo_restore_version($id, (int) ($_POST['version_id'] ?? 0));
                $flash('success', 'Older version restored as a draft.');
                $back('edit=' . $id);
            default:
                $flash('error', 'Unknown action.');
        }
    } catch (Throwable $e) {
        $msg = $e instanceof InvalidArgumentException || $e instanceof AiError ? $e->getMessage() : admin_error($e, 'complete this action');
        $flash('error', $msg);
        if ($action === 'generate') { $_SESSION['gen_form'] = $_POST; $back('', '#generator'); }
        if ($action === 'save') { $back('edit=' . $id); }
    }
    $back($ret, $id ? '#article-' . $id : '');
}

$flashMsg = $_SESSION['flash'] ?? null;
$genResult = $_SESSION['gen_result'] ?? null;
$genForm = $_SESSION['gen_form'] ?? null;
unset($_SESSION['flash'], $_SESSION['gen_result'], $_SESSION['gen_form']);

$STATUS_BADGE = ['draft' => 'muted-badge', 'generated' => 'muted-badge', 'review' => 'warn', 'approved' => 'ok', 'scheduled' => 'ok',
    'publishing' => 'warn', 'published' => 'ok', 'rejected' => 'bad', 'failed' => 'bad'];
$badge = static fn (string $s) => '<span class="badge ' . ($STATUS_BADGE[$s] ?? 'muted-badge') . '">' . h(SEO_ARTICLE_STATUSES[$s] ?? $s) . '</span>';
$localTime = static fn (?string $ts, string $tz) => $ts ? (new DateTimeImmutable($ts))->setTimezone(new DateTimeZone($tz))->format('d/m/Y H:i') : '';
$csrf = '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">';

$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : null;
$previewId = isset($_GET['preview']) ? (int) $_GET['preview'] : null;
$historyId = isset($_GET['history']) ? (int) $_GET['history'] : null;
$filter = (string) ($_GET['status'] ?? '');
$returnQuery = $filter !== '' ? 'status=' . $filter : '';

/* ---------- Edit window ---------- */
if ($editId) {
    $loadError = '';
    try {
        $a = seo_article($editId);
        $offers = seo_usable_offers();
    } catch (Throwable $e) {
        $loadError = admin_error($e, 'load the article');
    }
    seo_header('articles', 'Edit article', 'Every save keeps the previous version in History.');
    if ($flashMsg) echo '<div class="' . ($flashMsg[0] === 'success' ? 'success' : 'alert') . '">' . h($flashMsg[1]) . '</div>';
    if ($loadError) { echo '<div class="alert">' . h($loadError) . '</div>'; admin_footer(); exit; }
    if (in_array($a['status'], ['publishing', 'published'], true)) {
        echo '<div class="notice">This article is ' . h($a['status']) . ' and cannot be edited. Unpublish it first.</div><p><a href="' . $self . '">← Back to articles</a></p>';
        admin_footer();
        exit;
    }
    $md = (string) ($a['content']['markdown'] ?? '');
    $sources = seo_md_get_sources($md, $a['language']);
    $mdNoSources = seo_md_set_sources($md, [], $a['language']);
    $fi = $a['content']['featuredImage'] ?? null;
    $sections = array_column(seo_md_parse($mdNoSources)['sections'], 0);
    $usableIds = array_map('intval', array_column($offers, 'id'));
    ?>
<p><a href="<?= $self ?>#article-<?= $a['id'] ?>">← Back to articles</a> · <?= $badge($a['status']) ?> · <a href="<?= $self ?>?preview=<?= $a['id'] ?>">Preview</a> · <a href="<?= $self ?>?history=<?= $a['id'] ?>">History</a></p>
<?php if ($a['quality_notes']): ?>
  <div class="warning-note"><strong>Quality notes:</strong><ul class="notes"><?php foreach ($a['quality_notes'] as $n): ?><li><?= h($n) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>
<form method="post" class="card" id="edit-form" data-article="<?= $a['id'] ?>" data-lang="<?= h($a['language']) ?>">
  <?= $csrf ?>
  <input type="hidden" name="action" value="save">
  <input type="hidden" name="id" value="<?= $a['id'] ?>">
  <input type="hidden" name="sources" id="sources-json" value="<?= h(json_encode($sources, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>">
  <div class="form-grid">
    <label class="field span-2">Title <input type="text" name="title" id="title" value="<?= h($a['title']) ?>" maxlength="180" required></label>
    <label class="field">Slug <span class="muted small">/blog/<em>slug</em>/ — lowercase, numbers, hyphens</span>
      <input type="text" name="slug" value="<?= h($a['slug']) ?>" pattern="[a-z0-9]+(-[a-z0-9]+)*" maxlength="90" required></label>
    <label class="field">Focus keyword <input type="text" name="focus_keyword" id="kw" value="<?= h($a['focus_keyword']) ?>" maxlength="80" required></label>
    <label class="field span-2">Meta description <span class="counter" id="meta-count"></span>
      <textarea name="meta_description" id="meta" rows="2" maxlength="240"><?= h($a['meta_description']) ?></textarea>
      <span><button type="button" class="btn btn-ghost btn-sm" id="add-kw" hidden>Add the keyword for me</button>
        <button type="button" class="btn btn-primary btn-sm" id="ai-seo">✨ Generate SEO with AI</button>
        <span class="muted small" id="ai-seo-status">SEO title + meta description<?= $fi ? ' + featured image alt text' : '' ?>, from the text below (nothing is saved until you click Save).</span></span></label>
    <label class="field">SEO title <span class="muted small">(optional, under 60 characters)</span> <span class="counter" id="seo-count"></span>
      <input type="text" name="seo_title" id="seo-title" value="<?= h($a['seo_title']) ?>" maxlength="70"></label>
    <label class="field">Offer
      <select name="offer_id">
        <?php if (!in_array((int) $a['offer_id'], $usableIds, true)): ?><option value="<?= (int) $a['offer_id'] ?>" selected><?= h($a['offer_name'] ?? 'Current offer') ?> (not enabled)</option><?php endif; ?>
        <?php foreach ($offers as $o): ?><option value="<?= (int) $o['id'] ?>" <?= (int) $o['id'] === (int) $a['offer_id'] ? 'selected' : '' ?>><?= h($o['name']) ?></option><?php endforeach; ?>
      </select></label>

    <div class="field span-2">Featured image <span class="muted small">JPG, PNG or WebP, up to 5 MB — 1200 × 630 recommended. Alt text is required.</span>
      <div class="img-box">
        <div id="fi-preview"><?php if ($fi): ?><img src="<?= h($fi['url']) ?>" alt="" width="260"><?php else: ?><div class="art-thumb" style="width:260px;height:136px">No featured image yet</div><?php endif; ?></div>
        <div class="field">
          <input type="file" id="fi-file" accept="image/jpeg,image/png,image/webp">
          <span class="muted small" id="fi-status"><?= $fi ? h(($fi['width'] ?? '?') . ' × ' . ($fi['height'] ?? '?') . ' px') : '' ?></span>
          <label class="field">Alt text (what the picture shows — the AI fills it after upload, check it) <input type="text" name="fi_alt" id="fi-alt" value="<?= h($fi['alt'] ?? '') ?>" maxlength="200"></label>
          <input type="hidden" name="fi_url" id="fi-url" value="<?= h($fi['url'] ?? '') ?>">
          <input type="hidden" name="fi_width" id="fi-width" value="<?= (int) ($fi['width'] ?? 0) ?>">
          <input type="hidden" name="fi_height" id="fi-height" value="<?= (int) ($fi['height'] ?? 0) ?>">
          <?php if ($fi): ?><button type="button" class="btn btn-ghost btn-sm" id="fi-remove">Remove featured image</button><?php endif; ?>
        </div>
      </div>
    </div>

    <label class="field span-2">Content (Markdown) <span class="muted small">## for sections, ### for FAQ questions, [text](/path/) for links, ![alt](https://… "caption") for images. Sources are edited below.</span>
      <textarea name="markdown" id="md" class="md-area" spellcheck="true"><?= h($mdNoSources) ?></textarea></label>

    <div class="field span-2">Add an image to a section
      <div class="img-box">
        <label class="field">Section <select id="si-section"><?php foreach ($sections as $s): ?><option><?= h($s) ?></option><?php endforeach; ?></select></label>
        <label class="field">Image file <input type="file" id="si-file" accept="image/jpeg,image/png,image/webp"></label>
        <label class="field">Alt text <span class="muted small">(leave empty: the AI looks at the photo and writes it)</span> <input type="text" id="si-alt" maxlength="200"></label>
        <label class="field">Caption (optional) <input type="text" id="si-caption" maxlength="200"></label>
        <button type="button" class="btn btn-ghost" id="si-add">Upload and insert</button>
        <span class="muted small" id="si-status"></span>
      </div>
    </div>

    <div class="field span-2">External sources <span class="muted small">Shown in a “Sources” section just before the offer link.</span>
      <ul class="src-list" id="src-list"></ul>
      <div class="src-add">
        <label class="field">Name <input type="text" id="src-name" maxlength="160"></label>
        <label class="field">Link (https://…) <input type="url" id="src-url" placeholder="https://"></label>
        <button type="button" class="btn btn-ghost" id="src-add">Add source</button>
      </div>
      <span class="error-line" id="src-error"></span>
    </div>
  </div>
  <div class="edit-actions">
    <button type="submit" name="target" value="draft" class="btn btn-ghost">Save as Draft</button>
    <button type="submit" name="target" value="review" class="btn btn-primary">Save — Needs review</button>
    <label class="check"><input type="checkbox" name="stay" value="1" checked> Stay in the editor</label>
  </div>
</form>
<script>
(() => {
  const form = document.getElementById('edit-form');
  const csrf = form.querySelector('[name=csrf]').value;
  const articleId = form.dataset.article;
  const $ = (id) => document.getElementById(id);
  const norm = (s) => s.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/g, ' ').trim();
  const hasKw = (text, kw) => norm(kw) !== '' && (' ' + norm(text) + ' ').includes(' ' + norm(kw) + ' ');

  // Meta description counter + "Add the keyword for me"
  const meta = $('meta'), kw = $('kw'), addKw = $('add-kw'), seo = $('seo-title');
  const refresh = () => {
    const n = meta.value.trim().length;
    $('meta-count').textContent = n + ' / 80–240 characters';
    $('meta-count').classList.toggle('bad', n < 80 || n > 240);
    addKw.hidden = kw.value.trim() === '' || hasKw(meta.value, kw.value);
    const s = seo.value.trim().length;
    $('seo-count').textContent = s ? s + ' characters' : '';
    $('seo-count').classList.toggle('bad', s >= 60);
  };
  [meta, kw, seo].forEach((el) => el.addEventListener('input', refresh));
  addKw.addEventListener('click', () => {
    const k = kw.value.trim();
    let rest = meta.value.trim();
    if (rest && !/^\p{Lu}\p{Lu}/u.test(rest)) rest = rest.charAt(0).toLowerCase() + rest.slice(1);
    let out = k.charAt(0).toUpperCase() + k.slice(1) + (rest ? ' : ' + rest : '');
    if (out.length > 240) { out = out.slice(0, 241); out = out.slice(0, out.lastIndexOf(' ')).replace(/[ ,;:–—-]+$/, ''); }
    meta.value = out;
    refresh();
  });
  refresh();

  // AI helpers (JSON endpoints on this page).
  async function askAi(params) {
    const body = new URLSearchParams({ csrf, id: articleId, ...params });
    const r = await fetch(location.pathname, { method: 'POST', body, credentials: 'same-origin' });
    const j = await r.json().catch(() => ({ error: 'The server did not answer — please try again.' }));
    if (j.error) throw new Error(j.error);
    return j;
  }
  $('ai-seo').addEventListener('click', async () => {
    const btn = $('ai-seo'), st = $('ai-seo-status');
    btn.disabled = true; st.textContent = 'The AI is reading the article' + ($('fi-url').value ? ' and looking at the featured image' : '') + '… (up to a minute)';
    try {
      const r = await askAi({ action: 'ai_seo', title: $('title').value, focus_keyword: kw.value, markdown: $('md').value, fi_url: $('fi-url').value });
      seo.value = r.seoTitle; meta.value = r.metaDescription;
      if (r.featuredAlt) $('fi-alt').value = r.featuredAlt;
      refresh();
      [seo, meta].concat(r.featuredAlt ? [$('fi-alt')] : []).forEach((el) => { el.style.background = '#e6fbf1'; setTimeout(() => { el.style.background = ''; }, 2500); });
      st.textContent = 'Filled in green: check and edit, then Save. Related searches: ' + r.relatedKeywords.join(' · ');
    } catch (err) { st.textContent = err.message; }
    btn.disabled = false;
  });

  // One-time signed upload: ask the server for a link, then send the file straight to it.
  async function upload(file, kind) {
    if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) throw new Error('Use a JPG, PNG or WebP image.');
    if (file.size > 5 * 1024 * 1024) throw new Error('The image must be 5 MB or smaller.');
    const body = new URLSearchParams({ action: 'upload_link', csrf, id: articleId, kind, mime: file.type, size: file.size });
    const link = await fetch(location.pathname, { method: 'POST', body, credentials: 'same-origin' }).then((r) => r.json());
    if (link.error) throw new Error(link.error);
    const res = await fetch(link.uploadUrl, { method: 'PUT', body: file, headers: { 'Content-Type': file.type }, credentials: 'same-origin' }).then((r) => r.json());
    if (res.error) throw new Error(res.error);
    return res;
  }

  $('fi-file').addEventListener('change', async (e) => {
    const file = e.target.files[0];
    if (!file) return;
    $('fi-status').textContent = 'Uploading…';
    try {
      const r = await upload(file, 'featured');
      $('fi-url').value = r.url; $('fi-width').value = r.width; $('fi-height').value = r.height;
      $('fi-preview').innerHTML = '';
      const img = document.createElement('img'); img.src = r.url; img.width = 260; img.alt = '';
      $('fi-preview').appendChild(img);
      const size = r.width + ' × ' + r.height + ' px' + (r.width < 1200 || r.height < 630 ? ' — smaller than the recommended 1200 × 630' : '');
      $('fi-alt').required = true;
      $('fi-status').textContent = size + '. The AI is describing the photo for the alt text…';
      try {
        const d = await askAi({ action: 'ai_alt', url: r.url, context: 'Featured image of the article: ' + $('title').value });
        $('fi-alt').value = d.alt;
        $('fi-status').textContent = size + '. Alt text written by the AI — check it, then Save.';
      } catch (err) { $('fi-status').textContent = size + '. Add alt text, then Save. (' + err.message + ')'; $('fi-alt').focus(); }
    } catch (err) { $('fi-status').textContent = err.message; }
    e.target.value = '';
  });
  $('fi-remove')?.addEventListener('click', () => {
    $('fi-url').value = ''; $('fi-alt').value = ''; $('fi-preview').innerHTML = '<div class="art-thumb" style="width:260px;height:136px">No featured image yet</div>';
    $('fi-status').textContent = 'Removed — save to confirm.';
  });

  // Section images: inserted as a Markdown line at the end of the chosen section.
  $('si-add').addEventListener('click', async () => {
    const file = $('si-file').files[0], alt = $('si-alt').value.trim(), cap = $('si-caption').value.trim().replace(/"/g, '”');
    if (!file) { $('si-status').textContent = 'Choose an image file.'; return; }
    $('si-status').textContent = 'Uploading…';
    try {
      const r = await upload(file, 'section');
      let altText = alt, capText = cap;
      if (!altText) {
        $('si-status').textContent = 'The AI is describing the photo…';
        try {
          const d = await askAi({ action: 'ai_alt', url: r.url, context: $('si-section').value });
          altText = d.alt; if (!capText) capText = d.caption.replace(/"/g, '”');
        } catch (err) { throw new Error('Uploaded, but the alt text could not be written (' + err.message + ') — type it and try again.'); }
      }
      const line = '![' + altText.replace(/[\[\]]/g, '') + '](' + r.url + (capText ? ' "' + capText + '"' : '') + ')';
      const md = $('md').value.split('\n');
      const heading = '## ' + $('si-section').value;
      let at = md.findIndex((l) => l.trim() === heading);
      if (at < 0) throw new Error('That section is no longer in the text.');
      let end = md.length;
      for (let i = at + 1; i < md.length; i++) if (/^##\s/.test(md[i]) || /^\[[^\]]+\]\(\/[^)]*\)\s*$/.test(md[i])) { end = i; break; }
      while (end > at + 1 && md[end - 1].trim() === '') end--;
      md.splice(end, 0, '', line);
      $('md').value = md.join('\n');
      $('si-status').textContent = 'Image inserted with alt “' + altText + '” — check it in the text, then Save.';
      $('si-file').value = ''; $('si-alt').value = ''; $('si-caption').value = '';
    } catch (err) { $('si-status').textContent = err.message; }
  });

  // External sources
  let sources = JSON.parse($('sources-json').value || '[]');
  const render = () => {
    $('sources-json').value = JSON.stringify(sources);
    const list = $('src-list');
    list.innerHTML = sources.length ? '' : '<li class="muted">No sources.</li>';
    sources.forEach((s, i) => {
      const li = document.createElement('li');
      const a = document.createElement('a'); a.href = s.url; a.target = '_blank'; a.rel = 'noopener'; a.textContent = s.title + ' — ' + s.url;
      const b = document.createElement('button'); b.type = 'button'; b.className = 'btn btn-ghost btn-sm'; b.textContent = 'Remove';
      b.onclick = () => { sources.splice(i, 1); render(); };
      li.append(a, b); list.appendChild(li);
    });
  };
  $('src-add').addEventListener('click', () => {
    const t = $('src-name').value.trim(), u = $('src-url').value.trim();
    $('src-error').textContent = '';
    if (!t) { $('src-error').textContent = 'Give the source a name.'; return; }
    if (!/^https:\/\/[^\s/]+\.[^\s]+$/.test(u)) { $('src-error').textContent = 'The link must start with https://.'; return; }
    sources.push({ title: t, url: u }); $('src-name').value = ''; $('src-url').value = ''; render();
  });
  render();
})();
</script>
<?php
    admin_footer();
    exit;
}

/* ---------- Preview ---------- */
if ($previewId) {
    seo_header('articles', 'Preview', 'How the article will look on the blog.');
    try {
        $a = seo_article($previewId);
    } catch (Throwable $e) {
        echo '<div class="alert">' . h(admin_error($e, 'load the article')) . '</div>';
        admin_footer();
        exit;
    }
    $md = (string) ($a['content']['markdown'] ?? '');
    $fi = $a['content']['featuredImage'] ?? null;
    $counts = seo_link_counts($md);
    ?>
<p><a href="<?= $self ?>#article-<?= $a['id'] ?>">← Back to articles</a> · <?= $badge($a['status']) ?></p>
<div class="toolbar">
  <?php if (!in_array($a['status'], ['publishing', 'published'], true)): ?><a class="btn btn-primary" href="<?= $self ?>?edit=<?= $a['id'] ?>">Edit / add images</a><?php endif; ?>
  <?php if ($a['status'] === 'published'): ?><a class="btn btn-ghost" href="<?= h($a['published_url']) ?>" target="_blank" rel="noopener">Open on the blog ↗</a><?php endif; ?>
</div>
<section class="card">
  <dl class="kv">
    <dt>Focus keyword</dt><dd><?= h($a['focus_keyword']) ?></dd>
    <dt>SEO title</dt><dd><?= h($a['seo_title'] ?: '— (uses the title)') ?> <?php if ($a['seo_title']): ?><span class="muted small">(<?= mb_strlen($a['seo_title']) ?> characters)</span><?php endif; ?></dd>
    <dt>Meta description</dt><dd><?= h($a['meta_description']) ?> <span class="muted small">(<?= mb_strlen((string) $a['meta_description']) ?> characters)</span></dd>
    <dt>URL</dt><dd>/blog/<?= h($a['slug']) ?>/</dd>
    <dt>Links &amp; images</dt><dd><?= $counts['internal'] ?> internal links · <?= $counts['external'] ?> external links · <?= $counts['images'] ?> section image(s) · <?= (int) $a['word_count'] ?> words</dd>
  </dl>
</section>
<?php if ($fi): ?>
  <img class="preview-featured" src="<?= h($fi['url']) ?>" alt="<?= h($fi['alt'] ?? '') ?>" width="<?= (int) ($fi['width'] ?? 0) ?>" height="<?= (int) ($fi['height'] ?? 0) ?>">
<?php else: ?>
  <div class="no-image">No featured image yet</div>
<?php endif; ?>
<article class="card preview-article">
  <h1><?= h($a['title']) ?></h1>
  <?= seo_article_body_html($a) ?>
</article>
<?php
    admin_footer();
    exit;
}

/* ---------- History ---------- */
if ($historyId) {
    seo_header('articles', 'History', 'Saved versions of this article. Restoring one makes it a draft again.');
    if ($flashMsg) echo '<div class="' . ($flashMsg[0] === 'success' ? 'success' : 'alert') . '">' . h($flashMsg[1]) . '</div>';
    try {
        $a = seo_article($historyId);
        $versions = seo_article_versions($historyId);
    } catch (Throwable $e) {
        echo '<div class="alert">' . h(admin_error($e, 'load the history')) . '</div>';
        admin_footer();
        exit;
    }
    ?>
<p><a href="<?= $self ?>#article-<?= $a['id'] ?>">← Back to articles</a> · <strong><?= h($a['title']) ?></strong> · <?= $badge($a['status']) ?></p>
<section class="card">
  <?php if (!$versions): ?><p class="muted">No saved versions yet. A version is kept every time the article is edited, rewritten or regenerated.</p><?php endif; ?>
  <div class="table-wrap"><table>
    <thead><tr><th>Saved</th><th>Reason</th><th>Title</th><th>Status then</th><th class="num">Words</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($versions as $v): $s = $v['snapshot']; ?>
      <tr>
        <td class="nowrap"><?= h(date('d/m/Y H:i', strtotime($v['created_at']))) ?></td>
        <td><?= h($v['reason']) ?></td>
        <td><?= h($s['title'] ?? '') ?>
          <details><summary>Show content</summary><pre style="white-space:pre-wrap;font-size:12px;max-height:400px;overflow:auto"><?= h($s['content']['markdown'] ?? '') ?></pre></details></td>
        <td><?= $badge((string) ($s['status'] ?? 'draft')) ?></td>
        <td class="num"><?= (int) ($s['word_count'] ?? 0) ?></td>
        <td><?php if (!in_array($a['status'], ['publishing', 'published'], true)): ?>
          <form method="post" onsubmit="return confirm('Restore this version as a draft? The current text is kept in History.')"><?= $csrf ?>
            <input type="hidden" name="action" value="restore"><input type="hidden" name="id" value="<?= $a['id'] ?>"><input type="hidden" name="version_id" value="<?= (int) $v['id'] ?>">
            <button class="btn btn-ghost btn-sm">Restore</button></form><?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</section>
<?php
    admin_footer();
    exit;
}

/* ---------- Main: generator + list ---------- */
$loadError = '';
$options = $articles = $counts = [];
$models = [];
try {
    $options = seo_generator_options();
    $articles = seo_list_articles($filter);
    $counts = seo_article_status_counts();
    $models = seo_article_models();
} catch (Throwable $e) {
    $loadError = admin_error($e, 'load the articles');
}
$prefTopic = isset($_GET['topic']) ? (string) (int) $_GET['topic'] : '';
$form = $genForm ?? ['campaign_id' => '', 'offer_id' => '', 'keyword' => '', 'language' => 'fr', 'target_words' => '1000', 'tone' => 'professional',
    'model' => $models ? seo_default_model() : 'gpt-4o-mini', 'topic_id' => $prefTopic];
if ($prefTopic !== '' && !$genForm) {
    foreach ($options as $c) foreach ($c['topics'] as $t) if ($t['id'] === $prefTopic) {
        $form = array_merge($form, ['campaign_id' => $c['id'], 'offer_id' => $t['offer_id'], 'keyword' => $t['keyword'] ?: '', 'language' => $c['language']]);
    }
}
$total = array_sum($counts);

seo_header('articles', 'Articles & Approval', 'Generate, edit, add pictures and sources, review and publish.');
?>
<p class="top-line">Every article needs a picture and human approval before it publishes.</p>
<?php if ($flashMsg): ?><div class="<?= $flashMsg[0] === 'success' ? 'success' : 'alert' ?>"><?= h($flashMsg[1]) ?></div><?php endif; ?>
<?php if ($loadError): ?><div class="alert"><?= h($loadError) ?></div><?php endif; ?>

<section class="card" id="generator">
  <div class="card-head"><h2>Generate an article</h2><span class="muted small">Takes 30 seconds to 2 minutes. The cost is recorded in API Budget.</span></div>
  <?php if (!$options && !$loadError): ?>
    <p class="muted">Create a campaign (planning or active) with at least one enabled offer in <a href="/admin/seo-campaigns">Campaigns</a> first.</p>
  <?php else: ?>
  <form method="post" id="gen-form">
    <?= $csrf ?><input type="hidden" name="action" value="generate">
    <div class="gen-grid">
      <label class="field span-2">Campaign
        <select name="campaign_id" id="g-campaign" required>
          <option value="">Choose…</option>
          <?php foreach ($options as $c): ?><option value="<?= h($c['id']) ?>" data-lang="<?= h($c['language']) ?>" <?= $form['campaign_id'] === $c['id'] ? 'selected' : '' ?>><?= h($c['name']) ?> (<?= $c['status'] === 'draft' ? 'planning' : 'active' ?>)</option><?php endforeach; ?>
        </select></label>
      <label class="field span-2">Offer
        <select name="offer_id" id="g-offer" required>
          <option value="">Choose…</option>
          <?php foreach ($options as $c) foreach ($c['offers'] as $o): ?><option value="<?= h($o['id']) ?>" data-campaign="<?= h($c['id']) ?>" <?= $form['campaign_id'] === $c['id'] && $form['offer_id'] === $o['id'] ? 'selected' : '' ?>><?= h($o['name']) ?></option><?php endforeach; ?>
        </select></label>
      <label class="field span-2">Topic <span class="muted small">(optional — fills the offer and keyword)</span>
        <select name="topic_id" id="g-topic">
          <option value="">None</option>
          <?php foreach ($options as $c) foreach ($c['topics'] as $t): ?><option value="<?= h($t['id']) ?>" data-campaign="<?= h($c['id']) ?>" data-offer="<?= h($t['offer_id']) ?>" data-keyword="<?= h($t['keyword']) ?>" <?= (string) $form['topic_id'] === $t['id'] ? 'selected' : '' ?>><?= h(date('d/m', strtotime($t['planned_for']))) ?> · <?= h($t['title']) ?><?= $t['status'] === 'proposed' ? ' (proposed)' : '' ?></option><?php endforeach; ?>
        </select></label>
      <label class="field span-2">Focus keyword <input type="text" name="keyword" id="g-keyword" value="<?= h($form['keyword']) ?>" maxlength="80" required placeholder="e.g. entretien climatisation Nice"></label>
      <label class="field">Language <select name="language" id="g-lang"><?php foreach (SEO_LANGUAGES as $k => $l): ?><option value="<?= $k ?>" <?= $form['language'] === $k ? 'selected' : '' ?>><?= h($l) ?></option><?php endforeach; ?></select></label>
      <label class="field">Target length <select name="target_words"><?php foreach (SEO_TARGET_LENGTHS as $n): ?><option value="<?= $n ?>" <?= (string) $form['target_words'] === (string) $n ? 'selected' : '' ?>><?= $n ?> words</option><?php endforeach; ?></select></label>
      <label class="field">Tone <select name="tone"><?php foreach (SEO_TONES as $k => $l): ?><option value="<?= $k ?>" <?= $form['tone'] === $k ? 'selected' : '' ?>><?= h($l) ?></option><?php endforeach; ?></select></label>
      <label class="field">AI model <select name="model"><?php foreach ($models as $m): ?><option <?= $form['model'] === $m ? 'selected' : '' ?>><?= h($m) ?></option><?php endforeach; ?></select></label>
    </div>
    <div class="form-actions"><button class="btn btn-primary" id="g-submit">Generate article</button> <span class="muted small" id="g-wait" hidden>Writing the article and checking its sources… please keep this page open.</span></div>
  </form>
  <?php endif; ?>
  <?php if ($genResult): ?>
    <div class="gen-result">
      <h3><?= h($genResult['title']) ?></h3>
      <div class="art-meta"><span><?= (int) $genResult['words'] ?> words</span><span>Model: <?= h($genResult['model']) ?></span><span>Cost recorded: $<?= number_format((float) $genResult['cost'], 4) ?></span>
        <a href="<?= $self ?>?preview=<?= (int) $genResult['id'] ?>">Preview</a> <a href="<?= $self ?>?edit=<?= (int) $genResult['id'] ?>">Edit / add images</a></div>
      <?php if ($genResult['notes']): ?><ul class="notes"><?php foreach ($genResult['notes'] as $n): ?><li><?= h($n) ?></li><?php endforeach; ?></ul><?php else: ?><p class="small">No quality notes.</p><?php endif; ?>
    </div>
  <?php endif; ?>
</section>

<section class="card" id="list">
  <div class="card-head"><h2>Articles</h2></div>
  <nav class="tabs" style="margin:0 0 14px">
    <a href="<?= $self ?>#list" class="<?= $filter === '' ? 'active' : '' ?>">All<span><?= $total ?></span></a>
    <?php foreach (SEO_ARTICLE_STATUSES as $k => $l): if (empty($counts[$k])) continue; ?>
      <a href="<?= $self ?>?status=<?= $k ?>#list" class="<?= $filter === $k ? 'active' : '' ?>"><?= h($l) ?><span><?= (int) $counts[$k] ?></span></a>
    <?php endforeach; ?>
  </nav>
  <?php if (!$articles): ?><p class="muted">No articles<?= $filter !== '' ? ' with this status' : ' yet — generate the first one above' ?>.</p><?php endif; ?>
  <div class="art-list">
  <?php foreach ($articles as $a):
      $s = $a['status'];
      $fi = $a['content']['featuredImage'] ?? null;
      $rewritable = in_array($s, ['draft', 'generated', 'review', 'rejected', 'failed'], true) ? seo_rewritable_sections((string) ($a['content']['markdown'] ?? ''), $a['language']) : [];
      $hidden = $csrf . '<input type="hidden" name="id" value="' . $a['id'] . '"><input type="hidden" name="return" value="' . h($returnQuery) . '">';
      $btn = static fn (string $action, string $label, string $cls = 'btn-ghost', string $confirm = '') => '<form method="post"' . ($confirm ? ' onsubmit="return confirm(\'' . h($confirm) . '\')"' : '') . '>' . $hidden
          . '<input type="hidden" name="action" value="' . $action . '"><button class="btn btn-sm ' . $cls . '"' . (in_array($action, ['regenerate', 'rewrite'], true) ? ' data-slow' : '') . '>' . $label . '</button></form>';
  ?>
    <article class="art-card" id="article-<?= $a['id'] ?>">
      <?php if ($fi): ?><img class="art-thumb" src="<?= h($fi['url']) ?>" alt="<?= h($fi['alt'] ?? '') ?>" loading="lazy"><?php else: ?><div class="art-thumb">No picture</div><?php endif; ?>
      <div>
        <h3><?= h($a['title']) ?></h3>
        <div class="art-meta">
          <?= $badge($s) ?>
          <span>Offer: <?= h($a['offer_name'] ?? '—') ?></span>
          <span>Quality: <?php $qs = (int) $a['quality_score']; ?><span class="score <?= score_class($qs) ?>"><span style="width:<?= $qs ?>%"></span></span><?= $a['quality_score'] === null ? '—' : $qs . '%' ?></span>
          <span><?= (int) $a['word_count'] ?> words</span>
          <?php if ($a['offer_path']): ?><a href="<?= h(SEO_SITE_URL . $a['offer_path']) ?>" target="_blank" rel="noopener"><?= h(SEO_SITE_URL . $a['offer_path']) ?></a><?php endif; ?>
          <?php if ($s === 'scheduled'): ?><span>📅 <?= h($localTime($a['scheduled_for'], $a['time_zone'])) ?> (<?= h($a['time_zone']) ?>)</span><?php endif; ?>
          <?php if ($s === 'published'): ?><a href="<?= h($a['published_url']) ?>" target="_blank" rel="noopener">Live ↗</a><?php endif; ?>
        </div>
        <?php if ($a['quality_notes'] && $s !== 'published'): ?><ul class="notes"><?php foreach ($a['quality_notes'] as $n): ?><li><?= h($n) ?></li><?php endforeach; ?></ul><?php endif; ?>
        <?php if ($a['last_error'] && in_array($s, ['failed', 'scheduled'], true)): ?><p class="error-line">Last error (attempt <?= (int) $a['publish_attempts'] ?> of 3): <?= h($a['last_error']) ?></p><?php endif; ?>
        <?php if ($s === 'rejected' && $a['rejected_reason']): ?><p class="error-line">Rejected: <?= h($a['rejected_reason']) ?></p><?php endif; ?>
        <div class="art-actions">
          <a class="btn btn-ghost btn-sm" href="<?= $self ?>?preview=<?= $a['id'] ?>">Preview</a>
          <?php if (!in_array($s, ['publishing', 'published'], true)): ?><a class="btn btn-ghost btn-sm" href="<?= $self ?>?edit=<?= $a['id'] ?>">Edit</a><?php endif; ?>
          <?php if (in_array($s, ['draft', 'generated', 'rejected'], true)) echo $btn('submit', 'Submit for review'); ?>
          <?php if ($s === 'review') echo $btn('approve', 'Approve', 'btn-ok'); ?>
          <?php if (in_array($s, ['draft', 'generated', 'review', 'approved', 'scheduled', 'failed'], true)): ?>
            <details><summary class="btn btn-sm btn-danger">Reject</summary>
              <form method="post"><?= $hidden ?><input type="hidden" name="action" value="reject"><input type="text" name="reason" placeholder="Reason (optional)" maxlength="500"><button class="btn btn-sm btn-danger">Reject article</button></form>
            </details>
          <?php endif; ?>
          <?php if (in_array($s, ['draft', 'generated', 'review', 'rejected', 'failed'], true) && $a['generation']) echo $btn('regenerate', 'Regenerate', 'btn-ghost', 'Regenerate the whole article with the AI? The current text is kept in History and the featured image is kept.'); ?>
          <?php if ($rewritable): ?>
            <details><summary class="btn btn-sm btn-ghost">Rewrite this section only</summary>
              <form method="post"><?= $hidden ?><input type="hidden" name="action" value="rewrite">
                <select name="heading"><?php foreach ($rewritable as $hd): ?><option><?= h($hd) ?></option><?php endforeach; ?></select>
                <button class="btn btn-sm btn-primary" data-slow>Rewrite with AI</button></form>
              <span class="muted small">The FAQ, conclusion, sources and offer link are protected.</span>
            </details>
          <?php endif; ?>
          <?php if (in_array($s, ['approved', 'scheduled', 'failed'], true)): ?>
            <?= $btn('publish', 'Publish now', 'btn-ok', 'Publish this article on the blog now?') ?>
            <details><summary class="btn btn-sm btn-ghost">Schedule</summary>
              <form method="post"><?= $hidden ?><input type="hidden" name="action" value="schedule">
                <input type="datetime-local" name="when" value="<?= $s === 'scheduled' ? h((new DateTimeImmutable($a['scheduled_for']))->setTimezone(new DateTimeZone($a['time_zone']))->format('Y-m-d\TH:i')) : '' ?>">
                <button class="btn btn-sm btn-primary">Save time</button></form>
              <span class="muted small">Leave empty for the next free slot (<?= h($a['time_zone']) ?>).</span>
            </details>
          <?php endif; ?>
          <?php if ($s === 'published') echo $btn('unpublish', 'Unpublish', 'btn-danger', 'Remove this article from the blog?'); ?>
          <a class="btn btn-ghost btn-sm" href="<?= $self ?>?history=<?= $a['id'] ?>">History</a>
        </div>
      </div>
    </article>
  <?php endforeach; ?>
  </div>
</section>
<script>
(() => {
  const camp = document.getElementById('g-campaign');
  if (camp) {
    const offer = document.getElementById('g-offer'), topic = document.getElementById('g-topic'), kw = document.getElementById('g-keyword'), lang = document.getElementById('g-lang');
    const filter = (first) => {
      for (const sel of [offer, topic]) {
        for (const o of sel.options) if (o.dataset.campaign) o.hidden = o.dataset.campaign !== camp.value;
        if (sel.selectedOptions[0]?.hidden) sel.value = '';
      }
      const l = camp.selectedOptions[0]?.dataset.lang;
      if (l && !first) lang.value = l;
    };
    camp.addEventListener('change', () => filter(false));
    topic.addEventListener('change', () => {
      const o = topic.selectedOptions[0];
      if (!o?.dataset.campaign) return;
      offer.value = o.dataset.offer;
      if (o.dataset.keyword) kw.value = o.dataset.keyword;
    });
    filter(true);
    document.getElementById('gen-form').addEventListener('submit', () => {
      document.getElementById('g-submit').disabled = true;
      document.getElementById('g-submit').textContent = 'Generating…';
      document.getElementById('g-wait').hidden = false;
    });
  }
  document.querySelectorAll('[data-slow]').forEach((b) => b.form.addEventListener('submit', () => { setTimeout(() => { b.disabled = true; b.textContent = 'Working… (up to 2 minutes)'; }); }));
})();
</script>
<?php admin_footer();
