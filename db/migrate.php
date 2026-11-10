<?php
// Applies db/migrations/*.sql in order, once each. Run locally: `npm run db:migrate` (uses .env.local).
// Each file runs in ONE transaction together with its schema_migrations row: all or nothing.
// Migrations are additive only (never drop tables or data) and should stay idempotent (IF NOT EXISTS).
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../admin/_inc/db.php';
require __DIR__ . '/sql_split.php';

db_query('create table if not exists schema_migrations (name text primary key, applied_at timestamptz not null default now())');
$done = array_column(db_query('select name from schema_migrations'), 'name');

// Optional: `php db/migrate.php 009_x.sql` applies only the named file(s), so a teammate's
// unfinished migration in the same folder is never applied by accident. `--list` shows pending files.
$only = array_values(array_filter(array_slice($argv, 1), static fn ($a) => $a !== '--list'));
$listOnly = in_array('--list', $argv, true);

$files = glob(__DIR__ . '/migrations/*.sql') ?: [];
sort($files);
foreach ($files as $file) {
    $name = basename($file);
    if ($only && !in_array($name, $only, true)) continue;
    if ($listOnly) { echo (in_array($name, $done, true) ? 'applied ' : 'PENDING ') . "$name\n"; continue; }
    if (in_array($name, $done, true)) { echo "skip    $name\n"; continue; }
    $statements = array_map(static fn ($s) => [$s, []], sql_split_statements((string) file_get_contents($file)));
    $statements[] = ['insert into schema_migrations (name) values ($1)', [$name]];
    try {
        db_batch($statements);
    } catch (Throwable $e) {
        fwrite(STDERR, "FAILED  $name: {$e->getMessage()}\n");
        exit(1);
    }
    echo "applied $name (" . (count($statements) - 1) . " statements)\n";
}
