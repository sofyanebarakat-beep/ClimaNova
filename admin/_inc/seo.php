<?php
// On-page SEO analysis of every URL in sitemap.xml, read from the site's own files.
declare(strict_types=1);

const SITE = 'https://climanova-energie.fr';

const CATEGORIES = [
    'accueil'  => 'Accueil',
    'services' => 'Services',
    'villes'   => 'Villes',
    'blog'     => 'Blog',
    'pages'    => 'Autres pages',
];

function page_category(string $path): string
{
    if ($path === '/') return 'accueil';
    if (str_starts_with($path, '/services/')) return 'services';
    if (preg_match('~^/climatisation-[a-z-]+/?$~', $path)) return 'villes';
    if (preg_match('~^/blog/.+~', $path)) return 'blog';
    return 'pages';
}

function sitemap_urls(string $root): array
{
    $xml = @file_get_contents($root . '/sitemap.xml');
    return $xml && preg_match_all('~<loc>\s*(.*?)\s*</loc>~', $xml, $m) ? array_values(array_unique($m[1])) : [];
}

/**
 * Scores a page out of 100. Each check has a weight; partial credit where it makes sense.
 * Returns the page facts, the score, and the list of failed checks (with the points they cost).
 */
function analyse_page(string $root, string $url): array
{
    $path = parse_url($url, PHP_URL_PATH) ?: '/';
    $file = $root . rtrim($path, '/') . (str_ends_with($path, '.html') ? '' : '/index.html');
    $p = ['url' => $url, 'path' => $path, 'category' => page_category($path), 'score' => 0, 'issues' => [], 'title' => ''];
    if (!is_file($file)) {
        $p['issues'][] = ['Fichier introuvable', 100];
        return $p;
    }
    $html = (string) file_get_contents($file);
    $get = static fn (string $re) => preg_match($re, $html, $m) ? html_entity_decode(trim($m[1]), ENT_QUOTES, 'UTF-8') : '';

    $p['title'] = $get('~<title>(.*?)</title>~is');
    // The value ends at the SAME quote that opened it (descriptions often contain apostrophes: « d'eau », « MaPrimeRénov' »).
    $p['description'] = $get('~<meta\s+name=["\']description["\']\s+content="([^"]*)"~is') ?: $get("~<meta\\s+name=[\"']description[\"']\\s+content='([^']*)'~is");
    $p['published'] = $get('~article:published_time["\']\s+content=["\'](.*?)["\']~i') ?: $get('~"datePublished"\s*:\s*"(.*?)"~');

    // Visible text only: drop scripts, styles, JSON-LD and navigation chrome before counting words.
    $body = preg_replace('~<(script|style|noscript|svg|nav|header|footer)\b.*?</\1>~is', ' ', $html);
    $text = trim(preg_replace('~\s+~u', ' ', html_entity_decode(strip_tags((string) $body), ENT_QUOTES, 'UTF-8')));
    $p['words'] = $text === '' ? 0 : count(preg_split('~\s+~u', $text));

    $h1 = preg_match_all('~<h1[\s>]~i', $html);
    $h2 = preg_match_all('~<h2[\s>]~i', $html);
    preg_match_all('~<img\b[^>]*>~i', $html, $imgs);
    $imgsNoAlt = count(array_filter($imgs[0], static fn ($t) => !preg_match('~\balt=["\'][^"\']+~i', $t) && !str_contains($t, 'facebook.com/tr')));
    preg_match_all('~<a\b[^>]*href=["\'](/[^"\'#]*|https?://(www\.)?climanova-energie\.fr[^"\']*)~i', $html, $links);
    $internal = count(array_unique($links[1]));
    $tl = mb_strlen($p['title']);
    $dl = mb_strlen($p['description']);
    $minWords = $p['category'] === 'blog' ? 900 : 500;

    $checks = [
        // [label when failing, weight, points earned]
        ['Page bloquée (noindex)', 10, preg_match('~<meta\s+name=["\']robots["\']\s+content=["\'][^"\']*noindex~i', $html) ? 0 : 10],
        [$tl === 0 ? 'Titre manquant' : "Titre de $tl car. (idéal 30–65)", 15, $tl === 0 ? 0 : ($tl >= 30 && $tl <= 65 ? 15 : 7)],
        [$dl === 0 ? 'Meta description manquante' : "Description de $dl car. (idéal 70–165)", 15, $dl === 0 ? 0 : ($dl >= 70 && $dl <= 165 ? 15 : 7)],
        ["$h1 balise(s) H1 (il en faut 1)", 10, $h1 === 1 ? 10 : 0],
        ["$h2 sous-titre(s) H2 (au moins 2)", 5, $h2 >= 2 ? 5 : 0],
        ["{$p['words']} mots (viser $minWords+)", 15, $p['words'] >= $minWords ? 15 : ($p['words'] >= $minWords / 2 ? 7 : 0)],
        ['Canonical manquante', 5, preg_match('~<link\s+rel=["\']canonical~i', $html) ? 5 : 0],
        ['Image de partage (og:image) manquante', 5, preg_match('~property=["\']og:image["\']~i', $html) ? 5 : 0],
        ['Données structurées (JSON-LD) manquantes', 10, str_contains($html, 'application/ld+json') ? 10 : 0],
        ["$imgsNoAlt image(s) sans texte alt", 5, $imgsNoAlt === 0 ? 5 : 0],
        ["$internal liens internes (au moins 5)", 5, $internal >= 5 ? 5 : ($internal >= 2 ? 2 : 0)],
    ];
    foreach ($checks as [$label, $weight, $earned]) {
        $p['score'] += $earned;
        if ($earned < $weight) $p['issues'][] = [$label, $weight - $earned];
    }
    usort($p['issues'], static fn ($a, $b) => $b[1] <=> $a[1]);
    return $p;
}

function analyse_site(string $root): array
{
    return array_map(static fn ($u) => analyse_page($root, $u), sitemap_urls($root));
}

function score_class(int $score): string
{
    return $score >= 85 ? 'ok' : ($score >= 65 ? 'warn' : 'bad');
}

function fr_date(string $d): string
{
    if (!$d || !($t = strtotime($d))) return '—';
    $months = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
    return date('j', $t) . ' ' . $months[(int) date('n', $t) - 1] . ' ' . date('Y', $t);
}
