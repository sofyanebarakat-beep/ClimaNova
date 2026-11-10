-- API Budget + Automation Logs tabs. Additive only.

-- Logs: operation (for filtering), started/finished times, and statuses running / error.
alter table seo_automation_logs add column if not exists operation text;
alter table seo_automation_logs add column if not exists started_at timestamptz;
alter table seo_automation_logs add column if not exists finished_at timestamptz;
alter table seo_automation_logs drop constraint if exists seo_automation_logs_status_check;
alter table seo_automation_logs add constraint seo_automation_logs_status_check
  check (status in ('running', 'success', 'error', 'skipped', 'info', 'failed'));

update seo_automation_logs set
  operation = case
    when job in ('keyword_ideas', 'gsc_ideas', 'add_keyword') then 'keyword_research'
    when job in ('weekly_plan', 'regenerate_topic', 'add_topic') then 'weekly_planning'
    when job like '%article%' or job like 'rewrite%' then 'article_generation'
    when job like 'batch%' or job like 'scheduled_generation%' then 'scheduled_generation'
    when job like 'publish%' or job like 'schedule%' or job like 'unpublish%' then 'publishing'
    when job like 'autopilot%' then 'weekly_autopilot'
    when job like 'gsc%' or job like 'ga4%' or job like '%analytics%' or job like 'google%' then 'google_sync'
    else 'other' end
where operation is null;
update seo_automation_logs set started_at = created_at where started_at is null;
update seo_automation_logs set finished_at = created_at where finished_at is null and status <> 'running';

create index if not exists seo_automation_logs_operation_idx on seo_automation_logs (operation, created_at desc);
create index if not exists seo_automation_logs_status_idx on seo_automation_logs (status, created_at desc);

-- Budget: several warning thresholds (fractions of the allowance).
update seo_settings
   set value = value || jsonb_build_object('warning_thresholds', jsonb_build_array(0.5, 0.8, 0.95)), updated_at = now()
 where key = 'budget' and not (value ? 'warning_thresholds');

-- The budget is used up when spend + reservations REACH the allowance (not only when a request would exceed it),
-- so even a $0 request is refused at the limit when the hard stop is on.
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
   where status = 'reserved' and created_at < now() - interval '30 minutes';

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
