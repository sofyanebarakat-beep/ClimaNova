<?php
// Public side of SEO articles: image storage, page cache, published-article queries and the article page template.
// Used by the public blog (no admin session) and by the admin (cache clearing, previews).
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/seo_article_logic.php';

const SEO_CACHE_TTL = 300; // public pages read the database at most every 5 minutes

function seo_site_root(): string
{
    return dirname(__DIR__, 2);
}

/** Image "bucket": a folder OUTSIDE public_html (a git deploy can never wipe it), served by /seo-media/<file>. Created if missing. */
function seo_media_dir(): string
{
    $docroot = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
    $dir = ($docroot !== '' ? dirname($docroot) : dirname(__DIR__, 3)) . '/seo-media';
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) throw new RuntimeException("Could not create the image folder $dir");
    return $dir;
}

function seo_media_url(string $file): string
{
    return SEO_SITE_URL . '/seo-media/' . $file;
}

/* ---------- Cache ---------- */

function seo_cache_dir(): string
{
    $d = sys_get_temp_dir() . '/cn_seo_public';
    if (!is_dir($d)) @mkdir($d, 0700, true);
    return $d;
}

function seo_cache_get(string $key): mixed
{
    $f = seo_cache_dir() . '/' . hash('sha256', $key);
    if (!is_file($f) || filemtime($f) < time() - SEO_CACHE_TTL) return null;
    $v = @unserialize((string) file_get_contents($f), ['allowed_classes' => false]);
    return $v === false ? null : $v;
}

function seo_cache_set(string $key, mixed $value): void
{
    @file_put_contents(seo_cache_dir() . '/' . hash('sha256', $key), serialize($value), LOCK_EX);
}

/** Called after every publish/unpublish so the blog, sitemap and RSS show it at once. */
function seo_public_cache_clear(): void
{
    foreach (glob(seo_cache_dir() . '/*') ?: [] as $f) @unlink($f);
}

/* ---------- Published articles ---------- */

function seo_decode_article(array $r): array
{
    $r['content'] = json_decode((string) ($r['content'] ?? '{}'), true) ?: [];
    $r['related_keywords'] = seo_pg_array_parse($r['related_keywords'] ?? null);
    $r['word_count'] = (int) ($r['word_count'] ?? 0);
    return $r;
}

const SEO_PUBLIC_COLUMNS = 'a.id, a.slug, a.title, a.seo_title, a.meta_description, a.focus_keyword, a.related_keywords, a.language,
    a.content, a.word_count, a.published_at, a.updated_at, a.published_url, o.slug as offer_slug, o.name as offer_name, o.path as offer_path';

/** Every published SEO article, newest first (cached). Never throws: the blog must work even if the database does not. */
function seo_published_articles(): array
{
    $cached = seo_cache_get('published');
    if (is_array($cached)) return $cached;
    try {
        $rows = array_map('seo_decode_article', db_query('select ' . SEO_PUBLIC_COLUMNS . ' from seo_articles a left join seo_offers o on o.id = a.offer_id
            where a.status = \'published\' order by a.published_at desc'));
    } catch (Throwable $e) {
        error_log('[seo public] ' . $e->getMessage());
        return [];
    }
    seo_cache_set('published', $rows);
    return $rows;
}

function seo_published_article(string $slug): ?array
{
    if (!seo_valid_slug($slug)) return null;
    foreach (seo_published_articles() as $a) if ($a['slug'] === $slug) return $a;
    return null;
}

/* ---------- Static blog posts (blog/index.html) ---------- */

const SEO_FR_MONTHS = ['janvier' => 1, 'février' => 2, 'fevrier' => 2, 'mars' => 3, 'avril' => 4, 'mai' => 5, 'juin' => 6, 'juillet' => 7,
    'août' => 8, 'aout' => 8, 'septembre' => 9, 'octobre' => 10, 'novembre' => 11, 'décembre' => 12, 'decembre' => 12];

function seo_fr_date_to_ymd(string $s): ?string
{
    if (!preg_match('/(\d{1,2})\s+([\p{L}]+)\.?\s+(\d{4})/u', mb_strtolower($s), $m) || !isset(SEO_FR_MONTHS[$m[2]])) return null;
    return sprintf('%04d-%02d-%02d', (int) $m[3], SEO_FR_MONTHS[$m[2]], (int) $m[1]);
}

function seo_ymd_to_fr(string $ymd): string
{
    $t = strtotime($ymd);
    $months = array_keys(array_filter(SEO_FR_MONTHS, static fn ($v, $k) => !in_array($k, ['fevrier', 'aout', 'decembre'], true), ARRAY_FILTER_USE_BOTH));
    return $t ? date('j', $t) . ' ' . $months[(int) date('n', $t) - 1] . ' ' . date('Y', $t) : '';
}

/** List items of the static blog page: [['html','path','title','excerpt','date','image']] in page order. */
function seo_static_blog_cards(?string $html = null): array
{
    $html ??= (string) @file_get_contents(seo_site_root() . '/blog/index.html');
    preg_match_all('~<div role="listitem" class="w-dyn-item"[^>]*>.*?<div[^>]*class="blog-widget-divider[^"]*"[^>]*></div></div>~s', $html, $m);
    $cards = [];
    foreach ($m[0] as $card) {
        $get = static fn (string $re) => preg_match($re, $card, $x) ? html_entity_decode(trim($x[1]), ENT_QUOTES, 'UTF-8') : '';
        $cards[] = [
            'html' => $card,
            'path' => $get('~<a\b[^>]*\bhref="([^"]+)"~'),
            'title' => $get('~<h2[^>]*>(.*?)</h2>~s'),
            'excerpt' => $get('~<p\b[^>]*class="text-md[^"]*"[^>]*>(.*?)</p>~s'),
            'date' => seo_fr_date_to_ymd($get('~<div\b[^>]*class="text-md[^"]*"[^>]*>([^<]*\d{4}[^<]*)</div>~s')),
            'image' => $get('~<img\b[^>]*\bsrc="([^"]+)"~'),
        ];
    }
    return $cards;
}

/** Existing blog posts that articles may link to: static posts + published SEO articles. [['title','path']] */
function seo_all_blog_posts(): array
{
    $posts = [];
    foreach (seo_static_blog_cards() as $c) if ($c['path'] !== '' && $c['title'] !== '') $posts[] = ['title' => $c['title'], 'path' => seo_norm_internal($c['path'])];
    foreach (seo_published_articles() as $a) $posts[] = ['title' => $a['title'], 'path' => '/blog/' . $a['slug'] . '/'];
    return $posts;
}

/** A static page already lives at /blog/<slug>/. */
function seo_static_blog_exists(string $slug): bool
{
    return $slug !== '' && is_dir(seo_site_root() . '/blog/' . basename($slug));
}

/* ---------- Page template ---------- */

function seo_offer_category(?string $offerSlug): string
{
    return match (true) {
        $offerSlug === null => 'climatisation',
        str_starts_with($offerSlug, 'renovation') => 'renovation',
        $offerSlug === 'entretien' => 'depannage',
        in_array($offerSlug, ['climatisation', 'chauffage', 'electricite', 'plomberie'], true) => $offerSlug,
        default => 'climatisation',
    };
}

function seo_read_minutes(int $words): int
{
    return max(1, (int) ceil($words / 200));
}

/** Card in the blog list, same markup as the static cards. */
function seo_article_card_html(array $a): string
{
    $e = static fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
    $fi = $a['content']['featuredImage'] ?? [];
    $date = seo_ymd_to_fr(substr((string) $a['published_at'], 0, 10));
    return '<div role="listitem" class="w-dyn-item" data-category="' . $e(seo_offer_category($a['offer_slug'] ?? null)) . '">'
        . '<a href="/blog/' . $e($a['slug']) . '/" class="blog-widget w-inline-block"><div class="blog-widget-image-block">'
        . '<img src="' . $e($fi['url'] ?? '') . '" loading="lazy" alt="' . $e($fi['alt'] ?? '') . '" class="blog-widget-image" width="' . (int) ($fi['width'] ?? 1200) . '" height="' . (int) ($fi['height'] ?? 630) . '">'
        . '</div><div class="blog-widget-content-block"><div class="blog-widget-content-block-inner"><div class="blog-slide-meta-block">'
        . '<div class="text-md">' . $e($date) . '</div><div class="blog-slide-meta-divider"></div><div class="text-md">' . seo_read_minutes($a['word_count']) . ' min de lecture</div></div>'
        . '<div class="blog-widget-text-block"><h2 class="h4-style">' . $e($a['title']) . '</h2><p class="text-md">' . $e($a['meta_description']) . '</p></div></div>'
        . '<div class="blog-widget-button"><div class="blog-button-link cn-text-dark"><div class="button-text">Lire la suite</div></div></div></div></a>'
        . '<div class="blog-widget-divider"></div></div>';
}

/** Structured data for one article: BlogPosting + BreadcrumbList + FAQPage (from the FAQ section). */
function seo_article_jsonld(array $a): array
{
    $url = SEO_SITE_URL . '/blog/' . $a['slug'] . '/';
    $fi = $a['content']['featuredImage'] ?? [];
    $org = ['@type' => 'Organization', '@id' => SEO_SITE_URL . '/#organization', 'name' => 'ClimaNova Énergie', 'url' => SEO_SITE_URL . '/'];
    $graph = [[
        '@type' => 'BlogPosting', '@id' => $url, 'headline' => mb_substr($a['title'], 0, 110), 'description' => $a['meta_description'],
        'url' => $url, 'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $url], 'inLanguage' => $a['language'],
        'datePublished' => date(DATE_ATOM, strtotime((string) $a['published_at'])),
        'dateModified' => date(DATE_ATOM, strtotime((string) max($a['updated_at'], $a['published_at']))),
        'author' => $org, 'publisher' => $org + ['logo' => ['@type' => 'ImageObject', 'url' => SEO_SITE_URL . '/images/climanova-logo-premium.svg']],
        'keywords' => implode(', ', array_filter(array_merge([$a['focus_keyword']], $a['related_keywords']))),
        'wordCount' => $a['word_count'],
        // GEO: what AI assistants and Google read first — the direct answer, the voice-friendly parts, the cited sources.
        'isPartOf' => ['@type' => 'Blog', '@id' => SEO_SITE_URL . '/blog/#blog', 'name' => 'Blog ClimaNova Énergie', 'url' => SEO_SITE_URL . '/blog/'],
        'speakable' => ['@type' => 'SpeakableSpecification', 'cssSelector' => ['h1', '.cn-direct-answer', '.cn-key-takeaway']],
    ]];
    $md = (string) ($a['content']['markdown'] ?? '');
    $plainMd = static fn (string $m) => trim(html_entity_decode(preg_replace('/\s+/u', ' ', strip_tags(seo_markdown_to_html($m))), ENT_QUOTES, 'UTF-8'));
    foreach (seo_md_parse($md)['sections'] as [$h, $body]) {
        if ($h === seo_labels($a['language'])['quick'] && trim($body) !== '') { $graph[0]['abstract'] = $plainMd($body); break; }
    }
    $sources = seo_md_get_sources($md, $a['language']);
    if ($sources) $graph[0]['citation'] = array_map(static fn ($src) => ['@type' => 'CreativeWork', 'name' => $src['title'], 'url' => $src['url']], $sources);
    $about = [['@type' => 'Thing', 'name' => $a['focus_keyword']]];
    if (!empty($a['offer_path'])) $about[] = ['@type' => 'Service', 'name' => $a['offer_name'], 'url' => SEO_SITE_URL . $a['offer_path'], 'provider' => ['@id' => SEO_SITE_URL . '/#organization'], 'areaServed' => ['@type' => 'City', 'name' => 'Nice']];
    $graph[0]['about'] = $about;
    if (!empty($fi['url'])) {
        $graph[0]['image'] = ['@type' => 'ImageObject', 'url' => $fi['url'], 'width' => (int) ($fi['width'] ?? 0), 'height' => (int) ($fi['height'] ?? 0), 'caption' => $fi['alt'] ?? ''];
        $graph[0]['thumbnailUrl'] = $fi['url'];
    }
    $graph[] = ['@type' => 'BreadcrumbList', 'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Accueil', 'item' => SEO_SITE_URL . '/'],
        ['@type' => 'ListItem', 'position' => 2, 'name' => 'Blog', 'item' => SEO_SITE_URL . '/blog/'],
        ['@type' => 'ListItem', 'position' => 3, 'name' => $a['title'], 'item' => $url],
    ]];
    $faq = seo_md_faq((string) ($a['content']['markdown'] ?? ''), $a['language']);
    if ($faq) {
        $plain = static fn ($md) => trim(preg_replace('/\s+/u', ' ', strip_tags(seo_markdown_to_html($md))));
        $graph[] = ['@type' => 'FAQPage', 'mainEntity' => array_map(static fn ($f) => ['@type' => 'Question', 'name' => $f['question'],
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => html_entity_decode($plain($f['answer']), ENT_QUOTES, 'UTF-8')]], $faq)];
    }
    return ['@context' => 'https://schema.org', '@graph' => $graph];
}

/** Google Tag Manager + consent defaults, the same blocks scripts/inject-tracking.php puts on static pages. */
function seo_tracking_snippet(string $part): string
{
    $f = seo_site_root() . '/components/tracking-' . ($part === 'body' ? 'body' : 'head') . '.html';
    return is_file($f) ? rtrim((string) file_get_contents($f)) : '';
}

/** Article body HTML (shared by the public page and the admin preview). */
function seo_article_body_html(array $a): string
{
    $l = seo_labels($a['language']);
    return seo_markdown_to_html((string) ($a['content']['markdown'] ?? ''), [
        'skip_h1' => true,
        'wrap' => [$l['quick'] => 'cn-direct-answer', $l['takeaways'] => 'cn-key-takeaway'],
    ]);
}

/** The full public page for one published article. */
function seo_render_article_page(array $a): string
{
    $e = static fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
    $url = SEO_SITE_URL . '/blog/' . $a['slug'] . '/';
    $fi = $a['content']['featuredImage'] ?? [];
    $pageTitle = trim((string) $a['seo_title']) !== '' ? $a['seo_title'] : $a['title'];
    $published = date(DATE_ATOM, strtotime((string) $a['published_at']));
    $modified = date(DATE_ATOM, strtotime((string) max($a['updated_at'], $a['published_at'])));
    $jsonld = json_encode(seo_article_jsonld($a), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_PRETTY_PRINT);
    $img = $fi['url'] ?? '';
    ob_start();
    ?>
<!DOCTYPE html>
<html lang="<?= $e($a['language']) ?>">
<head>
<meta charset="utf-8">
<?= seo_tracking_snippet('head') ?>
<title><?= $e($pageTitle) ?></title>
<meta name="description" content="<?= $e($a['meta_description']) ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="index, follow, max-image-preview:large">
<link rel="canonical" href="<?= $e($url) ?>">
<meta property="og:type" content="article">
<meta property="og:locale" content="<?= $a['language'] === 'en' ? 'en_GB' : 'fr_FR' ?>">
<meta property="og:site_name" content="ClimaNova Énergie">
<meta property="og:title" content="<?= $e($pageTitle) ?>">
<meta property="og:description" content="<?= $e($a['meta_description']) ?>">
<meta property="og:url" content="<?= $e($url) ?>">
<?php if ($img): ?>
<meta property="og:image" content="<?= $e($img) ?>">
<meta property="og:image:width" content="<?= (int) ($fi['width'] ?? 0) ?>">
<meta property="og:image:height" content="<?= (int) ($fi['height'] ?? 0) ?>">
<meta property="og:image:alt" content="<?= $e($fi['alt'] ?? '') ?>">
<?php endif; ?>
<meta property="article:published_time" content="<?= $e($published) ?>">
<meta property="article:modified_time" content="<?= $e($modified) ?>">
<meta property="article:author" content="ClimaNova Énergie">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= $e($pageTitle) ?>">
<meta name="twitter:description" content="<?= $e($a['meta_description']) ?>">
<?php if ($img): ?><meta name="twitter:image" content="<?= $e($img) ?>">
<meta name="twitter:image:alt" content="<?= $e($fi['alt'] ?? '') ?>"><?php endif; ?>
<link rel="alternate" type="application/rss+xml" title="Blog ClimaNova Énergie" href="<?= SEO_SITE_URL ?>/blog/rss.xml">
<link href="/css/global.css?v=20260718-5" rel="stylesheet" type="text/css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<script src="/js/webfont.js"></script>
<script>WebFont.load({google:{families:["Instrument Sans:300,400,500,600,700"]}});</script>
<script>!function(o,c){var n=c.documentElement,t=" w-mod-";n.className+=t+"js",("ontouchstart"in o||o.DocumentTouch&&c instanceof DocumentTouch)&&(n.className+=t+"touch")}(window,document);</script>
<link rel="shortcut icon" href="/images/69787fd4f36671416d6263df_ClimaNova Energie-favicon.png" type="image/x-icon">
<?php if ($img): ?><link rel="preload" as="image" href="<?= $e($img) ?>"><?php endif; ?>
<script type="application/ld+json">
<?= $jsonld ?>

</script>
<style>
.cn-article-featured{margin:0 0 28px}.cn-article-featured img,.cn-article-figure img{width:100%;height:auto;border-radius:16px;display:block}
.cn-article-figure{margin:28px 0}.cn-article-figure figcaption{font-size:14px;color:#696867;margin-top:8px;text-align:center}
.cn-article-dates{font-size:14px;color:#696867;margin:0 0 20px}
</style>
</head>
<body>
<?= seo_tracking_snippet('body') ?>
<div class="page-wrapper">
  <div data-component="site-header"></div>
  <main class="main-wrapper">
    <section class="blog-details-banner-section">
      <div class="blog-details-banner-gradient"></div>
      <div class="container">
        <div class="blog-details-banner-content-wrapper">
          <div class="blog-details-banner-text-block">
            <h1 class="h2-style text-light"><?= $e($a['title']) ?></h1>
            <div class="blog-details-banner-meta-block">
              <div class="text-lg neutral-color-08">Par l'équipe ClimaNova Énergie</div>
              <div class="blog-details-banner-meta-divider"></div>
              <div class="text-lg neutral-color-08"><time datetime="<?= $e($published) ?>"><?= $e(seo_ymd_to_fr(substr((string) $a['published_at'], 0, 10))) ?></time></div>
              <div class="blog-details-banner-meta-divider"></div>
              <div class="text-lg neutral-color-08"><?= seo_read_minutes($a['word_count']) ?> min de lecture</div>
            </div>
          </div>
        </div>
      </div>
    </section>
    <section class="blog-details-section">
      <div class="container">
        <div class="blog-details-content-wrapper">
          <article class="blog-details-content-block">
            <?php if ($img): ?>
            <figure class="cn-article-featured"><img src="<?= $e($img) ?>" alt="<?= $e($fi['alt'] ?? '') ?>" width="<?= (int) ($fi['width'] ?? 0) ?>" height="<?= (int) ($fi['height'] ?? 0) ?>" fetchpriority="high"></figure>
            <?php endif; ?>
            <p class="cn-article-dates">Publié le <time datetime="<?= $e($published) ?>"><?= $e(seo_ymd_to_fr(substr($published, 0, 10))) ?></time><?php if (substr($modified, 0, 10) !== substr($published, 0, 10)): ?> · Mis à jour le <time datetime="<?= $e($modified) ?>"><?= $e(seo_ymd_to_fr(substr($modified, 0, 10))) ?></time><?php endif; ?></p>
            <div class="blog-details-top-content-block w-richtext">
<?= seo_article_body_html($a) ?>

            </div>
          </article>
          <aside class="blog-details-sidebar-block">
            <div class="blog-details-sidebar-widget cn-sidebar-cta">
              <div class="text-lg text-weight-semibold">Besoin d'un technicien ?</div>
              <div class="blog-details-sidebar-divider"></div>
              <p class="text-sm">Intervention à Nice et dans les Alpes-Maritimes · Devis gratuit</p>
              <a href="<?= $e($a['offer_path'] ?: '/demande-devis/') ?>" class="cn-btn cn-btn-primary cn-btn-full"><?= $e($a['offer_name'] ?: 'Demander un devis') ?> →</a>
              <p class="text-sm neutral-color-06"><a href="tel:+33652238164" class="cn-inline-link">+33 6 52 23 81 64</a></p>
            </div>
          </aside>
        </div>
      </div>
    </section>
  </main>
  <div data-component="site-footer"></div>
</div>
<script type="module" src="/js/core/main.js?v=20261111-2"></script>
</body>
</html>
<?php
    return (string) ob_get_clean();
}
