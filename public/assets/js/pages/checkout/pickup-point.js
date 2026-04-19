import { fetchJson } from "../../utils/fetch.js";
import { initPickupMap } from "./pickup-point-map.js";

let selectedPickupPoint = null;
let mapInitialized = false;

export function initPickupPointSelector({ onPickupPointChange, onShippingModeChange } = {}) {
    const shippingSection = document.getElementById("shipping-address-section");
    const pickupSection   = document.getElementById("pickup-point-section");
    const selectedEl      = document.getElementById("pickup-selected");
    const selectedLabel   = document.getElementById("pickup-selected-label");
    const openBtnWrapper  = document.getElementById("pickup-open-btn-wrapper");
    const openBtn         = document.getElementById("pickup-open-btn");
    const carrierOptions  = document.querySelectorAll(".carrier-option");
    const modalEl         = document.getElementById("colissimo-modal");
    const modalBodyEl     = document.getElementById("colissimo-modal-body");

    if (!carrierOptions.length) return;

    const mainContent = document.querySelector(".main_content");
    const zipCode     = mainContent?.dataset?.colissimoZipcode ?? "";
    const city        = mainContent?.dataset?.colissimoCity    ?? "";

    // Pré-charger la carte au premier signe d'interaction utilisateur
    const hasPickupCarrier = [...carrierOptions].some(l => l.dataset.hasPickup === "true");
    if (hasPickupCarrier && zipCode && modalBodyEl) {
        const preload = () => {
            if (mapInitialized) return;
            mapInitialized = true;
            initPickupMap(modalBodyEl, { zipCode, city, onSelect: handlePointSelected, modalEl });
        };
        const onFirstInteraction = () => {
            ["mousemove", "touchstart", "keydown", "scroll"].forEach(e =>
                window.removeEventListener(e, onFirstInteraction)
            );
            preload();
        };
        ["mousemove", "touchstart", "keydown", "scroll"].forEach(e =>
            window.addEventListener(e, onFirstInteraction, { passive: true })
        );
    }

    // État initial
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
    carrierOptions.forEach(label => {
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

    openBtn?.addEventListener("click", openModal);

    selectedEl?.addEventListener("click", async e => {
        if (!e.target.closest(".pickup-selected")) return;
        await clearPickupPoint(onPickupPointChange);
        selectedEl.classList.add("d-none");
        openBtnWrapper?.classList.remove("d-none");
        openModal();
    });

    async function handlePointSelected(point) {
        const normalize = v => String(v ?? "").replace(/[\r\n\t]+/g, " ").replace(/\s{2,}/g, " ").trim();
        const normalizedPoint = {
            id:         normalize(point.id),
            name:       normalize(point.name),
            address:    normalize(point.address),
            city:       normalize(point.city),
            postalCode: normalize(point.postalCode),
        };

        try {
            await fetchJson("/api/pickup-points/select", {
                method: "POST",
                headers: { "Content-Type": "application/json", Accept: "application/json" },
                body: JSON.stringify(normalizedPoint),
            });

            selectedPickupPoint = normalizedPoint;

            if (modalEl && window.bootstrap) {
                bootstrap.Modal.getInstance(modalEl)?.hide();
            }

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
    }

    function openModal() {
        if (!zipCode) { console.error("[PickupPoint] zipCode absent."); return; }

        if (!mapInitialized && modalBodyEl) {
            mapInitialized = true;
            initPickupMap(modalBodyEl, { zipCode, city, onSelect: handlePointSelected, modalEl });
        }

        if (modalEl && window.bootstrap) {
            bootstrap.Modal.getOrCreateInstance(modalEl, { focus: false }).show();
        }
    }

    function applyMode(isPickup) {
        shippingSection?.classList.toggle("d-none", isPickup);
        pickupSection?.classList.toggle("d-none", !isPickup);

        const shippingSelect = document.getElementById("shipping_address");
        if (shippingSelect) shippingSelect.required = !isPickup;

        const phoneSection = document.getElementById("pickup-phone-section");
        const phoneInput   = document.getElementById("pickup-phone");
        phoneSection?.classList.toggle("d-none", !isPickup);
        if (phoneInput) phoneInput.required = isPickup;
    }

    function updateCarrierActiveState(activeLabel) {
        carrierOptions.forEach(item => item.classList.remove("carrier-option--active"));
        activeLabel?.classList.add("carrier-option--active");
    }
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
        .replaceAll("&", "&amp;").replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;").replaceAll('"', "&quot;")
        .replaceAll("'", "&#039;");
}

export function getSelectedPickupPoint() {
    return selectedPickupPoint;
}
