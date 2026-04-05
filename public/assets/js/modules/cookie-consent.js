/**
 * Gestion du consentement aux cookies (RGPD / CNIL).
 *
 * Utilisation de Google Analytics :
 *   1. Définir window.GA_ID = 'G-XXXXXXXXXX' AVANT ce script (dans base.html.twig)
 *   2. GA sera chargé uniquement si l'utilisateur accepte les cookies analytics
 *
 * Consentement stocké dans localStorage sous la clé 'cookie_consent' :
 *   - null / absent  → bannière affichée
 *   - 'accepted'     → analytics chargé
 *   - 'declined'     → analytics non chargé
 */

const STORAGE_KEY = 'cookie_consent';
const BANNER_ID   = 'cookie-banner';

function getConsent() {
  return localStorage.getItem(STORAGE_KEY);
}

function setConsent(value) {
  localStorage.setItem(STORAGE_KEY, value);
}

function loadGoogleAnalytics() {
  const gaId = window.GA_ID;
  if (!gaId || document.getElementById('ga-script')) return;

  const script = document.createElement('script');
  script.id    = 'ga-script';
  script.async = true;
  script.src   = `https://www.googletagmanager.com/gtag/js?id=${gaId}`;
  document.head.appendChild(script);

  window.dataLayer = window.dataLayer || [];
  function gtag() { window.dataLayer.push(arguments); }
  window.gtag = gtag;
  gtag('js', new Date());
  gtag('config', gaId, { anonymize_ip: true });
}

function removeBanner() {
  document.getElementById(BANNER_ID)?.remove();
}

function handleAccept() {
  setConsent('accepted');
  removeBanner();
  loadGoogleAnalytics();
}

function handleDecline() {
  setConsent('declined');
  removeBanner();
}

function handleCustomize() {
  document.getElementById('cookie-banner-main').style.display    = 'none';
  document.getElementById('cookie-banner-detail').style.display  = 'block';
}

function handleDetailSave() {
  const analyticsChecked = document.getElementById('cookie-analytics')?.checked;
  setConsent(analyticsChecked ? 'accepted' : 'declined');
  removeBanner();
  if (analyticsChecked) loadGoogleAnalytics();
}

function buildBanner() {
  const banner = document.createElement('div');
  banner.id        = BANNER_ID;
  banner.setAttribute('role', 'dialog');
  banner.setAttribute('aria-label', 'Gestion des cookies');
  banner.innerHTML = `
    <div id="cookie-banner-main">
      <div class="cookie-banner__text">
        <strong>🍪 Ce site utilise des cookies</strong>
        <p>
          Les cookies nécessaires assurent le fonctionnement du site (panier, connexion).
          Les cookies analytiques (optionnels) nous aident à améliorer votre expérience.
          <a href="/page/politique-cookies" class="cookie-banner__link">En savoir plus</a>
        </p>
      </div>
      <div class="cookie-banner__actions">
        <button id="cookie-decline"   class="cookie-btn cookie-btn--outline">Refuser</button>
        <button id="cookie-customize" class="cookie-btn cookie-btn--outline">Personnaliser</button>
        <button id="cookie-accept"    class="cookie-btn cookie-btn--primary">Tout accepter</button>
      </div>
    </div>

    <div id="cookie-banner-detail" style="display:none;">
      <div class="cookie-banner__text">
        <strong>Paramétrer les cookies</strong>
        <ul class="cookie-list">
          <li>
            <label class="cookie-toggle">
              <input type="checkbox" checked disabled>
              <span class="cookie-toggle__label">
                <strong>Cookies nécessaires</strong>
                <small>Session, panier, authentification. Toujours actifs.</small>
              </span>
            </label>
          </li>
          <li>
            <label class="cookie-toggle">
              <input type="checkbox" id="cookie-analytics">
              <span class="cookie-toggle__label">
                <strong>Cookies analytiques</strong>
                <small>Google Analytics — mesure d'audience anonymisée.</small>
              </span>
            </label>
          </li>
        </ul>
      </div>
      <div class="cookie-banner__actions">
        <button id="cookie-detail-save" class="cookie-btn cookie-btn--primary">Enregistrer mes choix</button>
      </div>
    </div>
  `;
  document.body.appendChild(banner);

  document.getElementById('cookie-accept')?.addEventListener('click', handleAccept);
  document.getElementById('cookie-decline')?.addEventListener('click', handleDecline);
  document.getElementById('cookie-customize')?.addEventListener('click', handleCustomize);
  document.getElementById('cookie-detail-save')?.addEventListener('click', handleDetailSave);
}

export function initCookieConsent() {
  const consent = getConsent();

  if (consent === 'accepted') {
    // Déjà accepté → charger GA silencieusement
    loadGoogleAnalytics();
    return;
  }

  if (consent === 'declined') {
    // Déjà refusé → rien à faire
    return;
  }

  // Pas encore de choix → afficher la bannière
  buildBanner();
}

/**
 * Expose une fonction globale pour rouvrir le panneau de paramétrage
 * depuis le lien "Gérer mes cookies" dans le footer.
 */
export function reopenCookieConsent() {
  document.getElementById(BANNER_ID)?.remove();
  localStorage.removeItem(STORAGE_KEY);
  buildBanner();
}
