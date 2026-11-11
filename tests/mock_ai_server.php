<?php
// Fake OpenAI-compatible provider for tests/ai_flow_test.php. Behaviour is chosen by the requested model name.
// php -S 127.0.0.1:8098 tests/mock_ai_server.php
$body = json_decode((string) file_get_contents('php://input'), true) ?: [];
header('Content-Type: application/json');
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
// Batch API: /files (upload), /batches (create), /batches/{id} (status), /files/{id}/content (output JSONL)
$state = sys_get_temp_dir() . '/cn_mock_batches.json';
$db = is_file($state) ? (json_decode((string) file_get_contents($state), true) ?: []) : [];
$save = function () use (&$db, $state) { file_put_contents($state, json_encode($db)); };
if ($path === '/files' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $lines = file($_FILES['file']['tmp_name'] ?? '', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    $id = 'file-in-' . (count($db['files'] ?? []) + 1);
    $db['files'][$id] = $lines;
    $save();
    echo json_encode(['id' => $id, 'purpose' => $_POST['purpose'] ?? '', 'lines' => count($lines)]);
    return;
}
if ($path === '/batches' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (getenv('MOCK_BATCH_CREATE_FAILS') === '1') { http_response_code(500); echo '{"error":{"message":"mock create failure"}}'; return; }
    $id = 'batch-' . (count($db['batches'] ?? []) + 1);
    $db['batches'][$id] = ['input' => $body['input_file_id'] ?? null];
    $save();
    echo json_encode(['id' => $id, 'status' => 'validating', 'input_file_id' => $body['input_file_id'] ?? null,
        'completion_window' => $body['completion_window'] ?? null, 'endpoint' => $body['endpoint'] ?? null]);
    return;
}
if (preg_match('~^/batches/(batch-\d+)$~', $path, $m) && isset($db['batches'][$m[1]])) {
    echo json_encode(['id' => $m[1], 'status' => 'completed', 'output_file_id' => 'file-out-' . substr($m[1], 6)]);
    return;
}
if (preg_match('~^/files/file-out-(\d+)/content$~', $path, $m)) {
    $in = $db['batches']['batch-' . $m[1]]['input'] ?? null;
    $out = [];
    foreach ($db['files'][$in] ?? [] as $i => $l) {
        $req = json_decode($l, true);
        $content = $i === 1 ? 'pas du json' : '{"ok": true, "message": "Brouillon ' . $i . '"}';
        $out[] = json_encode(['custom_id' => $req['custom_id'], 'response' => ['status_code' => 200, 'body' => [
            'choices' => [['message' => ['content' => $content], 'finish_reason' => 'stop']], 'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 50]]]]);
    }
    echo implode("\n", $out);
    return;
}
$body = $body;
$rf = $body['response_format'] ?? [];
$schema = $rf['json_schema']['schema'] ?? [];
// The helper must always send a strict json_schema whose objects forbid extra properties and require every field.
if (($rf['type'] ?? '') !== 'json_schema' || ($rf['json_schema']['strict'] ?? false) !== true
    || ($schema['additionalProperties'] ?? true) !== false || ($schema['required'] ?? []) !== array_keys($schema['properties'] ?? [])) {
    http_response_code(400);
    echo '{"error":{"message":"mock: response_format is not strict json_schema"}}';
    return;
}
$model = $body['model'] ?? '';
$schemaName = $rf['json_schema']['name'] ?? '';
$user = json_decode((string) ($body['messages'][1]['content'] ?? '{}'), true) ?: [];
$send = static function (array $data) {
    echo json_encode(['choices' => [['message' => ['role' => 'assistant', 'content' => json_encode($data, JSON_UNESCAPED_UNICODE)], 'finish_reason' => 'stop']],
        'usage' => ['prompt_tokens' => 2000, 'completion_tokens' => 800]]);
};
$short = static fn (string $offer) => mb_strtolower(trim(preg_replace('/\s+(à|a)\s+nice.*$|\s+nice.*$|\s*&.*$/iu', '', $offer)));
if ($model === 'mock-ok' && $schemaName === 'keyword_ideas') {
    $patterns = ['devis gratuit %s', '%s appartement ancien', 'aide financière %s', '%s silencieux chambre', 'délai installation %s',
                 'entretien annuel %s', '%s copropriété autorisation', 'meilleur installateur %s avis', '%s consommation électrique', 'garantie décennale %s'];
    $ideas = [];
    foreach ($patterns as $i => $pat) {
        $o = $user['offers'][$i % count($user['offers'])];
        $ideas[] = ['keyword' => sprintf($pat, $short($o['name'])), 'offerId' => $o['offerId'], 'searchIntent' => $i % 2 ? 'commercial' : 'local',
            'cluster' => 'Cluster ' . ($i % 3 + 1), 'rationale' => 'Phrase de recherche locale liée à cette offre.'];
    }
    $ideas[] = ['keyword' => 'idée offre inconnue nice', 'offerId' => 999999, 'searchIntent' => 'local', 'cluster' => 'Divers', 'rationale' => 'Offre qui n’existe pas dans la campagne.'];
    $dup = $user['existingKeywords'][0] ?? $ideas[0]['keyword'];
    $ideas[] = ['keyword' => mb_strtoupper($dup), 'offerId' => $user['offers'][0]['offerId'], 'searchIntent' => 'local', 'cluster' => 'Divers', 'rationale' => 'Doublon volontaire d’un mot-clé existant.'];
    $send(['ideas' => $ideas]);
    return;
}
if ($model === 'mock-ok' && in_array($schemaName, ['weekly_plan', 'regenerate_topic'], true)) {
    $tpl = ['%s : le guide pour bien choisir son installateur', 'Bruit climatisation : que faire ?', 'Quelles aides financières pour %s en 2026 ?',
            'Entretien annuel : le calendrier idéal pour %s', 'Erreurs fréquentes à éviter lors d’un projet de %s', 'Comparatif des solutions disponibles pour %s',
            'Témoignage client : un chantier réussi pour %s', 'Délais d’intervention et organisation : %s', 'Check-list avant la visite technique : %s',
            'Garanties et assurances à vérifier pour %s', 'Logement ancien ou neuf : adapter %s', 'Copropriété : démarches et autorisations pour %s'];
    $regen = ['Nouveau regard sur %s : ce qu’il faut savoir avant de signer', 'Questions des lecteurs niçois sur %s, nos réponses d’experts',
              'Budget maîtrisé : planifier %s sans mauvaise surprise'];
    $topics = [];
    foreach ($user['slots'] as $i => $s) {
        $off = $short($s['offer']);
        $title = $schemaName === 'regenerate_topic'
            ? sprintf($regen[count($user['existingTitles'] ?? []) % count($regen)], $off)
            : (count($user['slots']) > 1 ? sprintf($tpl[$i % count($tpl)], $off) : sprintf('Mythes et idées reçues autour de %s, démêlés', $off));
        $topics[] = ['slot' => $s['slot'], 'title' => $title,
            'angle' => 'Le lecteur se demande comment avancer sur ' . $off . ' ; l’article répond point par point et renvoie vers ' . $s['offerPage'] . '.'];
    }
    $send(['topics' => $topics]);
    return;
}
$reply = static function (?string $content, string $finish = 'stop') {
    echo json_encode(['choices' => [['message' => ['role' => 'assistant', 'content' => $content], 'finish_reason' => $finish]],
        'usage' => ['prompt_tokens' => 1000, 'completion_tokens' => 500]]);
};
match ($model) {
    'mock-ok' => $reply('{"ok": true, "message": "Je fonctionne correctement."}'),
    'mock-empty' => $reply(''),
    'mock-notjson' => $reply('Bien sûr ! Voici ma réponse.'),
    'mock-cutoff' => $reply('{"ok": true, "message": "Je fonc', 'length'),
    'mock-invalid' => $reply('{"ok": true, "message": ""}'),
    'mock-401' => [http_response_code(401), print('{"error":{"message":"Incorrect API key"}}')],
    'mock-429' => [http_response_code(429), print('{"error":{"message":"Rate limit reached"}}')],
    'mock-500' => [http_response_code(500), print('{"error":{"message":"Server error"}}')],
    default => [http_response_code(404), print('{"error":{"message":"unknown model"}}')],
};
