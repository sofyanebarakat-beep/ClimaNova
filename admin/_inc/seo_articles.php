<?php
// Articles & Approval, Weekly Check, Calendar and publishing — server functions.
// Every entry point checks the admin session (seo_require_admin); every AI call goes through ai_generate().
declare(strict_types=1);

require_once __DIR__ . '/seo_agent.php';
require_once __DIR__ . '/ai.php';
require_once __DIR__ . '/seo_public.php';

const SEO_AUDIENCE = 'French people living in France';

/**
 * Fallback sources when no AI source can be confirmed, per offer slug. Replace with your own list of trusted pages.
 * Each list needs at least 2 entries.
 */
const SEO_TRUSTED_SOURCES = [
    'climatisation' => [['ADEME — Agir pour la transition', 'https://agirpourlatransition.ademe.fr/particuliers/'], ['France Rénov’', 'https://france-renov.gouv.fr/']],
    'chauffage' => [['France Rénov’', 'https://france-renov.gouv.fr/'], ['ADEME — Agir pour la transition', 'https://agirpourlatransition.ademe.fr/particuliers/']],
    'electricite' => [['Consuel', 'https://www.consuel.com/'], ['Service-Public.fr', 'https://www.service-public.fr/']],
    'plomberie' => [['Eaufrance', 'https://www.eaufrance.fr/'], ['Service-Public.fr', 'https://www.service-public.fr/']],
    'renovation' => [['France Rénov’', 'https://france-renov.gouv.fr/'], ['ANAH', 'https://www.anah.gouv.fr/']],
    'renovation-energetique' => [['France Rénov’', 'https://france-renov.gouv.fr/'], ['ANAH', 'https://www.anah.gouv.fr/']],
    'entretien' => [['ADEME — Agir pour la transition', 'https://agirpourlatransition.ademe.fr/particuliers/'], ['Service-Public.fr', 'https://www.service-public.fr/']],
    '*' => [['ADEME', 'https://www.ademe.fr/'], ['Service-Public.fr', 'https://www.service-public.fr/']],
];

/* ---------- Reading ---------- */

const SEO_ARTICLE_COLUMNS = "a.id, a.campaign_id, a.offer_id, a.topic_id, a.title, a.slug, a.status, a.focus_keyword, a.related_keywords, a.seo_title,
    a.meta_description, a.language, a.content, a.word_count, a.quality_score, a.quality_notes, a.generation, a.model, a.cost_usd,
    a.scheduled_for, a.published_at, a.published_url, a.publish_attempts, a.last_error, a.rejected_reason, a.created_at, a.updated_at,
    o.name as offer_name, o.path as offer_path, o.slug as offer_slug, c.name as campaign_name, c.auto_publish, c.time_zone,
    c.publish_days, c.publish_times, c.start_date, c.end_date";

function seo_article_row(array $r): array
{
    $r = seo_decode_article($r);
    $r['quality_notes'] = json_decode((string) ($r['quality_notes'] ?? '[]'), true) ?: [];
    $r['generation'] = json_decode((string) ($r['generation'] ?? '{}'), true) ?: [];
    $r['auto_publish'] = seo_pg_bool($r['auto_publish'] ?? null);
    $r['time_zone'] = $r['time_zone'] ?: 'Europe/Paris';
    $r['id'] = (int) $r['id'];
    return $r;
}

function seo_article(int $id): array
{
    seo_require_admin();
    $rows = db_query('select ' . SEO_ARTICLE_COLUMNS . ' from seo_articles a left join seo_offers o on o.id = a.offer_id
        left join seo_campaigns c on c.id = a.campaign_id where a.id = $1', [$id]);
    if (!$rows) throw new InvalidArgumentException('That article could not be found.');
    return seo_article_row($rows[0]);
}

/** Articles for the list (optionally one status, or several separated by commas), newest change first. */
function seo_list_articles(string $status = ''): array
{
    seo_require_admin();
    $statuses = array_values(array_filter(explode(',', $status), static fn ($s) => isset(SEO_ARTICLE_STATUSES[$s])));
    $rows = db_query('select ' . SEO_ARTICLE_COLUMNS . ' from seo_articles a left join seo_offers o on o.id = a.offer_id
        left join seo_campaigns c on c.id = a.campaign_id
        where cardinality($1::text[]) = 0 or a.status = any($1::text[])
        order by a.updated_at desc limit 300', [seo_pg_array($statuses)]);
    return array_map('seo_article_row', $rows);
}

function seo_article_status_counts(): array
{
    seo_require_admin();
    return array_column(db_query('select status, count(*)::int as n from seo_articles group by status'), 'n', 'status');
}

/** Campaigns the generator can use (planning = draft, or active), with their enabled offers and approved topics. */
function seo_generator_options(): array
{
    seo_require_admin();
    [$campaigns, $links, $topics] = db_batch([
        ["select id, name, status, language from seo_campaigns where status in ('draft', 'active') order by (status = 'active') desc, created_at desc"],
        ['select co.campaign_id, o.id, o.name from seo_campaign_offers co join seo_offers o on o.id = co.offer_id
          where o.is_active and o.promotion_enabled order by o.seo_priority desc, o.name'],
        ["select t.id, t.campaign_id, t.offer_id, t.title, t.status, t.planned_for, coalesce(k.keyword, '') as keyword from seo_topics t
          left join seo_keywords k on k.id = t.keyword_id where t.status in ('approved', 'proposed')
          and not exists (select 1 from seo_articles a where a.topic_id = t.id) order by t.planned_for"],
    ], true);
    foreach ($campaigns as &$c) {
        $c['offers'] = array_values(array_filter($links, static fn ($l) => $l['campaign_id'] === $c['id']));
        $c['topics'] = array_values(array_filter($topics, static fn ($t) => $t['campaign_id'] === $c['id']));
    }
    return $campaigns;
}

/** Models the generator offers: known OpenAI models when the provider is OpenAI, else the configured model. */
function seo_article_models(): array
{
    $cfg = ai_config();
    $models = $cfg['provider'] === 'openai' ? array_keys(AI_MODEL_PRICES) : [];
    if (!in_array($cfg['model'], $models, true)) array_unshift($models, $cfg['model']);
    return $models;
}

function seo_default_model(): string
{
    $m = seo_article_models();
    return in_array('gpt-4o-mini', $m, true) ? 'gpt-4o-mini' : $m[0];
}

/* ---------- Versions ---------- */

/** SQL that copies the current row into the history, to run in the same batch as the change. */
function seo_version_stmt(int $id, string $reason): array
{
    return ["insert into seo_article_versions (article_id, reason, snapshot)
             select id, $2, jsonb_build_object('title', title, 'slug', slug, 'status', status, 'seo_title', seo_title,
               'meta_description', meta_description, 'focus_keyword', focus_keyword, 'offer_id', offer_id, 'content', content,
               'word_count', word_count, 'quality_score', quality_score)
             from seo_articles where id = $1", [$id, $reason]];
}

function seo_article_versions(int $id): array
{
    seo_require_admin();
    return array_map(static function ($v) {
        $v['snapshot'] = json_decode((string) $v['snapshot'], true) ?: [];
        return $v;
    }, db_query('select id, created_at, reason, snapshot from seo_article_versions where article_id = $1 order by created_at desc limit 50', [$id]));
}

/* ---------- Duplicates ---------- */

/** One sentence if the article would duplicate another one (or a static blog post), else null. */
function seo_find_duplicate(?int $selfId, string $slug, string $title, string $hash, string $keyword): ?string
{
    if (seo_static_blog_exists($slug)) return "A blog post already exists at /blog/$slug/ — choose another slug.";
    $rows = db_query("select id, title, slug, content_hash, focus_keyword from seo_articles
                      where id <> coalesce($1::bigint, -1) and status <> 'rejected'", [$selfId]);
    $normTitle = seo_normalize($title);
    foreach ($rows as $r) {
        if ($r['slug'] === $slug) return "Another article (#{$r['id']}) already uses the slug “{$slug}”.";
        if (seo_normalize((string) $r['title']) === $normTitle) return "Another article (#{$r['id']}) already has this title.";
        if ($r['content_hash'] === $hash) return "Another article (#{$r['id']}) has the same content.";
        if ($keyword !== '' && $r['focus_keyword'] && seo_overlaps($keyword, (string) $r['focus_keyword'])) {
            return "The focus keyword overlaps article #{$r['id']} (“{$r['focus_keyword']}”) — choose a different keyword.";
        }
    }
    foreach (seo_static_blog_cards() as $c) {
        if (seo_normalize($c['title']) === $normTitle) return 'A published blog post already has this title.';
    }
    return null;
}

/* ---------- External sources ---------- */

/** First public IP of an https host, or null (blocks private/reserved networks). */
function seo_public_ip(string $host): ?string
{
    $ips = @gethostbynamel($host) ?: [];
    if (!$ips) return null;
    foreach ($ips as $ip) {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) return null;
    }
    return $ips[0];
}

/** Final HTTP status of a URL after up to 3 redirects (0 = unreachable or not allowed). */
function seo_url_status(string $url): int
{
    for ($hop = 0; $hop < 4; $hop++) {
        if (!preg_match('~^https://~i', $url)) return 0;
        $host = (string) parse_url($url, PHP_URL_HOST);
        $ip = seo_public_ip($host);
        if ($ip === null) return 0;
        $status = 0;
        $location = null;
        foreach ([true, false] as $head) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_NOBODY => $head, CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_TIMEOUT => 6, CURLOPT_CONNECTTIMEOUT => 4, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_RESOLVE => ["$host:443:$ip"], CURLOPT_RANGE => $head ? null : '0-2047',
                CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; ClimaNovaSourceCheck/1.0; +https://climanova-energie.fr/)',
                CURLOPT_HTTPHEADER => ['Accept: text/html,application/xhtml+xml,*/*;q=0.8', 'Accept-Language: fr-FR,fr;q=0.9'],
            ]);
            $resp = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            if (is_string($resp) && preg_match('/^location:\s*(\S+)/mi', $resp, $m)) $location = trim($m[1]);
            if ($head && in_array($status, [403, 405, 501, 0], true)) continue; // some servers refuse HEAD
            break;
        }
        if ($status >= 300 && $status < 400 && $location) {
            if (str_starts_with($location, '/')) $location = 'https://' . $host . $location;
            $url = $location;
            continue;
        }
        return $status;
    }
    return 0;
}

/**
 * Keeps an AI source only if its page loads; a missing page (404/410) falls back to the site's home page.
 * When nothing can be confirmed, 2 trusted sources for the offer are used. Social networks and our own domain are never accepted.
 * Returns [sources, notes].
 */
function seo_verify_sources(array $aiSources, ?string $offerSlug): array
{
    $kept = [];
    $notes = [];
    foreach (array_slice($aiSources, 0, 4) as $s) {
        $url = trim((string) $s['url']);
        $title = trim(preg_replace('/\s+/u', ' ', (string) $s['title']));
        if (!seo_source_url_allowed($url)) { $notes[] = "Source dropped (not allowed): $url"; continue; }
        $status = seo_url_status($url);
        if ($status >= 200 && $status < 300) { $kept[$url] = ['title' => $title, 'url' => $url]; continue; }
        if (in_array($status, [404, 410], true)) {
            $home = 'https://' . parse_url($url, PHP_URL_HOST) . '/';
            $hs = seo_url_status($home);
            if ($hs >= 200 && $hs < 300) { $kept[$home] = ['title' => $title, 'url' => $home]; $notes[] = "Source page not found, home page used instead: $home"; continue; }
        }
        $notes[] = "Source could not be confirmed (HTTP $status): $url";
    }
    if (!$kept) {
        foreach (array_slice(SEO_TRUSTED_SOURCES[$offerSlug] ?? SEO_TRUSTED_SOURCES['*'], 0, 2) as [$t, $u]) $kept[$u] = ['title' => $t, 'url' => $u];
        $notes[] = 'No AI source could be confirmed — 2 trusted sources for this offer were added.';
    }
    return [array_values($kept), $notes];
}

/* ---------- Generation ---------- */

function seo_article_rule(): array
{
    $s = static fn (int $min, int $max) => ['type' => 'string', 'min' => $min, 'max' => $max];
    return ['type' => 'object', 'props' => [
        'title' => $s(15, 180),
        'seoTitle' => $s(10, 90),
        'metaDescription' => $s(60, 300),
        'slug' => $s(3, 100),
        'focusKeyword' => $s(2, 80),
        'relatedKeywords' => ['type' => 'array', 'min' => 3, 'max' => 8, 'items' => $s(2, 80)],
        'introduction' => $s(100, 3000),
        'quickAnswer' => $s(100, 800),
        'keyTakeaways' => ['type' => 'array', 'min' => 3, 'max' => 5, 'items' => $s(10, 300)],
        'sections' => ['type' => 'array', 'min' => 5, 'max' => 8, 'items' => ['type' => 'object', 'props' => [
            'heading' => $s(5, 140),
            'paragraphs' => ['type' => 'array', 'min' => 3, 'max' => 4, 'items' => $s(60, 3000)],
        ]]],
        'faq' => ['type' => 'array', 'min' => 4, 'max' => 6, 'items' => ['type' => 'object', 'props' => ['question' => $s(10, 200), 'answer' => $s(40, 1500)]]],
        'conclusion' => $s(100, 3000),
        'externalSources' => ['type' => 'array', 'min' => 2, 'max' => 4, 'items' => ['type' => 'object', 'props' => ['title' => $s(3, 160), 'url' => $s(10, 400)]]],
    ]];
}

function seo_article_system_prompt(string $lang, string $tone, int $words): string
{
    $langName = SEO_LANGUAGES[$lang] ?? 'French';
    return "You write SEO blog articles for ClimaNova Energie (https://climanova-energie.fr/), a home-energy installer in Nice and the Alpes-Maritimes "
        . "(air conditioning, heat pumps, heating, electricity, plumbing, energy renovation). Write in $langName for " . SEO_AUDIENCE . ". "
        . "Tone: $tone. Target length: about $words words in total.\n"
        . "Rules:\n"
        . "- Use ONLY the offer facts given in the user message. Never invent prices, guarantees, statistics, figures, quotes, testimonials, certifications or credentials. When a fact is not given, stay general.\n"
        . "- Define key technical terms the first time you use them. Use question-style headings where natural.\n"
        . "- seoTitle: under 60 characters and contains the focus keyword. metaDescription: 90 to 200 characters and contains the focus keyword. slug: lowercase ASCII words joined by hyphens.\n"
        . "- quickAnswer: 40 to 70 words that answer the main question directly (for Google and AI assistants).\n"
        . "- keyTakeaways: 3 to 5 short points. sections: 5 to 8, each with a heading and 3 to 4 full paragraphs. faq: 4 to 6 question/answer pairs. relatedKeywords: 3 to 8.\n"
        . "- Links: you may link ONLY to the URLs in allowedInternalLinks, as Markdown [text](/path/). Write no other links in the text.\n"
        . "- externalSources: 2 to 4 reputable pages (official bodies, public agencies, standards organisations) with exact https URLs you are confident exist. Never social networks, never climanova-energie.fr.\n"
        . "- Text only, with Markdown **bold** and links allowed. No HTML, no headings inside paragraphs, no placeholders like [name].";
}

/** Validates the generator form. Returns [clean, campaign, offer]. */
function seo_generation_input(array $in): array
{
    seo_require_admin();
    $campaignId = (int) ($in['campaign_id'] ?? 0);
    $offerId = (int) ($in['offer_id'] ?? 0);
    $kw = trim(preg_replace('/\s+/u', ' ', (string) ($in['keyword'] ?? '')));
    $lang = (string) ($in['language'] ?? 'fr');
    $words = (int) ($in['target_words'] ?? 1000);
    $tone = (string) ($in['tone'] ?? 'professional');
    $model = (string) ($in['model'] ?? seo_default_model());
    $topicId = ($in['topic_id'] ?? '') !== '' && $in['topic_id'] !== null ? (int) $in['topic_id'] : null;
    if (mb_strlen($kw) < 2 || mb_strlen($kw) > 80) throw new InvalidArgumentException('The focus keyword must be between 2 and 80 characters.');
    if (!isset(SEO_LANGUAGES[$lang])) throw new InvalidArgumentException('Choose a language from the list.');
    if (!in_array($words, SEO_TARGET_LENGTHS, true)) throw new InvalidArgumentException('Choose a target length from the list.');
    if (!isset(SEO_TONES[$tone])) throw new InvalidArgumentException('Choose a tone from the list.');
    if (!in_array($model, seo_article_models(), true)) throw new InvalidArgumentException('Choose an AI model from the list.');
    [$c, $o, $t] = db_batch([
        ['select id, name, goal, status from seo_campaigns where id = $1', [$campaignId]],
        ['select o.id, o.name, o.slug, o.path, o.description, o.target_keywords from seo_campaign_offers co join seo_offers o on o.id = co.offer_id
          where co.campaign_id = $1 and o.id = $2 and o.is_active and o.promotion_enabled', [$campaignId, $offerId]],
        ['select id, title, angle, status from seo_topics where id = $1 and campaign_id = $2', [$topicId, $campaignId]],
    ], true);
    if (!$c || !in_array($c[0]['status'], ['draft', 'active'], true)) throw new InvalidArgumentException('Choose a campaign that is in planning or active.');
    if (!$o) throw new InvalidArgumentException('Choose one of this campaign’s enabled offers.');
    if ($topicId !== null && !$t) throw new InvalidArgumentException('That topic does not belong to this campaign.');
    if ($t && in_array($t[0]['status'], ['skipped', 'rejected'], true)) throw new InvalidArgumentException('This topic was skipped or rejected — choose another topic.');
    if ($t && $t[0]['status'] === 'queued' && empty($in['from_batch'])) throw new InvalidArgumentException('This topic is already waiting in an automatic batch.');
    $o[0]['target_keywords'] = seo_pg_array_parse($o[0]['target_keywords']);
    return [['campaign_id' => $campaignId, 'offer_id' => $offerId, 'keyword' => $kw, 'language' => $lang, 'target_words' => $words,
        'tone' => $tone, 'model' => $model, 'topic_id' => $topicId], $c[0], $o[0], $t[0] ?? null];
}

/** One sentence if this focus keyword overlaps another (non-rejected) article's keyword, else null. */
function seo_keyword_taken(string $keyword, ?int $excludeId = null): ?string
{
    seo_require_admin();
    foreach (db_query("select id, focus_keyword from seo_articles where id <> coalesce($1::bigint, -1) and status <> 'rejected' and focus_keyword is not null", [$excludeId]) as $r) {
        if (seo_overlaps($keyword, $r['focus_keyword'])) return "The focus keyword overlaps article #{$r['id']} (“{$r['focus_keyword']}”) — choose a different keyword.";
    }
    return null;
}

/**
 * Everything needed to ask the AI for an article, without calling it: validated settings, context and the messages.
 * Throws InvalidArgumentException (bad input, or a keyword another article already covers — checked before any money is spent).
 */
function seo_article_prepare(array $input, ?int $articleId = null): array
{
    seo_require_admin();
    [$g, $campaign, $offer, $topic] = seo_generation_input($input);
    $existing = $articleId ? seo_article($articleId) : null;
    $taken = seo_keyword_taken($g['keyword'], $articleId);
    if ($taken) throw new InvalidArgumentException($taken);

    $posts = array_values(array_filter(seo_all_blog_posts(), static fn ($p) => !$existing || $p['path'] !== '/blog/' . $existing['slug'] . '/'));
    $titles = array_column($posts, 'title');
    $relatedTitles = seo_related_titles($titles, array_merge([$g['keyword'], $offer['name']], $offer['target_keywords']), 5);
    $related = array_values(array_filter($posts, static fn ($p) => in_array($p['title'], $relatedTitles, true)));
    $related = array_slice(array_values(array_column($related, null, 'path')), 0, 5);

    $payload = [
        'focusKeyword' => $g['keyword'],
        'offer' => ['name' => $offer['name'], 'page' => $offer['path'], 'facts' => $offer['description'], 'targetKeywords' => $offer['target_keywords']],
        'campaign' => ['name' => $campaign['name'], 'goal' => $campaign['goal']],
        'topic' => $topic ? ['title' => $topic['title'], 'angle' => $topic['angle']] : null,
        'allowedInternalLinks' => array_map(static fn ($p) => ['title' => $p['title'], 'url' => $p['path']], $related),
        'avoidTitles' => seo_related_titles($titles, [$g['keyword'], $offer['name']], 30),
    ];
    return [
        'g' => $g, 'offer' => $offer, 'existing' => $existing, 'related' => $related, 'article_id' => $articleId,
        'rule' => seo_article_rule(), 'schema_name' => 'seo_article',
        'max_tokens' => min(16000, 4 * $g['target_words'] + 1500), // about 4 output tokens per requested word
        'messages' => [
            ['role' => 'system', 'content' => seo_article_system_prompt($g['language'], $g['tone'], $g['target_words'])],
            ['role' => 'user', 'content' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
        ],
    ];
}

/**
 * Turns a validated AI answer into a saved article (status "generated"): link rules, keyword placement, source checks,
 * Markdown order, duplicate rejection, quality checks, version history on regeneration.
 * Returns ['id', 'title', 'words', 'model', 'cost', 'notes'].
 */
function seo_article_finish(array $prep, array $a, float $aiCost, ?int $usageId): array
{
    seo_require_admin();
    @set_time_limit(300);
    ['g' => $g, 'offer' => $offer, 'existing' => $existing, 'related' => $related, 'article_id' => $articleId] = $prep;
    $notes = [];

    // Links: only the given blog posts; everything else becomes plain text.
    $allowed = array_column($related, 'path');
    foreach (['introduction', 'quickAnswer', 'conclusion'] as $f) $a[$f] = seo_restrict_links($a[$f], $allowed);
    $a['keyTakeaways'] = array_map(static fn ($t) => seo_restrict_links($t, $allowed), $a['keyTakeaways']);
    foreach ($a['sections'] as &$s) {
        $s['heading'] = strip_tags(preg_replace('/\[([^\]]*)\]\([^)]*\)/u', '$1', $s['heading']));
        $s['paragraphs'] = array_map(static fn ($p) => seo_restrict_links($p, $allowed), $s['paragraphs']);
    }
    unset($s);
    foreach ($a['faq'] as &$f) { $f['question'] = strip_tags(preg_replace('/\[([^\]]*)\]\([^)]*\)/u', '$1', $f['question'])); $f['answer'] = seo_restrict_links($f['answer'], $allowed); }
    unset($f);

    // Keyword in title and meta description: "keyword: rest", title ≤ 180, meta ≤ 240.
    $kw = $g['keyword'];
    $a['title'] = seo_ensure_keyword(strip_tags($a['title']), $kw, 180);
    $meta = seo_ensure_keyword(strip_tags($a['metaDescription']), $kw, 240);
    $seoTitle = trim(strip_tags($a['seoTitle']));
    if (!seo_contains_keyword($seoTitle, $kw) || mb_strlen($seoTitle) >= 60) {
        $seoTitle = seo_ensure_keyword($seoTitle, $kw, 59);
        $notes[] = 'The SEO title was adjusted to stay under 60 characters with the keyword.';
    }
    $qa = seo_word_count($a['quickAnswer']);
    if ($qa < 40 || $qa > 70) $notes[] = "The quick answer has $qa words (40–70 expected).";
    $metaLen = mb_strlen($meta);
    if ($metaLen < 90 || $metaLen > 200) $notes[] = "The meta description has $metaLen characters (90–200 expected).";

    [$sources, $sourceNotes] = seo_verify_sources($a['externalSources'], $offer['slug']);
    array_push($notes, ...$sourceNotes);

    $slug = seo_slugify($a['slug']) ?: seo_slugify($a['title']);
    if ($existing) $slug = $existing['slug']; // a regeneration keeps its URL
    if (!seo_valid_slug($slug)) $slug = seo_slugify($kw . ' ' . $offer['name']);

    $markdown = seo_build_markdown($a, $g['language'], array_slice($related, 0, 3), $sources, $offer['name'], $offer['path']);
    $hash = seo_content_hash($markdown);
    $dup = seo_find_duplicate($articleId, $slug, $a['title'], $hash, $kw);
    if ($dup) {
        seo_log('generate_article', 'failed', "Generated article rejected as a duplicate: $dup", ['usage_id' => $usageId, 'cost_usd' => $aiCost]);
        throw new InvalidArgumentException("The article was generated but rejected as a duplicate: $dup The cost was still recorded.");
    }

    $content = ['markdown' => $markdown];
    if (!empty($existing['content']['featuredImage'])) $content['featuredImage'] = $existing['content']['featuredImage'];
    $q = seo_quality_checks(['title' => $a['title'], 'meta_description' => $meta, 'focus_keyword' => $kw, 'markdown' => $markdown,
        'featured_image' => $content['featuredImage'] ?? null, 'language' => $g['language'], 'target_words' => $g['target_words'], 'offer_path' => $offer['path']]);
    $allNotes = array_values(array_merge($q['notes'], $notes));
    $cost = $aiCost + (float) ($existing['cost_usd'] ?? 0);
    $fields = [$g['campaign_id'], $g['offer_id'], $g['topic_id'], $a['title'], $slug, $kw, seo_pg_array(array_map('trim', $a['relatedKeywords'])), $seoTitle, $meta,
        $g['language'], json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $q['words'], $q['score'],
        json_encode($allNotes, JSON_UNESCAPED_UNICODE), json_encode($g, JSON_UNESCAPED_UNICODE), $g['model'], $cost, $hash];

    if ($existing) {
        [, $rows] = db_batch([
            seo_version_stmt($articleId, 'Before regeneration'),
            ["update seo_articles set campaign_id = $2, offer_id = $3, topic_id = $4, title = $5, slug = $6, focus_keyword = $7, related_keywords = $8::text[],
                 seo_title = $9, meta_description = $10, language = $11, content = $12::jsonb, word_count = $13, quality_score = $14,
                 quality_notes = $15::jsonb, generation = $16::jsonb, model = $17, cost_usd = $18, content_hash = $19, status = 'generated',
                 scheduled_for = null, rejected_reason = null, updated_at = now()
              where id = $1 returning id", [$articleId, ...$fields]],
        ]);
        $id = (int) $rows[0]['id'];
    } else {
        $stmts = [["insert into seo_articles (campaign_id, offer_id, topic_id, title, slug, focus_keyword, related_keywords, seo_title, meta_description,
                     language, content, word_count, quality_score, quality_notes, generation, model, cost_usd, content_hash, status)
                   values ($1, $2, $3, $4, $5, $6, $7::text[], $8, $9, $10, $11::jsonb, $12, $13, $14::jsonb, $15::jsonb, $16, $17, $18, 'generated')
                   returning id", $fields]];
        if ($g['topic_id']) $stmts[] = ["update seo_topics set status = 'used', updated_at = now() where id = $1", [$g['topic_id']]];
        $id = (int) db_batch($stmts)[0][0]['id'];
    }
    seo_log($existing ? 'regenerate_article' : 'generate_article', 'success',
        ($existing ? 'Article regenerated' : 'Article generated') . ": “{$a['title']}” ({$q['words']} words, quality {$q['score']}%).",
        ['article_id' => $id, 'usage_id' => $usageId, 'cost_usd' => $aiCost, 'model' => $g['model']]);
    return ['id' => $id, 'title' => $a['title'], 'words' => $q['words'], 'model' => $g['model'], 'cost' => $aiCost, 'notes' => $allNotes];
}

/** Generates an article now (new, or a regeneration of $articleId that keeps its featured image). */
function seo_generate_article(array $input, ?int $articleId = null): array
{
    @set_time_limit(300);
    $prep = seo_article_prepare($input, $articleId);
    $r = ai_generate('article', $prep['messages'], $prep['rule'], $prep['schema_name'], $prep['max_tokens'],
        ['model' => $prep['g']['model'], 'timeout' => 240, 'temperature' => 0.5]);
    return seo_article_finish($prep, $r['data'], (float) $r['cost'], (int) $r['usage_id']);
}

/* ---------- Automation hooks (used by the batch / autopilot jobs) ---------- */

/** Generator settings for a topic: its campaign, offer and keyword (or title), campaign language, 1000 words, professional tone. */
function seo_topic_generation_input(int $topicId, array $overrides = []): array
{
    seo_require_admin();
    $t = db_query('select t.id, t.campaign_id, t.offer_id, t.title, k.keyword, c.language from seo_topics t
                   join seo_campaigns c on c.id = t.campaign_id left join seo_keywords k on k.id = t.keyword_id where t.id = $1', [$topicId]);
    if (!$t) throw new InvalidArgumentException('That topic could not be found.');
    $t = $t[0];
    return array_merge(['campaign_id' => $t['campaign_id'], 'offer_id' => $t['offer_id'], 'topic_id' => $t['id'],
        'keyword' => trim((string) $t['keyword']) !== '' ? $t['keyword'] : seo_cut($t['title'], 80),
        'language' => $t['language'], 'target_words' => 1000, 'tone' => 'professional', 'model' => seo_default_model()], $overrides);
}

/**
 * One article request for a topic, WITHOUT calling the API (for a Batch JSONL line).
 * Returns ['body' => Chat Completions body, 'rule' => output rules for ai_validate, 'schemaName' => string, 'input' => generator settings].
 */
function seo_article_batch_body(int $topicId, array $overrides = []): array
{
    $input = seo_topic_generation_input($topicId, $overrides + ['from_batch' => true]);
    $prep = seo_article_prepare($input);
    return [
        'body' => ai_request_body($prep['g']['model'], $prep['messages'], ai_schema_from_rules($prep['rule']), $prep['schema_name'], $prep['max_tokens'], 0.5),
        'rule' => $prep['rule'], 'schemaName' => $prep['schema_name'], 'input' => $prep['g'],
    ];
}

/**
 * Saves a draft (status "generated") from a model answer received elsewhere (batch). The caller has already recorded usage.
 * Same validation, keyword placement, source checks and duplicate checks as seo_generate_article. Returns the article id.
 */
function seo_save_generated_from_output(int $topicId, array $data, float $cost, int $usageId, array $overrides = []): int
{
    $prep = seo_article_prepare(seo_topic_generation_input($topicId, $overrides + ['from_batch' => true]));
    $problems = ai_validate($data, $prep['rule']);
    if ($problems) throw new InvalidArgumentException('The AI answer is not a valid article: ' . implode('; ', array_slice($problems, 0, 3)) . '.');
    return (int) seo_article_finish($prep, $data, $cost, $usageId)['id'];
}

function seo_regenerate_article(int $id): array
{
    $a = seo_article($id);
    if (!in_array($a['status'], ['draft', 'generated', 'review', 'rejected', 'failed'], true)) {
        throw new InvalidArgumentException('Only draft, generated, review, rejected or failed articles can be regenerated.');
    }
    $g = $a['generation'];
    if (!$g) throw new InvalidArgumentException('This article has no saved generation settings, so it cannot be regenerated.');
    if (!in_array($g['model'] ?? '', seo_article_models(), true)) $g['model'] = seo_default_model();
    return seo_generate_article($g, $id);
}

/** Rewrites one H2 section with the AI. FAQ, conclusion, sources and the offer link are protected. */
function seo_rewrite_section(int $id, string $heading): void
{
    @set_time_limit(180);
    $a = seo_article($id);
    if (!in_array($a['status'], ['draft', 'generated', 'review', 'rejected', 'failed'], true)) {
        throw new InvalidArgumentException('Only draft, generated, review, rejected or failed articles can be rewritten.');
    }
    $md = (string) ($a['content']['markdown'] ?? '');
    if (!in_array($heading, seo_rewritable_sections($md, $a['language']), true)) {
        throw new InvalidArgumentException('Choose one of the article’s own H2 sections — the FAQ, conclusion, sources and offer link are protected.');
    }
    $current = '';
    foreach (seo_md_parse($md)['sections'] as [$h, $b]) if ($h === $heading) $current = $b;
    $allowed = array_map(static fn ($l) => $l['url'], array_filter(seo_md_links($md), static fn ($l) => !$l['image'] && str_starts_with($l['url'], '/blog/')));
    $s = static fn (int $min, int $max) => ['type' => 'string', 'min' => $min, 'max' => $max];
    $r = ai_generate('rewrite_section', [
        ['role' => 'system', 'content' => seo_article_system_prompt($a['language'], $a['generation']['tone'] ?? 'professional', 400)
            . "\nRewrite ONE section of an existing article: a clearer heading (question-style where natural) and 3 to 4 full, better paragraphs. Keep the facts; add none."],
        ['role' => 'user', 'content' => json_encode(['articleTitle' => $a['title'], 'focusKeyword' => $a['focus_keyword'], 'offer' => $a['offer_name'],
            'section' => ['heading' => $heading, 'text' => $current], 'allowedInternalLinks' => array_values(array_unique($allowed))], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
    ], ['type' => 'object', 'props' => ['heading' => $s(5, 140), 'paragraphs' => ['type' => 'array', 'min' => 3, 'max' => 4, 'items' => $s(60, 3000)]]],
        'section_rewrite', 2500, ['model' => in_array($a['model'], seo_article_models(), true) ? $a['model'] : seo_default_model()]);
    $paras = array_map(static fn ($p) => seo_restrict_links($p, $allowed), $r['data']['paragraphs']);
    $newHeading = trim(strip_tags(preg_replace('/\[([^\]]*)\]\([^)]*\)/u', '$1', $r['data']['heading'])));
    $newMd = seo_md_replace_section($md, $heading, $newHeading, implode("\n\n", $paras), $a['language']);
    if ($newMd === null) throw new InvalidArgumentException('That section could not be found any more — reload and try again.');
    seo_store_content($a, $newMd, $a['content']['featuredImage'] ?? null, null, "Before rewriting “{$heading}”", (float) $r['cost']);
    seo_log('rewrite_section', 'success', "Section “{$heading}” of article #$id rewritten.", ['article_id' => $id, 'usage_id' => $r['usage_id'], 'cost_usd' => $r['cost']]);
}

/** Saves new Markdown (and featured image) for an article, keeping the previous version; recomputes quality. */
function seo_store_content(array $a, string $md, ?array $featured, ?string $status, string $reason, float $extraCost = 0.0): array
{
    seo_require_admin();
    $content = ['markdown' => $md];
    if ($featured) $content['featuredImage'] = $featured;
    $q = seo_quality_checks(['title' => $a['title'], 'meta_description' => $a['meta_description'], 'focus_keyword' => $a['focus_keyword'],
        'markdown' => $md, 'featured_image' => $featured, 'language' => $a['language'],
        'target_words' => (int) ($a['generation']['target_words'] ?? 1000), 'offer_path' => (string) $a['offer_path']]);
    db_batch([
        seo_version_stmt($a['id'], $reason),
        ['update seo_articles set content = $2::jsonb, word_count = $3, quality_score = $4, quality_notes = $5::jsonb, content_hash = $6,
                cost_usd = cost_usd + $7, status = coalesce($8, status), updated_at = now() where id = $1',
            [$a['id'], json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $q['words'], $q['score'],
             json_encode($q['notes'], JSON_UNESCAPED_UNICODE), seo_content_hash($md), $extraCost, $status]],
    ]);
    return $q;
}

/* ---------- Editing ---------- */

/** Featured image from the edit form, or null. Only our own media URLs (or https) with alt text. */
function seo_featured_from_input(array $in): ?array
{
    $url = trim((string) ($in['fi_url'] ?? ''));
    if ($url === '') return null;
    if (!preg_match('~^https://[^\s"<>]+$~', $url)) throw new InvalidArgumentException('The featured image link must start with https://.');
    $alt = trim(preg_replace('/\s+/u', ' ', (string) ($in['fi_alt'] ?? '')));
    if ($alt === '') throw new InvalidArgumentException('Add alt text to the featured image (what the picture shows).');
    if (mb_strlen($alt) > 200) throw new InvalidArgumentException('The featured image alt text must be 200 characters or fewer.');
    return ['url' => $url, 'alt' => $alt, 'width' => max(0, (int) ($in['fi_width'] ?? 0)), 'height' => max(0, (int) ($in['fi_height'] ?? 0))];
}

/**
 * Saves the edit window. $target 'draft' or 'review'. Review requires every quality check to pass, otherwise the work is
 * saved as a draft and the problems are returned. Returns ['status' => saved status, 'notes' => [...]].
 */
function seo_save_article(int $id, array $in, string $target): array
{
    $a = seo_article($id);
    if (in_array($a['status'], ['publishing', 'published'], true)) throw new InvalidArgumentException('Published articles cannot be edited here.');
    if (!in_array($target, ['draft', 'review'], true)) $target = 'draft';
    $title = trim(preg_replace('/\s+/u', ' ', (string) ($in['title'] ?? '')));
    $slug = trim((string) ($in['slug'] ?? ''));
    $meta = trim(preg_replace('/\s+/u', ' ', (string) ($in['meta_description'] ?? '')));
    $kw = trim(preg_replace('/\s+/u', ' ', (string) ($in['focus_keyword'] ?? '')));
    $seoTitle = trim(preg_replace('/\s+/u', ' ', (string) ($in['seo_title'] ?? '')));
    $offerId = (int) ($in['offer_id'] ?? $a['offer_id']);
    $md = str_replace("\r\n", "\n", (string) ($in['markdown'] ?? ''));
    if (mb_strlen($title) < 1 || mb_strlen($title) > 180) throw new InvalidArgumentException('The title must be between 1 and 180 characters.');
    if (!seo_valid_slug($slug)) throw new InvalidArgumentException('The slug may only use lowercase letters, numbers and single hyphens (3–90 characters).');
    if (mb_strlen($meta) > 240) throw new InvalidArgumentException('The meta description must be 240 characters or fewer.');
    if (mb_strlen($kw) < 2 || mb_strlen($kw) > 80) throw new InvalidArgumentException('The focus keyword must be between 2 and 80 characters.');
    if (mb_strlen($seoTitle) > 70) throw new InvalidArgumentException('The SEO title must be 70 characters or fewer (under 60 is best).');
    if (strlen($md) > 200000) throw new InvalidArgumentException('The article is too long to save.');
    $sources = json_decode((string) ($in['sources'] ?? '[]'), true);
    if (!is_array($sources)) $sources = [];
    $clean = [];
    foreach ($sources as $s) {
        $t = trim(preg_replace('/\s+/u', ' ', (string) ($s['title'] ?? '')));
        $u = trim((string) ($s['url'] ?? ''));
        if ($t === '' || !str_starts_with($u, 'https://')) throw new InvalidArgumentException('Each source needs a name and a link that starts with https://.');
        if (!seo_source_url_allowed($u)) throw new InvalidArgumentException("The source $u is not allowed (social networks and this site cannot be sources).");
        $clean[] = ['title' => mb_substr($t, 0, 160), 'url' => $u];
    }
    $md = seo_md_set_sources($md, $clean, $a['language']);
    $featured = seo_featured_from_input($in);

    if ($offerId !== (int) $a['offer_id']) seo_assert_offer_usable($offerId);
    $offerPath = (string) (db_query('select path from seo_offers where id = $1', [$offerId])[0]['path'] ?? '');
    $hash = seo_content_hash($md);
    $dup = seo_find_duplicate($id, $slug, $title, $hash, $kw);
    if ($dup) throw new InvalidArgumentException($dup);

    $q = seo_quality_checks(['title' => $title, 'meta_description' => $meta, 'focus_keyword' => $kw, 'markdown' => $md, 'featured_image' => $featured,
        'language' => $a['language'], 'target_words' => (int) ($a['generation']['target_words'] ?? 1000), 'offer_path' => $offerPath]);
    $status = $target === 'review' && $q['notes'] ? 'draft' : $target;
    $content = ['markdown' => $md] + ($featured ? ['featuredImage' => $featured] : []);
    db_batch([
        seo_version_stmt($id, 'Before edit'),
        ["update seo_articles set title = $2, slug = $3, meta_description = $4, focus_keyword = $5, seo_title = $6, offer_id = $7,
                content = $8::jsonb, word_count = $9, quality_score = $10, quality_notes = $11::jsonb, content_hash = $12, status = $13,
                scheduled_for = null, submitted_at = case when $13 = 'review' then now() else submitted_at end, updated_at = now()
          where id = $1", [$id, $title, $slug, $meta, $kw, $seoTitle !== '' ? $seoTitle : null, $offerId,
            json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $q['words'], $q['score'],
            json_encode($q['notes'], JSON_UNESCAPED_UNICODE), $hash, $status]],
    ]);
    seo_log('edit_article', 'info', "Article #$id saved as $status.", ['article_id' => $id]);
    return ['status' => $status, 'notes' => $target === 'review' ? $q['notes'] : []];
}

function seo_restore_version(int $id, int $versionId): void
{
    $a = seo_article($id);
    if (in_array($a['status'], ['publishing', 'published'], true)) throw new InvalidArgumentException('Published articles cannot be restored here.');
    $v = db_query('select snapshot from seo_article_versions where id = $1 and article_id = $2', [$versionId, $id]);
    if (!$v) throw new InvalidArgumentException('That version could not be found.');
    $s = json_decode((string) $v[0]['snapshot'], true);
    $md = (string) ($s['content']['markdown'] ?? '');
    if (seo_find_duplicate($id, (string) $s['slug'], (string) $s['title'], seo_content_hash($md), (string) $s['focus_keyword']) !== null) {
        throw new InvalidArgumentException('This version would duplicate another article and cannot be restored.');
    }
    $q = seo_quality_checks(['title' => $s['title'], 'meta_description' => (string) $s['meta_description'], 'focus_keyword' => (string) $s['focus_keyword'],
        'markdown' => $md, 'featured_image' => $s['content']['featuredImage'] ?? null, 'language' => $a['language'],
        'target_words' => (int) ($a['generation']['target_words'] ?? 1000), 'offer_path' => (string) $a['offer_path']]);
    db_batch([
        seo_version_stmt($id, 'Before restoring an older version'),
        ["update seo_articles set title = $2, slug = $3, meta_description = $4, focus_keyword = $5, seo_title = $6, content = $7::jsonb,
                word_count = $8, quality_score = $9, quality_notes = $10::jsonb, content_hash = $11, status = 'draft', scheduled_for = null, updated_at = now()
          where id = $1", [$id, $s['title'], $s['slug'], $s['meta_description'], $s['focus_keyword'], $s['seo_title'] ?? null,
            json_encode($s['content'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $q['words'], $q['score'],
            json_encode($q['notes'], JSON_UNESCAPED_UNICODE), seo_content_hash($md)]],
    ]);
    seo_log('restore_version', 'info', "Article #$id restored to an older version (now a draft).", ['article_id' => $id, 'version_id' => $versionId]);
}

/* ---------- Review & approval ---------- */

function seo_quality_of(array $a): array
{
    return seo_quality_checks(['title' => $a['title'], 'meta_description' => (string) $a['meta_description'], 'focus_keyword' => (string) $a['focus_keyword'],
        'markdown' => (string) ($a['content']['markdown'] ?? ''), 'featured_image' => $a['content']['featuredImage'] ?? null, 'language' => $a['language'],
        'target_words' => (int) ($a['generation']['target_words'] ?? 1000), 'offer_path' => (string) $a['offer_path']]);
}

function seo_quality_message(array $notes): string
{
    return 'Fix these first: ' . implode(' ', $notes);
}

function seo_submit_for_review(int $id): void
{
    $a = seo_article($id);
    if ($a['status'] === 'review') return;
    if (!in_array($a['status'], ['draft', 'generated', 'rejected'], true)) throw new InvalidArgumentException('Only draft, generated or rejected articles can be submitted for review.');
    $q = seo_quality_of($a);
    db_query('update seo_articles set quality_score = $2, quality_notes = $3::jsonb where id = $1', [$id, $q['score'], json_encode($q['notes'], JSON_UNESCAPED_UNICODE)]);
    if ($q['notes']) throw new InvalidArgumentException(seo_quality_message($q['notes']));
    $rows = db_query("update seo_articles set status = 'review', submitted_at = now(), rejected_reason = null, updated_at = now()
                      where id = $1 and status in ('draft', 'generated', 'rejected') returning id", [$id]);
    if (!$rows) throw new InvalidArgumentException('The article changed in the meantime — reload and try again.');
    seo_log('submit_article', 'info', "Article #$id submitted for review.", ['article_id' => $id]);
}

/**
 * Approves an article in review. It is scheduled in the next free slot when the campaign has auto_publish on, or when
 * $alwaysSchedule (Weekly Check). Returns the slot (UTC) or null.
 */
function seo_approve_article(int $id, bool $alwaysSchedule = false): ?DateTimeImmutable
{
    $a = seo_article($id);
    if ($a['status'] !== 'review') throw new InvalidArgumentException('Only articles in review can be approved — submit it for review first.');
    $q = seo_quality_of($a);
    if ($q['notes']) throw new InvalidArgumentException(seo_quality_message($q['notes']));
    $rows = db_query("update seo_articles set status = 'approved', approved_at = now(), quality_score = $2, quality_notes = '[]'::jsonb, updated_at = now()
                      where id = $1 and status = 'review' returning id", [$id, $q['score']]);
    if (!$rows) throw new InvalidArgumentException('The article changed in the meantime — reload and try again.');
    seo_log('approve_article', 'success', "Article #$id approved.", ['article_id' => $id]);
    if ($a['auto_publish'] || $alwaysSchedule) {
        try {
            return seo_schedule_article($id, null);
        } catch (InvalidArgumentException $e) {
            seo_log('schedule_article', 'skipped', "Article #$id approved but not scheduled: " . $e->getMessage(), ['article_id' => $id]);
        }
    }
    return null;
}

function seo_reject_article(int $id, string $reason): void
{
    seo_require_admin();
    $reason = mb_substr(trim($reason), 0, 500);
    $rows = db_query("update seo_articles set status = 'rejected', rejected_reason = nullif($2, ''), scheduled_for = null, updated_at = now()
                      where id = $1 and status in ('draft', 'generated', 'review', 'approved', 'scheduled', 'failed') returning id", [$id, $reason]);
    if (!$rows) throw new InvalidArgumentException('This article cannot be rejected in its current status.');
    seo_log('reject_article', 'info', "Article #$id rejected." . ($reason !== '' ? " Reason: $reason" : ''), ['article_id' => $id]);
}

/* ---------- Scheduling ---------- */

function seo_campaign_schedule(array $a): array
{
    return [
        'publish_days' => $a['publish_days'] ? array_map('intval', seo_pg_array_parse($a['publish_days'])) : [1, 2, 3, 4, 5, 6, 7],
        'publish_times' => $a['publish_times'] ? seo_pg_array_parse($a['publish_times']) : ['09:00'],
        'time_zone' => $a['time_zone'] ?: 'Europe/Paris',
        'start_date' => $a['start_date'] ?: null,
        'end_date' => $a['end_date'] ?: null,
    ];
}

/**
 * Schedules an approved / scheduled / failed article. $localTime null = next free slot of its campaign;
 * otherwise "Y-m-d\TH:i" in the campaign's time zone. Returns the slot in UTC.
 */
function seo_schedule_article(int $id, ?string $localTime): DateTimeImmutable
{
    $a = seo_article($id);
    if (!in_array($a['status'], ['approved', 'scheduled', 'failed'], true)) throw new InvalidArgumentException('Only approved, scheduled or failed articles can be scheduled.');
    $sched = seo_campaign_schedule($a);
    $tz = new DateTimeZone($sched['time_zone']);
    for ($try = 0; $try < 3; $try++) {
        // Slots taken = scheduled/publishing articles; the weekly cap also counts what was already published.
        $occupied = array_column(db_query("select coalesce(case when status = 'published' then published_at end, scheduled_for) as at from seo_articles
            where id <> $1 and (status in ('scheduled', 'publishing') and scheduled_for is not null
                               or status = 'published' and published_at > now() - interval '8 days')", [$id]), 'at');
        if ($localTime === null) {
            $slot = seo_next_free_slot($sched, $occupied, new DateTimeImmutable('now'));
            if (!$slot) throw new InvalidArgumentException('No free publishing slot was found in the campaign’s dates — check its days, times and end date.');
        } else {
            $local = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $localTime, $tz);
            if (!$local) throw new InvalidArgumentException('Choose a valid date and time.');
            if ($local <= new DateTimeImmutable('now')) throw new InvalidArgumentException('Choose a time in the future.');
            $slot = $local->setTimezone(new DateTimeZone('UTC'));
            $week = $local->format('o-W');
            $n = 0;
            foreach ($occupied as $o) {
                $d = (new DateTimeImmutable($o))->setTimezone($tz);
                if ($d->format('Y-m-d H:i') === $local->format('Y-m-d H:i')) throw new InvalidArgumentException('Another article is already scheduled at that time — choose another slot.');
                if ($d->format('o-W') === $week) $n++;
            }
            if ($n >= SEO_MAX_PER_WEEK) throw new InvalidArgumentException('That week already has ' . SEO_MAX_PER_WEEK . ' scheduled articles — choose another week.');
        }
        try {
            $rows = db_query("update seo_articles set status = 'scheduled', scheduled_for = $2::timestamptz, publish_attempts = 0, last_error = null, updated_at = now()
                              where id = $1 and status in ('approved', 'scheduled', 'failed') returning id", [$id, $slot->format(DATE_ATOM)]);
        } catch (DbError $e) {
            if (str_contains($e->getMessage(), 'seo_articles_slot_uidx') || str_contains($e->getMessage(), 'duplicate key')) {
                if ($localTime !== null) throw new InvalidArgumentException('Another article was just scheduled at that time — choose another slot.');
                continue; // someone took this slot at the same moment: try the next one
            }
            throw $e;
        }
        if (!$rows) throw new InvalidArgumentException('The article changed in the meantime — reload and try again.');
        seo_log('schedule_article', 'success', "Article #$id scheduled for " . $slot->setTimezone($tz)->format('Y-m-d H:i') . " ({$sched['time_zone']}).", ['article_id' => $id]);
        return $slot;
    }
    throw new InvalidArgumentException('Could not find a free slot — please try again.');
}

/* ---------- Weekly Check ---------- */

/** Approves several drafts: submit for review (if needed) then approve and schedule. Returns [id => error|null]. */
function seo_weekly_approve(array $ids): array
{
    seo_require_admin();
    $out = [];
    foreach (array_unique(array_map('intval', $ids)) as $id) {
        try {
            seo_submit_for_review($id);
            seo_approve_article($id, true);
            $out[$id] = null;
        } catch (Throwable $e) {
            $out[$id] = $e instanceof InvalidArgumentException || $e instanceof AiError ? $e->getMessage() : admin_error($e, 'approve this article');
        }
    }
    $ok = count(array_filter($out, static fn ($v) => $v === null));
    seo_log('weekly_approve', $ok === count($out) ? 'success' : 'failed', "Weekly Check: $ok of " . count($out) . ' article(s) approved.', ['results' => $out]);
    return $out;
}

/* ---------- Publishing ---------- */

/** Reasons this article cannot go live (empty = OK). */
function seo_publish_problems(array $a): array
{
    $p = [];
    $md = (string) ($a['content']['markdown'] ?? '');
    $fi = $a['content']['featuredImage'] ?? null;
    if (!is_array($fi) || empty($fi['url']) || trim((string) ($fi['alt'] ?? '')) === '') $p[] = 'It has no featured image with alt text.';
    array_push($p, ...seo_unsafe_link_problems($md));
    if (!$a['offer_path'] || !seo_has_link_to($md, (string) $a['offer_path'])) $p[] = 'It has no link to the offer page.';
    if (!seo_valid_slug((string) $a['slug'])) $p[] = 'Its slug is not valid.';
    if (seo_static_blog_exists((string) $a['slug'])) $p[] = "A blog post already exists at /blog/{$a['slug']}/.";
    $dup = db_query("select id from seo_articles where id <> $1 and status = 'published' and (slug = $2 or content_hash = $3) limit 1",
        [$a['id'], $a['slug'], seo_content_hash($md)]);
    if ($dup) $p[] = "It duplicates published article #{$dup[0]['id']}.";
    return $p;
}

/**
 * Publishes one article. Claims it first (status publishing) so it can never publish twice.
 * Returns the public URL. Throws InvalidArgumentException with the reason when it fails (the article is then "failed").
 */
function seo_publish_article(int $id, string $by = 'manual'): string
{
    seo_require_admin();
    $claim = db_query("update seo_articles set status = 'publishing', publish_attempts = publish_attempts + 1, updated_at = now()
                       where id = $1 and status in ('approved', 'scheduled', 'failed') returning publish_attempts", [$id]);
    if (!$claim) throw new InvalidArgumentException('Only approved, scheduled or failed articles can be published (it may already be publishing).');
    $attempt = (int) $claim[0]['publish_attempts'];
    try {
        $a = seo_article($id);
        $problems = seo_publish_problems($a);
        if ($problems) throw new InvalidArgumentException('Not published: ' . implode(' ', $problems));
        $url = SEO_SITE_URL . '/blog/' . $a['slug'] . '/';
        db_query("update seo_articles set status = 'published', published_at = now(), published_url = $2, last_error = null, updated_at = now()
                  where id = $1 and status = 'publishing'", [$id, $url]);
        seo_public_cache_clear();
        seo_log('publish_article', 'success', "Article #$id published ($by): $url", ['article_id' => $id, 'attempt' => $attempt]);
        return $url;
    } catch (Throwable $e) {
        $msg = $e instanceof InvalidArgumentException ? $e->getMessage() : admin_error($e, 'publish the article');
        try {
            db_query("update seo_articles set status = 'failed', last_error = $2, updated_at = now() where id = $1 and status = 'publishing'", [$id, $msg]);
        } catch (Throwable $e2) {
            error_log('[publish] could not mark failed: ' . $e2->getMessage());
        }
        seo_log('publish_article', 'failed', "Article #$id could not be published (attempt $attempt of 3): $msg", ['article_id' => $id, 'attempt' => $attempt]);
        throw new InvalidArgumentException($msg);
    }
}

/**
 * The automatic job: publishes scheduled articles whose time has come and retries failed scheduled ones (max 3 attempts).
 * Articles stuck in "publishing" for 10 minutes are marked failed first.
 */
function seo_publish_due(): array
{
    seo_require_admin();
    db_query("update seo_articles set status = 'failed', last_error = 'Publishing was interrupted — it will be retried.', updated_at = now()
              where status = 'publishing' and updated_at < now() - interval '10 minutes'");
    $due = db_query("select a.id from seo_articles a left join seo_campaigns c on c.id = a.campaign_id
                     where a.scheduled_for <= now() and a.publish_attempts < 3
                       and (a.status = 'scheduled' or a.status = 'failed')
                     order by a.scheduled_for limit 20");
    $done = ['published' => 0, 'failed' => 0];
    foreach ($due as $r) {
        try {
            seo_publish_article((int) $r['id'], 'schedule');
            $done['published']++;
        } catch (Throwable $e) {
            $done['failed']++;
        }
    }
    if ($due) seo_log('publish_due', $done['failed'] ? 'failed' : 'success', "Scheduled publishing: {$done['published']} published, {$done['failed']} failed.", $done);
    return $done;
}

/**
 * The whole publish job in one call: approved articles of auto_publish campaigns take the next free slot,
 * then everything due is published (with recovery of stuck claims and up to 3 attempts).
 */
function seo_publish_job(): array
{
    seo_require_admin();
    $scheduled = 0;
    $rows = db_query("select a.id from seo_articles a join seo_campaigns c on c.id = a.campaign_id
                      where a.status = 'approved' and c.auto_publish order by a.approved_at nulls last, a.id limit 50");
    foreach ($rows as $r) {
        try {
            seo_schedule_article((int) $r['id'], null);
            $scheduled++;
        } catch (Throwable $e) {
            seo_log('schedule_article', 'skipped', "Article #{$r['id']} could not be scheduled: " . ($e instanceof InvalidArgumentException ? $e->getMessage() : admin_error($e, 'schedule it')), ['article_id' => (int) $r['id']]);
        }
    }
    return ['scheduled' => $scheduled] + seo_publish_due();
}

/** Takes a published article off the blog (back to approved). */
function seo_unpublish_article(int $id): void
{
    seo_require_admin();
    $rows = db_query("update seo_articles set status = 'approved', scheduled_for = null, updated_at = now() where id = $1 and status = 'published' returning id", [$id]);
    if (!$rows) throw new InvalidArgumentException('Only published articles can be unpublished.');
    seo_public_cache_clear();
    seo_log('unpublish_article', 'info', "Article #$id removed from the blog.", ['article_id' => $id]);
}

/* ---------- Uploads ---------- */

const SEO_IMAGE_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

/** One-time signed upload link (valid 10 minutes, one file). Creates the image folder if missing. */
function seo_create_upload_link(int $articleId, string $kind, string $mime, int $size): array
{
    $a = seo_article($articleId);
    if (!in_array($kind, ['featured', 'section'], true)) throw new InvalidArgumentException('Unknown image type.');
    if (!isset(SEO_IMAGE_TYPES[$mime])) throw new InvalidArgumentException('Use a JPG, PNG or WebP image.');
    if ($size <= 0 || $size > SEO_IMAGE_MAX_BYTES) throw new InvalidArgumentException('The image must be 5 MB or smaller.');
    seo_media_dir();
    $token = bin2hex(random_bytes(24));
    db_query("insert into seo_upload_tokens (token_hash, article_id, kind, max_bytes, expires_at) values ($1, $2, $3, $4, now() + interval '10 minutes')",
        [hash('sha256', $token), $a['id'], $kind, SEO_IMAGE_MAX_BYTES]);
    return ['uploadUrl' => '/admin/seo-upload?token=' . $token];
}

/**
 * Receives the file for a signed link (called by /admin/seo-upload). Claims the token first so it works only once.
 * Returns ['url', 'width', 'height'].
 */
function seo_receive_upload(string $token, string $body): array
{
    if (!preg_match('/^[a-f0-9]{48}$/', $token)) throw new InvalidArgumentException('This upload link is not valid.');
    $rows = db_query("update seo_upload_tokens set used_at = now() where token_hash = $1 and used_at is null and expires_at > now()
                      returning article_id, kind, max_bytes", [hash('sha256', $token)]);
    if (!$rows) throw new InvalidArgumentException('This upload link has expired or was already used — try the upload again.');
    if (strlen($body) === 0) throw new InvalidArgumentException('The file is empty.');
    if (strlen($body) > (int) $rows[0]['max_bytes']) throw new InvalidArgumentException('The image must be 5 MB or smaller.');
    $info = @getimagesizefromstring($body);
    $ext = $info ? (SEO_IMAGE_TYPES[$info['mime']] ?? null) : null;
    if (!$ext) throw new InvalidArgumentException('Use a JPG, PNG or WebP image.');
    $file = 'a' . (int) $rows[0]['article_id'] . '-' . $rows[0]['kind'] . '-' . bin2hex(random_bytes(8)) . '.' . $ext;
    if (@file_put_contents(seo_media_dir() . '/' . $file, $body, LOCK_EX) === false) throw new RuntimeException('Could not save the image file.');
    db_query('update seo_upload_tokens set file_name = $2 where token_hash = $1', [hash('sha256', $token), $file]);
    return ['url' => seo_media_url($file), 'width' => (int) $info[0], 'height' => (int) $info[1]];
}

/* ---------- Calendar ---------- */

/** Topics planned and articles scheduled/published in the week starting $monday (Paris dates). */
function seo_calendar_week(string $monday): array
{
    seo_require_admin();
    $end = (new DateTimeImmutable($monday))->modify('+7 days')->format('Y-m-d');
    [$topics, $articles] = db_batch([
        ["select t.id, t.title, t.status, t.planned_for, t.campaign_id, c.name as campaign_name, o.name as offer_name,
                 (select a.id from seo_articles a where a.topic_id = t.id limit 1) as article_id
            from seo_topics t join seo_campaigns c on c.id = t.campaign_id left join seo_offers o on o.id = t.offer_id
           where t.planned_for >= $1::date and t.planned_for < $2::date and t.status <> 'rejected' order by t.planned_for, t.id", [$monday, $end]],
        ["select a.id, a.title, a.status, a.scheduled_for, a.published_at, a.slug, a.last_error, coalesce(c.time_zone, 'Europe/Paris') as time_zone,
                 c.name as campaign_name, o.name as offer_name
            from seo_articles a left join seo_campaigns c on c.id = a.campaign_id left join seo_offers o on o.id = a.offer_id
           where (a.status in ('scheduled', 'publishing', 'failed') and a.scheduled_for >= ($1::date - 1) and a.scheduled_for < ($2::date + 1))
              or (a.status = 'published' and a.published_at >= ($1::date - 1) and a.published_at < ($2::date + 1))
           order by coalesce(a.scheduled_for, a.published_at)", [$monday, $end]],
    ], true);
    // Each article sits on the day of its slot in its campaign's time zone.
    $articles = array_values(array_filter(array_map(static function ($a) {
        $at = new DateTimeImmutable((string) ($a['status'] === 'published' ? $a['published_at'] : $a['scheduled_for']));
        $local = $at->setTimezone(new DateTimeZone($a['time_zone']));
        $a['local_day'] = $local->format('Y-m-d');
        $a['local_time'] = $local->format('H:i');
        $a['local_input'] = $local->format('Y-m-d\TH:i');
        return $a;
    }, $articles), static fn ($a) => $a['local_day'] >= $monday && $a['local_day'] < $end));
    $approved = db_query("select a.id, a.title, coalesce(c.time_zone, 'Europe/Paris') as time_zone from seo_articles a
                          left join seo_campaigns c on c.id = a.campaign_id where a.status = 'approved' order by a.approved_at");
    return ['topics' => $topics, 'articles' => $articles, 'approved' => $approved];
}

function seo_move_topic(int $topicId, string $date): void
{
    seo_require_admin();
    if (!seo_valid_date($date)) throw new InvalidArgumentException('Choose a valid date for the topic.');
    $rows = db_query("update seo_topics set planned_for = $2::date, week_start = $3::date, updated_at = now() where id = $1 and status <> 'rejected' returning title",
        [$topicId, $date, seo_monday($date)]);
    if (!$rows) throw new InvalidArgumentException('That topic could not be found.');
    seo_log('move_topic', 'info', "Topic “{$rows[0]['title']}” moved to $date.", ['topic_id' => $topicId]);
}
