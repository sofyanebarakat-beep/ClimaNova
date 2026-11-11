// Cookie consent banner for Google Tag Manager / GA4 (public site only, never /admin).
// The tags themselves are in components/tracking-head.html (put on every page by scripts/inject-tracking.php):
// consent is DENIED by default for visitors in the EU/EEA, the UK and Switzerland until they accept here.
// GA4 (G-KRKT1F5KXS) is loaded by the Tag Manager container GTM-56FJN7F5 — do not add a second GA4 tag.

const CONSENT_KEY = "cn_cookie_consent"; // "granted" | "denied" — also read by the head snippet on the next page load

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

function showBanner() {
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
    if (typeof window.gtag === "function") window.gtag("consent", "update", consentState(value));
    else (window.dataLayer = window.dataLayer || []).push(["consent", "update", consentState(value)]);
    bar.remove();
  });
  document.body.appendChild(bar);
}

export function initAnalytics() {
  if (location.pathname.startsWith("/admin")) return;
  if (typeof window.gtag !== "function") return; // page without the tracking snippet: nothing to consent to
  if (!storedConsent()) showBanner();
}
