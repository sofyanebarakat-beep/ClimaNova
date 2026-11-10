<?php
// Optional OpenAI Batch API client (half price, results within 24 h) for scheduled drafts.
// Used only by the automation step later. Works with OpenAI and OpenAI-compatible providers that offer
// /files + /batches (OpenAI, Groq). Budget reservation/recording for batch items is done by the caller.
declare(strict_types=1);

require_once __DIR__ . '/ai.php';

/** One JSONL line per request: {"custom_id", "method", "url", "body"} using the same strict body as ai_generate(). */
function ai_batch_line(string $customId, array $messages, array $rule, string $schemaName, int $maxOutput, ?string $model = null): string
{
    $body = ai_request_body($model ?? ai_config()['model'], $messages, ai_schema_from_rules($rule), $schemaName, $maxOutput);
    return json_encode(['custom_id' => $customId, 'method' => 'POST', 'url' => '/v1/chat/completions', 'body' => $body], JSON_UNESCAPED_UNICODE);
}

function ai_batch_http(string $method, string $path, array $opts = []): array
{
    $cfg = ai_config();
    if (!$cfg['api_key']) throw new AiError('Add an AI provider API key in Paramètres first.');
    $ch = curl_init($cfg['base_url'] . $path);
    $headers = ['Authorization: Bearer ' . $cfg['api_key']];
    $set = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 120, CURLOPT_CUSTOMREQUEST => $method];
    if (isset($opts['json'])) {
        $headers[] = 'Content-Type: application/json';
        $set[CURLOPT_POSTFIELDS] = json_encode($opts['json']);
    } elseif (isset($opts['multipart'])) {
        $set[CURLOPT_POSTFIELDS] = $opts['multipart'];
    }
    $set[CURLOPT_HTTPHEADER] = $headers;
    curl_setopt_array($ch, $set);
    $resp = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    if ($resp === false || $status < 200 || $status >= 300) {
        error_log("[ai-batch] $method $path → HTTP $status: " . mb_substr((string) $resp, 0, 300));
        throw new AiError(ai_failure_message($resp === false ? 0 : $status));
    }
    return ['status' => $status, 'body' => (string) $resp];
}

/** Uploads a JSONL file (one ai_batch_line() per line). Returns the file id. */
function ai_batch_upload(array $lines): string
{
    $tmp = tempnam(sys_get_temp_dir(), 'cn_batch_');
    file_put_contents($tmp, implode("\n", $lines) . "\n");
    try {
        $r = ai_batch_http('POST', '/files', ['multipart' => ['purpose' => 'batch', 'file' => new CURLFile($tmp, 'application/jsonl', 'batch.jsonl')]]);
    } finally {
        @unlink($tmp);
    }
    return (string) (json_decode($r['body'], true)['id'] ?? throw new AiError('The AI provider did not return a file id.'));
}

/** Creates the batch. Returns the batch object (id, status, …). */
function ai_batch_create(string $fileId, array $metadata = []): array
{
    $json = ['input_file_id' => $fileId, 'endpoint' => '/v1/chat/completions', 'completion_window' => '24h'];
    if ($metadata) $json['metadata'] = $metadata;
    return json_decode(ai_batch_http('POST', '/batches', ['json' => $json])['body'], true) ?: [];
}

/** Batch status: validating | in_progress | finalizing | completed | failed | expired | cancelled, plus output_file_id. */
function ai_batch_status(string $batchId): array
{
    return json_decode(ai_batch_http('GET', '/batches/' . rawurlencode($batchId))['body'], true) ?: [];
}

/**
 * Reads the output file and classifies each line with the same rules as ai_generate().
 * Returns [custom_id => ['kind' => ok|invalid_output|failed, 'data' => …, 'usage' => [in, out], 'reason' => …]].
 */
function ai_batch_results(string $outputFileId, array $rule): array
{
    $raw = ai_batch_http('GET', '/files/' . rawurlencode($outputFileId) . '/content')['body'];
    $out = [];
    foreach (preg_split('/\r?\n/', trim($raw)) as $line) {
        if ($line === '') continue;
        $row = json_decode($line, true);
        if (!is_array($row) || !isset($row['custom_id'])) continue;
        $resp = $row['response'] ?? null;
        $out[$row['custom_id']] = $resp
            ? ai_classify_response((int) ($resp['status_code'] ?? 0), json_encode($resp['body'] ?? null), $rule)
            : ['kind' => 'failed', 'data' => null, 'reason' => $row['error']['message'] ?? 'no_response', 'usage' => [0, 0]];
    }
    return $out;
}
