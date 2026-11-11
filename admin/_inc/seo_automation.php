<?php
// Automation: batch collection, weekly autopilot, sending due topics for drafting, and the cron guard.
// Runs only when SEO_AUTOMATION_ENABLED is "true" and the caller sent the right Bearer CRON_SECRET.
// Human control stays: drafts are saved as 'generated' and wait on the Weekly Check page; nothing publishes
// without approval and a featured image (enforced by the publishing code).
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/seo_cron.php';           // settings file, Bearer guard, run lock (no article dependencies)
require_once __DIR__ . '/seo_keywords.php';      // planner, keyword research (also loads seo_agent, ai, gsc)
require_once __DIR__ . '/ai_batch.php';
require_once __DIR__ . '/seo_articles.php';      // article hooks: batch body, save from output, publish job

const SEO_BATCH_PROVIDERS = ['openai', 'groq'];  // providers with an OpenAI-compatible /files + /batches API
const SEO_DIRECT_PER_RUN = 2;                     // free providers without batch: drafts generated directly per run
const SEO_SAVES_PER_RUN = 3;                      // saving a draft verifies sources over HTTP (~30 s each)
const SEO_MAX_FAILURES = 3;
const SEO_MAX_TOPIC_ATTEMPTS = 3;                // a topic whose drafts failed this often is no longer sent automatically

function seo_model_prices(string $model): array
{
    seo_require_admin(); // web: only after seo_cron_guard() (SEO_CRON_AUTHORIZED) or an admin session; CLI allowed
    $cfg = ai_config();
    if ($model === $cfg['model']) return [(float) $cfg['input_price'], (float) $cfg['output_price']];
    return defined('AI_MODEL_PRICES') && isset(AI_MODEL_PRICES[$model]) ? AI_MODEL_PRICES[$model] : [(float) $cfg['input_price'], (float) $cfg['output_price']];
}

/* ---------- 1. Collect finished batches ---------- */

/** A topic whose drafting failed goes back to the queue (approved) so a later run retries it. */
function seo_return_topic(int $topicId): void
{
    seo_require_admin(); // web: only after seo_cron_guard() (SEO_CRON_AUTHORIZED) or an admin session; CLI allowed
    db_query("update seo_topics set status = 'approved', draft_attempts = draft_attempts + 1, updated_at = now() where id = $1 and status = 'queued'", [$topicId]);
}

function seo_count_failed_attempt(int $topicId): void
{
    seo_require_admin(); // web: only after seo_cron_guard() (SEO_CRON_AUTHORIZED) or an admin session; CLI allowed
    db_query('update seo_topics set draft_attempts = draft_attempts + 1, updated_at = now() where id = $1', [$topicId]);
}

function seo_settle_failed_item(array $item, string $reason): void
{
    seo_require_admin(); // web: only after seo_cron_guard() (SEO_CRON_AUTHORIZED) or an admin session; CLI allowed
    if ($item['usage_id']) ai_settle((int) $item['usage_id'], 'failed', 0.0, 0, 0, ['batch' => 'true', 'outcome' => 'request_failed'], $reason);
    db_query("update seo_batch_items set status = 'returned', error = $2, settled_at = now() where id = $1", [$item['id'], mb_substr($reason, 0, 500)]);
    seo_return_topic((int) $item['topic_id']);
}

/** Batches never confirmed by the provider within 1 hour are released: reservations freed, topics back in the queue. */
function seo_release_unconfirmed_batches(): int
{
    seo_require_admin(); // web: only after seo_cron_guard() (SEO_CRON_AUTHORIZED) or an admin session; CLI allowed
    $batches = db_query("select id from seo_batches where status = 'submitting' and created_at < now() - interval '1 hour'");
    foreach ($batches as $b) {
        foreach (db_query("select * from seo_batch_items where batch_id = $1 and status = 'pending'", [$b['id']]) as $item) {
            seo_settle_failed_item($item, 'Batch submission was never confirmed.');
        }
        db_query("update seo_batches set status = 'released', error = 'Submission not confirmed within 1 hour.' where id = $1", [$b['id']]);
    }
    return count($batches);
}

/** Reads a provider file of JSONL results into [custom_id => line]. */
function seo_batch_file_lines(?string $fileId): array
{
    seo_require_admin(); // web: only after seo_cron_guard() (SEO_CRON_AUTHORIZED) or an admin session; CLI allowed
    if (!$fileId) return [];
    $out = [];
    foreach (preg_split('/\r?\n/', trim(ai_batch_http('GET', '/files/' . rawurlencode($fileId) . '/content')['body'])) as $line) {
        $row = json_decode($line, true);
        if (is_array($row) && isset($row['custom_id'])) $out[$row['custom_id']] = $row;
    }
    return $out;
}

/**
 * Checks submitted batches; for finished ones, records each item's real cost at HALF price first, then saves the draft.
 * Failed items return their topic to the queue. Returns counts.
 */
function seo_collect_batches(): array
{
    seo_require_admin(); // web: only after seo_cron_guard() (SEO_CRON_AUTHORIZED) or an admin session; CLI allowed
    $r = ['checked' => 0, 'saved' => 0, 'failed' => 0, 'pending' => 0];
    $saves = 0;
    foreach (db_query("select * from seo_batches where status in ('submitted', 'completed') order by id") as $batch) {
        $r['checked']++;
        if ($batch['status'] === 'submitted') {
            $st = ai_batch_status($batch['provider_batch_id']);
            $pst = (string) ($st['status'] ?? '');
            db_query('update seo_batches set provider_status = $2, output_file_id = $3, error_file_id = $4 where id = $1',
                [$batch['id'], $pst, $st['output_file_id'] ?? null, $st['error_file_id'] ?? null]);
            if (!in_array($pst, ['completed', 'expired', 'cancelled', 'failed'], true)) { $r['pending']++; continue; }
            db_query("update seo_batches set status = 'completed', completed_at = now() where id = $1", [$batch['id']]);
            $batch['output_file_id'] = $st['output_file_id'] ?? null;
            $batch['error_file_id'] = $st['error_file_id'] ?? null;
        }
        $lines = seo_batch_file_lines($batch['output_file_id']) + seo_batch_file_lines($batch['error_file_id']);
        [$inP, $outP] = seo_model_prices($batch['model']);
        foreach (db_query("select * from seo_batch_items where batch_id = $1 and status = 'pending' order by id", [$batch['id']]) as $item) {
            $line = $lines[$item['custom_id']] ?? null;
            $resp = $line['response'] ?? null;
            if (!$resp) { seo_settle_failed_item($item, (string) ($line['error']['message'] ?? 'No result in the batch output.')); $r['failed']++; continue; }
            $rule = json_decode($item['rule'], true);
            $res = ai_classify_response((int) ($resp['status_code'] ?? 0), json_encode($resp['body'] ?? null), $rule);
            if ($res['kind'] === 'failed') { seo_settle_failed_item($item, 'The provider rejected this request (HTTP ' . (int) ($resp['status_code'] ?? 0) . ').'); $r['failed']++; continue; }
            if ($res['kind'] === 'ok' && $saves >= SEO_SAVES_PER_RUN) continue; // keep for the next run (time limit)

            // The answer was billed: record the real cost (half price) BEFORE saving anything.
            [$in, $out] = $res['usage'];
            $cost = round(ai_real_cost($in, $out, $inP, $outP) * 0.5, 6);
            ai_settle((int) $item['usage_id'], 'succeeded', $cost, $in, $out,
                ['batch' => 'true', 'outcome' => $res['kind'] === 'ok' ? 'ok' : 'invalid_output', 'reason' => $res['reason'], 'half_price' => true]);
            if ($res['kind'] !== 'ok') {
                db_query("update seo_batch_items set status = 'returned', error = $2, settled_at = now() where id = $1", [$item['id'], 'Unusable answer: ' . $res['reason']]);
                seo_return_topic((int) $item['topic_id']);
                $r['failed']++;
                continue;
            }
            $saves++;
            try {
                $articleId = seo_save_generated_from_output((int) $item['topic_id'], $res['data'], $cost, (int) $item['usage_id'], json_decode($item['overrides'], true) ?: []);
                db_query("update seo_batch_items set status = 'saved', article_id = $2, settled_at = now() where id = $1", [$item['id'], $articleId]);
                $r['saved']++;
            } catch (Throwable $e) {
                error_log('[batch save] item ' . $item['id'] . ': ' . $e->getMessage());
                db_query("update seo_batch_items set status = 'returned', error = $2, settled_at = now() where id = $1", [$item['id'], mb_substr($e->getMessage(), 0, 500)]);
                seo_return_topic((int) $item['topic_id']);
                $r['failed']++;
            }
        }
        $left = (int) db_query("select count(*) c from seo_batch_items where batch_id = $1 and status = 'pending'", [$batch['id']])[0]['c'];
        if ($left === 0) db_query("update seo_batches set status = 'collected', collected_at = now() where id = $1", [$batch['id']]);
        else $r['pending'] += $left;
    }
    return $r;
}

/* ---------- 2. Weekly autopilot ---------- */

/**
 * For each active campaign with automation on: complete NEXT week's plan up to the weekly target
 * (research keywords first when too few unused remain), then approve the planned topics.
 * At most one paid attempt per campaign per day when the plan cannot be completed.
 */
function seo_weekly_autopilot(string $root, ?DateTimeImmutable $now = null): array
{
    seo_require_admin(); // web: only after seo_cron_guard() (SEO_CRON_AUTHORIZED) or an admin session; CLI allowed
    $now ??= new DateTimeImmutable('now');
    $monday = seo_next_monday($now);
    $today = $now->setTimezone(new DateTimeZone('Europe/Paris'))->format('Y-m-d');
    $out = [];
    $campaigns = db_query("select id, name, weekly_target, autopilot_attempted_on, start_date, end_date from seo_campaigns
                           where status = 'active' and automation_enabled
                             and (end_date is null or end_date >= $1::date) order by id", [$monday]);
    foreach ($campaigns as $c) {
        $cid = (int) $c['id'];
        $count = static fn () => (int) db_query("select count(*) c from seo_topics where campaign_id = $1 and week_start = $2::date
                                                  and status not in ('rejected', 'skipped')", [$cid, $monday])[0]['c'];
        $have = $count();
        $target = (int) $c['weekly_target'];
        if ($have >= $target) {
            $approved = seo_autopilot_approve($cid, $monday);
            $out[] = "“{$c['name']}”: week of $monday already planned ($have/$target)" . ($approved ? ", $approved topic(s) approved." : '.');
            continue;
        }
        if ($c['autopilot_attempted_on'] === $today) {
            $out[] = "“{$c['name']}”: already tried today and could not complete the plan — next try tomorrow.";
            continue;
        }
        $notes = [];
        try {
            $unused = (int) db_query('select count(*) c from seo_keywords where campaign_id = $1 and used_at is null', [$cid])[0]['c'];
            if ($unused < $target - $have) {
                $k = seo_ai_keyword_ideas($root, $cid);
                $notes[] = "researched keywords ({$k['added']} added)";
            }
            $p = seo_generate_week($root, $cid, $monday);
            $notes[] = "planned {$p['created']} topic(s)" . ($p['rejected'] ? ", {$p['rejected']} duplicate(s) rejected" : '');
        } catch (Throwable $e) {
            $notes[] = $e instanceof AiError || $e instanceof InvalidArgumentException ? $e->getMessage() : admin_error($e, 'plan the week');
        }
        $approved = seo_autopilot_approve($cid, $monday);
        $have = $count();
        if ($have < $target) db_query('update seo_campaigns set autopilot_attempted_on = $2::date where id = $1', [$cid, $today]);
        $out[] = "“{$c['name']}”: " . implode('; ', $notes) . "; $approved approved; week of $monday now $have/$target.";
    }
    return $out;
}

function seo_autopilot_approve(int $campaignId, string $monday): int
{
    seo_require_admin(); // web: only after seo_cron_guard() (SEO_CRON_AUTHORIZED) or an admin session; CLI allowed
    return count(db_query("update seo_topics set status = 'approved', updated_at = now()
                           where campaign_id = $1 and week_start = $2::date and status = 'proposed' returning id", [$campaignId, $monday]));
}

/* ---------- 3. Send due approved topics for drafting ---------- */

/**
 * Approved topics planned within the next 14 days (campaigns active with automation on), within each week's target
 * (topics already queued or drafted count toward it). Keyword repeats are skipped so the planner replaces them.
 * OpenAI/Groq: one Batch API request at half price. Other providers: up to SEO_DIRECT_PER_RUN drafts directly.
 */
function seo_send_due_topics(?DateTimeImmutable $now = null): array
{
    seo_require_admin(); // web: only after seo_cron_guard() (SEO_CRON_AUTHORIZED) or an admin session; CLI allowed
    [$from, $to] = seo_due_window($now ?? new DateTimeImmutable('now'));
    $topics = db_query("select t.id, t.campaign_id, t.week_start, t.planned_for, t.title, k.keyword
                          from seo_topics t join seo_campaigns c on c.id = t.campaign_id
                          left join seo_keywords k on k.id = t.keyword_id
                         where t.status = 'approved' and t.planned_for between $1::date and $2::date
                           and c.status = 'active' and c.automation_enabled
                           and t.draft_attempts < $3
                         order by t.planned_for, t.id", [$from, $to, SEO_MAX_TOPIC_ATTEMPTS]);
    $r = ['due' => count($topics), 'sent' => 0, 'skipped' => 0, 'failures' => 0, 'mode' => '', 'notes' => []];
    $stuck = (int) db_query("select count(*) c from seo_topics t where t.status = 'approved' and t.planned_for between $1::date and $2::date
                              and t.draft_attempts >= $3", [$from, $to, SEO_MAX_TOPIC_ATTEMPTS])[0]['c'];
    if ($stuck) $r['notes'][] = "$stuck topic(s) failed " . SEO_MAX_TOPIC_ATTEMPTS . ' drafting attempts and are no longer sent automatically — generate them by hand in Articles & Approval.';
    if (!$topics) return $r;

    $allowance = [];
    foreach (db_query("select t.campaign_id, t.week_start, c.weekly_target,
                              count(*) filter (where t.status = 'queued') as queued,
                              count(*) filter (where t.status = 'used') as drafted
                         from seo_topics t join seo_campaigns c on c.id = t.campaign_id
                        where t.week_start between ($1::date - 6) and $2::date
                        group by 1, 2, 3", [$from, $to]) as $w) {
        $allowance[$w['campaign_id'] . '|' . $w['week_start']] = seo_week_send_allowance((int) $w['weekly_target'], (int) $w['queued'], (int) $w['drafted']);
    }
    $cfg = ai_config();
    $batchMode = in_array($cfg['provider'], SEO_BATCH_PROVIDERS, true);
    $r['mode'] = $batchMode ? 'batch' : 'direct';
    $picked = seo_pick_due_topics($topics, $allowance, $batchMode ? 50 : SEO_DIRECT_PER_RUN);

    $items = [];
    foreach ($picked as $t) {
        if ($r['failures'] >= SEO_MAX_FAILURES) { $r['notes'][] = 'Stopped after ' . SEO_MAX_FAILURES . ' failures.'; break; }
        if ($t['keyword'] && ($why = seo_keyword_taken($t['keyword'])) !== null) {
            db_query("update seo_topics set status = 'skipped', updated_at = now() where id = $1", [$t['id']]);
            $r['skipped']++;
            $r['notes'][] = "Topic #{$t['id']} skipped: $why";
            continue;
        }
        try {
            $b = seo_article_batch_body((int) $t['id']);
        } catch (InvalidArgumentException $e) {
            db_query("update seo_topics set status = 'skipped', updated_at = now() where id = $1", [$t['id']]);
            $r['skipped']++;
            $r['notes'][] = "Topic #{$t['id']} skipped: " . $e->getMessage();
            continue;
        } catch (Throwable $e) {
            $r['failures']++;
            $r['notes'][] = "Topic #{$t['id']}: " . admin_error($e, 'prepare the draft request');
            continue;
        }
        if (!$batchMode) {
            try {
                $body = $b['body'];
                $res = ai_generate('scheduled_generation', $body['messages'], $b['rule'], $b['schemaName'],
                    (int) ($body['max_tokens'] ?? $body['max_completion_tokens'] ?? 4000), ['model' => $body['model']]);
                seo_save_generated_from_output((int) $t['id'], $res['data'], $res['cost'], $res['usage_id']);
                $r['sent']++;
            } catch (AiError $e) {
                $r['failures']++;
                $r['notes'][] = "Topic #{$t['id']}: " . $e->getMessage();
                if (str_contains($e->getMessage(), 'budget')) break;      // not the topic's fault: no attempt counted
                seo_count_failed_attempt((int) $t['id']);
            } catch (Throwable $e) {
                $r['failures']++;
                $r['notes'][] = "Topic #{$t['id']}: " . admin_error($e, 'save the draft');
                seo_count_failed_attempt((int) $t['id']);
            }
            continue;
        }
        $items[] = ['topic' => $t, 'b' => $b];
    }
    if ($batchMode && $items) seo_submit_batch($items, $r);
    return $r;
}

/** Reserves each item at half price, records the batch locally, uploads, creates, then confirms. */
function seo_submit_batch(array $items, array &$r): void
{
    seo_require_admin(); // web: only after seo_cron_guard() (SEO_CRON_AUTHORIZED) or an admin session; CLI allowed
    $cfg = ai_config();
    $model = $items[0]['b']['body']['model'];
    [$inP, $outP] = seo_model_prices($model);
    $batchId = (int) db_query("insert into seo_batches (provider, model, status) values ($1, $2, 'submitting') returning id", [$cfg['provider'], $model])[0]['id'];
    $lines = [];
    $reserved = [];
    foreach ($items as $i => $it) {
        $body = $it['b']['body'];
        $maxOut = (int) ($body['max_tokens'] ?? $body['max_completion_tokens'] ?? 4000);
        $estimate = round(ai_estimate_cost(ai_estimate_tokens(json_encode($body['messages'], JSON_UNESCAPED_UNICODE) . json_encode($body['response_format'])), $maxOut, $inP, $outP) * 0.5, 6);
        $res = db_query('select usage_id, allowed, message from reserve_seo_api_usage($1, $2, $3, $4::numeric)', [$cfg['provider'], $model, 'scheduled_generation', $estimate])[0];
        if ($res['allowed'] !== 't' && $res['allowed'] !== 'true') { $r['notes'][] = $res['message']; break; }
        db_query("update seo_api_usage set metadata = metadata || '{\"batch\": \"true\"}'::jsonb where id = $1", [$res['usage_id']]);
        $customId = "b{$batchId}-t{$it['topic']['id']}";
        db_query('insert into seo_batch_items (batch_id, topic_id, custom_id, usage_id, rule) values ($1, $2, $3, $4, $5::jsonb)',
            [$batchId, $it['topic']['id'], $customId, $res['usage_id'], json_encode($it['b']['rule'], JSON_UNESCAPED_UNICODE)]);
        db_query("update seo_topics set status = 'queued', updated_at = now() where id = $1", [$it['topic']['id']]);
        $lines[] = json_encode(['custom_id' => $customId, 'method' => 'POST', 'url' => '/v1/chat/completions', 'body' => $body], JSON_UNESCAPED_UNICODE);
        $reserved[] = $customId;
    }
    if (!$lines) {
        db_query("update seo_batches set status = 'failed', error = 'Nothing could be reserved within the budget.' where id = $1", [$batchId]);
        return;
    }
    db_query('update seo_batches set item_count = $2 where id = $1', [$batchId, count($lines)]);
    try {
        $fileId = ai_batch_upload($lines);
        $created = ai_batch_create($fileId, ['source' => 'climanova-seo', 'local_batch' => (string) $batchId]);
        if (empty($created['id'])) throw new AiError('The AI provider did not confirm the batch.');
        db_query("update seo_batches set status = 'submitted', provider_batch_id = $2, input_file_id = $3, provider_status = $4, confirmed_at = now() where id = $1",
            [$batchId, $created['id'], $fileId, $created['status'] ?? null]);
        $r['sent'] += count($lines);
    } catch (Throwable $e) {
        // Not confirmed: free the reservations now and put the topics back in the queue.
        foreach (db_query("select * from seo_batch_items where batch_id = $1 and status = 'pending'", [$batchId]) as $item) {
            seo_settle_failed_item($item, 'Batch submission failed: ' . $e->getMessage());
        }
        db_query("update seo_batches set status = 'failed', error = $2 where id = $1", [$batchId, mb_substr($e->getMessage(), 0, 500)]);
        $r['failures']++;
        $r['notes'][] = $e instanceof AiError ? $e->getMessage() : admin_error($e, 'submit the batch');
    }
}

/* ---------- Endpoint runners ---------- */

function seo_run_generate(string $root): array
{
    seo_require_admin(); // web: only after seo_cron_guard() (SEO_CRON_AUTHORIZED) or an admin session; CLI allowed
    if (!seo_cron_lock('generate')) {
        seo_log('scheduled_generation', 'skipped', 'Another scheduled generation run is still in progress — skipped this one.');
        return ['status' => 'locked'];
    }
    $summary = [];
    try {
        $id = seo_log_start('batch_collect', 'Collecting finished drafting batches…', 'scheduled_generation');
        try {
            $released = seo_release_unconfirmed_batches();
            $c = seo_collect_batches();
            $summary['collect'] = $c + ['released' => $released];
            seo_log_finish($id, $c['failed'] ? 'error' : ($c['checked'] || $released ? 'success' : 'skipped'),
                $c['checked'] || $released
                    ? "Batches checked: {$c['checked']}. Drafts saved: {$c['saved']}, returned to the queue: {$c['failed']}, still waiting: {$c['pending']}" . ($released ? ", unconfirmed batches released: $released." : '.')
                    : 'No drafting batch to collect.', $summary['collect']);
        } catch (Throwable $e) {
            seo_log_finish($id, 'error', 'Collecting batches failed: ' . ($e instanceof AiError ? $e->getMessage() : admin_error($e, 'collect the batches')));
        }

        $id = seo_log_start('autopilot', 'Planning next week for automated campaigns…', 'weekly_autopilot');
        try {
            $a = seo_weekly_autopilot($root);
            $summary['autopilot'] = $a;
            seo_log_finish($id, $a ? 'success' : 'skipped', $a ? implode(' ', $a) : 'No active campaign has automation turned on.', ['campaigns' => $a]);
        } catch (Throwable $e) {
            seo_log_finish($id, 'error', 'The weekly autopilot failed: ' . admin_error($e, 'run the autopilot'));
        }

        $id = seo_log_start('scheduled_generation', 'Sending due approved topics for drafting…', 'scheduled_generation');
        try {
            $s = seo_send_due_topics();
            $summary['send'] = $s;
            $msg = $s['due'] ? "Due approved topics: {$s['due']}. Sent for drafting: {$s['sent']} ({$s['mode']} mode), skipped: {$s['skipped']}, failures: {$s['failures']}." : 'No approved topic is due in the next 14 days.';
            if ($s['notes']) $msg .= ' ' . implode(' ', array_slice($s['notes'], 0, 5));
            seo_log_finish($id, $s['failures'] ? 'error' : ($s['sent'] || $s['skipped'] ? 'success' : 'skipped'), $msg, $s);
        } catch (Throwable $e) {
            seo_log_finish($id, 'error', 'Sending topics for drafting failed: ' . admin_error($e, 'send the topics'));
        }
    } finally {
        seo_cron_unlock('generate');
    }
    return ['status' => 'done'] + $summary;
}

function seo_run_publish(): array
{
    seo_require_admin(); // web: only after seo_cron_guard() (SEO_CRON_AUTHORIZED) or an admin session; CLI allowed
    if (!seo_cron_lock('publish')) {
        seo_log('publish_job', 'skipped', 'Another publishing run is still in progress — skipped this one.');
        return ['status' => 'locked'];
    }
    $id = seo_log_start('publish_job', 'Scheduling and publishing approved articles…', 'publishing');
    try {
        $p = seo_publish_job();
        $nothing = !($p['scheduled'] ?? 0) && !($p['published'] ?? 0) && !($p['failed'] ?? 0);
        seo_log_finish($id, ($p['failed'] ?? 0) ? 'error' : ($nothing ? 'skipped' : 'success'),
            $nothing ? 'Nothing to schedule or publish.' : sprintf('Scheduled %d, published %d, failed %d.', $p['scheduled'] ?? 0, $p['published'] ?? 0, $p['failed'] ?? 0), $p);
        return ['status' => 'done'] + $p;
    } catch (Throwable $e) {
        seo_log_finish($id, 'error', 'Publishing failed: ' . admin_error($e, 'publish'));
        return ['status' => 'error'];
    } finally {
        seo_cron_unlock('publish');
    }
}
