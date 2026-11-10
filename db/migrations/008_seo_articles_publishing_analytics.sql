-- Articles & Approval, Weekly Check, Calendar/publishing and Analytics. Additive only.

-- Article lifecycle: draft | generated → review → approved → scheduled → publishing → published (or rejected / failed).
alter table seo_articles drop constraint if exists seo_articles_status_check;
alter table seo_articles add constraint seo_articles_status_check
  check (status in ('draft', 'generated', 'review', 'approved', 'scheduled', 'publishing', 'published', 'rejected', 'failed'));

alter table seo_articles add column if not exists topic_id          bigint references seo_topics (id);
alter table seo_articles add column if not exists focus_keyword     text;
alter table seo_articles add column if not exists related_keywords  text[] not null default '{}';
alter table seo_articles add column if not exists seo_title         text;
alter table seo_articles add column if not exists meta_description  text;
alter table seo_articles add column if not exists language          text not null default 'fr';
alter table seo_articles add column if not exists content           jsonb not null default '{}';   -- {markdown, featuredImage{url,alt,width,height}}
alter table seo_articles add column if not exists word_count        integer not null default 0;
alter table seo_articles add column if not exists quality_score     smallint;
alter table seo_articles add column if not exists quality_notes     jsonb not null default '[]';
alter table seo_articles add column if not exists generation        jsonb not null default '{}';   -- settings used, so it can be regenerated
alter table seo_articles add column if not exists model             text;
alter table seo_articles add column if not exists cost_usd          numeric(12, 6) not null default 0;
alter table seo_articles add column if not exists content_hash      text;
alter table seo_articles add column if not exists submitted_at      timestamptz;
alter table seo_articles add column if not exists approved_at       timestamptz;
alter table seo_articles add column if not exists rejected_reason   text;
alter table seo_articles add column if not exists published_url     text;
alter table seo_articles add column if not exists publish_attempts  smallint not null default 0;
alter table seo_articles add column if not exists last_error        text;

create unique index if not exists seo_articles_content_hash_uidx on seo_articles (content_hash) where content_hash is not null;
-- One scheduled article per slot: a second approval can never take an occupied slot.
create unique index if not exists seo_articles_slot_uidx on seo_articles (scheduled_for) where status = 'scheduled';
create index if not exists seo_articles_published_idx on seo_articles (published_at desc) where status = 'published';

-- Every save keeps the previous version.
create table if not exists seo_article_versions (
  id          bigint generated always as identity primary key,
  article_id  bigint not null references seo_articles (id),
  created_at  timestamptz not null default now(),
  reason      text not null,
  snapshot    jsonb not null
);
create index if not exists seo_article_versions_article_idx on seo_article_versions (article_id, created_at desc);

-- One-time signed upload links (only the SHA-256 of the token is stored).
create table if not exists seo_upload_tokens (
  token_hash  text primary key,
  article_id  bigint not null references seo_articles (id),
  kind        text not null check (kind in ('featured', 'section')),
  max_bytes   integer not null,
  expires_at  timestamptz not null,
  used_at     timestamptz,
  file_name   text,
  created_at  timestamptz not null default now()
);

-- Google data. page_path '' = site total for that day.
create table if not exists seo_analytics_daily (
  source            text not null check (source in ('gsc', 'ga4')),
  day               date not null,
  page_path         text not null default '',
  clicks            integer not null default 0,
  impressions       integer not null default 0,
  position          real,
  sessions          integer not null default 0,
  engaged_sessions  integer not null default 0,
  article_id        bigint references seo_articles (id),
  offer_id          bigint references seo_offers (id),
  primary key (source, day, page_path)
);
create index if not exists seo_analytics_daily_article_idx on seo_analytics_daily (article_id) where article_id is not null;
create index if not exists seo_analytics_daily_offer_idx on seo_analytics_daily (offer_id) where offer_id is not null;

create table if not exists seo_analytics_events (
  day          date not null,
  event_name   text not null,
  event_count  integer not null default 0,
  key_events   integer not null default 0,
  primary key (day, event_name)
);

insert into seo_settings (key, value) values ('analytics_sync', '{"running": false}') on conflict (key) do nothing;
