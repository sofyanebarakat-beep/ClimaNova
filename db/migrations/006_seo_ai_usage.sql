-- Shared AI layer: usage lifecycle reserved → succeeded | failed, and an atomic budget reservation.
-- Additive: new columns, a widened status check (old values stay valid), a replaced view and a new function.

alter table seo_api_usage add column if not exists metadata jsonb not null default '{}';
alter table seo_api_usage add column if not exists error text;

alter table seo_api_usage drop constraint if exists seo_api_usage_status_check;
alter table seo_api_usage add constraint seo_api_usage_status_check
  check (status in ('reserved', 'succeeded', 'failed', 'recorded', 'released'));

-- Spend = real cost of answered calls ('succeeded'; 'recorded' kept for older rows). Reserved = calls in flight.
create or replace view seo_monthly_usage as
select
  date_trunc('month', created_at at time zone 'Europe/Paris')::date                                      as month,
  coalesce(sum(cost_usd) filter (where status in ('succeeded', 'recorded')), 0)::numeric(12, 6)           as recorded_usd,
  coalesce(sum(reserved_usd) filter (where status = 'reserved'), 0)::numeric(12, 6)                       as reserved_usd,
  count(*) filter (where status in ('succeeded', 'recorded'))                                             as requests,
  count(*) filter (where status = 'failed')                                                               as failed_requests,
  count(*) filter (where metadata ->> 'outcome' = 'invalid_output')                                       as invalid_outputs,
  coalesce(sum(input_tokens), 0)                                                                          as input_tokens,
  coalesce(sum(output_tokens), 0)                                                                         as output_tokens
from seo_api_usage
group by 1;

-- Reserve budget for one AI call. Serialised with an advisory lock so parallel calls cannot overspend.
-- Returns usage_id when allowed, otherwise allowed = false and a one-sentence message.
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

  -- A reservation older than 30 minutes belongs to a call that never finished: release it (no charge).
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

  if v_hard_stop and v_committed + greatest(p_estimate, 0) > v_allowance then
    return query select null::bigint, false,
      format('The monthly AI budget would be exceeded ($%s committed of $%s, this request needs up to $%s) — raise the allowance or wait until next month.',
             to_char(v_committed, 'FM999990.00'), to_char(v_allowance, 'FM999990.00'), to_char(greatest(p_estimate, 0), 'FM999990.0000'));
    return;
  end if;

  insert into seo_api_usage (provider, model, purpose, status, reserved_usd)
  values (p_provider, p_model, p_purpose, 'reserved', greatest(p_estimate, 0))
  returning id into v_id;

  return query select v_id, true, null::text;
end $$;
