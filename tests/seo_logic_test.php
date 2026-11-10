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

echo "\n$passed passed, $failed failed\n";
exit($failed ? 1 : 0);
