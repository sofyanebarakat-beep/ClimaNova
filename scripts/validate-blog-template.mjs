#!/usr/bin/env node

import { readFile } from "node:fs/promises";

const files = process.argv.slice(2);

if (!files.length) {
  console.error("Usage: node scripts/validate-blog-template.mjs blog/<slug>/index.html [...]");
  process.exit(1);
}

const CLIMATISATION_TOPIC = /(climatisation|climatiseur|clim-reversible|confort-thermique|entretien-clim|depannage-clim|preparer-logement-ete)/i;
const HEADER = '<div data-component="site-header"></div>';
const FOOTER = '<div data-component="site-footer"></div>';
const MAIN_SCRIPT = /<script\s+type="module"\s+src="\/js\/core\/main\.js(?:\?[^\"]*)?"\s*><\/script>/i;
const CLIMATISATION_PILLAR_LINK = /<a\b[^>]*href=["'](?:https:\/\/climanova-energie\.fr)?\/climatisation-nice\/?["'][^>]*>/i;

let failed = false;

function count(haystack, needle) {
  return haystack.split(needle).length - 1;
}

for (const file of files) {
  const html = await readFile(file, "utf8");
  const errors = [];

  if (count(html, HEADER) !== 1) {
    errors.push("expected exactly one shared site-header mount point");
  }

  if (count(html, FOOTER) !== 1) {
    errors.push("expected exactly one shared site-footer mount point");
  }

  if (!MAIN_SCRIPT.test(html)) {
    errors.push("missing /js/core/main.js module script");
  }

  if (CLIMATISATION_TOPIC.test(file) && !CLIMATISATION_PILLAR_LINK.test(html)) {
    errors.push("missing contextual link to /climatisation-nice/");
  }

  if (errors.length) {
    failed = true;
    console.error(`FAIL ${file}`);
    errors.forEach((error) => console.error(`  - ${error}`));
  } else {
    console.log(`PASS ${file}`);
  }
}

if (failed) {
  process.exit(1);
}
