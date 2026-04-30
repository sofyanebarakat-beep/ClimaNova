import { assetUrl } from "../utils/paths.js";

function withAssetUrls(html) {
  return html.replaceAll("__ASSET__/", assetUrl(""));
}

export function renderFooter(target) {
  target.outerHTML = withAssetUrls(FOOTER_TEMPLATE);
}

const FOOTER_TEMPLATE = `<footer class="footer-section">
    <div class="container">
      <div class="footer-top-content-block">
        <div class="footer-logo-block-wrap">
          <a href="/ClimaNova/index.html" class="footer-logo-block w-inline-block">
            <img loading="lazy" src="__ASSET__/images/climanova-logo-footer.png" alt="ClimaNova Energie" class="footer-logo">
          </a>
          <p class="text-md neutral-9">ClimaNova Energie assure l'installation et l'entretien de vos équipements à Nice et en région PACA — climatisation, électricité, plomberie et chauffage.</p>
        </div>
        <div class="footer-link-block">
          <div class="newsletter-form-block w-form">
            <form id="email-form" name="email-form" data-name="Email Form" method="get" class="newsletter-form">
              <input class="newsletter-text-field w-input" maxlength="256" name="email" placeholder="Votre adresse email..." type="email" id="email" required>
              <input type="submit" data-wait="Envoi…" class="newsletter-submit-button w-button" value="S'inscrire">
              <label for="email" class="newsletter-label-text">Recevez nos conseils et offres exclusives !</label>
            </form>
            <div class="newsletter-success-message w-form-done"><div>Merci ! Votre inscription a bien été enregistrée.</div></div>
            <div class="w-form-fail"><div>Oups ! Une erreur s'est produite. Veuillez réessayer.</div></div>
          </div>
        </div>
      </div>
      <div class="footer-middle-content-block">
        <div class="footer-link-column">
          <div class="footer-list-title">Services</div>
          <div class="footer-link-list">
            <div class="w-dyn-list">
              <div role="list" class="footer-collection-list w-dyn-items">
                <div role="listitem" class="w-dyn-item"><a href="/ClimaNova/services/climatisation.html" class="footer-link-text">Climatisation</a></div>
                <div role="listitem" class="w-dyn-item"><a href="/ClimaNova/services/chauffage.html" class="footer-link-text">Chauffage</a></div>
                <div role="listitem" class="w-dyn-item"><a href="/ClimaNova/services/electricite.html" class="footer-link-text">Électricité</a></div>
                <div role="listitem" class="w-dyn-item"><a href="/ClimaNova/services/plomberie.html" class="footer-link-text">Plomberie</a></div>
                <div role="listitem" class="w-dyn-item"><a href="/ClimaNova/services/renovation.html" class="footer-link-text">Rénovation énergétique</a></div>
                <div role="listitem" class="w-dyn-item"><a href="/ClimaNova/services/plomberie-fuites.html" class="footer-link-text">Plomberie &amp; Fuites</a></div>
              </div>
            </div>
          </div>
        </div>
        <div class="footer-link-column">
          <div class="footer-list-title">Explorer</div>
          <div class="footer-link-list">
            <a href="/ClimaNova/innerpages/about-us.html" class="footer-link-text">À propos</a>
            <a href="/ClimaNova/innerpages/projects.html" class="footer-link-text">Réalisations</a>
            <a href="/ClimaNova/innerpages/blog.html" class="footer-link-text">Blog</a>
            <a href="/ClimaNova/innerpages/faq.html" class="footer-link-text">FAQ</a>
            <a href="/ClimaNova/template-pages/license.html" class="footer-link-text">Mentions légales</a>
            <a href="/ClimaNova/innerpages/contact-us.html" class="footer-link-text">Nous contacter</a>
          </div>
        </div>
        <div class="footer-link-column">
          <div class="footer-list-title">Contact</div>
          <div class="footer-link-list">
            <div class="footer-contact-info-wrap">
              <div class="footer-contact-info-icon w-embed">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="28" viewBox="0 0 24 28" fill="none">
                  <path d="M9.15826 7.71223L8.7556 6.80625C8.49232 6.21388 8.36068 5.91768 8.16381 5.69101C7.91708 5.40694 7.59547 5.19794 7.23568 5.08785C6.94859 5 6.62446 5 5.97621 5C5.02791 5 4.55376 5 4.15573 5.18229C3.68687 5.39702 3.26344 5.86328 3.09473 6.3506C2.95151 6.76429 2.99254 7.18943 3.07458 8.0397C3.94791 17.0902 8.90982 22.0521 17.9603 22.9255C18.8106 23.0075 19.2358 23.0485 19.6494 22.9053C20.1368 22.7366 20.603 22.3132 20.8178 21.8443C21 21.4463 21 20.9721 21 20.0238C21 19.3756 21 19.0515 20.9122 18.7644C20.8021 18.4046 20.5931 18.083 20.309 17.8362C20.0824 17.6394 19.7862 17.5077 19.1938 17.2444L18.2878 16.8418C17.6463 16.5567 17.3255 16.4141 16.9996 16.3831C16.6876 16.3534 16.3731 16.3972 16.0811 16.5109C15.776 16.6297 15.5064 16.8544 14.967 17.3039C14.4302 17.7512 14.1618 17.9749 13.8338 18.0947C13.543 18.201 13.1586 18.2403 12.8524 18.1952C12.5069 18.1443 12.2424 18.0029 11.7133 17.7202C10.0673 16.8405 9.15953 15.9328 8.27987 14.2868C7.99714 13.7577 7.85578 13.4932 7.80487 13.1477C7.75974 12.8415 7.79908 12.4571 7.9053 12.1663C8.02512 11.8383 8.24881 11.5699 8.69619 11.033C9.14562 10.4937 9.37034 10.224 9.48915 9.91891C9.60285 9.62694 9.64662 9.3124 9.61695 9.00048C9.58594 8.67452 9.44338 8.35376 9.15826 7.71223Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                </svg>
              </div>
              <a href="tel:+33652238164" class="footer-link-text">+33 6 52 23 81 64</a>
            </div>
            <div class="footer-contact-info-wrap">
              <div class="footer-contact-info-icon w-embed">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="28" viewBox="0 0 24 28" fill="none">
                  <path d="M2 8L8.91302 11.917C11.4616 13.361 12.5384 13.361 15.087 11.917L22 8" stroke="#C6C5C2" stroke-width="1.5" stroke-linejoin="round"/>
                  <path d="M2.01577 15.4756C2.08114 18.5412 2.11383 20.0739 3.24496 21.2094C4.37608 22.3448 5.95033 22.3843 9.09883 22.4634C11.0393 22.5122 12.9607 22.5122 14.9012 22.4634C18.0497 22.3843 19.6239 22.3448 20.7551 21.2094C21.8862 20.0739 21.9189 18.5412 21.9842 15.4756C22.0053 14.4899 22.0053 13.5101 21.9842 12.5244C21.9189 9.45886 21.8862 7.92609 20.7551 6.79066C19.6239 5.65523 18.0497 5.61568 14.9012 5.53657C12.9607 5.48781 11.0393 5.48781 9.09882 5.53656C5.95033 5.61566 4.37608 5.65521 3.24495 6.79065C2.11382 7.92608 2.08114 9.45885 2.01576 12.5244C1.99474 13.5101 1.99475 14.4899 2.01577 15.4756Z" stroke="#C6C5C2" stroke-width="1.5" stroke-linejoin="round"/>
                </svg>
              </div>
              <a href="mailto:contact@climanova-energie.fr" class="footer-link-text">contact@climanova-energie.fr</a>
            </div>
            <div class="footer-contact-info-wrap">
              <div class="footer-contact-info-icon w-embed">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="28" viewBox="0 0 24 28" fill="none">
                  <path d="M14.5 11C14.5 12.3807 13.3807 13.5 12 13.5C10.6193 13.5 9.5 12.3807 9.5 11C9.5 9.61929 10.6193 8.5 12 8.5C13.3807 8.5 14.5 9.61929 14.5 11Z" stroke="#C6C5C2" stroke-width="1.5"/>
                  <path d="M13.2574 19.4936C12.9201 19.8184 12.4693 20 12.0002 20C11.531 20 11.0802 19.8184 10.7429 19.4936C7.6543 16.5008 3.51519 13.1575 5.53371 8.30373C6.6251 5.67932 9.24494 4 12.0002 4C14.7554 4 17.3752 5.67933 18.4666 8.30373C20.4826 13.1514 16.3536 16.5111 13.2574 19.4936Z" stroke="#C6C5C2" stroke-width="1.5"/>
                </svg>
              </div>
              <a href="https://www.google.com/maps/place/218+Route+de+Turin,+06300+Nice" target="_blank" class="footer-link-text">218 ROUTE DE TURIN 06300 NICE</a>
            </div>
            <div class="footer-contact-info-wrap">
              <div class="footer-link-text">SIRET 99131644900016</div>
            </div>
          </div>
        </div>
        <div class="footer-social-block">
          <div class="footer-list-title">Suivez-nous</div>
          <div class="footer-social-icon-wrap">
            <a href="https://www.facebook.com/" target="_blank" class="footer-social-icon-block w-inline-block"><div class="footer-social-icon w-embed"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none"><path fill-rule="evenodd" clip-rule="evenodd" d="M5.15087 8.61238C4.33607 8.61238 4.16602 8.7723 4.16602 9.5383V10.9272C4.16602 11.6933 4.33607 11.8531 5.15087 11.8531H7.12056V17.4087C7.12056 18.1747 7.29061 18.3346 8.10541 18.3346H10.0751C10.8899 18.3346 11.0599 18.1747 11.0599 17.4087V11.8531H13.2716C13.8896 11.8531 14.0489 11.7402 14.2186 11.1816L14.6407 9.79272C14.9314 8.8358 14.7523 8.61238 13.6937 8.61238H11.0599V6.2976C11.0599 5.78623 11.5008 5.37167 12.0448 5.37167H14.8478C15.6626 5.37167 15.8327 5.21179 15.8327 4.44574V2.59389C15.8327 1.82784 15.6626 1.66797 14.8478 1.66797H12.0448C9.32518 1.66797 7.12056 3.74073 7.12056 6.2976V8.61238H5.15087Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg></div></a>
            <a href="https://www.instagram.com/" target="_blank" class="footer-social-icon-block w-inline-block"><div class="footer-social-icon w-embed"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M2.08398 9.9987C2.08398 6.26675 2.08398 4.40077 3.24335 3.2414C4.40273 2.08203 6.2687 2.08203 10.0007 2.08203C13.7326 2.08203 15.5986 2.08203 16.758 3.2414C17.9173 4.40077 17.9173 6.26675 17.9173 9.9987C17.9173 13.7306 17.9173 15.5966 16.758 16.756C15.5986 17.9154 13.7326 17.9154 10.0007 17.9154C6.2687 17.9154 4.40273 17.9154 3.24335 16.756C2.08398 15.5966 2.08398 13.7306 2.08398 9.9987Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="M13.75 10C13.75 12.0711 12.0711 13.75 10 13.75C7.92893 13.75 6.25 12.0711 6.25 10C6.25 7.92893 7.92893 6.25 10 6.25C12.0711 6.25 13.75 7.92893 13.75 10Z" stroke="currentColor" stroke-width="1.5"/><path d="M14.591 5.41797H14.582" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></div></a>
            <a href="https://www.linkedin.com/" target="_blank" class="footer-social-icon-block w-inline-block"><div class="footer-social-icon w-embed"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M3.74935 7.91797H3.33268C2.54701 7.91797 2.15417 7.91797 1.91009 8.16204C1.66602 8.40614 1.66602 8.79897 1.66602 9.58464V16.668C1.66602 17.4536 1.66602 17.8465 1.91009 18.0906C2.15417 18.3346 2.54701 18.3346 3.33268 18.3346H3.74935C4.53502 18.3346 4.92786 18.3346 5.17194 18.0906C5.41602 17.8465 5.41602 17.4536 5.41602 16.668V9.58464C5.41602 8.79897 5.41602 8.40614 5.17194 8.16204C4.92786 7.91797 4.53502 7.91797 3.74935 7.91797Z" stroke="currentColor" stroke-width="1.5"/><path d="M5.41602 3.54297C5.41602 4.5785 4.57655 5.41797 3.54102 5.41797C2.50548 5.41797 1.66602 4.5785 1.66602 3.54297C1.66602 2.50744 2.50548 1.66797 3.54102 1.66797C4.57655 1.66797 5.41602 2.50744 5.41602 3.54297Z" stroke="currentColor" stroke-width="1.5"/><path d="M10.271 7.91797H9.58268C8.79702 7.91797 8.40418 7.91797 8.16009 8.16204C7.91602 8.40614 7.91602 8.79897 7.91602 9.58464V16.668C7.91602 17.4536 7.91602 17.8465 8.16009 18.0906C8.40418 18.3346 8.79702 18.3346 9.58268 18.3346H9.99935C10.785 18.3346 11.1778 18.3346 11.4219 18.0906C11.666 17.8465 11.666 17.4536 11.666 16.668L11.6661 13.7514C11.6661 12.3707 12.1061 11.2514 13.4059 11.2514C14.0558 11.2514 14.5827 11.8111 14.5827 12.5014V16.2514C14.5827 17.0371 14.5827 17.4299 14.8268 17.674C15.0708 17.9181 15.4637 17.9181 16.2493 17.9181H16.6649C17.4504 17.9181 17.8432 17.9181 18.0873 17.6741C18.3313 17.4301 18.3314 17.0373 18.3316 16.2518L18.3328 11.6681C18.3328 9.59714 16.363 7.91817 14.4133 7.91817C13.3034 7.91817 12.3133 8.46222 11.6661 9.31297C11.666 8.78789 11.666 8.52539 11.552 8.33047C11.4798 8.20702 11.3769 8.10424 11.2535 8.03203C11.0586 7.91797 10.7961 7.91797 10.271 7.91797Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg></div></a>
          </div>
        </div>
      </div>
      <div class="footer-bottom-block">
        <p class="text-sm neutral-color-9">© Copyright 2026, Tous droits réservés — <a href="/ClimaNova/index.html" class="footer-bottom-link">ClimaNova Energie</a> <a href="https://sbmarketing.fr/" target="_blank" class="footer-bottom-link">Made by SB Marketing</a></p>
        <div class="footer-bottom-text-blcok">
          <a href="/ClimaNova/innerpages/privacy-policy" class="footer-bottom-link">Politique de confidentialité</a>
          <a href="/ClimaNova/innerpages/terms-conditions" class="footer-bottom-link">Conditions générales</a>
        </div>
      </div>
    </div>
  </footer>`;
