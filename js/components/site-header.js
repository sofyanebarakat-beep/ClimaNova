import { assetUrl, currentPath } from "../utils/paths.js";

function withAssetUrls(html) {
  return html.replaceAll("__ASSET__/", assetUrl(""));
}

function markCurrentNav(container) {
  const path = currentPath();
  const isServicesArea = path.includes("/services/") || path.endsWith("/services");

  container.querySelectorAll(".nav-menu-link, .cn-services-submenu-link").forEach((link) => {
    const href = link.getAttribute("href") || "";
    const normalizedHref = href === "/"
      ? "/"
      : href.replace(/\.html$/, "").replace(/\/$/, "");
    const isCurrent = link.dataset.navSection === "services"
      ? isServicesArea
      : path === normalizedHref || (
        normalizedHref !== "/"
        && path.endsWith(normalizedHref.replace(/^\/ClimaNova/, ""))
      );

    link.classList.toggle("w--current", isCurrent);
    if (isCurrent) {
      link.setAttribute("aria-current", "page");
    } else {
      link.removeAttribute("aria-current");
    }
  });
}

function initServicesDropdown(header) {
  const dropdown = header.querySelector(".cn-services-dropdown");
  const toggleArea = header.querySelector(".cn-services-dropdown-toggle");
  const toggle = header.querySelector(".cn-services-dropdown-button");

  if (!dropdown || !toggleArea || !toggle) return;

  const setOpen = (isOpen) => {
    dropdown.classList.toggle("is-open", isOpen);
    toggle.setAttribute("aria-expanded", String(isOpen));
  };

  toggleArea.addEventListener("click", (event) => {
    event.preventDefault();
    setOpen(!dropdown.classList.contains("is-open"));
  });

  document.addEventListener("click", (event) => {
    if (!dropdown.contains(event.target)) setOpen(false);
  });

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") {
      setOpen(false);
      toggle.focus();
    }
  });
}

function initMobileNav(header) {
  const toggle = header.querySelector(".nav-toggle");
  const nav = header.querySelector(".nav-wrapper");
  const menu = header.querySelector(".nav-main-menu");

  if (!toggle || !nav || !menu) return;
  if (!nav.id) nav.id = "site-mobile-menu";

  toggle.setAttribute("aria-controls", nav.id);
  toggle.setAttribute("aria-expanded", "false");
  toggle.setAttribute("aria-label", "Ouvrir le menu");

  const setOpen = (isOpen) => {
    header.classList.toggle("is-mobile-menu-open", isOpen);
    nav.classList.toggle("is-mobile-open", isOpen);
    nav.toggleAttribute("data-nav-menu-open", isOpen);
    menu.classList.toggle("is-mobile-open", isOpen);
    toggle.classList.toggle("w--open", isOpen);
    toggle.setAttribute("aria-expanded", String(isOpen));
    toggle.setAttribute("aria-label", isOpen ? "Fermer le menu" : "Ouvrir le menu");
  };

  toggle.addEventListener("click", () => setOpen(!nav.classList.contains("is-mobile-open")));
  nav.querySelectorAll("a").forEach((link) => link.addEventListener("click", () => setOpen(false)));

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && nav.classList.contains("is-mobile-open")) {
      setOpen(false);
      toggle.focus();
    }
  });

  window.addEventListener("resize", () => {
    if (window.innerWidth > 991) setOpen(false);
  });
}

function initScrollHeader(header) {
  const firstSection = document.querySelector("main > section, .main-wrapper > section");

  // Cache full header height once before any scroll state changes.
  // Using header.offsetHeight live causes a feedback loop: toggling is-scrolled
  // shrinks the header, which changes offsetHeight, which flips the condition back.
  const fullHeaderH = header.offsetHeight;

  const syncScrollState = () => {
    const reachedSecondSection = firstSection
      ? firstSection.getBoundingClientRect().bottom <= fullHeaderH
      : window.scrollY > 24;

    header.classList.toggle("is-scrolled", reachedSecondSection);
  };

  syncScrollState();
  window.addEventListener("scroll", syncScrollState, { passive: true });
  window.addEventListener("resize", syncScrollState);
}

function initServiceRotator(header) {
  const target = header.querySelector("[data-service-rotator]");
  const prefix = header.querySelector("[data-service-prefix]");
  if (!target || !prefix) return;

  const items = [
    { prefix: "Experts en", html: "Climatisation", delay: 2800 },
    { prefix: "Experts en", html: "Chauffage", delay: 2800 },
    { prefix: "Experts en", html: "Plomberie", delay: 2800 },
    { prefix: "Experts en", html: "Électricité", delay: 2800 },
    { prefix: "", html: '<span class="cn-tagline-blue">Votre confort, notre</span>&nbsp;<span class="cn-tagline-green">énergie</span>', delay: 40000, tagline: true },
  ];
  let index = 0;

  const renderNext = () => {
    target.classList.add("is-changing");
    window.setTimeout(() => {
      index = (index + 1) % items.length;
      const item = items[index];
      prefix.textContent = item.prefix;
      target.innerHTML = item.html;
      target.classList.toggle("is-tagline", Boolean(item.tagline));
      target.classList.remove("is-changing");
      window.setTimeout(renderNext, item.delay);
    }, 180);
  };

  window.setTimeout(renderNext, items[0].delay);
}

export function renderHeader(target) {
  target.outerHTML = withAssetUrls(HEADER_TEMPLATE);
  const header = document.querySelector(".cn-header");

  if (header) {
    markCurrentNav(header);
    initServicesDropdown(header);
    initMobileNav(header);
    initScrollHeader(header);
    initServiceRotator(header);
  }
}

const socialLinks = `
  <a href="https://www.facebook.com/profile.php?id=61589614010567" target="_blank" rel="noopener" aria-label="Facebook"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M13.5 21v-7h2.75l.41-3H13.5V9.08c0-.87.24-1.46 1.48-1.46H16.8V4.94a24.37 24.37 0 0 0-2.37-.12c-2.35 0-3.96 1.44-3.96 4.07V11H7.8v3h2.67v7h3.03Z"/></svg></a>
  <a href="https://www.instagram.com/climanova.energie/" target="_blank" rel="noopener" aria-label="Instagram"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7.5 2h9A5.5 5.5 0 0 1 22 7.5v9a5.5 5.5 0 0 1-5.5 5.5h-9A5.5 5.5 0 0 1 2 16.5v-9A5.5 5.5 0 0 1 7.5 2Zm0 2A3.5 3.5 0 0 0 4 7.5v9A3.5 3.5 0 0 0 7.5 20h9a3.5 3.5 0 0 0 3.5-3.5v-9A3.5 3.5 0 0 0 16.5 4h-9Zm10.75 1.5a1.25 1.25 0 1 1 0 2.5 1.25 1.25 0 0 1 0-2.5ZM12 7a5 5 0 1 1 0 10 5 5 0 0 1 0-10Zm0 2a3 3 0 1 0 0 6 3 3 0 0 0 0-6Z"/></svg></a>
  <a href="https://www.tiktok.com/@climanova.energie" target="_blank" rel="noopener" aria-label="TikTok"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15.5 3c.35 1.78 1.4 3.25 3 4.13A6.73 6.73 0 0 0 21 8v3.03a9.54 9.54 0 0 1-5.5-1.77v6.48a6.26 6.26 0 1 1-5.38-6.2v3.1a3.3 3.3 0 1 0 2.38 3.1V3h3Z"/></svg></a>
`;

const HEADER_TEMPLATE = `
<header class="cn-header" role="banner">
  <div class="cn-header-top"><div class="container cn-header-top-inner">
    <div class="cn-brand-cluster"><a href="/" class="cn-brand" aria-label="ClimaNova Énergie, retour à l'accueil"><picture><source srcset="__ASSET__/images/climanova-logo.webp" type="image/webp"><img loading="eager" src="__ASSET__/images/climanova-logo.png" alt="ClimaNova Énergie" class="cn-brand-logo"></picture></a><div class="cn-service-rotator" aria-live="polite"><span data-service-prefix>Experts en</span><strong data-service-rotator>Climatisation</strong></div></div>
    <div class="cn-header-actions" aria-label="Actions rapides"><a href="tel:+33652238164" class="cn-phone-button" aria-label="Appeler ClimaNova Énergie au +33 6 52 23 81 64"><span class="cn-phone-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M6.62 10.79a15.05 15.05 0 0 0 6.59 6.59l2.2-2.2a1 1 0 0 1 1.02-.24 11.36 11.36 0 0 0 3.57.57 1 1 0 0 1 1 1V20a1 1 0 0 1-1 1C10.61 21 3 14.39 3 6a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1 11.36 11.36 0 0 0 .57 3.57 1 1 0 0 1-.25 1.02l-2.2 2.2Z" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg></span><span>+33 6 52 23 81 64</span></a><a href="/demande-devis/" class="cn-action-button cn-action-button-secondary">Demande de devis</a></div>
  </div></div>
  <div class="cn-header-nav"><div class="container cn-header-nav-inner">
    <a href="/" class="cn-scroll-brand" aria-label="ClimaNova Énergie, retour à l'accueil"><picture><source srcset="__ASSET__/images/climanova-logo.webp" type="image/webp"><img src="__ASSET__/images/climanova-logo.png" alt="ClimaNova Énergie"></picture></a>
    <nav class="nav-wrapper cn-nav" aria-label="Navigation principale"><div class="nav-main-menu cn-nav-menu">
      <div class="nav-menu-item"><a href="/" class="nav-menu-link">Accueil</a></div>
      <div class="nav-menu-item"><a href="/about-us" class="nav-menu-link">À propos</a></div>
      <div class="nav-services-dropdown cn-services-dropdown"><div class="nav-service-dropdown-toggle cn-services-dropdown-toggle"><div class="nav-menu-item"><button type="button" class="nav-menu-link cn-services-dropdown-button" data-nav-section="services" aria-haspopup="true" aria-expanded="false">Services</button></div><span class="cn-dropdown-chevron" aria-hidden="true"></span></div><nav class="nav-service-dropdown-list cn-services-dropdown-list" aria-label="Services"><a href="/services" class="nav-list-item-link cn-services-submenu-link">Tous les services</a><a href="/services/climatisation" class="nav-list-item-link cn-services-submenu-link">Climatisation</a><a href="/services/chauffage" class="nav-list-item-link cn-services-submenu-link">Chauffage</a><a href="/services/electricite" class="nav-list-item-link cn-services-submenu-link">Électricité</a><a href="/services/plomberie" class="nav-list-item-link cn-services-submenu-link">Plomberie</a><a href="/services/renovation-energetique" class="nav-list-item-link cn-services-submenu-link">Rénovation énergétique</a><a href="/services/entretien" class="nav-list-item-link cn-services-submenu-link">Entretien &amp; dépannage</a></nav></div>
      <div class="nav-menu-item"><a href="/blog" class="nav-menu-link">Blog</a></div>
      <div class="nav-menu-item"><a href="/faq" class="nav-menu-link">FAQ</a></div>
      <div class="cn-mobile-panel"><a href="tel:+33652238164" class="cn-mobile-phone"><span class="cn-mobile-phone-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M6.62 10.79a15.05 15.05 0 0 0 6.59 6.59l2.2-2.2a1 1 0 0 1 1.02-.24 11.36 11.36 0 0 0 3.57.57 1 1 0 0 1 1 1V20a1 1 0 0 1-1 1C10.61 21 3 14.39 3 6a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1 11.36 11.36 0 0 0 .57 3.57 1 1 0 0 1-.25 1.02l-2.2 2.2Z" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg></span><span>Appeler le +33 6 52 23 81 64</span></a><a href="/demande-devis/" class="cn-mobile-action cn-mobile-action-secondary">Demande de devis</a><div class="cn-social-links cn-social-links-mobile" aria-label="Réseaux sociaux">${socialLinks}</div></div>
    </div></nav>
    <div class="cn-social-links cn-social-links-desktop" aria-label="Réseaux sociaux">${socialLinks}</div>
    <a href="/demande-devis/" class="cn-scroll-cta"><span class="cn-scroll-cta-full">Demande de devis</span><span class="cn-scroll-cta-short">Devis</span></a>
    <button type="button" class="nav-toggle cn-nav-toggle" aria-controls="site-mobile-menu" aria-expanded="false" aria-label="Ouvrir le menu"><span></span><span></span><span></span></button>
  </div></div>
</header>`;
