-- Campaigns tab: multi-offer campaigns with a weekly publishing schedule. Additive only.

alter table seo_campaigns add column if not exists language text not null default 'fr';
alter table seo_campaigns add column if not exists target_countries text[] not null default '{FR}';
alter table seo_campaigns add column if not exists weekly_target smallint not null default 3;
alter table seo_campaigns add column if not exists publish_days smallint[] not null default '{1,3,5}';   -- ISO weekdays: 1 = Monday … 7 = Sunday
alter table seo_campaigns add column if not exists publish_times text[] not null default '{09:00}';      -- HH:mm, local to time_zone
alter table seo_campaigns add column if not exists time_zone text not null default 'Europe/Paris';
alter table seo_campaigns add column if not exists automation_enabled boolean not null default false;  -- write drafts from approved topics
alter table seo_campaigns add column if not exists auto_publish boolean not null default false;        -- publish approved articles on schedule

do $$
begin
  if not exists (select 1 from pg_constraint where conname = 'seo_campaigns_weekly_target_range') then
    alter table seo_campaigns add constraint seo_campaigns_weekly_target_range check (weekly_target between 1 and 12);
  end if;
  if not exists (select 1 from pg_constraint where conname = 'seo_campaigns_name_length') then
    alter table seo_campaigns add constraint seo_campaigns_name_length check (char_length(name) between 5 and 120) not valid;
  end if;
  if not exists (select 1 from pg_constraint where conname = 'seo_campaigns_dates_order') then
    alter table seo_campaigns add constraint seo_campaigns_dates_order check (end_date is null or start_date is null or end_date >= start_date);
  end if;
end $$;

-- A campaign promotes one or more offers.
create table if not exists seo_campaign_offers (
  campaign_id  bigint not null references seo_campaigns (id),
  offer_id     bigint not null references seo_offers (id),
  created_at   timestamptz not null default now(),
  primary key (campaign_id, offer_id)
);
create index if not exists seo_campaign_offers_offer_idx on seo_campaign_offers (offer_id);

-- Same rule as campaigns/articles: only enabled offers that are still in the catalog can be linked.
drop trigger if exists seo_campaign_offers_enabled_offer on seo_campaign_offers;
create trigger seo_campaign_offers_enabled_offer before insert or update of offer_id on seo_campaign_offers
  for each row execute function seo_require_enabled_offer();
