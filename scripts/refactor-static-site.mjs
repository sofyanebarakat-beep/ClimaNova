import fs from "node:fs";
import path from "node:path";

const root = process.cwd();
const excludedDirs = new Set([".git", "node_modules", "ClimaNovaEnergie copy", "climavovaall"]);
const sourceHtml = path.join(root, "innerpages/about-us.html");

function read(file) {
  return fs.readFileSync(file, "utf8");
}

function write(file, content) {
  fs.writeFileSync(file, content);
}

function listHtmlFiles(dir = root) {
  const entries = fs.readdirSync(dir, { withFileTypes: true });
  const files = [];

  for (const entry of entries) {
    if (excludedDirs.has(entry.name)) continue;

    const fullPath = path.join(dir, entry.name);
    if (entry.isDirectory()) {
      files.push(...listHtmlFiles(fullPath));
    } else if (entry.isFile() && entry.name.endsWith(".html")) {
      files.push(fullPath);
    }
  }

  return files;
}

function findMatchingTag(html, startIndex, tagName) {
  const tagPattern = new RegExp(`<\\/?${tagName}\\b[^>]*>`, "gi");
  tagPattern.lastIndex = startIndex;

  let depth = 0;
  let match;

  while ((match = tagPattern.exec(html))) {
    const token = match[0];
    const isClosing = token.startsWith(`</`);
    const isSelfClosing = token.endsWith("/>");

    if (!isClosing && !isSelfClosing) depth += 1;
    if (isClosing) depth -= 1;

    if (depth === 0) {
      return tagPattern.lastIndex;
    }
  }

  return -1;
}

function extractDivByClass(html, className) {
  const classPattern = new RegExp(`<div\\b(?=[^>]*class=["'][^"']*${className}[^"']*["'])[^>]*>`, "i");
  const match = classPattern.exec(html);
  if (!match) throw new Error(`Could not find div.${className}`);

  const end = findMatchingTag(html, match.index, "div");
  if (end === -1) throw new Error(`Could not match div.${className}`);

  return {
    start: match.index,
    end,
    html: html.slice(match.index, end),
  };
}

function extractFooter(html) {
  const start = html.indexOf('<footer class="footer-section"');
  if (start === -1) throw new Error("Could not find footer");

  const end = findMatchingTag(html, start, "footer");
  if (end === -1) throw new Error("Could not match footer");

  return {
    start,
    end,
    html: html.slice(start, end),
  };
}

function componentTemplate(html) {
  return html
    .replace(/(?<!__ASSET__\/)(?:\.\.\/)?images\//g, "__ASSET__/images/")
    .replace(/(?<!__ASSET__\/)(?:\.\.\/)?media\//g, "__ASSET__/media/");
}

function jsTemplateLiteral(content) {
  return content.replace(/\\/g, "\\\\").replace(/`/g, "\\`").replace(/\$\{/g, "\\${");
}

function replaceConstTemplate(file, constName, template) {
  const current = read(file);
  const next = current.replace(
    new RegExp(`const ${constName} = \`[\\s\\S]*?\`;`),
    `const ${constName} = \`${jsTemplateLiteral(template)}\`;`,
  );

  write(file, next);
}

function relativePrefix(file) {
  const relative = path.relative(root, path.dirname(file));
  if (!relative) return "";
  return `${"../".repeat(relative.split(path.sep).length)}`;
}

function replaceStylesheets(html, prefix) {
  let next = html;
  next = next.replace(/<link[^>]+href=["'][^"']*repairly\.webflow\.shared\.c9f4e2b89\.css["'][^>]*>\s*/g, "");
  next = next.replace(/<link[^>]+href=["'][^"']*brand\.css["'][^>]*>\s*/g, "");

  if (!next.includes("css/global.css")) {
    const viewport = /<meta[^>]+name=["']viewport["'][^>]*>/i.exec(next);
    const globalLink = `<link href="${prefix}css/global.css" rel="stylesheet" type="text/css">`;

    if (viewport) {
      next = `${next.slice(0, viewport.index + viewport[0].length)}${globalLink}${next.slice(viewport.index + viewport[0].length)}`;
    } else {
      next = next.replace("</head>", `${globalLink}</head>`);
    }
  }

  return next;
}

function replaceShell(html) {
  let next = html;

  const topBar = extractDivByClass(next, "top-bar");
  const afterTopBar = next.slice(topBar.end);
  const headerRelative = extractDivByClass(afterTopBar, "header w-nav");
  const headerEnd = topBar.end + headerRelative.end;
  next = `${next.slice(0, topBar.start)}<div data-component="site-header"></div>${next.slice(headerEnd)}`;

  const footer = extractFooter(next);
  next = `${next.slice(0, footer.start)}<div data-component="site-footer"></div>${next.slice(footer.end)}`;

  return next;
}

function ensureMainScript(html, prefix) {
  if (html.includes("js/core/main.js")) return html;

  const script = `<script type="module" src="${prefix}js/core/main.js"></script>`;
  const firstRuntimeScript = /<script[^>]+src=["'][^"']*(jquery|webflow)[^"']*["'][^>]*><\/script>/i.exec(html);

  if (firstRuntimeScript) {
    return `${html.slice(0, firstRuntimeScript.index)}${script}${html.slice(firstRuntimeScript.index)}`;
  }

  return html.replace("</body>", `${script}</body>`);
}

const source = read(sourceHtml);
const componentsDir = path.join(root, "components");
const normalizedHeader = source.includes("top-bar") && source.includes("header w-nav")
  ? componentTemplate(`${extractDivByClass(source, "top-bar").html}\n${extractDivByClass(source, "header w-nav").html}`)
  : read(path.join(componentsDir, "header.html"));
const normalizedFooter = source.includes("footer-section")
  ? componentTemplate(extractFooter(source).html)
  : read(path.join(componentsDir, "footer.html"));

write(path.join(componentsDir, "header.html"), normalizedHeader);
write(path.join(componentsDir, "footer.html"), normalizedFooter);
replaceConstTemplate(path.join(root, "js/components/site-header.js"), "HEADER_TEMPLATE", normalizedHeader);
replaceConstTemplate(path.join(root, "js/components/site-footer.js"), "FOOTER_TEMPLATE", normalizedFooter);

for (const file of listHtmlFiles()) {
  const relative = path.relative(root, file);
  const prefix = relativePrefix(file);
  let html = read(file);

  if (!html.includes("top-bar") || !html.includes("footer-section")) continue;

  html = replaceStylesheets(html, prefix);
  html = replaceShell(html);
  html = ensureMainScript(html, prefix);
  write(file, html);
  console.log(`refactored ${relative}`);
}
