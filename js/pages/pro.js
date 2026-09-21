/*
 * /pro/ — formulaire de candidature partenaire.
 *
 * Envoi :
 *  - si <form data-endpoint="https://…"> est renseigné → POST JSON vers cet endpoint ;
 *  - sinon → ouverture d'un e-mail pré-rempli vers data-fallback-email (aucun service tiers).
 */

const form = document.querySelector("[data-pro-form]");

if (form) {
  const errorBox = form.querySelector("[data-pro-error]");
  const submitButton = form.querySelector("[data-pro-submit]");
  const tradeInputs = [...form.querySelectorAll('input[name="metiers"]')];
  const endpoint = (form.dataset.endpoint || "").trim();
  const fallbackEmail = form.dataset.fallbackEmail || "contact@climanova-energie.fr";

  const showError = (message) => {
    errorBox.textContent = message;
    errorBox.classList.add("is-visible");
  };
  const clearError = () => errorBox.classList.remove("is-visible");

  // Les cartes « offre » pré-sélectionnent les métiers correspondants.
  document.querySelectorAll("[data-profil]").forEach((link) => {
    link.addEventListener("click", () => {
      const wanted = link.dataset.profil.split(",");
      tradeInputs.forEach((input) => { input.checked = wanted.includes(input.value); });
      clearError();
    });
  });

  tradeInputs.forEach((input) => input.addEventListener("change", clearError));

  const collect = () => {
    const data = new FormData(form);
    return {
      entreprise: (data.get("entreprise") || "").trim(),
      contact: (data.get("contact") || "").trim(),
      telephone: (data.get("telephone") || "").trim(),
      email: (data.get("email") || "").trim(),
      siret: (data.get("siret") || "").trim(),
      code_postal: (data.get("code_postal") || "").trim(),
      metiers: tradeInputs.filter((input) => input.checked).map((input) => input.closest("label").textContent.trim()),
      rge: data.get("rge") || "",
      disponibilite: data.get("disponibilite") || "",
      message: (data.get("message") || "").trim(),
      source: "climanova-energie.fr/pro/",
    };
  };

  const buildMailto = (p) => {
    const lines = [
      "Nouvelle candidature partenaire ClimaNova Pro",
      "",
      `Entreprise : ${p.entreprise}`,
      `Contact : ${p.contact}`,
      `Téléphone : ${p.telephone}`,
      `E-mail : ${p.email}`,
      `SIRET : ${p.siret || "—"}`,
      `Code postal : ${p.code_postal}`,
      `Métiers : ${p.metiers.join(", ")}`,
      `Certification RGE : ${p.rge}`,
      `Disponibilité : ${p.disponibilite}`,
      "",
      p.message ? `Message : ${p.message}` : "",
    ];
    const subject = `Candidature partenaire — ${p.entreprise}`;
    return `mailto:${fallbackEmail}?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(lines.join("\n"))}`;
  };

  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    clearError();

    // Anti-spam : le champ piège doit rester vide.
    if (form.elements.website && form.elements.website.value) return;

    if (!tradeInputs.some((input) => input.checked)) {
      showError("Sélectionnez au moins un métier.");
      tradeInputs[0].focus();
      return;
    }
    if (!form.reportValidity()) return;

    const payload = collect();
    submitButton.disabled = true;
    const label = submitButton.querySelector("[data-pro-submit-label]");
    const initialLabel = label.textContent;
    label.textContent = "Envoi en cours…";

    try {
      if (endpoint) {
        const response = await fetch(endpoint, {
          method: "POST",
          headers: { "Content-Type": "application/json", Accept: "application/json" },
          body: JSON.stringify(payload),
        });
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        form.dataset.mode = "sent";
      } else {
        window.location.href = buildMailto(payload);
        form.dataset.mode = "mailto";
      }

      form.classList.add("is-sent");
      form.querySelector(".cn-pro-success").focus();
      if (typeof window.fbq === "function") window.fbq("trackCustom", "PartnerApplication");
    } catch (error) {
      showError(`L'envoi a échoué. Réessayez ou appelez-nous au +33 6 52 23 81 64.`);
    } finally {
      submitButton.disabled = false;
      label.textContent = initialLabel;
    }
  });
}
