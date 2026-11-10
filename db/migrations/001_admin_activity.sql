-- Login history for the /admin area.
create table if not exists admin_activity (
  id          bigint generated always as identity primary key,
  created_at  timestamptz not null default now(),
  email       text not null,
  event       text not null check (event in ('login', 'login_failed', 'logout')),
  ip          inet,
  user_agent  text
);

create index if not exists admin_activity_created_at_idx on admin_activity (created_at desc);
