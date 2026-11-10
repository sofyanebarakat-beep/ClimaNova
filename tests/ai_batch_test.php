<?php
// Batch API client against tests/mock_ai_server.php (no database needed).
declare(strict_types=1);
putenv('AI_PROVIDER=openai');
putenv('AI_API_KEY=test-key');
putenv('AI_BASE_URL=http://127.0.0.1:8098');
putenv('AI_MODEL=mock-ok');
require __DIR__ . '/../admin/_inc/ai_batch.php';

$failed = 0; $passed = 0;
function check(string $name, $actual, $expected): void
{
    global $failed, $passed;
    if ($actual === $expected) { $passed++; return; }
    $failed++;
    echo "FAIL $name\n  expected: " . var_export($expected, true) . "\n  actual:   " . var_export($actual, true) . "\n";
}
$rule = ['type' => 'object', 'props' => ['ok' => ['type' => 'boolean'], 'message' => ['type' => 'string', 'min' => 2]]];
$lines = [];
foreach (['draft-1', 'draft-2', 'draft-3'] as $id) $lines[] = ai_batch_line($id, [['role' => 'user', 'content' => 'Écris un brouillon']], $rule, 'draft', 400);
$first = json_decode($lines[0], true);
check('line has custom_id, method, url', [$first['custom_id'], $first['method'], $first['url']], ['draft-1', 'POST', '/v1/chat/completions']);
check('line body is strict json_schema', [$first['body']['response_format']['json_schema']['strict'], $first['body']['response_format']['json_schema']['schema']['additionalProperties']], [true, false]);
$fileId = ai_batch_upload($lines);
check('upload returns file id', $fileId, 'file-in-1');
$batch = ai_batch_create($fileId, ['campaign' => '1']);
check('create batch with 24h window', [$batch['id'], $batch['input_file_id'], $batch['completion_window'], $batch['endpoint']], ['batch-1', 'file-in-1', '24h', '/v1/chat/completions']);
$st = ai_batch_status('batch-1');
check('status completed with output file', [$st['status'], $st['output_file_id']], ['completed', 'file-out-1']);
$res = ai_batch_results($st['output_file_id'], $rule);
check('results keyed by custom_id', array_keys($res), ['draft-1', 'draft-2', 'draft-3']);
check('valid lines are ok', [$res['draft-1']['kind'], $res['draft-1']['data']['message'], $res['draft-3']['kind']], ['ok', 'Brouillon 0', 'ok']);
check('bad line is invalid_output with usage', [$res['draft-2']['kind'], $res['draft-2']['usage']], ['invalid_output', [100, 50]]);
try { ai_batch_status('missing'); $r = 'no error'; } catch (AiError $e) { $r = $e->getMessage(); }
check('unknown batch → one-sentence error', $r, 'The AI request was rejected by the provider — please try again.');
echo "\n$passed passed, $failed failed\n";
exit($failed ? 1 : 0);
