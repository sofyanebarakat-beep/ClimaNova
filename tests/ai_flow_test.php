<?php
// End-to-end test of ai_generate() against tests/mock_ai_server.php and a NEON TEST BRANCH.
// TEST_DATABASE_URL=… php tests/ai_flow_test.php   (refuses to run against the production database)
declare(strict_types=1);

$testUrl = getenv('TEST_DATABASE_URL') ?: '';
$prod = (string) (preg_match('/^DATABASE_URL="?([^"\n]+)/m', (string) @file_get_contents(__DIR__ . '/../.env.local'), $m) ? $m[1] : '');
$host = static fn (string $u) => preg_replace('/-pooler$/', '', explode('.', (string) parse_url($u, PHP_URL_HOST))[0]);
if ($testUrl === '' || ($prod !== '' && $host($testUrl) === $host($prod))) {
    fwrite(STDERR, "Set TEST_DATABASE_URL to a Neon test branch (never production).\n");
    exit(2);
}
putenv("DATABASE_URL=$testUrl");
putenv('AI_PROVIDER=openai');
putenv('AI_API_KEY=test-key');
putenv('AI_BASE_URL=http://127.0.0.1:8098');
putenv('AI_INPUT_COST_PER_MILLION_USD=0.15');
putenv('AI_OUTPUT_COST_PER_MILLION_USD=0.60');

require __DIR__ . '/../admin/_inc/db.php';
require __DIR__ . '/../admin/_inc/ai.php';

$failed = 0;
$passed = 0;
function check(string $name, $actual, $expected): void
{
    global $failed, $passed;
    if ($actual === $expected) { $passed++; return; }
    $failed++;
    echo "FAIL $name\n  expected: " . var_export($expected, true) . "\n  actual:   " . var_export($actual, true) . "\n";
}
$rule = ['type' => 'object', 'props' => ['ok' => ['type' => 'boolean'], 'message' => ['type' => 'string', 'min' => 2, 'max' => 200]]];
$msgs = [['role' => 'user', 'content' => 'Test']];
$run = static function (string $model) use ($rule, $msgs) {
    try { return ['ok', ai_generate('flow_test', $msgs, $rule, 'flow_test', 300, ['model' => $model])]; }
    catch (AiError $e) { return ['error', $e->getMessage()]; }
};
$last = static fn () => db_query("select status, cost_usd::float8 as cost, reserved_usd::float8 as reserved, input_tokens, output_tokens,
                                         metadata->>'outcome' as outcome, error is not null as has_error
                                    from seo_api_usage order by id desc limit 1")[0];
$realCost = round((1000 * 0.15 + 500 * 0.60) / 1e6, 6); // what the mock reports as usage

db_query("update seo_settings set value = '{\"monthly_allowance_usd\": 20, \"warning_threshold_pct\": 80, \"hard_stop\": true}' where key = 'budget'");

// Success
[$k, $r] = $run('mock-ok');
check('ok returns validated data', [$k, $r['data'] ?? null], ['ok', ['ok' => true, 'message' => 'Je fonctionne correctement.']]);
$u = $last();
check('ok row succeeded with real cost', [$u['status'], round((float) $u['cost'], 6), (int) $u['input_tokens'], (int) $u['output_tokens'], $u['outcome']], ['succeeded', $realCost, 1000, 500, 'ok']);
check('reservation was the estimate (> 0)', (float) $u['reserved'] > 0, true);

// Answered but unusable: billed, outcome invalid_output, fixed admin message
foreach (['mock-empty', 'mock-notjson', 'mock-cutoff', 'mock-invalid'] as $m) {
    [$k, $r] = $run($m);
    check("$m shows the unusable-answer message", [$k, $r], ['error', 'The AI returned an unusable answer. Nothing was saved; please try again.']);
    $u = $last();
    check("$m still records the real cost", [$u['status'], round((float) $u['cost'], 6), $u['outcome']], ['succeeded', $realCost, 'invalid_output']);
}

// Request failed: cost 0, status failed
foreach (['mock-401' => 'The AI provider refused the API key — check it in Paramètres.',
          'mock-429' => 'The AI provider’s rate limit was reached — wait a minute and try again.',
          'mock-500' => 'The AI provider is having problems right now — please try again later.'] as $m => $msg) {
    check("$m message", $run($m), ['error', $msg]);
    $u = $last();
    check("$m row failed at cost 0", [$u['status'], (float) $u['cost'], $u['outcome'], $u['has_error']], ['failed', 0.0, 'request_failed', 't']);
}
putenv('AI_BASE_URL=http://127.0.0.1:9'); // nothing listens there
check('network error message', $run('mock-ok'), ['error', 'Could not reach the AI provider — check the connection and try again.']);
check('network error row failed at cost 0', [$last()['status'], (float) $last()['cost']], ['failed', 0.0]);
putenv('AI_BASE_URL=http://127.0.0.1:8098');

check('no reservation left open', (int) db_query("select count(*) c from seo_api_usage where status = 'reserved'")[0]['c'], 0);

// Budget refusal: no row is created and the function's sentence is shown
db_query("update seo_settings set value = '{\"monthly_allowance_usd\": 0.000001, \"warning_threshold_pct\": 80, \"hard_stop\": true}' where key = 'budget'");
$before = (int) db_query('select count(*) c from seo_api_usage')[0]['c'];
[$k, $r] = $run('mock-ok');
check('budget refusal stops the call', [$k, str_starts_with($r, 'The monthly AI budget would be exceeded')], ['error', true]);
check('budget refusal creates no usage row', (int) db_query('select count(*) c from seo_api_usage')[0]['c'], $before);
// Hard stop off: allowed even over the allowance
db_query("update seo_settings set value = '{\"monthly_allowance_usd\": 0.000001, \"warning_threshold_pct\": 80, \"hard_stop\": false}' where key = 'budget'");
check('hard stop off allows the call', $run('mock-ok')[0], 'ok');
db_query("update seo_settings set value = '{\"monthly_allowance_usd\": 20, \"warning_threshold_pct\": 80, \"hard_stop\": true}' where key = 'budget'");

// Missing prices refuse before anything happens
putenv('AI_INPUT_COST_PER_MILLION_USD');
putenv('AI_OUTPUT_COST_PER_MILLION_USD');
check('missing prices message', $run('mock-ok'), ['error', AI_PRICES_MISSING_MESSAGE]);

// Monthly view counts it all
$v = db_query("select requests, failed_requests, invalid_outputs from seo_monthly_usage where month = date_trunc('month', now() at time zone 'Europe/Paris')::date")[0];
check('view counts answered/failed/invalid', [(int) $v['requests'] >= 6, (int) $v['failed_requests'] >= 4, (int) $v['invalid_outputs'] >= 4], [true, true, true]);

echo "\n$passed passed, $failed failed\n";
exit($failed ? 1 : 0);
