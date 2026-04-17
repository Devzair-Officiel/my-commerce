import { fetchJson } from "../../utils/fetch.js";

let selectedPickupPoint = null;
let widgetInitialized = false;

export function initPickupPointSelector({ onPickupPointChange, onShippingModeChange } = {}) {
    const shippingSection  = document.getElementById("shipping-address-section");
    const pickupSection    = document.getElementById("pickup-point-section");
    const selectedEl       = document.getElementById("pickup-selected");
    const selectedLabel    = document.getElementById("pickup-selected-label");
    const openBtnWrapper   = document.getElementById("pickup-open-btn-wrapper");
    const openBtn          = document.getElementById("pickup-open-btn");
    const carrierOptions   = document.querySelectorAll(".carrier-option");
    const modalEl          = document.getElementById("colissimo-modal");

    if (!carrierOptions.length) return;

    // Données pour pré-remplir le widget depuis le dataset
    const mainContent = document.querySelector(".main_content");
    const token       = mainContent?.dataset?.colissimoToken ?? "";
    const zipCode     = mainContent?.dataset?.colissimoZipcode ?? "";
    const city        = mainContent?.dataset?.colissimoCity ?? "";

    // Pré-initialiser dès le chargement si un carrier point relais existe sur la page
    const hasPickupCarrier = [...carrierOptions].some(l => l.dataset.hasPickup === "true");
    if (hasPickupCarrier && token) {
        initWidget();
    }

    // Appliquer le mode initial selon le carrier coché
    // Aucun carrier sélectionné par défaut — on masque les deux sections
    const checkedRadio = document.querySelector(".carrier-radio:checked");
    if (checkedRadio) {
        const label = checkedRadio.closest(".carrier-option");
        applyMode(label?.dataset?.hasPickup === "true");
        updateCarrierActiveState(label);
    } else {
        shippingSection?.classList.add("d-none");
        pickupSection?.classList.add("d-none");
    }

    // Changement de carrier
    carrierOptions.forEach((label) => {
        label.addEventListener("click", async () => {
            updateCarrierActiveState(label);
            const radio = label.querySelector(".carrier-radio");
            if (radio) radio.checked = true;

            const isPickup = label.dataset.hasPickup === "true";
            applyMode(isPickup);
            onShippingModeChange?.(isPickup);

            if (!isPickup) {
                await clearPickupPoint(onPickupPointChange);
                selectedEl?.classList.add("d-none");
                openBtnWrapper?.classList.remove("d-none");
                if (selectedLabel) selectedLabel.textContent = "";
            }
        });
    });

    // Bouton "Choisir un point relais" → ouvre la modale
    openBtn?.addEventListener("click", () => openModal());

    // Bouton "Changer de point de retrait" — délégation d'événement (le bouton peut être recréé par le DOM)
    selectedEl?.addEventListener("click", async (e) => {
        if (!e.target.closest(".pickup-selected")) return;
        await clearPickupPoint(onPickupPointChange);
        selectedEl.classList.add("d-none");
        openBtnWrapper?.classList.remove("d-none");
        openModal();
    });

    // Callback global appelé par le widget quand un point est sélectionné
    window.onColissimoPointSelected = async (point) => {
        const normalize = (v) => String(v ?? "").replace(/[\r\n\t]+/g, " ").replace(/\s{2,}/g, " ").trim();
        const normalizedPoint = {
            id:         normalize(point.identifiant),
            name:       normalize(point.nom),
            address:    normalize(point.adresse1),
            city:       normalize(point.localite),
            postalCode: normalize(point.codePostal),
        };

        try {
            await fetchJson("/api/pickup-points/select", {
                method: "POST",
                headers: { "Content-Type": "application/json", Accept: "application/json" },
                body: JSON.stringify(normalizedPoint),
            });

            selectedPickupPoint = normalizedPoint;

            // Fermer la modale Bootstrap
            if (modalEl && window.bootstrap) {
                bootstrap.Modal.getInstance(modalEl)?.hide();
            }

            // Afficher le récapitulatif (même style qu'une carte d'adresse)
            if (selectedLabel) {
                selectedLabel.innerHTML =
                    `<div class="address-card-name">${escapeHtml(normalizedPoint.name)}</div>` +
                    `<div class="address-card-details">` +
                    `${escapeHtml(normalizedPoint.address)}<br>` +
                    `${escapeHtml(normalizedPoint.postalCode)} ${escapeHtml(normalizedPoint.city)}` +
                    `</div>`;
            }
            selectedEl?.classList.remove("d-none");
            openBtnWrapper?.classList.add("d-none");

            onPickupPointChange?.(normalizedPoint);
        } catch (error) {
            console.error("[PickupPoint] Erreur sélection :", error);
        }
    };

    const modalBodyEl = document.getElementById("colissimo-modal-body");
    const loaderEl    = document.getElementById("colissimo-loader");

    function hideLoader() {
        if (loaderEl) {
            loaderEl.style.transition = "opacity 0.3s";
            loaderEl.style.opacity = "0";
            setTimeout(() => loaderEl.remove(), 300);
        }
    }

    // Démarre l'initialisation du widget dans le container hors-écran
    function initWidget() {
        if (widgetInitialized || !token) return;

        waitForColissimoPlugin(5000).then((ready) => {
            if (!ready) {
                console.error("[PickupPoint] Plugin Colissimo non chargé.");
                hideLoader();
                return;
            }

            jQuery("#colissimo-widget-container").frameColissimoOpen({
                URLColissimo:      "https://ws.colissimo.fr",
                ceLang:            "FR",
                ceCountryList:     "FR",
                ceCountry:         "FR",
                ceZipCode:         zipCode,
                ceTown:            city,
                token:             token,
                callBackFrame:     "onColissimoPointSelected",
                dyPreparationTime: "1",
                dyWeight:          "500",
                origin:            "WIDGET",
                filterRelay:       "1",
            });

            widgetInitialized = true;

            // Quand les données sont prêtes, masquer le loader (si la modale est déjà ouverte)
            waitForWidgetReady().then(hideLoader);
        });
    }

    // Déplace le widget du container hors-écran vers la modale
    function moveWidgetToModal() {
        const widgetEl = document.getElementById("colissimo-widget-container");
        if (widgetEl && modalBodyEl && widgetEl.parentElement !== modalBodyEl) {
            modalBodyEl.appendChild(widgetEl);
        }
    }

    function openModal() {
        if (!token) {
            console.error("[PickupPoint] Token Colissimo absent.");
            return;
        }

        // Déplacer le widget (déjà pré-initialisé ou en cours) dans la modale
        moveWidgetToModal();

        if (modalEl && window.bootstrap) {
            const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            modal.show();
        }

        // Si la pré-init n'a pas encore démarré (carrier sélectionné très rapidement)
        initWidget();
    }

    function applyMode(isPickup) {
        shippingSection?.classList.toggle("d-none", isPickup);
        pickupSection?.classList.toggle("d-none", !isPickup);

        const shippingSelect = document.getElementById("shipping_address");
        if (shippingSelect) shippingSelect.required = !isPickup;

        const phoneSection = document.getElementById("pickup-phone-section");
        const phoneInput   = document.getElementById("pickup-phone");
        if (phoneSection) phoneSection.classList.toggle("d-none", !isPickup);
        if (phoneInput)   phoneInput.required = isPickup;
    }

    function updateCarrierActiveState(activeLabel) {
        carrierOptions.forEach((item) => item.classList.remove("carrier-option--active"));
        activeLabel?.classList.add("carrier-option--active");
    }
}

// Attend la fin de la requête XHR GetPointsRetraitGET (données des points relais)
// C'est la dernière requête significative du widget (~2.5s)
function waitForWidgetReady(timeout = 10000) {
    return new Promise((resolve) => {
        const timer = setTimeout(resolve, timeout); // sécurité : retire le spinner au bout de 10s quoi qu'il arrive

        const OriginalXHR = window.XMLHttpRequest;
        const OriginalOpen = OriginalXHR.prototype.open;

        OriginalXHR.prototype.open = function (method, url, ...args) {
            if (typeof url === "string" && url.includes("GetPointsRetraitGET")) {
                this.addEventListener("loadend", () => {
                    // Restaurer la méthode open originale
                    OriginalXHR.prototype.open = OriginalOpen;
                    clearTimeout(timer);
                    // Petit délai pour laisser le widget rendre les marqueurs
                    setTimeout(resolve, 300);
                }, { once: true });
            }
            return OriginalOpen.call(this, method, url, ...args);
        };
    });
}

function waitForColissimoPlugin(timeout = 5000) {
    return new Promise((resolve) => {
        const start = Date.now();
        const check = () => {
            if (typeof jQuery !== "undefined" && jQuery.fn.frameColissimoOpen) {
                resolve(true);
            } else if (Date.now() - start > timeout) {
                resolve(false);
            } else {
                setTimeout(check, 100);
            }
        };
        check();
    });
}

async function clearPickupPoint(onPickupPointChange) {
    selectedPickupPoint = null;
    try {
        await fetchJson("/api/pickup-points/select", {
            method: "DELETE",
            headers: { Accept: "application/json" },
        });
    } catch (error) {
        console.warn("[PickupPoint] Suppression session point relais échouée :", error);
    }
    onPickupPointChange?.(null);
}

function escapeHtml(value) {
    return String(value)
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&#039;");
}

export function getSelectedPickupPoint() {
    return selectedPickupPoint;
}
