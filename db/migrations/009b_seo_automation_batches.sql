-- Automation: OpenAI Batch tracking, topic queue statuses, autopilot attempts. Additive only.

-- Topics: 'queued' = waiting in a batch, 'skipped' = its keyword repeats an existing article (the planner replaces it).
alter table seo_topics drop constraint if exists seo_topics_status_check;
alter table seo_topics add constraint seo_topics_status_check
  check (status in ('proposed', 'approved', 'queued', 'used', 'skipped', 'rejected'));

-- Failed automatic drafting attempts per topic (batch or direct). After 3, the topic is only drafted by hand.
alter table seo_topics add column if not exists draft_attempts smallint not null default 0;

-- Autopilot: the day of the last paid attempt that could not complete a week's plan (max one per campaign per day).
alter table seo_campaigns add column if not exists autopilot_attempted_on date;

create table if not exists seo_batches (
  id                 bigint generated always as identity primary key,
  provider           text not null,
  model              text not null,
  status             text not null default 'submitting'
                       check (status in ('submitting', 'submitted', 'completed', 'collected', 'failed', 'released')),
  provider_batch_id  text unique,
  provider_status    text,
  input_file_id      text,
  output_file_id     text,
  error_file_id      text,
  item_count         integer not null default 0,
  error              text,
  created_at         timestamptz not null default now(),
  confirmed_at       timestamptz,     -- the provider accepted the batch (submission confirmed)
  completed_at       timestamptz,
  collected_at       timestamptz
);
create index if not exists seo_batches_status_idx on seo_batches (status, created_at);

create table if not exists seo_batch_items (
  id          bigint generated always as identity primary key,
  batch_id    bigint not null references seo_batches (id),
  topic_id    bigint not null references seo_topics (id),
  custom_id   text not null unique,
  usage_id    bigint references seo_api_usage (id),
  overrides   jsonb not null default '{}',
  rule        jsonb not null,
  status      text not null default 'pending' check (status in ('pending', 'saved', 'failed', 'returned')),
  article_id  bigint references seo_articles (id),
  error       text,
  created_at  timestamptz not null default now(),
  settled_at  timestamptz
);
create index if not exists seo_batch_items_batch_idx on seo_batch_items (batch_id, status);

-- Batch reservations wait up to 24 h for results: only release them as stale after 26 h (normal calls: 30 min).
create or replace function reserve_seo_api_usage(p_provider text, p_model text, p_purpose text, p_estimate numeric)
returns table (usage_id bigint, allowed boolean, message text)
language plpgsql as $$
declare
  v_budget    jsonb;
  v_allowance numeric;
  v_hard_stop boolean;
  v_committed numeric;
  v_id        bigint;
begin
  perform pg_advisory_xact_lock(hashtext('seo_api_budget'));

  update seo_api_usage
     set status = 'failed', cost_usd = 0, settled_at = now(),
         metadata = metadata || '{"outcome": "stale_reservation"}'
   where status = 'reserved'
     and created_at < now() - case when metadata ->> 'batch' = 'true' then interval '26 hours' else interval '30 minutes' end;

  select value into v_budget from seo_settings where key = 'budget';
  v_allowance := coalesce((v_budget ->> 'monthly_allowance_usd')::numeric, 0);
  v_hard_stop := coalesce((v_budget ->> 'hard_stop')::boolean, true);

  select coalesce(recorded_usd, 0) + coalesce(reserved_usd, 0) into v_committed
    from seo_monthly_usage
   where month = date_trunc('month', now() at time zone 'Europe/Paris')::date;
  v_committed := coalesce(v_committed, 0);

  if v_hard_stop and (v_committed >= v_allowance or v_committed + greatest(p_estimate, 0) > v_allowance) then
    return query select null::bigint, false,
      case when v_committed >= v_allowance
        then format('The monthly AI budget is used up ($%s spent or reserved of $%s) — raise the allowance in API Budget or wait until next month.',
                    to_char(v_committed, 'FM999990.00'), to_char(v_allowance, 'FM999990.00'))
        else format('The monthly AI budget would be exceeded ($%s committed of $%s, this request needs up to $%s) — raise the allowance or wait until next month.',
                    to_char(v_committed, 'FM999990.00'), to_char(v_allowance, 'FM999990.00'), to_char(greatest(p_estimate, 0), 'FM999990.0000'))
      end;
    return;
  end if;

  insert into seo_api_usage (provider, model, purpose, status, reserved_usd)
  values (p_provider, p_model, p_purpose, 'reserved', greatest(p_estimate, 0))
  returning id into v_id;

  return query select v_id, true, null::text;
end $$;
