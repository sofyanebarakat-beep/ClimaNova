<?php
// Shared layout for every SEO page: title + one-line subtitle, then the tab bar.
declare(strict_types=1);

require_once __DIR__ . '/layout.php';

const SEO_TABS = [
    'dashboard'  => ['Dashboard', '/admin/seo'],
    'weekly'     => ['Weekly Check', '/admin/seo-weekly-check'],
    'offers'     => ['Offers', '/admin/seo-offers'],
    'campaigns'  => ['Campaigns', '/admin/seo-campaigns'],
    'keywords'   => ['Keywords & Topics', '/admin/seo-keywords-topics'],
    'articles'   => ['Articles & Approval', '/admin/seo-articles'],
    'calendar'   => ['Calendar', '/admin/seo-calendar'],
    'analytics'  => ['Analytics', '/admin/seo-analytics'],
    'budget'     => ['API Budget', '/admin/seo-budget'],
    'logs'       => ['Automation Logs', '/admin/seo-logs'],
];

function seo_header(string $tab, string $title, string $subtitle): void
{
    admin_header($title . ' · SEO', 'seo');
    ?>
<div class="seo-head">
  <h1><?= h($title) ?></h1>
  <p class="muted"><?= h($subtitle) ?></p>
</div>
<nav class="seo-tabs" aria-label="SEO sections">
  <?php foreach (SEO_TABS as $key => [$label, $href]): ?>
    <a href="<?= h($href) ?>" class="<?= $key === $tab ? 'active' : '' ?>" <?= $key === $tab ? 'aria-current="page"' : '' ?>><?= h($label) ?></a>
  <?php endforeach; ?>
</nav>
<?php
}

/** Placeholder body for tabs that later prompts will build. */
function seo_coming_soon(string $what): void
{
    echo '<section class="card empty"><p><strong>Coming in a later step.</strong></p><p class="muted">' . h($what) . '</p></section>';
}
