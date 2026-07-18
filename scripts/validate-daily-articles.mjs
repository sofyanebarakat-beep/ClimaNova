import fs from "node:fs";
import path from "node:path";

const root = process.cwd();
const history = JSON.parse(fs.readFileSync(path.join(root, "scripts/daily-article-history.json"), "utf8"));
const latestDate = history.at(-1)?.date;
if (!latestDate) {
  console.log("No generated article history yet; configuration is valid.");
  process.exit(0);
}
const latest = history.filter((item) => item.date === latestDate);
const errors = [];
const expectedTracks = new Set(["climatisation", "chauffage", "electricite", "plomberie", "renovation-energetique", "entretien-depannage", "climanova-energie"]);
const configuredMinimum = Number.parseInt(process.env.DAILY_MIN_ARTICLES || "1", 10);
const minimumArticles = Number.isInteger(configuredMinimum) && configuredMinimum > 0 ? configuredMinimum : 1;
if (latest.length < minimumArticles) errors.push(`${latestDate}: expected at least ${minimumArticles} article(s), found ${latest.length}`);
if (latest.length !== expectedTracks.size) console.warn(`${latestDate}: partial batch — expected 7 articles, found ${latest.length}`);
for (const track of expectedTracks) {
  if (!latest.some((item) => item.track === track)) console.warn(`${latestDate}: missing track ${track}`);
}
for (const item of latest) {
  const file = path.join(root, "blog", item.slug, "index.html");
  if (!fs.existsSync(file)) { errors.push(`${item.slug}: missing file`); continue; }
  const html = fs.readFileSync(file, "utf8");
  const count = (pattern) => (html.match(pattern) || []).length;
  if (count(/<h1\b/g) !== 1) errors.push(`${item.slug}: expected exactly one h1`);
  if (count(/<h2\b/g) < 9) errors.push(`${item.slug}: expected at least nine h2 headings`);
  if (count(/<h3\b/g) < 12) errors.push(`${item.slug}: expected at least twelve FAQ h3 headings`);
  for (const required of ['rel="canonical"', 'application/ld+json', 'property="og:title"', 'name="twitter:card"', 'href="/demande-devis/"']) {
    if (!html.includes(required)) errors.push(`${item.slug}: missing ${required}`);
  }
}
if (errors.length) {
  console.error(errors.join("\n"));
  process.exit(1);
}
console.log(`Validated ${latest.length} article(s) for ${latestDate}.`);
