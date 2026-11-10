<?php
declare(strict_types=1);
require __DIR__ . '/_inc/auth.php';
require __DIR__ . '/_inc/seo.php';
require __DIR__ . '/_inc/gsc.php';
require __DIR__ . '/_inc/ai.php';
require __DIR__ . '/_inc/layout.php';
require_login();

$notice = ['db' => '', 'gsc' => '', 'ai' => ''];
$aiTest = $_SESSION['ai_test'] ?? null;
unset($_SESSION['ai_test']);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['save_ai', 'test_ai'], true)) {
    if (!csrf_valid($_POST['csrf'] ?? null)) {
        $notice['ai'] = 'Session expirée, veuillez réessayer.';
    } elseif ($_POST['action'] === 'save_ai') {
        try {
            ai_save_settings((string) ($_POST['provider'] ?? ''), (string) ($_POST['api_key'] ?? ''), trim((string) ($_POST['model'] ?? '')),
                trim((string) ($_POST['input_price'] ?? '')), trim((string) ($_POST['output_price'] ?? '')));
            header('Location: /admin/parametres.php?ok=ai#moteur-ia');
            exit;
        } catch (Throwable $e) {
            $notice['ai'] = admin_error($e, 'save the AI settings');
        }
    } else {
        // One real, tiny structured call through the shared helper (budget, schema, cost tracking).
        try {
            $t = microtime(true);
            $r = ai_generate('connection_test', [
                ['role' => 'system', 'content' => 'You are a connection test. Answer in French.'],
                ['role' => 'user', 'content' => 'Confirme que tu fonctionnes en une phrase courte.'],
            ], ['type' => 'object', 'props' => ['ok' => ['type' => 'boolean'], 'message' => ['type' => 'string', 'min' => 2, 'max' => 200]]],
                'connection_test', 200);
            $_SESSION['ai_test'] = ['success', sprintf('The AI answered in %.1f s: “%s” (cost $%s).', microtime(true) - $t, $r['data']['message'], number_format($r['cost'], 6))];
        } catch (AiError $e) {
            $_SESSION['ai_test'] = ['error', $e->getMessage()];
        } catch (Throwable $e) {
            $_SESSION['ai_test'] = ['error', admin_error($e, 'test the AI connection')];
        }
        header('Location: /admin/parametres.php#moteur-ia');
        exit;
    }
}
$saved = $_GET['ok'] ?? '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if (!csrf_valid($_POST['csrf'] ?? null)) {
        $notice[$action === 'save_gsc' ? 'gsc' : 'db'] = 'Session expirée, veuillez réessayer.';
    } elseif ($action === 'save_db') {
        $url = trim((string) ($_POST['database_url'] ?? ''));
        if (!db_valid_url($url)) {
            $notice['db'] = 'Ce n’est pas une adresse de connexion Neon valide (postgresql://…neon.tech/…).';
        } else {
            try {
                db_save_url($url);
                header('Location: /admin/parametres.php?ok=db#base-de-donnees');
                exit;
            } catch (DbError $e) {
                $notice['db'] = $e->getMessage();
            }
        }
    } elseif ($action === 'save_gsc') {
        $key = (string) ($_POST['key_json'] ?? '');
        if (!empty($_FILES['key_file']['tmp_name']) && is_uploaded_file($_FILES['key_file']['tmp_name'])) {
            $key = (string) file_get_contents($_FILES['key_file']['tmp_name'], false, null, 0, 20000);
        }
        try {
            gsc_save($key, (string) ($_POST['site'] ?? ''));
            header('Location: /admin/parametres.php?ok=gsc#search-console');
            exit;
        } catch (GscError $e) {
            $notice['gsc'] = $e->getMessage();
        }
    }
}

// Live checks
$db = ['ok' => false, 'msg' => 'Non configurée'];
if (db_configured()) {
    try {
        $t = microtime(true);
        $v = db_query("select split_part(version(), ' ', 2) as v");
        $db = ['ok' => true, 'msg' => 'PostgreSQL ' . ($v[0]['v'] ?? '') . ' · ' . (int) round((microtime(true) - $t) * 1000) . ' ms'];
    } catch (DbError $e) {
        $db = ['ok' => false, 'msg' => $e->getMessage()];
    }
}
$gscCfg = gsc_config();
$gsc = ['ok' => false, 'msg' => 'Non connecté'];
if ($gscCfg) {
    try {
        $rows = gsc_query(['date'], date('Y-m-d', strtotime('-9 days')), date('Y-m-d', strtotime('-2 days')), 10);
        $gsc = ['ok' => true, 'msg' => count($rows) . ' jour(s) de données sur 7 · ' . $gscCfg['site']];
    } catch (Throwable $e) {
        $gsc = ['ok' => false, 'msg' => $e->getMessage()];
    }
}

admin_header('Paramètres', 'parametres');
?>
<h1>Paramètres</h1>
<p class="muted">Connexions de l’espace administrateur. Les clés sont enregistrées sur le serveur, hors du dossier public, et ne sont jamais affichées ni envoyées sur GitHub.</p>

<?php
$ai = ai_config();
$aiReady = $ai['api_key'] && $ai['input_price'] !== null && $ai['output_price'] !== null;
$aiMonth = null;
try {
    $aiMonth = db_query("select requests, failed_requests, invalid_outputs, input_tokens, output_tokens, recorded_usd
                           from seo_monthly_usage where month = date_trunc('month', now() at time zone 'Europe/Paris')::date")[0] ?? null;
} catch (Throwable $e) {
    error_log('ai month usage: ' . $e->getMessage());
}
?>
<section class="card" id="moteur-ia">
  <div class="card-head">
    <h2>AI engine</h2>
    <span class="badge <?= $aiReady ? 'ok' : 'muted-badge' ?>"><?= $aiReady ? 'Configured' : 'Not configured' ?></span>
  </div>
  <?php if ($saved === 'ai'): ?><div class="success">AI settings saved. Use “Test connection” to check them.</div><?php endif; ?>
  <?php if ($notice['ai']): ?><div class="alert"><?= h($notice['ai']) ?></div><?php endif; ?>
  <?php if ($aiTest): ?><div class="<?= $aiTest[0] === 'success' ? 'success' : 'alert' ?>"><?= h($aiTest[1]) ?></div><?php endif; ?>
  <p class="muted small">Every AI feature goes through one server helper: it checks the monthly API budget first, asks for strict JSON,
    records the real cost, and rejects unusable answers. The key stays on the server (outside the public folder) and is never shown again.</p>
  <?php if ($aiReady): ?>
    <p><?= h(AI_PROVIDERS[$ai['provider']]['label'] ?? $ai['provider']) ?> · <code><?= h($ai['model']) ?></code> · key ending in <code>…<?= h(substr((string) $ai['api_key'], -4)) ?></code>
      · $<?= h($ai['input_price']) ?> / $<?= h($ai['output_price']) ?> per 1M tokens (input / output)</p>
    <?php if ($aiMonth): ?><p class="muted small">This month: <?= (int) $aiMonth['requests'] ?> answered request(s), <?= (int) $aiMonth['failed_requests'] ?> failed,
      <?= (int) $aiMonth['invalid_outputs'] ?> unusable answer(s), <?= number_format((int) $aiMonth['input_tokens'] + (int) $aiMonth['output_tokens'], 0, '.', ' ') ?> tokens, $<?= h(number_format((float) $aiMonth['recorded_usd'], 4)) ?>.</p><?php endif; ?>
    <form method="post" action="/admin/parametres.php#moteur-ia" class="inline-form">
      <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="action" value="test_ai">
      <button type="submit" class="btn btn-ghost">Test connection</button>
    </form>
  <?php endif; ?>
  <details <?= $aiReady ? '' : 'open' ?>>
    <summary><?= $aiReady ? 'Change AI settings' : 'Connect an AI provider (free with Google Gemini)' ?></summary>
    <ol class="steps">
      <li>Recommended free option: open <a href="https://aistudio.google.com/apikey" target="_blank" rel="noopener">Google AI Studio → API keys</a>, sign in with Google and click <strong>Create API key</strong> (no card needed).</li>
      <li>Paste the key below, keep the model and prices (0 = free tier), and save. Then click <strong>Test connection</strong>.</li>
    </ol>
    <form method="post" action="/admin/parametres.php#moteur-ia" class="stack-form" id="ai-form">
      <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="action" value="save_ai">
      <label>Provider
        <select name="provider" id="ai-provider">
          <?php foreach (AI_PROVIDERS as $k => $p): ?>
            <option value="<?= h($k) ?>" data-model="<?= h($p['model']) ?>" data-in="<?= h($p['input']) ?>" data-out="<?= h($p['output']) ?>" <?= $ai['provider'] === $k ? 'selected' : '' ?>><?= h($p['label']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>API key
        <input type="password" name="api_key" autocomplete="off" placeholder="<?= $ai['api_key'] ? 'Saved — leave empty to keep it' : 'Paste your API key' ?>">
      </label>
      <label>Model <input type="text" name="model" id="ai-model" value="<?= h($ai['model']) ?>"></label>
      <div class="form-grid">
        <label>Input price (USD per 1M tokens) <input type="text" name="input_price" id="ai-in" inputmode="decimal" value="<?= h($ai['input_price'] ?? AI_PROVIDERS[$ai['provider']]['input'] ?? '0') ?>"></label>
        <label>Output price (USD per 1M tokens) <input type="text" name="output_price" id="ai-out" inputmode="decimal" value="<?= h($ai['output_price'] ?? AI_PROVIDERS[$ai['provider']]['output'] ?? '0') ?>"></label>
      </div>
      <button type="submit" class="btn btn-primary">Save AI settings</button>
    </form>
    <script>
      // Changing provider fills in its default model and prices (you can still edit them).
      document.getElementById('ai-provider').addEventListener('change', (e) => {
        const o = e.target.selectedOptions[0];
        document.getElementById('ai-model').value = o.dataset.model;
        document.getElementById('ai-in').value = o.dataset.in;
        document.getElementById('ai-out').value = o.dataset.out;
      });
    </script>
  </details>
</section>

<section class="card" id="search-console">
  <div class="card-head">
    <h2>Google Search Console</h2>
    <span class="badge <?= $gsc['ok'] ? 'ok' : ($gscCfg ? 'warn' : 'muted-badge') ?>"><?= $gsc['ok'] ? 'Connecté' : ($gscCfg ? 'Erreur' : 'Non connecté') ?></span>
  </div>
  <?php if ($saved === 'gsc'): ?><div class="success">Clé enregistrée.</div><?php endif; ?>
  <?php if ($notice['gsc']): ?><div class="alert"><?= h($notice['gsc']) ?></div><?php endif; ?>
  <p class="<?= $gsc['ok'] ? '' : 'muted' ?>"><?= h($gsc['msg']) ?><?= $gscCfg ? ' · compte ' . h($gscCfg['sa']['client_email']) : '' ?></p>

  <details <?= $gscCfg ? '' : 'open' ?>>
    <summary><?= $gscCfg ? 'Remplacer la clé' : 'Comment connecter (10 minutes)' ?></summary>
    <ol class="steps">
      <li>Sur <a href="https://console.cloud.google.com/" target="_blank" rel="noopener">console.cloud.google.com</a> : créez un projet, puis activez <strong>Google Search Console API</strong> (menu API et services → Bibliothèque).</li>
      <li>IAM et administration → <strong>Comptes de service</strong> → Créer (aucun rôle à ajouter) → onglet <strong>Clés</strong> → Ajouter une clé → <strong>JSON</strong>. Un fichier est téléchargé.</li>
      <li>Dans <a href="https://search.google.com/search-console" target="_blank" rel="noopener">Search Console</a> → Paramètres → Utilisateurs et autorisations → <strong>Ajouter</strong> l’e-mail <code>client_email</code> du fichier, autorisation « Restreint ».</li>
      <li>Envoyez le fichier ci-dessous.</li>
    </ol>
    <form method="post" action="/admin/parametres.php#search-console" enctype="multipart/form-data" class="stack-form">
      <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="action" value="save_gsc">
      <label>Fichier de clé JSON <input type="file" name="key_file" accept=".json,application/json"></label>
      <label>… ou collez son contenu <textarea name="key_json" rows="3" placeholder='{"type": "service_account", …}' autocomplete="off"></textarea></label>
      <label>Propriété Search Console
        <input type="text" name="site" value="<?= h($gscCfg['site'] ?? 'sc-domain:climanova-energie.fr') ?>">
        <span class="muted small">« sc-domain:climanova-energie.fr » pour une propriété Domaine, « https://climanova-energie.fr/ » pour un Préfixe d’URL.</span>
      </label>
      <button type="submit" class="btn btn-primary">Enregistrer la clé</button>
    </form>
  </details>
</section>

<section class="card" id="base-de-donnees">
  <div class="card-head">
    <h2>Base de données (Neon)</h2>
    <span class="badge <?= $db['ok'] ? 'ok' : (db_configured() ? 'warn' : 'muted-badge') ?>"><?= $db['ok'] ? 'Connectée' : (db_configured() ? 'Erreur' : 'Non configurée') ?></span>
  </div>
  <?php if ($saved === 'db'): ?><div class="success">Connexion enregistrée.</div><?php endif; ?>
  <?php if ($notice['db']): ?><div class="alert"><?= h($notice['db']) ?></div><?php endif; ?>
  <p class="<?= $db['ok'] ? '' : 'muted' ?>"><?= h($db['msg']) ?></p>
  <p class="muted small">Utilisée pour l’historique des connexions et l’historique quotidien du classement SEO.</p>
  <details <?= db_configured() ? '' : 'open' ?>>
    <summary><?= db_configured() ? 'Remplacer l’adresse de connexion' : 'Connecter la base' ?></summary>
    <form method="post" action="/admin/parametres.php#base-de-donnees" class="db-form">
      <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="action" value="save_db">
      <input type="password" name="database_url" required placeholder="postgresql://…@….neon.tech/neondb?sslmode=require" autocomplete="off">
      <button type="submit" class="btn btn-primary">Enregistrer</button>
    </form>
  </details>
</section>
<?php admin_footer();
