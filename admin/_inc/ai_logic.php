<?php
// Pure AI-layer logic: cost math, strict JSON schema builder, output validator. Tested in tests/ai_logic_test.php.
declare(strict_types=1);

/** Providers with an OpenAI-compatible Chat Completions API. Prices are USD per 1M tokens. */
const AI_PROVIDERS = [
    'gemini' => ['label' => 'Google Gemini (free tier)', 'base_url' => 'https://generativelanguage.googleapis.com/v1beta/openai',
                 'model' => 'gemini-3.8-flash', 'input' => '0', 'output' => '0', 'key_url' => 'https://aistudio.google.com/apikey'],
    'groq'   => ['label' => 'Groq (free tier)', 'base_url' => 'https://api.groq.com/openai/v1',
                 'model' => 'openai/gpt-oss-20b', 'input' => '0', 'output' => '0', 'key_url' => 'https://console.groq.com/keys'],
    'openai' => ['label' => 'OpenAI (paid)', 'base_url' => 'https://api.openai.com/v1',
                 'model' => 'gpt-4o-mini', 'input' => '0.15', 'output' => '0.60', 'key_url' => 'https://platform.openai.com/api-keys'],
];

/** Known OpenAI model prices (USD per 1M tokens: input, output), used when an article picks a model other than the configured one. */
const AI_MODEL_PRICES = [
    'gpt-4o-mini' => [0.15, 0.60], 'gpt-4.1-mini' => [0.40, 1.60], 'gpt-4.1-nano' => [0.10, 0.40],
    'gpt-4o' => [2.50, 10.00], 'gpt-4.1' => [2.00, 8.00],
];

/** Rough token count: ~3 characters per token for French text (deliberately on the high side). */
function ai_estimate_tokens(string $text): int
{
    return (int) ceil(mb_strlen($text) / 3);
}

/** Worst-case cost of a call (whole prompt + every allowed output token) plus a 20% margin. */
function ai_estimate_cost(int $promptTokens, int $maxOutputTokens, float $inPerM, float $outPerM): float
{
    return round(($promptTokens * $inPerM + $maxOutputTokens * $outPerM) / 1_000_000 * 1.2, 6);
}

/** Real cost from the usage the provider reported. */
function ai_real_cost(int $inputTokens, int $outputTokens, float $inPerM, float $outPerM): float
{
    return round(($inputTokens * $inPerM + $outputTokens * $outPerM) / 1_000_000, 6);
}

function ai_is_gpt5(string $model): bool
{
    return (bool) preg_match('/^(openai\/)?gpt-5/i', $model);
}

/**
 * Chat Completions request body. GPT-5 family: max_completion_tokens + reasoning_effort "minimal",
 * no temperature. Others: max_tokens + temperature.
 */
function ai_request_body(string $model, array $messages, array $schema, string $schemaName, int $maxOutput, float $temperature = 0.4): array
{
    $body = [
        'model' => $model,
        'messages' => $messages,
        'response_format' => ['type' => 'json_schema', 'json_schema' => ['name' => $schemaName, 'strict' => true, 'schema' => $schema]],
    ];
    if (ai_is_gpt5($model)) {
        $body['max_completion_tokens'] = $maxOutput;
        $body['reasoning_effort'] = 'minimal';
    } else {
        $body['max_tokens'] = $maxOutput;
        $body['temperature'] = $temperature;
    }
    return $body;
}

/*
 * Output rules — one definition drives both the strict schema sent to the AI and the validation afterwards.
 *   ['type' => 'object', 'props' => ['title' => [...], ...]]
 *   ['type' => 'string', 'min' => 10, 'max' => 70, 'enum' => [...]]
 *   ['type' => 'integer'|'number', 'min' => 1, 'max' => 100]
 *   ['type' => 'boolean']
 *   ['type' => 'array', 'items' => [...], 'min' => 3, 'max' => 8]
 * min/max are NOT put in the schema (providers handle them unevenly); ai_validate() enforces them.
 */
function ai_schema_from_rules(array $rule): array
{
    $type = $rule['type'];
    if ($type === 'object') {
        $props = [];
        foreach ($rule['props'] as $name => $r) $props[$name] = ai_schema_from_rules($r);
        return ['type' => 'object', 'properties' => $props, 'required' => array_keys($props), 'additionalProperties' => false];
    }
    if ($type === 'array') return ['type' => 'array', 'items' => ai_schema_from_rules($rule['items'])];
    $s = ['type' => $type];
    if (isset($rule['enum'])) $s['enum'] = $rule['enum'];
    if (isset($rule['description'])) $s['description'] = $rule['description'];
    return $s;
}

/** Returns a list of problems (empty = valid). Paths look like "faq[2].question". */
function ai_validate(mixed $value, array $rule, string $path = 'output'): array
{
    $e = [];
    switch ($rule['type']) {
        case 'object':
            if (!is_array($value) || array_is_list($value) && $value !== []) return ["$path must be an object"];
            foreach ($rule['props'] as $name => $r) {
                if (!array_key_exists($name, $value)) { $e[] = "$path.$name is missing"; continue; }
                array_push($e, ...ai_validate($value[$name], $r, "$path.$name"));
            }
            foreach (array_diff(array_keys($value), array_keys($rule['props'])) as $extra) $e[] = "$path.$extra is not allowed";
            return $e;
        case 'array':
            if (!is_array($value) || !array_is_list($value)) return ["$path must be a list"];
            if (isset($rule['min']) && count($value) < $rule['min']) $e[] = "$path needs at least {$rule['min']} items";
            if (isset($rule['max']) && count($value) > $rule['max']) $e[] = "$path allows at most {$rule['max']} items";
            foreach ($value as $i => $item) array_push($e, ...ai_validate($item, $rule['items'], "{$path}[$i]"));
            return $e;
        case 'string':
            if (!is_string($value)) return ["$path must be text"];
            $len = mb_strlen(trim($value));
            if (isset($rule['min']) && $len < $rule['min']) $e[] = "$path is shorter than {$rule['min']} characters";
            if (isset($rule['max']) && $len > $rule['max']) $e[] = "$path is longer than {$rule['max']} characters";
            if (isset($rule['enum']) && !in_array($value, $rule['enum'], true)) $e[] = "$path has an unexpected value";
            return $e;
        case 'integer':
        case 'number':
            $ok = $rule['type'] === 'integer' ? is_int($value) : (is_int($value) || is_float($value));
            if (!$ok) return ["$path must be a " . $rule['type']];
            if (isset($rule['min']) && $value < $rule['min']) $e[] = "$path is below {$rule['min']}";
            if (isset($rule['max']) && $value > $rule['max']) $e[] = "$path is above {$rule['max']}";
            return $e;
        case 'boolean':
            return is_bool($value) ? [] : ["$path must be true or false"];
    }
    return ["$path has an unknown rule type"];
}

/**
 * Classifies a finished HTTP exchange with the provider.
 * Returns ['kind' => 'ok'|'invalid_output'|'failed', 'data' => ?array, 'reason' => string, 'usage' => [in, out]].
 * 'invalid_output' = the provider answered (and billed) but the content is unusable.
 */
function ai_classify_response(int $status, ?string $body, array $rule): array
{
    $json = $body !== null ? json_decode($body, true) : null;
    // Output = everything billed after the prompt. Gemini leaves its "thinking" tokens out of completion_tokens but counts them in total_tokens.
    $in = (int) ($json['usage']['prompt_tokens'] ?? 0);
    $out = (int) ($json['usage']['completion_tokens'] ?? 0);
    $out = max($out, (int) ($json['usage']['total_tokens'] ?? 0) - $in);
    $usage = [$in, $out];
    if ($status < 200 || $status >= 300 || !is_array($json) || !isset($json['choices'])) {
        return ['kind' => 'failed', 'data' => null, 'reason' => "http_$status", 'usage' => [0, 0]];
    }
    $choice = $json['choices'][0] ?? [];
    $content = $choice['message']['content'] ?? null;
    if (($choice['finish_reason'] ?? '') === 'length') return ['kind' => 'invalid_output', 'data' => null, 'reason' => 'cut_off', 'usage' => $usage];
    if (!empty($choice['message']['refusal'])) return ['kind' => 'invalid_output', 'data' => null, 'reason' => 'refused', 'usage' => $usage];
    if (!is_string($content) || trim($content) === '') return ['kind' => 'invalid_output', 'data' => null, 'reason' => 'empty', 'usage' => $usage];
    $data = json_decode($content, true);
    if (!is_array($data)) return ['kind' => 'invalid_output', 'data' => null, 'reason' => 'not_json', 'usage' => $usage];
    $problems = ai_validate($data, $rule);
    if ($problems) return ['kind' => 'invalid_output', 'data' => null, 'reason' => 'validation: ' . implode('; ', array_slice($problems, 0, 5)), 'usage' => $usage];
    return ['kind' => 'ok', 'data' => $data, 'reason' => 'ok', 'usage' => $usage];
}

/** One sentence for the admin when the request itself failed. */
function ai_failure_message(int $status): string
{
    return match (true) {
        $status === 401 || $status === 403 => 'The AI provider refused the API key — check it in Paramètres.',
        $status === 429 => 'The AI provider’s rate limit was reached — wait a minute and try again.',
        $status === 0 => 'Could not reach the AI provider — check the connection and try again.',
        $status >= 500 => 'The AI provider is having problems right now — please try again later.',
        default => 'The AI request was rejected by the provider — please try again.',
    };
}
