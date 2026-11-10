-- SEO Marketing Agent: core tables. Additive only.

create table if not exists seo_offers (
  id           bigint generated always as identity primary key,
  slug         text not null unique,
  name         text not null,
  url          text not null,
  category     text not null default 'service',
  description  text,
  is_active    boolean not null default true,
  synced_at    timestamptz,
  created_at   timestamptz not null default now(),
  updated_at   timestamptz not null default now()
);

create table if not exists seo_campaigns (
  id           bigint generated always as identity primary key,
  offer_id     bigint references seo_offers (id),
  name         text not null,
  status       text not null default 'draft' check (status in ('draft', 'active', 'paused', 'completed')),
  goal         text,
  start_date   date,
  end_date     date,
  created_at   timestamptz not null default now(),
  updated_at   timestamptz not null default now()
);
create index if not exists seo_campaigns_status_idx on seo_campaigns (status);

create table if not exists seo_articles (
  id                   bigint generated always as identity primary key,
  campaign_id          bigint references seo_campaigns (id),
  offer_id             bigint references seo_offers (id),
  title                text not null,
  slug                 text unique,
  status               text not null default 'draft'
                         check (status in ('draft', 'generated', 'approved', 'scheduled', 'published', 'rejected', 'failed')),
  featured_image_path  text,
  scheduled_for        timestamptz,
  published_at         timestamptz,
  created_at           timestamptz not null default now(),
  updated_at           timestamptz not null default now()
);
create index if not exists seo_articles_status_idx on seo_articles (status);
create index if not exists seo_articles_scheduled_idx on seo_articles (scheduled_for);

create table if not exists seo_automation_logs (
  id          bigint generated always as identity primary key,
  created_at  timestamptz not null default now(),
  job         text not null,
  status      text not null check (status in ('success', 'failed', 'skipped', 'info')),
  message     text not null,
  details     jsonb
);
create index if not exists seo_automation_logs_created_idx on seo_automation_logs (created_at desc);

-- Every AI request: a reservation first (estimated max cost), then the real cost once it returns.
create table if not exists seo_api_usage (
  id             bigint generated always as identity primary key,
  created_at     timestamptz not null default now(),
  provider       text not null default 'openai',
  model          text not null,
  purpose        text not null,
  status         text not null default 'reserved' check (status in ('reserved', 'recorded', 'released')),
  reserved_usd   numeric(12, 6) not null default 0,
  cost_usd       numeric(12, 6) not null default 0,
  input_tokens   integer,
  output_tokens  integer,
  request_id     text,
  settled_at     timestamptz
);
create index if not exists seo_api_usage_created_idx on seo_api_usage (created_at);

create table if not exists seo_settings (
  key         text primary key,
  value       jsonb not null,
  updated_at  timestamptz not null default now()
);
insert into seo_settings (key, value) values
  ('budget', '{"monthly_allowance_usd": 20, "warning_threshold_pct": 80, "hard_stop": true}')
on conflict (key) do nothing;

create table if not exists seo_conversions (
  id           bigint generated always as identity primary key,
  occurred_at  timestamptz not null default now(),
  source       text not null default 'manual',
  event_name   text,
  article_id   bigint references seo_articles (id),
  page_path    text,
  value        numeric(12, 2)
);
create index if not exists seo_conversions_occurred_idx on seo_conversions (occurred_at);

-- Spend per calendar month in Paris time: recorded = real cost, reserved = still-open reservations.
create or replace view seo_monthly_usage as
select
  date_trunc('month', created_at at time zone 'Europe/Paris')::date               as month,
  coalesce(sum(cost_usd) filter (where status = 'recorded'), 0)::numeric(12, 6)      as recorded_usd,
  coalesce(sum(reserved_usd) filter (where status = 'reserved'), 0)::numeric(12, 6)  as reserved_usd,
  count(*) filter (where status = 'recorded')                                        as requests
from seo_api_usage
group by 1;
