<?php
// Pure-logic tests for the SEO agent. Run: npm test
declare(strict_types=1);
require __DIR__ . '/../admin/_inc/seo_logic.php';

$failed = 0;
$passed = 0;
function check(string $name, $actual, $expected): void
{
    global $failed, $passed;
    if ($actual === $expected) { $passed++; return; }
    $failed++;
    echo "FAIL $name\n  expected: " . var_export($expected, true) . "\n  actual:   " . var_export($actual, true) . "\n";
}

// Budget math
$b = seo_budget_summary(20, 5.5, 2.5, 80, true);
check('committed = recorded + reserved', $b['committed'], 8.0);
check('remaining', $b['remaining'], 12.0);
check('used pct', $b['used_pct'], 40.0);
check('state ok under threshold', $b['state'], 'ok');
check('not blocked', $b['blocked'], false);

check('warning at threshold', seo_budget_summary(20, 16, 0, 80, true)['state'], 'warning');
check('warning counts reservations', seo_budget_summary(20, 10, 6.5, 80, true)['state'], 'warning');
$x = seo_budget_summary(20, 12, 8, 80, true);
check('exhausted when reservations fill allowance', $x['state'], 'exhausted');
check('blocked with hard stop', $x['blocked'], true);
check('remaining never negative', seo_budget_summary(20, 25, 0, 80, true)['remaining'], 0.0);
check('used pct capped at 100', seo_budget_summary(20, 25, 0, 80, true)['used_pct'], 100.0);
check('not blocked without hard stop', seo_budget_summary(20, 25, 0, 80, false)['blocked'], false);
check('zero allowance is exhausted', seo_budget_summary(0, 0, 0, 80, true)['state'], 'exhausted');
check('no float drift', seo_budget_summary(1, 0.1, 0.2, 80, true)['committed'], 0.3);

// Budget gate for a new request
check('allows request that fits', seo_budget_allows(seo_budget_summary(20, 10, 0, 80, true), 9.99), true);
check('allows request exactly at limit', seo_budget_allows(seo_budget_summary(20, 10, 0, 80, true), 10.0), true);
check('blocks request over limit', seo_budget_allows(seo_budget_summary(20, 10, 0, 80, true), 10.01), false);
check('reservations count against new requests', seo_budget_allows(seo_budget_summary(20, 5, 14, 80, true), 2), false);
check('soft limit always allows', seo_budget_allows(seo_budget_summary(20, 30, 0, 80, false), 5), true);

// Week bounds (Paris, Monday start, DST-safe)
[$s, $e] = seo_week_bounds(new DateTimeImmutable('2026-11-11 15:00', new DateTimeZone('UTC')));
check('week starts Monday 00:00 Paris', $s->format('Y-m-d H:i T'), '2026-11-09 00:00 CET');
check('week ends next Monday', $e->format('Y-m-d H:i'), '2026-11-16 00:00');
[$s] = seo_week_bounds(new DateTimeImmutable('2026-11-08 23:30', new DateTimeZone('UTC'))); // Monday 00:30 in Paris
check('UTC Sunday night is Paris Monday', $s->format('Y-m-d'), '2026-11-09');
[$s] = seo_week_bounds(new DateTimeImmutable('2026-11-15 22:59', new DateTimeZone('UTC'))); // Sunday 23:59 Paris
check('Sunday belongs to the previous Monday', $s->format('Y-m-d'), '2026-11-09');
[$s, $e] = seo_week_bounds(new DateTimeImmutable('2026-03-25 12:00', new DateTimeZone('UTC')));
check('DST week start', $s->format('Y-m-d H:i T'), '2026-03-23 00:00 CET');
check('DST week end keeps local midnight', $e->format('Y-m-d H:i T'), '2026-03-30 00:00 CEST');

// Offer extraction from service pages
check('service page becomes offer', seo_offer_from_path('/services/chauffage/', 'Chauffage à Nice — Pompe à chaleur RGE | ClimaNova'),
    ['slug' => 'chauffage', 'name' => 'Chauffage à Nice']);
check('services index is not an offer', seo_offer_from_path('/services/', 'Services'), null);
check('blog is not an offer', seo_offer_from_path('/blog/x/', 'X'), null);
check('missing title falls back to slug', seo_offer_from_path('/services/renovation-energetique/', ''),
    ['slug' => 'renovation-energetique', 'name' => 'Renovation energetique']);
check('money format', seo_money(1234.5), '$1,234.50');

// Offers: path, keywords, priority
check('offer path from slug', seo_offer_path('climatisation'), '/services/climatisation/');
check('keywords trimmed, deduped, empties dropped', seo_parse_keywords(" clim nice, Clim  Nice ,, pompe à chaleur ,"), ['clim nice', 'pompe à chaleur']);
check('keywords accept new lines and semicolons', seo_parse_keywords("a\nb;c"), ['a', 'b', 'c']);
check('empty keywords', seo_parse_keywords('  , ,'), []);
check('keyword length capped', mb_strlen(seo_parse_keywords(str_repeat('é', 120))[0]), 80);
check('at most 30 keywords', count(seo_parse_keywords(implode(',', range(1, 50)))), 30);
check('priority 1 ok', seo_parse_priority('1'), 1);
check('priority 100 ok', seo_parse_priority(' 100 '), 100);
check('priority 0 rejected', seo_parse_priority('0'), null);
check('priority 101 rejected', seo_parse_priority('101'), null);
check('priority decimal rejected', seo_parse_priority('5.5'), null);
check('priority text rejected', seo_parse_priority('high'), null);
check('priority empty rejected', seo_parse_priority(''), null);
$kw = ['clim nice', 'pompe "air-air"', 'a,b', 'back\\slash', 'é'];
check('pg array round trip', seo_pg_array_parse(seo_pg_array($kw)), $kw);
check('pg array parses unquoted words', seo_pg_array_parse('{clim,"pompe à chaleur"}'), ['clim', 'pompe à chaleur']);
check('pg empty array', seo_pg_array_parse('{}'), []);

// Campaign validation
$okInput = ['name' => 'Climatisation Nice été', 'goal' => '', 'language' => 'fr', 'countries' => 'fr, MC ,fr', 'weekly_target' => '12',
    'start_date' => '2026-11-16', 'end_date' => '2027-03-01', 'days' => ['1', '2', '3', '4', '5', '6'], 'times' => '17:00, 9:00',
    'time_zone' => 'Europe/Paris', 'status' => 'active', 'offers' => ['2', '5'], 'automation_enabled' => '1'];
$v = seo_validate_campaign($okInput, [2, 5, 7]);
check('valid campaign has no errors', $v['errors'], []);
check('countries uppercased and deduped', $v['data']['countries'], ['FR', 'MC']);
check('times normalised and sorted', $v['data']['times'], ['09:00', '17:00']);
check('days as ints', $v['data']['days'], [1, 2, 3, 4, 5, 6]);
check('switches parsed', [$v['data']['automation_enabled'], $v['data']['auto_publish']], [true, false]);
check('empty end date becomes null', seo_validate_campaign(['end_date' => ''] + $okInput, [2, 5])['data']['end_date'], null);
$err = static fn (array $over, array $usable = [2, 5]) => seo_validate_campaign(array_merge($okInput, $over), $usable)['errors'];
check('name too short', $err(['name' => 'Clim']), ['Campaign name must be between 5 and 120 characters.']);
check('name too long', $err(['name' => str_repeat('a', 121)]), ['Campaign name must be between 5 and 120 characters.']);
check('no offer', $err(['offers' => []]), ['Choose at least one offer to promote.']);
check('disabled offer refused', $err(['offers' => ['2', '9']]), ['One of the chosen offers is not enabled for promotion — enable it in the Offers tab first.']);
check('duplicate day', $err(['days' => ['1', '1', '2', '3', '4', '5', '6']]), ['Each publishing day can only be chosen once.']);
check('day out of range', $err(['days' => ['0', '8', '1', '2', '3', '4', '5', '6']]), ['Publishing days must be weekdays from Monday to Sunday.']);
check('no days', in_array('Choose at least one publishing day.', $err(['days' => []]), true), true);
check('bad time format', $err(['times' => '9h, 17:00', 'weekly_target' => '6']), ['“9h” is not a time in HH:mm format (for example 09:00).']);
check('25:00 rejected', $err(['times' => '25:00', 'weekly_target' => '6']), ['“25:00” is not a time in HH:mm format (for example 09:00).']);
check('duplicate time', $err(['times' => '09:00, 9:00', 'weekly_target' => '6']), ['The publishing time 09:00 is listed twice.']);
check('more than 4 times', $err(['times' => '08:00,09:00,10:00,11:00,12:00']), ['Use 4 publishing times or fewer.']);
check('end before start', $err(['end_date' => '2026-11-15']), ['The end date cannot be before the start date.']);
check('same start and end ok', $err(['end_date' => '2026-11-16']), []);
check('invalid date', $err(['start_date' => '2026-02-30']), ['Choose a valid start date.']);
check('invalid time zone', $err(['time_zone' => 'Paris/France']), ['“Paris/France” is not a valid time zone (for example Europe/Paris).']);
check('weekly target 13 rejected', $err(['weekly_target' => '13']), ['Weekly article target must be a whole number from 1 to 12.']);
check('weekly target 0 rejected', $err(['weekly_target' => '0']), ['Weekly article target must be a whole number from 1 to 12.']);
check('target above slots', $err(['days' => ['1', '2', '3', '4', '5']]), ['A weekly target of 12 needs at least 12 publishing slots; you have 10 (days × times).']);
check('bad country code', $err(['countries' => 'FR, FRA']), ['“FRA” is not a 2-letter country code (for example FR).']);
check('no country', $err(['countries' => ' ']), ['Add at least one target country code (for example FR).']);
check('bad status', $err(['status' => 'archived']), ['Choose a status from the list.']);
check('bad language', $err(['language' => 'xx']), ['Choose a language from the list.']);
check('days label weekdays', seo_days_label([5, 1, 2, 3, 4]), 'Mon–Fri');
check('days label list', seo_days_label([1, 3, 5]), 'Mon, Wed, Fri');

// Keywords & topics: text matching
check('normalize accents and punctuation', seo_normalize('Climatisation à Nice : Prix 2026 !'), 'climatisation a nice prix 2026');
check('tokens drop stopwords and plurals', seo_tokens('Les pompes à chaleur pour votre maison'), ['pompe', 'chaleur', 'maison']);
check('exact duplicate overlaps', seo_overlaps('Climatisation Nice', 'climatisation  NICE'), true);
check('plural/accents overlap', seo_overlaps('pompe à chaleur nice', 'Pompes a chaleur Nice'), true);
check('short phrase contained in longer one', seo_overlaps('clim réversible nice', 'installation clim réversible nice'), true);
check('different topics do not overlap', seo_overlaps('entretien climatisation nice', 'fuite d’eau urgence plombier'), false);
check('similar long titles overlap', seo_overlaps('Prix climatisation maison 2026 : budget complet', 'Climatisation maison : prix et budget 2026'), true);
check('distinct titles on same offer do not', seo_overlaps('Climatisation : 5 erreurs à éviter avant l’été', 'Prix d’une climatisation réversible à Nice en 2026'), false);
check('brand query detected', [seo_is_brand_query('ClimaNova énergie avis'), seo_is_brand_query('clima nova nice'), seo_is_brand_query('climatisation nice')], [true, true, false]);
$titles = ['Prix pompe à chaleur 2026', 'Fuite d’eau : que faire', 'Climatisation réversible Nice', 'Entretien climatisation Nice', 'Électricien Nice'];
check('related titles ranked by shared words', seo_related_titles($titles, ['Climatisation à Nice', 'clim réversible'], 60), ['Climatisation réversible Nice', 'Entretien climatisation Nice', 'Électricien Nice']);
check('related titles limit', count(seo_related_titles(array_fill(0, 100, 'climatisation x') + ['a'], ['climatisation'], 60)), 1);
$gscRows = [
    ['keys' => ['climanova energie'], 'impressions' => 900, 'clicks' => 80, 'position' => 1.2],
    ['keys' => ['climatisation nice'], 'impressions' => 500, 'clicks' => 3, 'position' => 18.4],
    ['keys' => ['prix clim reversible appartement'], 'impressions' => 120, 'clicks' => 1, 'position' => 22.06],
    ['keys' => ['entretien pac air eau'], 'impressions' => 300, 'clicks' => 0, 'position' => 31],
];
check('gsc ideas: brand + existing removed, sorted by impressions', array_column(seo_gsc_ideas($gscRows, ['Climatisation Nice']), 'query'), ['entretien pac air eau', 'prix clim reversible appartement']);
check('gsc ideas keep real figures', seo_gsc_ideas($gscRows, [])[0], ['query' => 'climatisation nice', 'impressions' => 500, 'clicks' => 3, 'position' => 18.4]);
check('gsc ideas top N', count(seo_gsc_ideas(array_fill(0, 40, ['keys' => ['x y z'], 'impressions' => 1, 'clicks' => 0, 'position' => 9]), [], 25)), 25);

// Weekly plan
check('monday of a Thursday', seo_monday('2026-11-19'), '2026-11-16');
check('monday of a Monday', seo_monday('2026-11-16'), '2026-11-16');
check('monday of a Sunday', seo_monday('2026-11-22'), '2026-11-16');
check('week index 0 in first week', seo_week_index('2026-11-18', '2026-11-16'), 0);
check('week index counts weeks', seo_week_index('2026-11-18', '2026-12-07'), 3);
$camp = ['weekly_target' => 4, 'publish_days' => [1, 3, 5], 'publish_times' => ['17:00', '09:00'], 'start_date' => '2026-11-01', 'end_date' => null, 'week_index' => 0];
$kws = [['id' => 11, 'offer_id' => 1], ['id' => 12, 'offer_id' => 1], ['id' => 21, 'offer_id' => 2], ['id' => 90, 'offer_id' => null]];
$plan = seo_plan_week($camp, '2026-11-16', [1, 2], $kws, []);
check('plan fills weekly target', count($plan), 4);
check('plan dates/times in order', array_map(static fn ($s) => $s['date'] . ' ' . $s['time'], $plan), ['2026-11-16 09:00', '2026-11-16 17:00', '2026-11-18 09:00', '2026-11-18 17:00']);
check('plan balances offers', array_column($plan, 'offer_id'), [1, 2, 1, 2]);
check('plan uses each keyword once, then campaign-wide, then none', array_column($plan, 'keyword_id'), [11, 21, 12, 90]);
check('rotation: next week starts with the next offer', array_column(seo_plan_week(['week_index' => 1] + $camp, '2026-11-23', [1, 2], [], []), 'offer_id'), [2, 1, 2, 1]);
check('rotation with 3 offers, week 2', array_column(seo_plan_week(['week_index' => 2, 'weekly_target' => 3] + $camp, '2026-11-30', [1, 2, 3], [], []), 'offer_id'), [3, 1, 2]);
$taken = [['date' => '2026-11-16', 'offer_id' => 1], ['date' => '2026-11-18', 'offer_id' => 1], ['date' => '2026-11-18', 'offer_id' => 2]];
$refill = seo_plan_week($camp, '2026-11-16', [1, 2], [], $taken);
check('refill uses the free slot, not a taken one', array_map(static fn ($s) => $s['date'] . ' ' . $s['time'], $refill), ['2026-11-16 17:00']);
check('refill goes to the offer with fewest topics', array_column($refill, 'offer_id'), [2]);
check('full week plans nothing', seo_plan_week($camp, '2026-11-16', [1, 2], [], array_fill(0, 4, ['date' => '2026-11-20', 'offer_id' => 1])), []);
check('balance with 3 offers and 7 slots', array_count_values(array_column(seo_plan_week(['weekly_target' => 6, 'publish_days' => [1, 2, 3]] + $camp, '2026-11-16', [1, 2, 3], [], []), 'offer_id')), [1 => 2, 2 => 2, 3 => 2]);
check('dates outside campaign skipped', array_column(seo_plan_week(['start_date' => '2026-11-18', 'end_date' => '2026-11-18'] + $camp, '2026-11-16', [1], [], []), 'date'), ['2026-11-18', '2026-11-18']);
check('no offers plans nothing', seo_plan_week($camp, '2026-11-16', [], $kws, []), []);
check('target above slots is capped by slots', count(seo_plan_week(['weekly_target' => 12] + $camp, '2026-11-16', [1], [], [])), 6);
[$d, $errs] = seo_validate_topic(['title' => '  Clim  à Nice : 5 conseils ', 'angle' => 'x', 'planned_for' => '2026-11-18']);
check('topic validation cleans title', [$d['title'], $errs], ['Clim à Nice : 5 conseils', []]);
check('topic validation errors', seo_validate_topic(['title' => 'Court', 'planned_for' => '2026-13-01'])[1], ['Topic title must be between 10 and 160 characters.', 'Choose a valid date for the topic.']);

// Migration splitter
require __DIR__ . '/../db/sql_split.php';
check('splits simple statements', sql_split_statements("create table a (x int);\n-- note; here\nselect 1;"), ['create table a (x int)', 'select 1']);
check('keeps $$ bodies whole', count(sql_split_statements("create function f() returns int language plpgsql as \$\$ begin return 1; end \$\$;\nselect 2;")), 2);
check('keeps tagged dollar bodies', sql_split_statements("do \$x\$ begin perform 1; end \$x\$;"), ["do \$x\$ begin perform 1; end \$x\$"]);
check('ignores semicolons in strings', sql_split_statements("insert into t values ('a;b', 'it''s');"), ["insert into t values ('a;b', 'it''s')"]);
check('ignores block comments', sql_split_statements("/* x; y */ select 1;"), ['select 1']);

echo "\n$passed passed, $failed failed\n";
exit($failed ? 1 : 0);
