<?php
// The ONE server helper for every AI call: budget reservation → strict-JSON request → cost recorded → validation.
// Secrets live only on the server: in the environment, or in .env.ai outside public_html (written by Paramètres).
declare(strict_types=1);

require_once __DIR__ . '/ai_logic.php';

/** Thrown with a one-sentence message that is safe to show an admin. */
final class AiError extends RuntimeException {}

const AI_INVALID_OUTPUT_MESSAGE = 'The AI returned an unusable answer. Nothing was saved; please try again.';
const AI_PRICES_MISSING_MESSAGE = 'Set AI_INPUT_COST_PER_MILLION_USD and AI_OUTPUT_COST_PER_MILLION_USD before generation (use 0 for a free provider).';

function ai_env_file_server(): string
{
    $docroot = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
    return ($docroot !== '' ? dirname($docroot) : dirname(__DIR__, 3)) . '/.env.ai';
}

/** KEY=value pairs from the first env files that exist (server file, then local dev files). */
function ai_env_files(): array
{
    static $vars = null;
    if ($vars !== null) return $vars;
    $vars = [];
    foreach ([dirname(__DIR__, 2) . '/.env.local', dirname(__DIR__, 2) . '/.env.ai', ai_env_file_server()] as $f) {
        if (!is_file($f) || !is_readable($f)) continue;
        foreach (file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            if (preg_match('/^\s*([A-Z0-9_]+)\s*=\s*"?(.*?)"?\s*$/', $line, $m)) $vars[$m[1]] = $m[2]; // later files win
        }
    }
    return $vars;
}

function ai_env(string ...$names): ?string
{
    foreach ($names as $n) {
        $v = getenv($n);
        if ($v !== false && $v !== '') return $v;
        $f = ai_env_files()[$n] ?? null;
        if ($f !== null && $f !== '') return $f;
    }
    return null;
}

/** Current AI settings. OPENAI_* names are accepted as aliases. */
function ai_config(): array
{
    $provider = ai_env('AI_PROVIDER') ?? (ai_env('OPENAI_API_KEY') ? 'openai' : 'gemini');
    $preset = AI_PROVIDERS[$provider] ?? AI_PROVIDERS['gemini'];
    return [
        'provider' => $provider,
        'api_key' => ai_env('AI_API_KEY', 'OPENAI_API_KEY'),
        'base_url' => rtrim(ai_env('AI_BASE_URL') ?? $preset['base_url'], '/'),
        'model' => ai_env('AI_MODEL', 'OPENAI_MODEL') ?? $preset['model'],
        'input_price' => ai_env('AI_INPUT_COST_PER_MILLION_USD', 'OPENAI_INPUT_COST_PER_MILLION_USD'),
        'output_price' => ai_env('AI_OUTPUT_COST_PER_MILLION_USD', 'OPENAI_OUTPUT_COST_PER_MILLION_USD'),
    ];
}

/** Writes the AI settings to .env.ai outside public_html (chmod 600). An empty key keeps the saved one. */
function ai_save_settings(string $provider, string $apiKey, string $model, string $inPrice, string $outPrice): void
{
    if (!isset(AI_PROVIDERS[$provider])) throw new InvalidArgumentException('Choose an AI provider from the list.');
    if (!preg_match('/^[A-Za-z0-9._:\/-]{2,80}$/', $model)) throw new InvalidArgumentException('Enter a valid model name, for example ' . AI_PROVIDERS[$provider]['model'] . '.');
    foreach ([$inPrice, $outPrice] as $p) {
        if (!preg_match('/^\d{1,4}(\.\d{1,6})?$/', $p)) throw new InvalidArgumentException('Prices must be numbers in USD per million tokens, for example 0 or 0.15.');
    }
    $key = trim($apiKey) !== '' ? trim($apiKey) : (string) ai_config()['api_key'];
    if ($key === '') throw new InvalidArgumentException('Paste the API key from your AI provider.');
    if (preg_match('/[\s"]/', $key)) throw new InvalidArgumentException('The API key contains spaces or quotes — copy it again.');
    $lines = ['# AI settings — written by /admin Paramètres. Do not share.',
        "AI_PROVIDER=\"$provider\"", "AI_API_KEY=\"$key\"", "AI_MODEL=\"$model\"",
        "AI_INPUT_COST_PER_MILLION_USD=\"$inPrice\"", "AI_OUTPUT_COST_PER_MILLION_USD=\"$outPrice\""];
    $file = ai_env_file_server();
    if (@file_put_contents($file, implode("\n", $lines) . "\n", LOCK_EX) === false) throw new RuntimeException("Could not write $file");
    @chmod($file, 0600);
}

function ai_settle(int $usageId, string $status, float $cost, int $in, int $out, array $meta, ?string $error = null): void
{
    db_query('update seo_api_usage set status = $2, cost_usd = $3, input_tokens = $4, output_tokens = $5,
                     metadata = metadata || $6::jsonb, error = $7, settled_at = now()
              where id = $1 and status = $8',
        [$usageId, $status, $cost, $in, $out, json_encode($meta, JSON_UNESCAPED_UNICODE), $error, 'reserved']);
}

/**
 * Runs one structured AI call.
 *   $purpose   short label stored with the usage row (e.g. "keyword_ideas")
 *   $messages  Chat Completions messages
 *   $rule      output rules (see ai_logic.php) — builds the strict schema AND validates the answer
 * Returns ['data' => validated array, 'usage_id' => int, 'cost' => float].
 * Throws AiError with a one-sentence admin message. Callers save their own data only after this returns.
 */
function ai_generate(string $purpose, array $messages, array $rule, string $schemaName, int $maxOutput = 1500, array $opts = []): array
{
    $cfg = ai_config();
    if ($cfg['input_price'] === null || $cfg['output_price'] === null) throw new AiError(AI_PRICES_MISSING_MESSAGE);
    if (!$cfg['api_key']) throw new AiError('Add an AI provider API key in Paramètres before generation.');
    $model = $opts['model'] ?? $cfg['model'];
    $inP = (float) $cfg['input_price'];
    $outP = (float) $cfg['output_price'];
    if ($model !== $cfg['model']) {
        // Another model than the configured one: only known OpenAI models, priced from AI_MODEL_PRICES.
        if ($cfg['provider'] !== 'openai' || !isset(AI_MODEL_PRICES[$model])) {
            throw new AiError("The model $model cannot be used with the AI provider set in Paramètres — choose {$cfg['model']}.");
        }
        [$inP, $outP] = AI_MODEL_PRICES[$model];
    }

    // 1. Estimate and reserve (atomic budget check in the database).
    $schema = ai_schema_from_rules($rule);
    $promptTokens = ai_estimate_tokens(json_encode($messages, JSON_UNESCAPED_UNICODE) . json_encode($schema));
    $estimate = ai_estimate_cost($promptTokens, $maxOutput, $inP, $outP);
    $r = db_query('select usage_id, allowed, message from reserve_seo_api_usage($1, $2, $3, $4::numeric)',
        [$cfg['provider'], $model, $purpose, $estimate])[0] ?? null;
    if (!$r || ($r['allowed'] !== 't' && $r['allowed'] !== 'true')) throw new AiError($r['message'] ?? 'The AI budget check failed — please try again.');
    $usageId = (int) $r['usage_id'];

    // 2. Strict structured-output request.
    $body = ai_request_body($model, $messages, $schema, $schemaName, $maxOutput, (float) ($opts['temperature'] ?? 0.4));
    $ch = curl_init($cfg['base_url'] . '/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => (int) ($opts['timeout'] ?? 120),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $cfg['api_key']],
        CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE),
    ]);
    $resp = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $netErr = $resp === false ? curl_error($ch) : null;

    $result = ai_classify_response($status, $resp === false ? null : (string) $resp, $rule);
    $meta = ['provider' => $cfg['provider'], 'http_status' => $status, 'estimate_usd' => $estimate, 'schema' => $schemaName];

    // 5. The request itself failed: nothing billed.
    if ($result['kind'] === 'failed') {
        $detail = $netErr ?? mb_substr((string) (json_decode((string) $resp, true)['error']['message'] ?? $resp), 0, 500);
        ai_settle($usageId, 'failed', 0.0, 0, 0, $meta + ['outcome' => 'request_failed'], $detail);
        error_log("[ai] $purpose failed (HTTP $status): $detail");
        throw new AiError(ai_failure_message($resp === false ? 0 : $status));
    }

    // 3/4. The provider answered, so it was billed: record the real cost FIRST, before anything else is saved.
    [$in, $out] = $result['usage'];
    $cost = ai_real_cost($in, $out, $inP, $outP);
    if ($result['kind'] === 'invalid_output') {
        ai_settle($usageId, 'succeeded', $cost, $in, $out, $meta + ['outcome' => 'invalid_output', 'reason' => $result['reason']]);
        error_log("[ai] $purpose invalid output: {$result['reason']}");
        throw new AiError(AI_INVALID_OUTPUT_MESSAGE);
    }
    ai_settle($usageId, 'succeeded', $cost, $in, $out, $meta + ['outcome' => 'ok']);
    return ['data' => $result['data'], 'usage_id' => $usageId, 'cost' => $cost];
}

/** Lists the models the key can use (GET /models). Used by the "Test connection" button. */
function ai_list_models(): array
{
    $cfg = ai_config();
    if (!$cfg['api_key']) throw new AiError('Add an AI provider API key in Paramètres first.');
    $ch = curl_init($cfg['base_url'] . '/models');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $cfg['api_key']]]);
    $resp = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    if ($resp === false || $status !== 200) throw new AiError(ai_failure_message($resp === false ? 0 : $status));
    return array_map(static fn ($m) => preg_replace('~^models/~', '', (string) ($m['id'] ?? '')), json_decode((string) $resp, true)['data'] ?? []);
}
