<?php
// Pure tests for the AI layer: cost math, strict schema, validation, response classification.
declare(strict_types=1);
require __DIR__ . '/../admin/_inc/ai_logic.php';

$failed = 0;
$passed = 0;
function check(string $name, $actual, $expected): void
{
    global $failed, $passed;
    if ($actual === $expected) { $passed++; return; }
    $failed++;
    echo "FAIL $name\n  expected: " . var_export($expected, true) . "\n  actual:   " . var_export($actual, true) . "\n";
}

// Cost math
check('estimate adds 20% margin', ai_estimate_cost(1_000_000, 1_000_000, 0.15, 0.60), 0.9);
check('estimate small call', ai_estimate_cost(2000, 1500, 0.15, 0.60), 0.00144);
check('free provider costs nothing', ai_estimate_cost(50_000, 4000, 0, 0), 0.0);
check('real cost has no margin', ai_real_cost(1_000_000, 1_000_000, 0.15, 0.60), 0.75);
check('real cost rounding', ai_real_cost(1234, 567, 0.15, 0.60), 0.000525);
check('token estimate ~3 chars', ai_estimate_tokens(str_repeat('é', 300)), 100);
check('token estimate rounds up', ai_estimate_tokens('abcd'), 2);

// GPT-5 vs others
$sch = ['type' => 'object', 'properties' => [], 'required' => [], 'additionalProperties' => false];
$b5 = ai_request_body('gpt-5-mini', [], $sch, 'x', 800);
check('gpt-5 uses max_completion_tokens', [$b5['max_completion_tokens'] ?? null, $b5['reasoning_effort'] ?? null], [800, 'minimal']);
check('gpt-5 sends no max_tokens or temperature', [isset($b5['max_tokens']), isset($b5['temperature'])], [false, false]);
$b4 = ai_request_body('gpt-4o-mini', [], $sch, 'x', 800, 0.3);
check('others use max_tokens + temperature', [$b4['max_tokens'], $b4['temperature'], isset($b4['reasoning_effort'])], [800, 0.3, false]);
check('strict json_schema format', [$b4['response_format']['type'], $b4['response_format']['json_schema']['strict']], ['json_schema', true]);
check('gemini is not gpt-5', ai_is_gpt5('gemini-2.5-flash'), false);
check('openai/gpt-5 prefix detected', ai_is_gpt5('openai/gpt-5-nano'), true);

// Strict schema from rules
$rule = ['type' => 'object', 'props' => [
    'title' => ['type' => 'string', 'min' => 10, 'max' => 70],
    'intent' => ['type' => 'string', 'enum' => ['info', 'commercial']],
    'score' => ['type' => 'integer', 'min' => 1, 'max' => 100],
    'faq' => ['type' => 'array', 'min' => 2, 'max' => 3, 'items' => ['type' => 'object', 'props' => [
        'question' => ['type' => 'string', 'min' => 5], 'answer' => ['type' => 'string', 'min' => 5]]]],
]];
$schema = ai_schema_from_rules($rule);
check('root lists all properties as required', $schema['required'], ['title', 'intent', 'score', 'faq']);
check('root has additionalProperties false', $schema['additionalProperties'], false);
check('nested object is strict too', [$schema['properties']['faq']['items']['additionalProperties'], $schema['properties']['faq']['items']['required']], [false, ['question', 'answer']]);
check('no min/max lengths in schema', str_contains(json_encode($schema), 'min') || str_contains(json_encode($schema), 'max'), false);
check('enum kept in schema', $schema['properties']['intent']['enum'], ['info', 'commercial']);

// Validation
$good = ['title' => 'Climatisation à Nice : prix 2026', 'intent' => 'commercial', 'score' => 80,
    'faq' => [['question' => 'Quel prix ?', 'answer' => 'Entre 1 500 et 3 500 €.'], ['question' => 'Quelle aide ?', 'answer' => 'MaPrimeRénov.']]];
check('valid output passes', ai_validate($good, $rule), []);
check('title too short', ai_validate(['title' => 'Clim'] + $good, $rule), ['output.title is shorter than 10 characters']);
check('too many faq items', ai_validate(['faq' => array_fill(0, 4, $good['faq'][0])] + $good, $rule), ['output.faq allows at most 3 items']);
check('nested item checked', ai_validate(['faq' => [$good['faq'][0], ['question' => 'Q?', 'answer' => 'Assez long']]] + $good, $rule), ['output.faq[1].question is shorter than 5 characters']);
check('missing field', ai_validate(array_diff_key($good, ['score' => 1]), $rule), ['output.score is missing']);
check('extra field', ai_validate($good + ['extra' => 1], $rule), ['output.extra is not allowed']);
check('wrong enum', ai_validate(['intent' => 'other'] + $good, $rule), ['output.intent has an unexpected value']);
check('integer range', ai_validate(['score' => 150] + $good, $rule), ['output.score is above 100']);
check('float is not integer', ai_validate(['score' => 5.5] + $good, $rule), ['output.score must be a integer']);

// Response classification
$ok = static fn (string $content, string $finish = 'stop') => json_encode(['choices' => [['message' => ['content' => $content], 'finish_reason' => $finish]],
    'usage' => ['prompt_tokens' => 120, 'completion_tokens' => 80]]);
$c = ai_classify_response(200, $ok(json_encode($good)), $rule);
check('ok answer', [$c['kind'], $c['usage']], ['ok', [120, 80]]);
check('cut off is invalid but billed', [ai_classify_response(200, $ok('{"title": "Clim', 'length'), $rule)['kind'], ai_classify_response(200, $ok('{', 'length'), $rule)['usage']], ['invalid_output', [120, 80]]);
check('empty content is invalid', ai_classify_response(200, $ok('  '), $rule)['reason'], 'empty');
check('not json is invalid', ai_classify_response(200, $ok('Voici la réponse'), $rule)['reason'], 'not_json');
check('validation failure is invalid', str_starts_with(ai_classify_response(200, $ok(json_encode(['score' => 0] + $good)), $rule)['reason'], 'validation:'), true);
check('refusal is invalid', ai_classify_response(200, json_encode(['choices' => [['message' => ['content' => null, 'refusal' => 'No'], 'finish_reason' => 'stop']], 'usage' => ['prompt_tokens' => 5, 'completion_tokens' => 1]]), $rule)['reason'], 'refused');
check('429 is a failed request, not billed', [ai_classify_response(429, '{"error":{}}', $rule)['kind'], ai_classify_response(429, '{"error":{}}', $rule)['usage']], ['failed', [0, 0]]);
check('network error is failed', ai_classify_response(0, null, $rule)['kind'], 'failed');
check('401 message', ai_failure_message(401), 'The AI provider refused the API key — check it in Paramètres.');
check('5xx message', ai_failure_message(503), 'The AI provider is having problems right now — please try again later.');

echo "\n$passed passed, $failed failed\n";
exit($failed ? 1 : 0);
