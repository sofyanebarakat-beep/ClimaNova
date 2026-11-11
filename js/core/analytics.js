// Google Analytics 4 for the public site (never loaded in /admin).
// Consent mode v2: analytics and ads storage are DENIED by default for visitors in the EU/EEA, the UK and Switzerland
// (Google applies the region rule from the visitor's location) until they accept cookies in the banner below.
// Set the measurement ID (Admin → Data streams → G-XXXXXXX) to turn tracking on; while empty nothing is loaded.
export const GA4_MEASUREMENT_ID = "";

const CONSENT_KEY = "cn_cookie_consent"; // "granted" | "denied"
const CONSENT_REGIONS = [
  "AT", "BE", "BG", "HR", "CY", "CZ", "DK", "EE", "FI", "FR", "DE", "GR", "HU", "IE", "IT", "LV", "LT", "LU", "MT", "NL",
  "PL", "PT", "RO", "SK", "SI", "ES", "SE", "IS", "LI", "NO", "GB", "CH",
];

function storedConsent() {
  try { return localStorage.getItem(CONSENT_KEY); } catch { return null; }
}

function saveConsent(value) {
  try { localStorage.setItem(CONSENT_KEY, value); } catch { /* private mode: ask again next visit */ }
}

function consentState(value) {
  const v = value === "granted" ? "granted" : "denied";
  return { ad_storage: v, analytics_storage: v, ad_user_data: v, ad_personalization: v };
}

function showBanner(gtag) {
  if (document.getElementById("cn-cookie-banner")) return;
  const bar = document.createElement("div");
  bar.id = "cn-cookie-banner";
  bar.setAttribute("role", "dialog");
  bar.setAttribute("aria-label", "Cookies");
  bar.style.cssText = "position:fixed;left:16px;right:16px;bottom:16px;z-index:9999;max-width:560px;margin:0 auto;background:#fff;color:#222;"
    + "border:1px solid #deddd9;border-radius:14px;box-shadow:0 10px 30px rgba(0,0,0,.15);padding:16px;font:15px/1.5 'Instrument Sans',system-ui,sans-serif";
  bar.innerHTML = '<p style="margin:0 0 12px">Nous utilisons des cookies de mesure d’audience (Google Analytics) pour améliorer le site. '
    + 'Vous pouvez les accepter ou les refuser. <a href="/privacy-policy/" style="color:#1664bf">En savoir plus</a></p>'
    + '<div style="display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap">'
    + '<button type="button" data-consent="denied" style="font:inherit;padding:8px 16px;border-radius:10px;border:1px solid #deddd9;background:#fff;cursor:pointer">Refuser</button>'
    + '<button type="button" data-consent="granted" style="font:inherit;padding:8px 16px;border-radius:10px;border:0;background:#1664bf;color:#fff;font-weight:600;cursor:pointer">Accepter</button></div>';
  bar.addEventListener("click", (e) => {
    const value = e.target?.dataset?.consent;
    if (!value) return;
    saveConsent(value);
    gtag("consent", "update", consentState(value));
    bar.remove();
  });
  document.body.appendChild(bar);
}

export function initAnalytics() {
  if (!GA4_MEASUREMENT_ID || location.pathname.startsWith("/admin")) return;
  window.dataLayer = window.dataLayer || [];
  function gtag() { window.dataLayer.push(arguments); }
  window.gtag = gtag;

  // Defaults must be set before the tag loads. Denied in the listed regions, granted elsewhere.
  gtag("consent", "default", { ...consentState("denied"), region: CONSENT_REGIONS, wait_for_update: 500 });
  gtag("consent", "default", consentState("granted"));
  const saved = storedConsent();
  if (saved) gtag("consent", "update", consentState(saved));

  gtag("js", new Date());
  gtag("config", GA4_MEASUREMENT_ID);
  const s = document.createElement("script");
  s.async = true;
  s.src = "https://www.googletagmanager.com/gtag/js?id=" + encodeURIComponent(GA4_MEASUREMENT_ID);
  document.head.appendChild(s);

  if (!saved) showBanner(gtag);
}
