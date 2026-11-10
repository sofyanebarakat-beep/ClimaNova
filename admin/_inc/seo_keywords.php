<?php
// Keywords & Topics server functions. Every entry point checks the admin session; every AI call uses ai_generate().
declare(strict_types=1);

require_once __DIR__ . '/seo_agent.php';
require_once __DIR__ . '/ai.php';
require_once __DIR__ . '/gsc.php';

/** Campaign + its enabled offers (with target keywords). Throws InvalidArgumentException if missing. */
function seo_campaign_context(int $campaignId): array
{
    seo_require_admin();
    [$c, $offers] = db_batch([
        ['select id, name, goal, language, target_countries, weekly_target, start_date, end_date, publish_days, publish_times, time_zone, status
           from seo_campaigns where id = $1', [$campaignId]],
        ['select o.id, o.name, o.slug, o.path, o.description, o.target_keywords, o.seo_priority
           from seo_campaign_offers co join seo_offers o on o.id = co.offer_id
          where co.campaign_id = $1 and o.is_active and o.promotion_enabled
          order by o.seo_priority desc, o.name', [$campaignId]],
    ], true);
    if (!$c) throw new InvalidArgumentException('That campaign could not be found.');
    $c = $c[0];
    $c['publish_days'] = array_map('intval', seo_pg_array_parse($c['publish_days']));
    $c['publish_times'] = seo_pg_array_parse($c['publish_times']);
    $c['target_countries'] = seo_pg_array_parse($c['target_countries']);
    foreach ($offers as &$o) {
        $o['id'] = (int) $o['id'];
        $o['target_keywords'] = seo_pg_array_parse($o['target_keywords']);
    }
    $c['offers'] = $offers;
    return $c;
}

function seo_campaign_options(): array
{
    seo_require_admin();
    return db_query("select id, name, status from seo_campaigns order by (status = 'active') desc, created_at desc");
}

/** Titles that new topics must not repeat: public blog posts, SEO articles and other topics. */
function seo_known_titles(string $root, ?int $excludeTopicId = null): array
{
    $titles = [];
    foreach (analyse_site($root) as $p) {
        if ($p['category'] === 'blog' && $p['title'] !== '') $titles[] = preg_replace('/\s*[|–—-]\s*ClimaNova.*$/u', '', $p['title']);
    }
    [$articles, $topics] = db_batch([
        ['select title from seo_articles', []],
        ["select title from seo_topics where status <> 'rejected' and id <> coalesce($1::bigint, -1)", [$excludeTopicId]],
    ], true);
    return array_merge($titles, array_column($articles, 'title'), array_column($topics, 'title'));
}

function seo_list_keywords(int $campaignId): array
{
    seo_require_admin();
    return db_query('select k.id, k.keyword, k.search_intent, k.cluster, k.rationale, k.priority, k.source, k.offer_id, o.name as offer_name,
                            k.gsc_impressions, k.gsc_clicks, k.gsc_position, k.used_at
                       from seo_keywords k left join seo_offers o on o.id = k.offer_id
                      where k.campaign_id = $1
                      order by (k.used_at is null) desc, k.priority desc, k.created_at desc', [$campaignId]);
}

function seo_offer_in_campaign(array $ctx, ?int $offerId): void
{
    if ($offerId !== null && !in_array($offerId, array_column($ctx['offers'], 'id'), true)) {
        throw new InvalidArgumentException('Choose one of this campaign’s enabled offers, or leave the offer empty for the whole campaign.');
    }
}

/** Adds one keyword. Exact duplicates (after normalising) are refused with a clear sentence. */
function seo_add_keyword(int $campaignId, ?int $offerId, string $text, array $extra = []): int
{
    $ctx = seo_campaign_context($campaignId);
    seo_offer_in_campaign($ctx, $offerId);
    $kw = trim(preg_replace('/\s+/u', ' ', $text));
    if (mb_strlen($kw) < 2 || mb_strlen($kw) > 120) throw new InvalidArgumentException('A keyword must be between 2 and 120 characters.');
    $intent = $extra['search_intent'] ?? 'informational';
    if (!isset(SEO_INTENTS[$intent])) $intent = 'informational';
    $rows = db_query('insert into seo_keywords (campaign_id, offer_id, keyword, normalized, search_intent, cluster, rationale, source,
                                                gsc_impressions, gsc_clicks, gsc_position)
                      values ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11)
                      on conflict (campaign_id, normalized) do nothing returning id',
        [$campaignId, $offerId, $kw, seo_normalize($kw), $intent, $extra['cluster'] ?? null, $extra['rationale'] ?? null,
         $extra['source'] ?? 'manual', $extra['gsc_impressions'] ?? null, $extra['gsc_clicks'] ?? null, $extra['gsc_position'] ?? null]);
    if (!$rows) throw new InvalidArgumentException('“' . $kw . '” is already in this campaign’s keywords.');
    return (int) $rows[0]['id'];
}

/** AI keyword ideas: 8–12 ideas spread across the campaign's offers. Returns ['added' => n, 'dropped' => n]. */
function seo_ai_keyword_ideas(string $root, int $campaignId): array
{
    $ctx = seo_campaign_context($campaignId);
    if (!$ctx['offers']) throw new InvalidArgumentException('This campaign has no enabled offer — enable one in the Offers tab first.');
    $existing = array_column(seo_list_keywords($campaignId), 'keyword');
    $terms = [];
    foreach ($ctx['offers'] as $o) array_push($terms, $o['name'], ...$o['target_keywords']);
    $titles = seo_related_titles(seo_known_titles($root), $terms, 60);

    $payload = [
        'brand' => 'ClimaNova Energie', 'site' => 'https://climanova-energie.fr/', 'audience' => 'French people living in France',
        'language' => $ctx['language'], 'countries' => $ctx['target_countries'],
        'campaign' => ['name' => $ctx['name'], 'goal' => $ctx['goal']],
        'offers' => array_map(static fn ($o) => ['offerId' => $o['id'], 'name' => $o['name'], 'page' => $o['path'],
            'description' => $o['description'], 'targetKeywords' => $o['target_keywords']], $ctx['offers']),
        'existingKeywords' => $existing,
        'existingTitles' => $titles,
    ];
    $rule = ['type' => 'object', 'props' => ['ideas' => ['type' => 'array', 'min' => 8, 'max' => 12, 'items' => ['type' => 'object', 'props' => [
        'keyword' => ['type' => 'string', 'min' => 3, 'max' => 80],
        'offerId' => ['type' => 'integer'],
        'searchIntent' => ['type' => 'string', 'enum' => array_keys(SEO_INTENTS)],
        'cluster' => ['type' => 'string', 'min' => 2, 'max' => 60],
        'rationale' => ['type' => 'string', 'min' => 10, 'max' => 240],
    ]]]]];
    $r = ai_generate('keyword_ideas', [
        ['role' => 'system', 'content' => "You are an SEO strategist for a French home-energy installer (air conditioning, heating, electricity, plumbing, energy renovation) in Nice and the Alpes-Maritimes. "
            . "Suggest search keywords real people in France type into Google, written in the campaign language. "
            . "Return 8 to 12 ideas spread across ALL the offers; offerId must be one of the given offerIds. "
            . "Do not repeat or closely paraphrase existing keywords or titles. Prefer specific, local and long-tail phrases. "
            . "Never invent search volume, difficulty, prices or statistics. The rationale is one sentence explaining why the keyword fits the offer."],
        ['role' => 'user', 'content' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
    ], $rule, 'keyword_ideas', 1800);

    $offerIds = array_column($ctx['offers'], 'id');
    $seen = array_merge($existing, $titles);
    $added = 0;
    $dropped = 0;
    $values = [];
    $params = [];
    foreach ($r['data']['ideas'] as $idea) {
        $kw = trim(preg_replace('/\s+/u', ' ', $idea['keyword']));
        if (!in_array($idea['offerId'], $offerIds, true) || seo_overlaps_any($kw, $seen)) { $dropped++; continue; }
        $seen[] = $kw;
        $b = count($params);
        $values[] = sprintf('($%d, $%d, $%d, $%d, $%d, $%d, $%d, \'ai\')', $b + 1, $b + 2, $b + 3, $b + 4, $b + 5, $b + 6, $b + 7);
        array_push($params, $campaignId, $idea['offerId'], $kw, seo_normalize($kw), $idea['searchIntent'], $idea['cluster'], $idea['rationale']);
    }
    if ($values) {
        $ins = db_query('insert into seo_keywords (campaign_id, offer_id, keyword, normalized, search_intent, cluster, rationale, source)
                         values ' . implode(',', $values) . ' on conflict (campaign_id, normalized) do nothing returning id', $params);
        $added = count($ins);
        $dropped += count($values) - $added;
    }
    seo_log('keyword_ideas', 'success', "AI keyword ideas for “{$ctx['name']}”: $added added, $dropped dropped as duplicates or invalid.",
        ['campaign_id' => $campaignId, 'usage_id' => $r['usage_id'], 'cost_usd' => $r['cost']]);
    return ['added' => $added, 'dropped' => $dropped];
}

/** Free ideas from the last 90 days of real Search Console queries (brand and existing keywords removed). */
function seo_gsc_keyword_ideas(int $campaignId): array
{
    seo_require_admin();
    if (!gsc_config()) throw new InvalidArgumentException('Connect Google Search Console in Paramètres to get free keyword ideas.');
    $existing = array_column(seo_list_keywords($campaignId), 'keyword');
    try {
        $rows = gsc_query(['query'], date('Y-m-d', strtotime('-92 days')), date('Y-m-d', strtotime('-2 days')), 1000);
    } catch (GscError $e) {
        error_log('[gsc ideas] ' . $e->getMessage());
        throw new InvalidArgumentException('Search Console could not be read — check the connection in Paramètres.');
    }
    return seo_gsc_ideas($rows, $existing, 25);
}

/* ---------- Topics ---------- */

function seo_list_topics(int $campaignId, string $monday): array
{
    seo_require_admin();
    return db_query("select t.id, t.title, t.angle, t.planned_for, t.status, t.source, t.offer_id, o.name as offer_name,
                            t.keyword_id, k.keyword
                       from seo_topics t
                       left join seo_offers o on o.id = t.offer_id
                       left join seo_keywords k on k.id = t.keyword_id
                      where t.campaign_id = $1 and t.week_start = $2::date and t.status <> 'rejected'
                      order by t.planned_for, t.id", [$campaignId, $monday]);
}

function seo_topic_rule(int $count): array
{
    return ['type' => 'object', 'props' => ['topics' => ['type' => 'array', 'min' => $count, 'max' => $count, 'items' => ['type' => 'object', 'props' => [
        'slot' => ['type' => 'integer', 'min' => 1, 'max' => $count],
        'title' => ['type' => 'string', 'min' => 20, 'max' => 110],
        'angle' => ['type' => 'string', 'min' => 30, 'max' => 400],
    ]]]]];
}

function seo_topic_system_prompt(): string
{
    return "You plan blog articles for ClimaNova Energie, an RGE-certified installer in Nice (air conditioning, heat pumps, heating, electricity, plumbing, energy renovation). "
        . "Readers are French people living in France. Write titles and angles in the campaign language. "
        . "Each title is specific and useful (ideally 45 to 70 characters), contains the slot's keyword naturally when one is given, and never repeats or paraphrases an existing title. "
        . "The angle is 1 to 2 sentences: the reader's question, what the article will cover, and how it leads to the offer's page. "
        . "No clickbait and no invented prices, statistics or promises.";
}

/**
 * Fills the open slots of one week (up to the weekly target), balancing and rotating offers and using each unused keyword once.
 * Returns ['created' => n, 'rejected' => n, 'open' => slots that were requested].
 */
function seo_generate_week(string $root, int $campaignId, string $monday): array
{
    $ctx = seo_campaign_context($campaignId);
    if (!$ctx['offers']) throw new InvalidArgumentException('This campaign has no enabled offer — enable one in the Offers tab first.');
    if (!seo_valid_date($monday) || seo_monday($monday) !== $monday) throw new InvalidArgumentException('Choose a Monday for the weekly plan.');
    [$planned, $kw] = db_batch([
        ["select planned_for as date, offer_id from seo_topics where campaign_id = $1 and week_start = $2::date and status <> 'rejected'", [$campaignId, $monday]],
        ['select id, keyword, offer_id from seo_keywords where campaign_id = $1 and used_at is null
           and (offer_id is null or offer_id = any($2::bigint[])) order by priority desc, created_at', [$campaignId, '{' . implode(',', array_column($ctx['offers'], 'id')) . '}']],
    ], true);
    $ctx['week_index'] = seo_week_index($ctx['start_date'], $monday);
    $slots = seo_plan_week($ctx, $monday, array_column($ctx['offers'], 'id'), $kw, $planned);
    if (!$slots) {
        throw new InvalidArgumentException(count($planned) >= (int) $ctx['weekly_target']
            ? 'This week already has ' . $ctx['weekly_target'] . ' topic(s), the campaign’s weekly target.'
            : 'No publishing day of this week falls inside the campaign’s dates.');
    }
    $kwById = array_column($kw, 'keyword', 'id');
    $offerById = array_column($ctx['offers'], null, 'id');
    $known = seo_known_titles($root);
    $payload = ['campaign' => ['name' => $ctx['name'], 'goal' => $ctx['goal'], 'language' => $ctx['language']],
        'existingTitles' => seo_related_titles($known, array_column($ctx['offers'], 'name'), 60),
        'slots' => array_map(static fn ($s, $i) => ['slot' => $i + 1, 'date' => $s['date'],
            'offer' => $offerById[$s['offer_id']]['name'], 'offerPage' => $offerById[$s['offer_id']]['path'],
            'keyword' => $s['keyword_id'] ? $kwById[$s['keyword_id']] : null,
            'offerTargetKeywords' => $offerById[$s['offer_id']]['target_keywords']], $slots, array_keys($slots))];
    $r = ai_generate('weekly_plan', [
        ['role' => 'system', 'content' => seo_topic_system_prompt() . ' Return exactly one topic per slot, using the slot numbers given.'],
        ['role' => 'user', 'content' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
    ], seo_topic_rule(count($slots)), 'weekly_plan', 2500);

    $bySlot = [];
    foreach ($r['data']['topics'] as $t) $bySlot[$t['slot']] = $t;
    $created = 0;
    $rejected = 0;
    $stmts = [];
    foreach ($slots as $i => $s) {
        $t = $bySlot[$i + 1] ?? null;
        $title = $t ? trim(preg_replace('/\s+/u', ' ', $t['title'])) : '';
        if (!$t || seo_overlaps_any($title, $known)) { $rejected++; continue; }
        $known[] = $title;
        $stmts[] = ["insert into seo_topics (campaign_id, offer_id, keyword_id, title, normalized, angle, planned_for, week_start, source)
                     values ($1, $2, $3, $4, $5, $6, $7::date, $8::date, 'ai')",
            [$campaignId, $s['offer_id'], $s['keyword_id'], $title, seo_normalize($title), trim($t['angle']), $s['date'], $monday]];
        if ($s['keyword_id']) $stmts[] = ['update seo_keywords set used_at = now(), updated_at = now() where id = $1', [$s['keyword_id']]];
        $created++;
    }
    if ($stmts) db_batch($stmts);
    seo_log('weekly_plan', $created ? 'success' : 'skipped', "Weekly plan for “{$ctx['name']}” ($monday): $created topic(s) created, $rejected rejected as duplicates.",
        ['campaign_id' => $campaignId, 'week' => $monday, 'usage_id' => $r['usage_id'], 'cost_usd' => $r['cost']]);
    return ['created' => $created, 'rejected' => $rejected, 'open' => count($slots)];
}

function seo_topic(int $id): array
{
    seo_require_admin();
    $rows = db_query('select t.*, k.keyword from seo_topics t left join seo_keywords k on k.id = t.keyword_id where t.id = $1', [$id]);
    if (!$rows) throw new InvalidArgumentException('That topic could not be found.');
    return $rows[0];
}

/** Rewrites one topic's title and angle with the AI (same offer, keyword and date). */
function seo_regenerate_topic(string $root, int $topicId): void
{
    $t = seo_topic($topicId);
    $ctx = seo_campaign_context((int) $t['campaign_id']);
    $offer = array_column($ctx['offers'], null, 'id')[(int) $t['offer_id']] ?? null;
    if (!$offer) throw new InvalidArgumentException('This topic’s offer is no longer enabled — enable it in the Offers tab first.');
    $known = seo_known_titles($root, $topicId);
    $known[] = $t['title'];
    $payload = ['campaign' => ['name' => $ctx['name'], 'goal' => $ctx['goal'], 'language' => $ctx['language']],
        'existingTitles' => array_merge([$t['title']], seo_related_titles($known, [$offer['name'], (string) $t['keyword']], 60)),
        'slots' => [['slot' => 1, 'date' => $t['planned_for'], 'offer' => $offer['name'], 'offerPage' => $offer['path'],
            'keyword' => $t['keyword'], 'offerTargetKeywords' => $offer['target_keywords']]]];
    $r = ai_generate('regenerate_topic', [
        ['role' => 'system', 'content' => seo_topic_system_prompt() . ' Write a NEW title and angle that differ clearly from every existing title, including the current one.'],
        ['role' => 'user', 'content' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
    ], seo_topic_rule(1), 'regenerate_topic', 600);
    $title = trim(preg_replace('/\s+/u', ' ', $r['data']['topics'][0]['title']));
    if (seo_overlaps_any($title, $known)) throw new InvalidArgumentException('The AI suggested a title that repeats an existing one — please try again.');
    db_query("update seo_topics set title = $2, normalized = $3, angle = $4, status = 'proposed', updated_at = now() where id = $1",
        [$topicId, $title, seo_normalize($title), trim($r['data']['topics'][0]['angle'])]);
    seo_log('regenerate_topic', 'success', "Topic #$topicId regenerated.", ['usage_id' => $r['usage_id'], 'cost_usd' => $r['cost']]);
}

function seo_update_topic(string $root, int $topicId, array $input): void
{
    $t = seo_topic($topicId);
    [$d, $errors] = seo_validate_topic($input);
    if ($errors) throw new InvalidArgumentException(implode(' ', $errors));
    if (seo_overlaps_any($d['title'], seo_known_titles($root, $topicId))) throw new InvalidArgumentException('This title repeats an existing topic, article or blog post — choose a different one.');
    db_query('update seo_topics set title = $2, normalized = $3, angle = $4, planned_for = $5::date, week_start = $6::date, updated_at = now() where id = $1',
        [$topicId, $d['title'], seo_normalize($d['title']), $d['angle'], $d['planned_for'], seo_monday($d['planned_for'])]);
    unset($t);
}

function seo_approve_topic(int $topicId): void
{
    seo_require_admin();
    $rows = db_query("update seo_topics set status = 'approved', updated_at = now() where id = $1 and status = 'proposed' returning id", [$topicId]);
    if (!$rows) throw new InvalidArgumentException('Only proposed topics can be approved.');
}

function seo_add_topic(string $root, int $campaignId, ?int $offerId, ?int $keywordId, array $input): int
{
    $ctx = seo_campaign_context($campaignId);
    if ($offerId === null) throw new InvalidArgumentException('Choose the offer this topic promotes.');
    seo_offer_in_campaign($ctx, $offerId);
    [$d, $errors] = seo_validate_topic($input);
    if ($errors) throw new InvalidArgumentException(implode(' ', $errors));
    if ($keywordId !== null && !in_array((string) $keywordId, array_column(seo_list_keywords($campaignId), 'id'), true)) {
        throw new InvalidArgumentException('Choose a keyword from this campaign, or leave it empty.');
    }
    if (seo_overlaps_any($d['title'], seo_known_titles($root))) throw new InvalidArgumentException('This title repeats an existing topic, article or blog post — choose a different one.');
    $stmts = [["insert into seo_topics (campaign_id, offer_id, keyword_id, title, normalized, angle, planned_for, week_start, source)
                values ($1, $2, $3, $4, $5, $6, $7::date, $8::date, 'manual') returning id",
        [$campaignId, $offerId, $keywordId, $d['title'], seo_normalize($d['title']), $d['angle'], $d['planned_for'], seo_monday($d['planned_for'])]]];
    if ($keywordId !== null) $stmts[] = ['update seo_keywords set used_at = coalesce(used_at, now()), updated_at = now() where id = $1', [$keywordId]];
    return (int) db_batch($stmts)[0][0]['id'];
}
