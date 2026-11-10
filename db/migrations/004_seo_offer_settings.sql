-- Offers tab: admin settings per offer. Additive only.
-- is_active          = the offer still exists in the catalog (managed by "Sync offers")
-- promotion_enabled  = the admin allows campaigns/articles to promote it (never touched by sync)

alter table seo_offers add column if not exists path text;
alter table seo_offers add column if not exists promotion_enabled boolean not null default true;
alter table seo_offers add column if not exists target_keywords text[] not null default '{}';
alter table seo_offers add column if not exists seo_priority smallint not null default 50;

do $$
begin
  if not exists (select 1 from pg_constraint where conname = 'seo_offers_priority_range') then
    alter table seo_offers add constraint seo_offers_priority_range check (seo_priority between 1 and 100);
  end if;
end $$;

update seo_offers set path = '/services/' || slug || '/' where path is null;

-- Only enabled offers (promotion on and still in the catalog) can be attached to a campaign or article.
-- Checked when the row is created or its offer changes, so existing rows stay editable if an offer is later disabled.
create or replace function seo_require_enabled_offer() returns trigger
language plpgsql as $$
begin
  if new.offer_id is not null
     and (tg_op = 'INSERT' or new.offer_id is distinct from old.offer_id)
     and not exists (select 1 from seo_offers o where o.id = new.offer_id and o.promotion_enabled and o.is_active) then
    raise exception 'Offer % is not enabled for promotion', new.offer_id using errcode = 'check_violation';
  end if;
  return new;
end $$;

drop trigger if exists seo_campaigns_enabled_offer on seo_campaigns;
create trigger seo_campaigns_enabled_offer before insert or update of offer_id on seo_campaigns
  for each row execute function seo_require_enabled_offer();

drop trigger if exists seo_articles_enabled_offer on seo_articles;
create trigger seo_articles_enabled_offer before insert or update of offer_id on seo_articles
  for each row execute function seo_require_enabled_offer();
