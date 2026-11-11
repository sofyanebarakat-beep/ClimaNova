<?php
// Pure-logic tests for articles, publishing and analytics. Run: npm test
declare(strict_types=1);
require __DIR__ . '/../admin/_inc/seo_article_logic.php';

$failed = 0;
$passed = 0;
function check(string $name, $actual, $expected): void
{
    global $failed, $passed;
    if ($actual === $expected) { $passed++; return; }
    $failed++;
    echo "FAIL $name\n  expected: " . var_export($expected, true) . "\n  actual:   " . var_export($actual, true) . "\n";
}

// Slugs
check('slugify accents', seo_slugify('Pompe à chaleur : prix 2026 !'), 'pompe-a-chaleur-prix-2026');
check('slugify max 80 at hyphen', strlen(seo_slugify(str_repeat('climatisation ', 20))) <= 80, true);
check('valid slug', seo_valid_slug('prix-clim-nice'), true);
check('invalid slug uppercase', seo_valid_slug('Prix-clim'), false);
check('invalid slug double hyphen', seo_valid_slug('prix--clim'), false);
check('invalid slug too short', seo_valid_slug('ab'), false);

// Keyword fixes
check('keyword present untouched', seo_ensure_keyword('Prix de la climatisation à Nice', 'climatisation à Nice', 180), 'Prix de la climatisation à Nice');
check('keyword added at start', seo_ensure_keyword('Combien coûte une installation ?', 'clim Nice', 180), 'Clim Nice : combien coûte une installation ?');
check('keyword matching ignores accents', seo_contains_keyword('Pompe a chaleur air-eau', 'pompe à chaleur'), true);
check('keyword whole words only', seo_contains_keyword('climatiseur', 'climatisation'), false);
check('keyword words in a natural sentence', seo_contains_keyword("Entretien de chauffage à Nice en hiver : conseils", 'entretien chauffage Nice hiver'), true);
check('keyword plural ok', seo_contains_keyword('Les pompes à chaleur à Nice', 'pompe à chaleur Nice'), true);
check('keyword missing one word', seo_contains_keyword('Entretien du chauffage en hiver', 'entretien chauffage Nice hiver'), false);
check('no duplicate prefix when words present', seo_ensure_keyword("Entretien de chauffage à Nice en hiver : conseils", 'entretien chauffage Nice hiver', 180), "Entretien de chauffage à Nice en hiver : conseils");
$long = seo_ensure_keyword(str_repeat('mot ', 80), 'pompe à chaleur', 240);
check('meta kept under 240', mb_strlen($long) <= 240, true);
check('fixed meta starts with keyword', str_starts_with($long, 'Pompe à chaleur : '), true);
check('cut at word boundary', seo_cut('un deux trois quatre', 12), 'un deux');

// Word count and language
check('word count ignores syntax and urls', seo_word_count("# Titre\n\nUn [lien utile](https://a.fr/x) ici.\n\n![alt](https://a.fr/i.jpg)\n\n- point un"), 7);
$fr = str_repeat('La pompe à chaleur est une solution pour votre maison et elle permet des économies dans le sud. ', 4);
$en = str_repeat('The heat pump is a solution for your home and it can save you money in the south of the country. ', 4);
check('detects French', seo_detect_language($fr), 'fr');
check('detects English', seo_detect_language($en), 'en');
check('too short to tell', seo_detect_language('Bonjour à tous'), null);

// Placeholders
check('finds bracket placeholder', seo_find_placeholder('Appelez-nous au [numéro de téléphone].'), '[numéro de téléphone]');
check('real links are not placeholders', seo_find_placeholder('Voir [notre offre](/services/x/).'), null);
check('finds lorem', seo_find_placeholder('Lorem ipsum dolor') !== null, true);

// Link restriction
$prose = 'Lisez [ce guide](/blog/guide-clim/) et [ceci](https://evil.com/x) ou [cela](/blog/autre/) puis [site](https://climanova-energie.fr/blog/guide-clim) <b>gras</b>.';
check('only allowed links kept', seo_restrict_links($prose, ['/blog/guide-clim/']),
    'Lisez [ce guide](/blog/guide-clim/) et ceci ou cela puis [site](/blog/guide-clim/) gras.');
check('ai images become text', seo_restrict_links('![photo](https://x.fr/a.jpg)', []), 'photo');

// Source URLs
check('source https ok', seo_source_url_allowed('https://www.ademe.fr/particuliers'), true);
check('source http refused', seo_source_url_allowed('http://www.ademe.fr/'), false);
check('source social refused', seo_source_url_allowed('https://www.facebook.com/ademe'), false);
check('source own domain refused', seo_source_url_allowed('https://climanova-energie.fr/blog/'), false);
check('source subdomain social refused', seo_source_url_allowed('https://fr.linkedin.com/company/x'), false);

// Unsafe links
check('safe markdown', seo_unsafe_link_problems("[a](/services/x/) [b](https://ademe.fr/)\n![c](https://cdn.fr/i.jpg \"cap\")"), []);
check('javascript blocked', count(seo_unsafe_link_problems('[a](javascript:alert(1))')) > 0, true);
check('html blocked', count(seo_unsafe_link_problems('Hello <script>x</script>')) > 0, true);
check('http blocked', count(seo_unsafe_link_problems('[a](http://x.fr/)')) > 0, true);
check('protocol-relative blocked', count(seo_unsafe_link_problems('[a](//x.fr/)')) > 0, true);

// Build + parse Markdown
$ai = [
    'title' => 'Climatisation à Nice : le guide', 'introduction' => 'Intro avec [lien](/blog/a/).', 'quickAnswer' => 'Réponse directe.',
    'keyTakeaways' => ['Point 1', 'Point 2', 'Point 3'],
    'sections' => [['heading' => 'Pourquoi installer ?', 'paragraphs' => ['P1', 'P2', 'P3']], ['heading' => 'Combien ça coûte ?', 'paragraphs' => ['P4', 'P5', 'P6']]],
    'faq' => [['question' => 'Q1 ?', 'answer' => 'R1.'], ['question' => 'Q2 ?', 'answer' => 'R2.'], ['question' => 'Q3 ?', 'answer' => 'R3.']],
    'conclusion' => 'Fin.',
];
$md = seo_build_markdown($ai, 'fr', [['title' => 'Article A', 'path' => '/blog/a']], [['title' => 'ADEME', 'url' => 'https://www.ademe.fr/']], 'Climatisation', '/services/climatisation/');
$doc = seo_md_parse($md);
check('title parsed', $doc['title'], 'Climatisation à Nice : le guide');
check('section order', array_column($doc['sections'], 0),
    ['Réponse rapide', 'Points clés à retenir', 'Pourquoi installer ?', 'Combien ça coûte ?', 'Articles liés', 'Questions fréquentes', 'Conclusion', 'Sources']);
check('offer link is the tail', $doc['tail'], '[Découvrir notre offre « Climatisation » →](/services/climatisation/)');
check('round trip', seo_md_join(seo_md_parse($md)), $md);
check('faq extracted', count(seo_md_faq($md, 'fr')), 3);
check('faq answer', seo_md_faq($md, 'fr')[1], ['question' => 'Q2 ?', 'answer' => 'R2.']);
check('sources read', seo_md_get_sources($md, 'fr'), [['title' => 'ADEME', 'url' => 'https://www.ademe.fr/']]);
check('has offer link', seo_has_link_to($md, '/services/climatisation'), true);
check('rewritable sections', seo_rewritable_sections($md, 'fr'), ['Pourquoi installer ?', 'Combien ça coûte ?']);
check('counts', seo_link_counts($md), ['internal' => 3, 'external' => 1, 'images' => 0]);

$md2 = seo_md_set_sources($md, [['title' => 'ADEME', 'url' => 'https://www.ademe.fr/'], ['title' => 'France Rénov', 'url' => 'https://france-renov.gouv.fr/']], 'fr');
check('sources replaced, still before offer link', array_slice(array_column(seo_md_parse($md2)['sections'], 0), -1), ['Sources']);
check('two sources now', count(seo_md_get_sources($md2, 'fr')), 2);
check('offer link kept', seo_md_parse($md2)['tail'], $doc['tail']);
$md3 = seo_md_set_sources($md, [], 'fr');
check('sources removed', in_array('Sources', array_column(seo_md_parse($md3)['sections'], 0), true), false);
check('offer link kept without sources', seo_md_parse($md3)['tail'], $doc['tail']);

$withImg = str_replace("P1\n", "P1\n\n![Unité extérieure](https://x.fr/i.jpg \"Légende\")\n", $md);
$re = seo_md_replace_section($withImg, 'Pourquoi installer ?', 'Pourquoi installer une clim ?', "N1\n\nN2\n\nN3", 'fr');
check('section rewritten', seo_md_parse((string) $re)['sections'][2][0], 'Pourquoi installer une clim ?');
check('section image kept', str_contains((string) $re, '![Unité extérieure](https://x.fr/i.jpg "Légende")'), true);
check('protected section refused', seo_md_replace_section($md, 'Questions fréquentes', 'x', 'y', 'fr'), null);
check('unknown section refused', seo_md_replace_section($md, 'Nope', 'x', 'y', 'fr'), null);

// Rendering
$html = seo_markdown_to_html("# T\n\nUn **gras** et *italique* <script>.\n\n- [int](/a/)\n- [ext](https://b.fr/)\n- [bad](javascript:x)\n\n![Alt](https://x.fr/i.jpg \"Cap\")", ['skip_h1' => true]);
check('h1 skipped', str_contains($html, '<h1>'), false);
check('html escaped', str_contains($html, '&lt;script&gt;'), true);
check('bold', str_contains($html, '<strong>gras</strong>'), true);
check('italic', str_contains($html, '<em>italique</em>'), true);
check('internal link', str_contains($html, '<a href="/a/">int</a>'), true);
check('external new tab', str_contains($html, '<a href="https://b.fr/" target="_blank" rel="noopener noreferrer">ext</a>'), true);
check('bad link plain text', str_contains($html, '<li>bad</li>'), true);
check('figure with caption', str_contains($html, '<figcaption>Cap</figcaption>'), true);
$wrapped = seo_markdown_to_html("## Réponse rapide\n\nOui.\n\n## Suite\n\nTexte.", ['wrap' => ['Réponse rapide' => 'cn-direct-answer']]);
check('wrap section', str_contains($wrapped, "<div class=\"cn-direct-answer\">\n<p>Oui.</p>\n</div>"), true);

// Quality checks
$body = str_repeat("La climatisation à Nice est une solution efficace pour votre maison et elle permet de mieux vivre l'été. ", 40);
$good = seo_build_markdown(array_merge($ai, ['sections' => [['heading' => 'Climatisation à Nice : pourquoi ?', 'paragraphs' => [$body, 'P2', 'P3']]]]),
    'fr', [], [['title' => 'ADEME', 'url' => 'https://www.ademe.fr/']], 'Climatisation', '/services/climatisation/');
$q = seo_quality_checks(['title' => 'Climatisation à Nice : le guide', 'meta_description' => str_pad('Climatisation à Nice : tout savoir ', 120, 'x'),
    'focus_keyword' => 'climatisation à Nice', 'markdown' => $good, 'featured_image' => ['url' => 'https://x/i.jpg', 'alt' => 'Clim'],
    'language' => 'fr', 'target_words' => 1000, 'offer_path' => '/services/climatisation/']);
check('good article passes', $q['notes'], []);
check('good score 100', $q['score'], 100);
$q2 = seo_quality_checks(['title' => 'Court', 'meta_description' => 'trop court', 'focus_keyword' => 'pompe à chaleur', 'markdown' => "# Court\n\nTexte [à compléter].",
    'featured_image' => null, 'language' => 'fr', 'target_words' => 2000, 'offer_path' => '/services/chauffage/']);
check('bad article fails many', count($q2['notes']) >= 9, true);
check('needs an external source', in_array('Add at least one external source (an https link).', $q2['notes'], true), true);
check('needs featured image note', in_array('Add a featured image with alt text.', $q2['notes'], true), true);
check('min words = half target', (bool) array_filter($q2['notes'], static fn ($n) => str_contains($n, 'at least 1000')), true);

// Slots
$camp = ['publish_days' => [1, 3, 5], 'publish_times' => ['09:00', '17:00'], 'time_zone' => 'Europe/Paris', 'start_date' => '2026-11-01', 'end_date' => null];
$now = new DateTimeImmutable('2026-11-11 10:00', new DateTimeZone('Europe/Paris')); // Wednesday
$s = seo_next_free_slot($camp, [], $now);
check('next slot same day later time', $s?->setTimezone(new DateTimeZone('Europe/Paris'))->format('Y-m-d H:i'), '2026-11-11 17:00');
check('slot returned in UTC', $s?->getTimezone()->getName(), 'UTC');
$s2 = seo_next_free_slot($camp, ['2026-11-11 16:00:00+00'], $now);
check('occupied slot skipped', $s2?->setTimezone(new DateTimeZone('Europe/Paris'))->format('Y-m-d H:i'), '2026-11-13 09:00');
$late = seo_next_free_slot($camp + ['x' => 1], [], new DateTimeImmutable('2026-11-01 08:00', new DateTimeZone('Europe/Paris')));
check('respects start date (Sunday → Monday)', $late?->setTimezone(new DateTimeZone('Europe/Paris'))->format('Y-m-d H:i'), '2026-11-02 09:00');
$ended = array_merge($camp, ['end_date' => '2026-11-11']);
check('respects end date', seo_next_free_slot($ended, ['2026-11-11 16:00:00+00'], $now), null);
$ny = array_merge($camp, ['time_zone' => 'America/New_York']);
check('campaign time zone', seo_next_free_slot($ny, [], $now)?->format('Y-m-d H:i'), '2026-11-11 14:00');
$full = [];
for ($i = 0; $i < 12; $i++) $full[] = '2026-11-12 ' . sprintf('%02d', $i) . ':00:00+00'; // 12 already this week (Thursday)
check('max 12 per week', seo_next_free_slot($camp, $full, $now)?->setTimezone(new DateTimeZone('Europe/Paris'))->format('Y-m-d H:i'), '2026-11-16 09:00');
$spring = ['publish_days' => [7], 'publish_times' => ['09:00'], 'time_zone' => 'Europe/Paris', 'start_date' => '2026-03-01', 'end_date' => null];
check('DST: 09:00 Paris after spring change', seo_next_free_slot($spring, [], new DateTimeImmutable('2026-03-28 12:00 UTC'))?->format('Y-m-d H:i'), '2026-03-29 07:00');

// Analytics URL matching
check('page key strips host/query/slash', seo_page_key('https://climanova-energie.fr/blog/Prix-Clim?utm=x#top'), '/blog/prix-clim/');
check('page key www', seo_page_key('https://www.climanova-energie.fr/blog/prix-clim/'), '/blog/prix-clim/');
check('page key decodes', seo_page_key('/blog/r%C3%A9novation/'), '/blog/rénovation/');
check('page key index.html', seo_page_key('/services/climatisation/index.html'), '/services/climatisation/');
check('page key other host', seo_page_key('https://example.com/x'), null);
check('page key root', seo_page_key('https://climanova-energie.fr'), '/');
$merged = seo_merge_page_rows([
    ['day' => '2026-11-01', 'page_path' => '/a/', 'clicks' => 2, 'impressions' => 100, 'position' => 4.0],
    ['day' => '2026-11-01', 'page_path' => '/a/', 'clicks' => 1, 'impressions' => 300, 'position' => 8.0],
]);
check('merged one row', count($merged), 1);
check('merged clicks', $merged[0]['clicks'], 3);
check('weighted position', $merged[0]['position'], 7.0);

check('gsc url prefix keeps slash', seo_normalize_gsc_site('https://climanova-energie.fr'), 'https://climanova-energie.fr/');
check('gsc domain drops slash', seo_normalize_gsc_site('sc-domain:climanova-energie.fr/'), 'sc-domain:climanova-energie.fr');
check('gsc invalid', seo_normalize_gsc_site('climanova-energie.fr'), null);
check('403 message', str_starts_with(seo_google_error(403, 'gsc', 'sa@x.iam.gserviceaccount.com'), 'Add the service account email'), true);
check('conversion consultation', seo_conversion_kind('generate_lead'), 'consultation');
check('conversion registration', seo_conversion_kind('sign_up'), 'registration');
check('conversion none', seo_conversion_kind('page_view'), null);

echo "\n$passed passed, $failed failed\n";
exit($failed ? 1 : 0);
