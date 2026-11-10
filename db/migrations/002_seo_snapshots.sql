-- One row per page per day: on-page SEO score and Google Search Console figures (last 28 days).
create table if not exists seo_snapshots (
  snapshot_date  date not null default current_date,
  path           text not null,
  category       text not null,
  score          smallint not null check (score between 0 and 100),
  position       real,
  clicks         integer,
  impressions    integer,
  top_query      text,
  created_at     timestamptz not null default now(),
  primary key (snapshot_date, path)
);

create index if not exists seo_snapshots_path_date_idx on seo_snapshots (path, snapshot_date desc);
