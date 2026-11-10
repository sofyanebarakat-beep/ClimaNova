<?php
// Applies db/migrations/*.sql in order, once each. Run locally: `npm run db:migrate` (uses .env.local).
// Each file is sent as a single statement batch; keep migrations idempotent (IF NOT EXISTS).
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../admin/_inc/db.php';

db_query('create table if not exists schema_migrations (name text primary key, applied_at timestamptz not null default now())');
$done = array_column(db_query('select name from schema_migrations'), 'name');

$files = glob(__DIR__ . '/migrations/*.sql') ?: [];
sort($files);
foreach ($files as $file) {
    $name = basename($file);
    if (in_array($name, $done, true)) { echo "skip    $name\n"; continue; }
    // The HTTP endpoint runs one statement per call, so split on semicolons at line ends.
    $sql = preg_replace('/^\s*--.*$/m', '', (string) file_get_contents($file));
    foreach (array_filter(array_map('trim', preg_split('/;\s*$/m', $sql))) as $stmt) db_query($stmt);
    db_query('insert into schema_migrations (name) values ($1)', [$name]);
    echo "applied $name\n";
}
