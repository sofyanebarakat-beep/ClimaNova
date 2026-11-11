<?php
// Pure article logic: Markdown building/rendering, link rules, quality checks, slots, URL matching.
// No database, no network. Covered by tests/seo_article_logic_test.php.
declare(strict_types=1);

require_once __DIR__ . '/seo_logic.php';

const SEO_SITE_URL = 'https://climanova-energie.fr';
const SEO_SITE_HOST = 'climanova-energie.fr';
const SEO_TARGET_LENGTHS = [500, 750, 1000, 1500, 2000];
const SEO_TONES = ['professional' => 'Professional', 'friendly' => 'Friendly', 'expert' => 'Expert'];
const SEO_ARTICLE_STATUSES = [
    'draft' => 'Draft', 'generated' => 'Generated', 'review' => 'Needs review', 'approved' => 'Approved',
    'scheduled' => 'Scheduled', 'publishing' => 'Publishing', 'published' => 'Published', 'rejected' => 'Rejected', 'failed' => 'Failed',
];
const SEO_MAX_PER_WEEK = 12;
const SEO_IMAGE_MAX_BYTES = 5 * 1024 * 1024;

/** Section headings written by the server, per language. */
const SEO_LABELS = [
    'fr' => ['quick' => 'Réponse rapide', 'takeaways' => 'Points clés à retenir', 'related' => 'Articles liés',
             'faq' => 'Questions fréquentes', 'conclusion' => 'Conclusion', 'sources' => 'Sources',
             'offer' => 'Découvrir notre offre « %s »'],
    'en' => ['quick' => 'Quick answer', 'takeaways' => 'Key takeaways', 'related' => 'Related articles',
             'faq' => 'Frequently asked questions', 'conclusion' => 'Conclusion', 'sources' => 'Sources',
             'offer' => 'Discover our “%s” offer'],
];

/** Social networks are never accepted as sources. */
const SEO_SOCIAL_HOSTS = ['facebook.com', 'fb.com', 'instagram.com', 'twitter.com', 'x.com', 'linkedin.com', 'tiktok.com',
    'youtube.com', 'youtu.be', 'pinterest.com', 'pinterest.fr', 'reddit.com', 'threads.net', 'snapchat.com', 'whatsapp.com', 't.me', 'telegram.org'];

function seo_labels(string $lang): array
{
    return SEO_LABELS[$lang] ?? SEO_LABELS['fr'];
}

/** Headings of sections that "Rewrite this section only" may never touch. */
function seo_protected_headings(string $lang): array
{
    $l = seo_labels($lang);
    return [$l['quick'], $l['takeaways'], $l['related'], $l['faq'], $l['conclusion'], $l['sources']];
}

/* ---------- Text helpers ---------- */

/** "Pompe à chaleur : prix 2026" → "pompe-a-chaleur-prix-2026" (max 80 chars, cut at a hyphen). */
function seo_slugify(string $text): string
{
    $s = str_replace(' ', '-', seo_normalize($text));
    if (strlen($s) > 80) $s = rtrim(substr($s, 0, (int) (strrpos(substr($s, 0, 81), '-') ?: 80)), '-');
    return trim($s, '-');
}

function seo_valid_slug(string $slug): bool
{
    return strlen($slug) >= 3 && strlen($slug) <= 90 && (bool) preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $slug);
}

/** Cuts text to $max characters at a word boundary (no trailing punctuation dangling). */
function seo_cut(string $text, int $max): string
{
    $text = trim(preg_replace('/\s+/u', ' ', $text));
    if (mb_strlen($text) <= $max) return $text;
    $cut = mb_substr($text, 0, $max + 1);
    $sp = mb_strrpos($cut, ' ');
    $cut = $sp !== false && $sp > $max * 0.5 ? mb_substr($cut, 0, $sp) : mb_substr($text, 0, $max);
    return rtrim($cut, " ,;:–—-");
}

function seo_contains_keyword(string $text, string $keyword): bool
{
    $k = seo_normalize($keyword);
    return $k !== '' && str_contains(' ' . seo_normalize($text) . ' ', ' ' . $k . ' ');
}

/** Keyword missing → "Keyword: rest", kept under $max characters. */
function seo_ensure_keyword(string $text, string $keyword, int $max): string
{
    $text = trim(preg_replace('/\s+/u', ' ', $text));
    $keyword = trim($keyword);
    if ($keyword === '' || seo_contains_keyword($text, $keyword)) return seo_cut($text, $max);
    $first = mb_strtoupper(mb_substr($keyword, 0, 1)) . mb_substr($keyword, 1);
    $rest = $text === '' ? '' : mb_strtolower(mb_substr($text, 0, 1)) . mb_substr($text, 1);
    // Keep proper first words ("Nice", "ClimaNova") capitalised: only lowercase when the 2nd letter is lowercase.
    if ($text !== '' && preg_match('/^\p{Lu}\p{Lu}/u', $text)) $rest = $text;
    return seo_cut($rest === '' ? $first : "$first : $rest", $max);
}

/** Words in Markdown, ignoring syntax, URLs and image lines. */
function seo_word_count(string $md): int
{
    $t = preg_replace('/^!\[.*$/m', ' ', $md);
    $t = preg_replace('/\[([^\]]*)\]\([^)]*\)/u', '$1', (string) $t);
    $t = preg_replace('/[#*_>`|]+/u', ' ', (string) $t);
    $t = preg_replace('/^\s*(\d+\.|-)\s+/mu', ' ', (string) $t);
    preg_match_all("/[\p{L}\p{N}][\p{L}\p{N}'’-]*/u", (string) $t, $m);
    return count($m[0]);
}

function seo_content_hash(string $md): string
{
    return hash('sha256', seo_normalize(preg_replace('/\(([^)]*)\)/', ' ', $md)));
}

/** 'fr' / 'en' / null from stopword frequencies. */
function seo_detect_language(string $text): ?string
{
    $words = explode(' ', seo_normalize(preg_replace('/\[[^\]]*\]\([^)]*\)/u', ' ', $text)));
    if (count($words) < 30) return null;
    $fr = ['le', 'la', 'les', 'des', 'est', 'et', 'pour', 'une', 'dans', 'vous', 'votre', 'que', 'qui', 'sur', 'avec', 'du', 'au', 'pas', 'sont', 'il'];
    $en = ['the', 'and', 'is', 'for', 'you', 'your', 'with', 'that', 'this', 'are', 'of', 'to', 'in', 'on', 'it', 'be', 'can', 'not', 'or', 'as'];
    $count = array_count_values($words);
    $sf = array_sum(array_map(static fn ($w) => $count[$w] ?? 0, $fr));
    $se = array_sum(array_map(static fn ($w) => $count[$w] ?? 0, $en));
    if ($sf === $se) return null;
    return $sf > $se ? 'fr' : 'en';
}

/** Placeholder-looking text left in an article (returns the first match, or null). */
function seo_find_placeholder(string $md): ?string
{
    $plain = preg_replace('/\[[^\]]+\]\([^)]+\)/u', ' ', $md); // real links are fine
    if (preg_match('/\[(?:[^\]\n]{0,40})\]|\{\{[^}]*\}\}|\b(?:lorem ipsum|TODO|TBD|XXX|à compléter|a completer|insert (?:here|keyword)|placeholder)\b/iu', (string) $plain, $m)) {
        return trim($m[0]);
    }
    return null;
}

/* ---------- Links ---------- */

function seo_host_of(string $url): string
{
    return strtolower(preg_replace('/^www\./', '', (string) parse_url($url, PHP_URL_HOST)));
}

function seo_is_social(string $url): bool
{
    $h = seo_host_of($url);
    foreach (SEO_SOCIAL_HOSTS as $s) if ($h === $s || str_ends_with($h, '.' . $s)) return true;
    return false;
}

/** Can this URL be listed as an external source? https, a real host, not social, not our own domain. */
function seo_source_url_allowed(string $url): bool
{
    if (!preg_match('~^https://[^\s/?#]+\.[a-z]{2,}([/?#][^\s]*)?$~i', $url) || preg_match('/[\s<>"]/', $url)) return false;
    $h = seo_host_of($url);
    return $h !== '' && $h !== SEO_SITE_HOST && !str_ends_with($h, '.' . SEO_SITE_HOST) && !seo_is_social($url);
}

/** Normalised internal path: "/blog/x" → "/blog/x/" (files keep their extension). */
function seo_norm_internal(string $path): string
{
    $path = preg_replace('~[?#].*$~', '', $path);
    $path = '/' . ltrim((string) $path, '/');
    if (!preg_match('~\.[a-z0-9]{2,5}$~i', $path)) $path = rtrim($path, '/') . '/';
    return $path;
}

/**
 * AI prose: every Markdown link whose target is not one of the allowed internal paths becomes plain text.
 * Absolute links to our own site count as internal.
 */
function seo_restrict_links(string $text, array $allowedPaths): string
{
    $allowed = array_map('seo_norm_internal', $allowedPaths);
    $text = preg_replace_callback('/(!?)\[([^\]]*)\]\(([^)\s]*)(?:\s+"[^"]*")?\)/u', static function ($m) use ($allowed) {
        if ($m[1] === '!') return $m[2]; // the AI may not add images
        $url = $m[3];
        if (preg_match('~^https?://(www\.)?' . preg_quote(SEO_SITE_HOST, '~') . '(/.*)?$~i', $url, $u)) $url = $u[2] ?? '/';
        if (str_starts_with($url, '/') && !str_starts_with($url, '//') && in_array(seo_norm_internal($url), $allowed, true)) {
            return '[' . $m[2] . '](' . seo_norm_internal($url) . ')';
        }
        return $m[2];
    }, $text);
    // Bare URLs and raw HTML are not allowed in prose either.
    $text = preg_replace('~<[^>]+>~', '', (string) $text);
    return (string) $text;
}

/** Every link and image target in Markdown: [['text','url','image'=>bool,'line']]. */
function seo_md_links(string $md): array
{
    preg_match_all('/(!?)\[([^\]]*)\]\(([^)\s]*)(?:\s+"([^"]*)")?\)/u', $md, $m, PREG_SET_ORDER);
    return array_map(static fn ($x) => ['text' => $x[2], 'url' => $x[3], 'image' => $x[1] === '!', 'title' => $x[4] ?? ''], $m);
}

/** Problems that block publishing: links that are not "/path" or https, raw HTML, javascript:. */
function seo_unsafe_link_problems(string $md): array
{
    $p = [];
    if (preg_match('/<\s*\/?\s*[a-z!][^>]*>/i', $md)) $p[] = 'The article contains HTML code — use Markdown only.';
    if (preg_match('/javascript\s*:|data\s*:|vbscript\s*:/i', $md)) $p[] = 'The article contains a javascript: or data: link.';
    foreach (seo_md_links($md) as $l) {
        $u = $l['url'];
        $ok = (str_starts_with($u, '/') && !str_starts_with($u, '//')) || preg_match('~^https://[^\s/]+\.[^\s/]+~i', $u);
        if (!$ok) { $p[] = "The link “{$u}” is not allowed — use an internal path starting with / or an https link."; break; }
    }
    return $p;
}

function seo_link_counts(string $md): array
{
    $c = ['internal' => 0, 'external' => 0, 'images' => 0];
    foreach (seo_md_links($md) as $l) {
        if ($l['image']) $c['images']++;
        elseif (str_starts_with($l['url'], '/')) $c['internal']++;
        elseif (str_starts_with($l['url'], 'http')) $c['external']++;
    }
    return $c;
}

function seo_has_link_to(string $md, string $path): bool
{
    $target = seo_norm_internal($path);
    foreach (seo_md_links($md) as $l) {
        $u = preg_replace('~^https?://(www\.)?' . preg_quote(SEO_SITE_HOST, '~') . '~i', '', $l['url']);
        if (!$l['image'] && str_starts_with((string) $u, '/') && seo_norm_internal((string) $u) === $target) return true;
    }
    return false;
}

/* ---------- Markdown structure ---------- */

/**
 * Splits Markdown into ['title' => H1 text, 'intro' => text before the first H2, 'sections' => [[heading, body]], 'tail' => text after the
 * last section that is not part of it (the final offer link line)].
 */
function seo_md_parse(string $md): array
{
    $md = str_replace("\r\n", "\n", $md);
    $title = '';
    if (preg_match('/^#\s+(.+)$/m', $md, $m, PREG_OFFSET_CAPTURE)) {
        $title = trim($m[1][0]);
        $md = substr($md, 0, $m[0][1]) . substr($md, $m[0][1] + strlen($m[0][0]));
    }
    $parts = preg_split('/^##\s+(?!#)(.+)$/m', $md, -1, PREG_SPLIT_DELIM_CAPTURE);
    $intro = trim((string) array_shift($parts));
    $sections = [];
    for ($i = 0; $i < count($parts); $i += 2) {
        $sections[] = [trim($parts[$i]), trim($parts[$i + 1] ?? '')];
    }
    $tail = '';
    if ($sections) {
        // The offer link is the last line, separated from the Sources list by a blank line.
        $last = &$sections[count($sections) - 1][1];
        if (preg_match('/\n\n(\[[^\]]+\]\(\/[^)]*\))\s*$/u', $last, $mm) || preg_match('/^(\[[^\]]+\]\(\/[^)]*\))\s*$/u', $last, $mm)) {
            $tail = $mm[1];
            $last = trim(substr($last, 0, strlen($last) - strlen($mm[0])));
        }
        unset($last);
    }
    return ['title' => $title, 'intro' => $intro, 'sections' => $sections, 'tail' => $tail];
}

function seo_md_join(array $doc): string
{
    $out = [];
    if ($doc['title'] !== '') $out[] = '# ' . $doc['title'];
    if ($doc['intro'] !== '') $out[] = $doc['intro'];
    foreach ($doc['sections'] as [$h, $b]) $out[] = "## $h" . ($b !== '' ? "\n\n$b" : '');
    if ($doc['tail'] !== '') $out[] = $doc['tail'];
    return implode("\n\n", $out) . "\n";
}

function seo_offer_link_line(string $lang, string $offerName, string $offerPath): string
{
    return '[' . sprintf(seo_labels($lang)['offer'], $offerName) . ' →](' . seo_norm_internal($offerPath) . ')';
}

function seo_sources_markdown(array $sources): string
{
    return implode("\n", array_map(static fn ($s) => '- [' . str_replace([']', '['], '', trim($s['title'])) . '](' . $s['url'] . ')', $sources));
}

/** Sources listed in the "Sources" section: [['title','url']]. */
function seo_md_get_sources(string $md, string $lang): array
{
    $label = seo_labels($lang)['sources'];
    foreach (seo_md_parse($md)['sections'] as [$h, $b]) {
        if ($h !== $label) continue;
        return array_map(static fn ($l) => ['title' => $l['text'], 'url' => $l['url']],
            array_values(array_filter(seo_md_links($b), static fn ($l) => !$l['image'])));
    }
    return [];
}

/** Replaces (or creates) the Sources section, placed just before the final offer link. */
function seo_md_set_sources(string $md, array $sources, string $lang): string
{
    $label = seo_labels($lang)['sources'];
    $doc = seo_md_parse($md);
    $doc['sections'] = array_values(array_filter($doc['sections'], static fn ($s) => $s[0] !== $label));
    if ($sources) $doc['sections'][] = [$label, seo_sources_markdown($sources)];
    return seo_md_join($doc);
}

/** FAQ pairs from the FAQ section ("### Question" followed by the answer). */
function seo_md_faq(string $md, string $lang): array
{
    $label = seo_labels($lang)['faq'];
    foreach (seo_md_parse($md)['sections'] as [$h, $b]) {
        if ($h !== $label && !preg_match('/^(faq|questions fréquentes|frequently asked)/iu', $h)) continue;
        $parts = preg_split('/^###\s+(.+)$/m', $b, -1, PREG_SPLIT_DELIM_CAPTURE);
        $faq = [];
        for ($i = 1; $i < count($parts); $i += 2) {
            $q = trim($parts[$i]);
            $a = trim($parts[$i + 1] ?? '');
            if ($q !== '' && $a !== '') $faq[] = ['question' => $q, 'answer' => $a];
        }
        return $faq;
    }
    return [];
}

/** H2 headings that "Rewrite this section only" may change. */
function seo_rewritable_sections(string $md, string $lang): array
{
    $protected = seo_protected_headings($lang);
    return array_values(array_filter(array_column(seo_md_parse($md)['sections'], 0), static fn ($h) => !in_array($h, $protected, true)));
}

/** Replaces one H2 section's heading and body. Returns null if the heading is not found or protected. */
function seo_md_replace_section(string $md, string $heading, string $newHeading, string $newBody, string $lang): ?string
{
    if (in_array($heading, seo_protected_headings($lang), true)) return null;
    $doc = seo_md_parse($md);
    foreach ($doc['sections'] as $i => [$h]) {
        if ($h !== $heading) continue;
        // Section images survive a rewrite.
        preg_match_all('/^!\[.*$/m', $doc['sections'][$i][1], $imgs);
        $body = trim($newBody) . ($imgs[0] ? "\n\n" . implode("\n\n", $imgs[0]) : '');
        $doc['sections'][$i] = [trim($newHeading), $body];
        return seo_md_join($doc);
    }
    return null;
}

/**
 * Builds the article Markdown in the fixed order: title, introduction, Quick answer, Key takeaways, sections,
 * Related articles, FAQ, conclusion, Sources, final link to the offer page.
 *   $a: AI fields (already link-restricted); $related: [['title','path']]; $sources: [['title','url']]
 */
function seo_build_markdown(array $a, string $lang, array $related, array $sources, string $offerName, string $offerPath): string
{
    $l = seo_labels($lang);
    $doc = ['title' => trim($a['title']), 'intro' => trim($a['introduction']), 'sections' => [], 'tail' => seo_offer_link_line($lang, $offerName, $offerPath)];
    $doc['sections'][] = [$l['quick'], trim($a['quickAnswer'])];
    $doc['sections'][] = [$l['takeaways'], implode("\n", array_map(static fn ($t) => '- ' . trim($t), $a['keyTakeaways']))];
    foreach ($a['sections'] as $s) {
        $doc['sections'][] = [trim($s['heading']), implode("\n\n", array_map('trim', $s['paragraphs']))];
    }
    if ($related) {
        $doc['sections'][] = [$l['related'], implode("\n", array_map(static fn ($r) => '- [' . $r['title'] . '](' . seo_norm_internal($r['path']) . ')', array_slice($related, 0, 3)))];
    }
    $faq = [];
    foreach ($a['faq'] as $f) $faq[] = '### ' . trim($f['question']) . "\n\n" . trim($f['answer']);
    $doc['sections'][] = [$l['faq'], implode("\n\n", $faq)];
    $doc['sections'][] = [$l['conclusion'], trim($a['conclusion'])];
    if ($sources) $doc['sections'][] = [$l['sources'], seo_sources_markdown($sources)];
    return seo_md_join($doc);
}

/* ---------- Markdown → HTML (safe: everything escaped, only /paths and https links) ---------- */

function seo_md_inline(string $text): string
{
    $out = '';
    $pos = 0;
    preg_match_all('/\[([^\]]+)\]\(([^)\s]+)\)/u', $text, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
    foreach ($m as $x) {
        $out .= seo_md_emphasis(htmlspecialchars(substr($text, $pos, $x[0][1] - $pos), ENT_QUOTES, 'UTF-8'));
        $label = seo_md_emphasis(htmlspecialchars($x[1][0], ENT_QUOTES, 'UTF-8'));
        $url = $x[2][0];
        if (str_starts_with($url, '/') && !str_starts_with($url, '//') && !preg_match('/[\s"<>]/', $url)) {
            $out .= '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">' . $label . '</a>';
        } elseif (preg_match('~^https://[^\s"<>]+$~i', $url)) {
            $out .= '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener noreferrer">' . $label . '</a>';
        } else {
            $out .= $label;
        }
        $pos = $x[0][1] + strlen($x[0][0]);
    }
    return $out . seo_md_emphasis(htmlspecialchars(substr($text, $pos), ENT_QUOTES, 'UTF-8'));
}

function seo_md_emphasis(string $escaped): string
{
    $s = preg_replace('/\*\*(?=\S)(.+?)(?<=\S)\*\*/u', '<strong>$1</strong>', $escaped);
    return (string) preg_replace('/(?<![*\w])\*(?=\S)([^*]+?)(?<=\S)\*(?![*\w])/u', '<em>$1</em>', (string) $s);
}

function seo_heading_id(string $text): string
{
    return seo_slugify($text) ?: 'section';
}

/**
 * Renders Markdown to HTML.
 *   $opts['skip_h1']  drop the "# Title" line (the page prints its own H1)
 *   $opts['wrap']     [heading => css class]: wraps that H2 section's body in <div class="…">
 *   $opts['img_dims'] [url => [w, h]] for section images
 */
function seo_markdown_to_html(string $md, array $opts = []): string
{
    $lines = explode("\n", str_replace("\r\n", "\n", $md));
    $html = [];
    $para = [];
    $list = null; // ['ul'|'ol', items]
    $wrapOpen = false;
    $flushPara = static function () use (&$para, &$html) {
        if ($para) $html[] = '<p>' . seo_md_inline(implode(' ', $para)) . '</p>';
        $para = [];
    };
    $flushList = static function () use (&$list, &$html) {
        if ($list) $html[] = "<{$list[0]}>" . implode('', array_map(static fn ($i) => '<li>' . seo_md_inline($i) . '</li>', $list[1])) . "</{$list[0]}>";
        $list = null;
    };
    foreach ($lines as $raw) {
        $line = rtrim($raw);
        $t = trim($line);
        if ($t === '') { $flushPara(); $flushList(); continue; }
        if (preg_match('/^(#{1,3})\s+(.+)$/', $t, $m)) {
            $flushPara(); $flushList();
            $level = strlen($m[1]);
            if ($level === 1) { if (empty($opts['skip_h1'])) $html[] = '<h1>' . seo_md_inline($m[2]) . '</h1>'; continue; }
            if ($level === 2) {
                if ($wrapOpen) { $html[] = '</div>'; $wrapOpen = false; }
                $html[] = '<h2 id="' . seo_heading_id($m[2]) . '">' . seo_md_inline($m[2]) . '</h2>';
                $cls = $opts['wrap'][trim($m[2])] ?? null;
                if ($cls) { $html[] = '<div class="' . htmlspecialchars($cls, ENT_QUOTES) . '">'; $wrapOpen = true; }
                continue;
            }
            $html[] = '<h3>' . seo_md_inline($m[2]) . '</h3>';
            continue;
        }
        if (preg_match('/^!\[([^\]]*)\]\(([^)\s]+)(?:\s+"([^"]*)")?\)$/u', $t, $m)) {
            $flushPara(); $flushList();
            if (!preg_match('~^(https://[^\s"<>]+|/[^\s"<>]*)$~i', $m[2])) continue;
            $dims = $opts['img_dims'][$m[2]] ?? null;
            $html[] = '<figure class="cn-article-figure"><img src="' . htmlspecialchars($m[2], ENT_QUOTES) . '" alt="' . htmlspecialchars($m[1], ENT_QUOTES) . '" loading="lazy"'
                . ($dims ? ' width="' . (int) $dims[0] . '" height="' . (int) $dims[1] . '"' : '') . '>'
                . (($m[3] ?? '') !== '' ? '<figcaption>' . htmlspecialchars($m[3], ENT_QUOTES) . '</figcaption>' : '') . '</figure>';
            continue;
        }
        if (preg_match('/^[-*]\s+(.+)$/', $t, $m) || preg_match('/^\d+[.)]\s+(.+)$/', $t, $m2)) {
            $flushPara();
            $type = isset($m[1]) && preg_match('/^[-*]\s/', $t) ? 'ul' : 'ol';
            $item = $type === 'ul' ? $m[1] : $m2[1];
            if (!$list || $list[0] !== $type) { $flushList(); $list = [$type, []]; }
            $list[1][] = $item;
            continue;
        }
        if (str_starts_with($t, '>')) {
            $flushPara(); $flushList();
            $html[] = '<blockquote><p>' . seo_md_inline(ltrim(substr($t, 1))) . '</p></blockquote>';
            continue;
        }
        $flushList();
        $para[] = $t;
    }
    $flushPara();
    $flushList();
    if ($wrapOpen) $html[] = '</div>';
    return implode("\n", $html);
}

/* ---------- Quality checks ---------- */

/**
 * Runs every check. $a: title, meta_description, focus_keyword, markdown, featured_image (array|null), language, target_words, offer_path.
 * Returns ['score' => 0-100, 'notes' => [one sentence per failed check], 'checks' => [[label, ok]]].
 */
function seo_quality_checks(array $a): array
{
    $md = (string) $a['markdown'];
    $kw = trim((string) $a['focus_keyword']);
    $title = trim((string) $a['title']);
    $meta = trim((string) $a['meta_description']);
    $words = seo_word_count($md);
    $minWords = max(500, intdiv((int) $a['target_words'], 2));
    $lang = (string) $a['language'];
    $detected = seo_detect_language($md);
    $placeholder = seo_find_placeholder($md . "\n" . $title . "\n" . $meta);
    $metaLen = mb_strlen($meta);
    $doc = seo_md_parse($md);
    $body = $doc['intro'] . "\n" . implode("\n", array_map(static fn ($s) => $s[0] . "\n" . $s[1], $doc['sections']));
    $images = array_filter(seo_md_links($md), static fn ($l) => $l['image']);
    $noAlt = count(array_filter($images, static fn ($l) => trim($l['text']) === ''));
    $fi = $a['featured_image'] ?? null;
    $faq = count(seo_md_faq($md, $lang));
    $langName = SEO_LANGUAGES[$lang] ?? $lang;

    $checks = [
        [mb_strlen($title) >= 8, 'The title must be at least 8 characters.'],
        [$words >= $minWords, "The article has $words words; it needs at least $minWords (half the target, minimum 500)."],
        [$detected === null || $detected === $lang, "The article does not look like it is written in $langName."],
        [$placeholder === null, 'The article still contains a placeholder (“' . $placeholder . '”) — replace it.'],
        [$metaLen >= 80 && $metaLen <= 240, "The meta description is $metaLen characters; it must be 80 to 240."],
        [$kw !== '' && seo_contains_keyword($title, $kw), 'The focus keyword is missing from the title.'],
        [$kw !== '' && seo_contains_keyword($meta, $kw), 'The focus keyword is missing from the meta description.'],
        [$kw !== '' && seo_contains_keyword($body, $kw), 'The focus keyword is missing from the article body.'],
        [($a['offer_path'] ?? '') !== '' && seo_has_link_to($md, (string) $a['offer_path']), 'The article has no link to the offer page.'],
        [$faq >= 3, "The FAQ has $faq question(s); it needs at least 3."],
        [$noAlt === 0, "$noAlt image(s) in the article have no alt text."],
        [is_array($fi) && !empty($fi['url']) && trim((string) ($fi['alt'] ?? '')) !== '', 'Add a featured image with alt text.'],
        [seo_link_counts($md)['external'] >= 1, 'Add at least one external source (an https link).'],
    ];
    $passed = count(array_filter($checks, static fn ($c) => $c[0]));
    return [
        'score' => (int) round($passed / count($checks) * 100),
        'notes' => array_values(array_map(static fn ($c) => $c[1], array_filter($checks, static fn ($c) => !$c[0]))),
        'words' => $words,
    ];
}

/* ---------- Scheduling ---------- */

/**
 * Next free publishing slot after $now, as a UTC DateTimeImmutable, or null if none in the next 26 weeks.
 *   $c: publish_days (ISO 1-7), publish_times (HH:mm), time_zone, start_date, end_date (nullable)
 *   $occupied: UTC timestamps (any format strtotime accepts) of every scheduled article
 * Skips occupied slots, respects the campaign dates, and never puts more than 12 articles in one week (Mon–Sun, campaign time zone).
 */
function seo_next_free_slot(array $c, array $occupied, DateTimeImmutable $now, int $maxPerWeek = SEO_MAX_PER_WEEK): ?DateTimeImmutable
{
    $tz = new DateTimeZone($c['time_zone'] ?: 'Europe/Paris');
    $taken = [];
    $perWeek = [];
    foreach ($occupied as $o) {
        $d = (new DateTimeImmutable((string) $o))->setTimezone($tz);
        $taken[$d->format('Y-m-d H:i')] = true;
        $wk = $d->format('o-W');
        $perWeek[$wk] = ($perWeek[$wk] ?? 0) + 1;
    }
    $days = array_map('intval', $c['publish_days']);
    $times = $c['publish_times'];
    sort($times);
    if (!$days || !$times) return null;
    $local = $now->setTimezone($tz);
    $date = $local->setTime(0, 0);
    if (!empty($c['start_date']) && $date->format('Y-m-d') < $c['start_date']) $date = new DateTimeImmutable($c['start_date'] . ' 00:00', $tz);
    for ($i = 0; $i < 26 * 7; $i++, $date = $date->modify('+1 day')) {
        $ymd = $date->format('Y-m-d');
        if (!empty($c['end_date']) && $ymd > $c['end_date']) return null;
        if (!in_array((int) $date->format('N'), $days, true)) continue;
        if (($perWeek[$date->format('o-W')] ?? 0) >= $maxPerWeek) continue;
        foreach ($times as $t) {
            [$h, $m] = array_map('intval', explode(':', $t));
            $slot = $date->setTime($h, $m);
            if ($slot <= $local || isset($taken[$slot->format('Y-m-d H:i')])) continue;
            return $slot->setTimezone(new DateTimeZone('UTC'));
        }
    }
    return null;
}

/* ---------- Analytics: URL matching ---------- */

/**
 * Any URL variant of a page → one path: decoded, lowercase, no query/fragment/index.html, trailing slash on folders.
 * Returns null for URLs on another host.
 */
function seo_page_key(string $url): ?string
{
    $url = trim($url);
    if (preg_match('~^https?://~i', $url)) {
        $h = seo_host_of($url);
        if ($h !== SEO_SITE_HOST) return null;
        $url = (string) (parse_url($url, PHP_URL_PATH) ?? '/');
    }
    $path = preg_replace('~[?#].*$~', '', $url);
    $path = mb_strtolower(rawurldecode((string) $path));
    $path = preg_replace('~/+~', '/', '/' . ltrim($path, '/'));
    $path = preg_replace('~/index\.html?$~', '/', (string) $path);
    $path = preg_replace('~\.html?$~', '', (string) $path);
    if ($path !== '/' && !preg_match('~\.[a-z0-9]{2,5}$~', $path)) $path = rtrim($path, '/') . '/';
    return $path;
}

/** Merges rows that point at the same page: sums clicks/impressions/sessions, position weighted by impressions. */
function seo_merge_page_rows(array $rows): array
{
    $out = [];
    foreach ($rows as $r) {
        $key = $r['day'] . '|' . $r['page_path'];
        if (!isset($out[$key])) { $out[$key] = $r + ['_pw' => 0.0]; $out[$key]['clicks'] = 0; $out[$key]['impressions'] = 0; $out[$key]['sessions'] = 0; $out[$key]['engaged_sessions'] = 0; }
        $o = &$out[$key];
        $o['clicks'] += (int) ($r['clicks'] ?? 0);
        $o['impressions'] += (int) ($r['impressions'] ?? 0);
        $o['sessions'] += (int) ($r['sessions'] ?? 0);
        $o['engaged_sessions'] += (int) ($r['engaged_sessions'] ?? 0);
        $o['_pw'] += (float) ($r['position'] ?? 0) * (int) ($r['impressions'] ?? 0);
        unset($o);
    }
    foreach ($out as &$o) {
        $o['position'] = $o['impressions'] > 0 ? round($o['_pw'] / $o['impressions'], 2) : null;
        unset($o['_pw']);
    }
    return array_values($out);
}

/** "https://example.com" → "https://example.com/"; "sc-domain:example.com/" → "sc-domain:example.com". Null when invalid. */
function seo_normalize_gsc_site(string $site): ?string
{
    $site = trim($site);
    if (preg_match('~^sc-domain:([a-z0-9.-]+\.[a-z]{2,})/*$~i', $site, $m)) return 'sc-domain:' . strtolower($m[1]);
    if (preg_match('~^https?://[a-z0-9.-]+\.[a-z]{2,}(/[^\s?#]*)?$~i', $site)) return rtrim($site, '/') . '/';
    return null;
}

/** Plain-language message for a Google API error. */
function seo_google_error(int $status, string $service, string $email, string $googleMessage = ''): string
{
    $where = $service === 'ga4' ? 'Google Analytics property (Admin → Property access management)' : 'Search Console property (Settings → Users and permissions)';
    return match (true) {
        $status === 0 => "Could not reach Google — please try again in a minute.",
        $status === 400 && $service === 'ga4' && str_contains($googleMessage, 'property') => 'The GA4 property ID looks wrong — use the numeric property ID, not the G- measurement ID.',
        $status === 400 => 'Google rejected the request: ' . rtrim($googleMessage ?: 'invalid request', '.') . '.',
        $status === 401 => 'Google refused the service account key — paste the key file again.',
        $status === 403 && str_contains($googleMessage, 'has not been used') || $status === 403 && str_contains($googleMessage, 'disabled')
            => ($service === 'ga4' ? 'Enable the Google Analytics Data API' : 'Enable the Google Search Console API') . ' in the service account’s Google Cloud project.',
        $status === 403 => "Add the service account email ($email) as a user on this $where.",
        $status === 404 => $service === 'ga4' ? 'The GA4 property was not found — check GA4_PROPERTY_ID.' : 'The Search Console property was not found — GSC_SITE_URL must match the property exactly.',
        $status === 429 => 'Google’s quota was reached — wait a few minutes and sync again.',
        $status >= 500 => 'Google is having problems right now — please try again later.',
        default => "Google returned an error (HTTP $status).",
    };
}

/** GA4 event names → conversion bucket. */
function seo_conversion_kind(string $event): ?string
{
    $e = strtolower($event);
    if (preg_match('/lead|contact|devis|quote|consult|appointment|rendez|call|phone|whatsapp|form_submit/', $e)) return 'consultation';
    if (preg_match('/sign_?up|register|inscription|subscribe|purchase|checkout|booking|reservation/', $e)) return 'registration';
    return null;
}
