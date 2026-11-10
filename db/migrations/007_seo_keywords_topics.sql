-- Keywords & Topics tab. Additive only.

create table if not exists seo_keywords (
  id              bigint generated always as identity primary key,
  campaign_id     bigint not null references seo_campaigns (id),
  offer_id        bigint references seo_offers (id),               -- null = campaign-wide
  keyword         text not null check (char_length(keyword) between 2 and 120),
  normalized      text not null,                                   -- lowercase, no accents/punctuation: duplicate key
  search_intent   text not null default 'informational'
                    check (search_intent in ('informational', 'commercial', 'transactional', 'local', 'navigational')),
  cluster         text,
  rationale       text,
  priority        smallint not null default 50 check (priority between 1 and 100),
  source          text not null default 'manual' check (source in ('manual', 'ai', 'search_console')),
  gsc_impressions integer,                                         -- real Search Console figures only, never AI estimates
  gsc_clicks      integer,
  gsc_position    real,
  used_at         timestamptz,                                     -- set when a topic uses it (each keyword is used once)
  created_at      timestamptz not null default now(),
  updated_at      timestamptz not null default now(),
  unique (campaign_id, normalized)
);
create index if not exists seo_keywords_campaign_idx on seo_keywords (campaign_id, used_at);

create table if not exists seo_topics (
  id              bigint generated always as identity primary key,
  campaign_id     bigint not null references seo_campaigns (id),
  offer_id        bigint references seo_offers (id),
  keyword_id      bigint references seo_keywords (id),
  title           text not null check (char_length(title) between 10 and 160),
  normalized      text not null,
  angle           text,
  planned_for     date not null,
  week_start      date not null,                                   -- Monday of planned_for's week
  status          text not null default 'proposed' check (status in ('proposed', 'approved', 'rejected', 'used')),
  source          text not null default 'ai' check (source in ('ai', 'manual')),
  created_at      timestamptz not null default now(),
  updated_at      timestamptz not null default now()
);
create index if not exists seo_topics_campaign_week_idx on seo_topics (campaign_id, week_start);

-- Keywords and topics can only point at offers that are enabled and still in the catalog.
drop trigger if exists seo_keywords_enabled_offer on seo_keywords;
create trigger seo_keywords_enabled_offer before insert or update of offer_id on seo_keywords
  for each row execute function seo_require_enabled_offer();
drop trigger if exists seo_topics_enabled_offer on seo_topics;
create trigger seo_topics_enabled_offer before insert or update of offer_id on seo_topics
  for each row execute function seo_require_enabled_offer();
