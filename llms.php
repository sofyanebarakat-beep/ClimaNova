<?php
// /llms.txt — a plain summary of the site for AI assistants (GEO): who we are, services, areas, every guide.
// Built from the live pages and the published SEO articles, so it never goes stale. Cached 1 hour.
declare(strict_types=1);
require_once __DIR__ . '/admin/_inc/seo_public.php';
require_once __DIR__ . '/admin/_inc/seo.php';

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: public, max-age=3600');
$cached = seo_cache_get('llms_txt');
if (is_string($cached) && filemtime(seo_cache_dir() . '/' . hash('sha256', 'llms_txt')) > time() - 3600) { echo $cached; exit; }

$clean = static fn (string $t) => trim(preg_replace('/\s*[|–—]\s*ClimaNova.*$/u', '', html_entity_decode($t, ENT_QUOTES, 'UTF-8')));
$line = static fn (string $title, string $url, string $desc) => "- [$title]($url)" . ($desc !== '' ? ": $desc" : '');
$services = $cities = [];
foreach (sitemap_urls(__DIR__) as $url) {
    $p = analyse_page(__DIR__, $url);
    if ($p['category'] === 'services' && $p['path'] !== '/services/') $services[] = $line($clean($p['title']), $url, (string) ($p['description'] ?? ''));
    if ($p['category'] === 'villes') $cities[] = $line($clean($p['title']), $url, (string) ($p['description'] ?? ''));
}
$guides = [];
foreach (seo_published_articles() as $a) $guides[$a['published_at']][] = $line($a['title'], SEO_SITE_URL . '/blog/' . $a['slug'] . '/', (string) $a['meta_description']);
foreach (seo_static_blog_cards() as $c) if ($c['path'] !== '') $guides[$c['date'] ?? '0'][] = $line($c['title'], SEO_SITE_URL . seo_norm_internal($c['path']), $c['excerpt']);
krsort($guides);

$out = "# ClimaNova Énergie\n\n"
    . "> Installateur certifié RGE à Nice et dans les Alpes-Maritimes (06) : climatisation, pompes à chaleur, chauffage, électricité, plomberie, "
    . "entretien et dépannage 7j/7, rénovation énergétique. Devis gratuit. Interventions à Nice, Antibes, Cannes, Cagnes-sur-Mer, Menton et environs.\n\n"
    . "- Site : " . SEO_SITE_URL . "/\n- Téléphone : +33 6 52 23 81 64\n- E-mail : contact@climanova-energie.fr\n- Adresse : 218 Route de Turin, 06300 Nice, France\n"
    . "- Demande de devis : " . SEO_SITE_URL . "/demande-devis/\n\n"
    . "## Services\n\n" . implode("\n", $services) . "\n\n"
    . "## Zones d'intervention\n\n" . implode("\n", $cities) . "\n\n"
    . "## Guides et conseils (blog)\n\n" . implode("\n", array_merge(...array_values($guides) ?: [[]])) . "\n\n"
    . "## Pages utiles\n\n" . $line('Questions fréquentes', SEO_SITE_URL . '/faq/', '') . "\n" . $line('À propos', SEO_SITE_URL . '/about-us/', '') . "\n";
seo_cache_set('llms_txt', $out);
echo $out;
