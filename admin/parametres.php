<?php
declare(strict_types=1);
require __DIR__ . '/_inc/auth.php';
require __DIR__ . '/_inc/seo.php';
require __DIR__ . '/_inc/gsc.php';
require __DIR__ . '/_inc/layout.php';
require_login();

$notice = ['db' => '', 'gsc' => ''];
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
