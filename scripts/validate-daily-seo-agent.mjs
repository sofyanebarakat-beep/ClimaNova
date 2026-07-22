#!/usr/bin/env node

import fs from "node:fs";
import path from "node:path";

const root = process.cwd();
const reportPath = path.join(root, "scripts/daily-seo-agent-report.json");
if (!fs.existsSync(reportPath)) {
  console.log("No daily agent report; nothing to validate.");
  process.exit(0);
}

const report = JSON.parse(fs.readFileSync(reportPath, "utf8"));
const errors = [];
const count = (text, pattern) => (text.match(pattern) || []).length;

for (const item of report.generated) {
  const file = path.join(root, "blog", item.slug, "index.html");
  if (!fs.existsSync(file)) { errors.push(`${item.slug}: file missing`); continue; }
  const html = fs.readFileSync(file, "utf8");
  const checks = [
    [count(html, /<h1\b/gi) === 1, "requires exactly one H1"],
    [count(html, /<table\b/gi) >= 7, "requires at least 7 tables"],
    [count(html, /class=["']cn-direct-answer["']/gi) >= 10, "requires at least 10 direct-answer blocks"],
    [count(html, /"@type":"Question"/g) === 20, "requires exactly 20 FAQ schema questions"],
    [count(html, /data-component="site-header"/g) === 1, "requires one shared header"],
    [count(html, /data-component="site-footer"/g) === 1, "requires one shared footer"],
    [/<script type="module" src="\/js\/core\/main\.js/.test(html), "requires the shared module script"],
    [/<link rel="canonical"/.test(html) && /property="og:title"/.test(html) && /name="twitter:card"/.test(html), "requires canonical, OG and Twitter metadata"],
    [count(html, /cn-blog-cta-banner--full/g) >= 2, "requires full CTA banners"],
    [count(html, /blog-details-sidebar-post-block/g) === 4, "requires exactly four related posts"],
  ];
  for (const [ok, message] of checks) if (!ok) errors.push(`${item.slug}: ${message}`);
}

if (report.generated.length > 5) errors.push("Agent generated more than five articles");
if (errors.length) {
  for (const error of errors) console.error(`FAIL ${error}`);
  process.exit(1);
}
console.log(`PASS: ${report.generated.length} generated article(s) validated.`);

