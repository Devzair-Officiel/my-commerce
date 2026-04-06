import { fetchJson } from "../../utils/fetch.js";

let selectedPickupPoint = null;
let map = null;
let markersLayer = null;

export function initPickupPointSelector({ onPickupPointChange, onShippingModeChange } = {}) {
    const shippingSection = document.getElementById("shipping-address-section");
    const pickupSection = document.getElementById("pickup-point-section");
    const selectedEl = document.getElementById("pickup-selected");
    const selectedLabel = document.getElementById("pickup-selected-label");
    const changeBtn = document.getElementById("pickup-change-btn");
    const carrierOptions = document.querySelectorAll(".carrier-option");

    const postalCodeInput = document.getElementById("pickup-postal-code");
    const searchBtn = document.getElementById("pickup-search-btn");
    const loaderEl = document.getElementById("pickup-loader");
    const errorEl = document.getElementById("pickup-error");
    const resultsEl = document.getElementById("pickup-results");
    const resultsListEl = document.getElementById("pickup-results-list");

    if (!carrierOptions.length) {
        return;
    }

    const checkedRadio = document.querySelector(".carrier-radio:checked");
    if (checkedRadio) {
        const label = checkedRadio.closest(".carrier-option");
        const isPickup = label?.dataset?.hasPickup === "true";
        applyMode(isPickup);
        updateCarrierActiveState(label);
    }

    carrierOptions.forEach((label) => {
        label.addEventListener("click", async () => {
            carrierOptions.forEach((item) => item.classList.remove("carrier-option--active"));
            label.classList.add("carrier-option--active");

            const radio = label.querySelector(".carrier-radio");
            if (radio) {
                radio.checked = true;
            }

            const isPickup = label.dataset.hasPickup === "true";
            applyMode(isPickup);
            onShippingModeChange?.(isPickup);

            if (!isPickup) {
                await clearPickupPoint(onPickupPointChange);
                resetPickupUi({
                    selectedEl,
                    selectedLabel,
                    errorEl,
                    resultsEl,
                    resultsListEl,
                });
            }
        });
    });

    changeBtn?.addEventListener("click", async () => {
        selectedEl?.classList.add("d-none");
        if (selectedLabel) {
            selectedLabel.textContent = "";
        }

        await clearPickupPoint(onPickupPointChange);

        if (resultsEl) {
            resultsEl.classList.remove("d-none");
        }
    });

    searchBtn?.addEventListener("click", async () => {
        await searchPickupPoints({
            postalCodeInput,
            loaderEl,
            errorEl,
            resultsEl,
            resultsListEl,
            selectedEl,
            selectedLabel,
            onPickupPointChange,
        });
    });

    postalCodeInput?.addEventListener("keydown", async (event) => {
        if (event.key === "Enter") {
            event.preventDefault();
            await searchPickupPoints({
                postalCodeInput,
                loaderEl,
                errorEl,
                resultsEl,
                resultsListEl,
                selectedEl,
                selectedLabel,
                onPickupPointChange,
            });
        }
    });

    function applyMode(isPickup) {
        if (shippingSection) {
            shippingSection.classList.toggle("d-none", isPickup);
        }

        if (pickupSection) {
            pickupSection.classList.toggle("d-none", !isPickup);
        }

        const shippingSelect = document.getElementById("shipping_address");
        if (shippingSelect) {
            shippingSelect.required = !isPickup;
        }
    }

    function updateCarrierActiveState(activeLabel) {
        carrierOptions.forEach((item) => item.classList.remove("carrier-option--active"));
        activeLabel?.classList.add("carrier-option--active");
    }
}

async function searchPickupPoints({
    postalCodeInput,
    loaderEl,
    errorEl,
    resultsEl,
    resultsListEl,
    selectedEl,
    selectedLabel,
    onPickupPointChange,
}) {
    const postalCode = String(postalCodeInput?.value ?? "").trim();

    hideError(errorEl);
    clearResults(resultsListEl);

    if (resultsEl) {
        resultsEl.classList.add("d-none");
    }

    if (!/^\d{5}$/.test(postalCode)) {
        showError(errorEl, "Veuillez saisir un code postal valide à 5 chiffres.");
        postalCodeInput?.focus();
        return;
    }

    toggleLoader(loaderEl, true);

    try {
        const points = await fetchJson(`/api/pickup-points?postal_code=${encodeURIComponent(postalCode)}`, {
            method: "GET",
            headers: {
                Accept: "application/json",
            },
        });

        if (!Array.isArray(points) || points.length === 0) {
            showError(errorEl, "Aucun point relais trouvé pour ce code postal.");
            return;
        }

        if (resultsEl) {
            resultsEl.classList.remove("d-none");
        }

        renderPickupPoints({
            points,
            resultsListEl,
            selectedEl,
            selectedLabel,
            onPickupPointChange,
        });
    } catch (error) {
        console.error("[PickupPoint] Erreur recherche :", error);
        showError(errorEl, "Le service de recherche des points relais est temporairement indisponible.");
    } finally {
        toggleLoader(loaderEl, false);
    }
}

function renderPickupPoints({
    points,
    resultsListEl,
    selectedEl,
    selectedLabel,
    onPickupPointChange,
}) {
    clearResults(resultsListEl);
    initMap(points);

    points.forEach((point, index) => {
        const button = document.createElement("button");
        button.type = "button";
        button.className = "btn btn-outline-secondary text-start p-3";
        button.dataset.pointIndex = String(index);
        button.innerHTML = `
            <div class="fw-semibold">${escapeHtml(point.name ?? "")}</div>
            <div>${escapeHtml(point.address ?? "")}</div>
            <div>${escapeHtml(point.postalCode ?? "")} ${escapeHtml(point.city ?? "")}</div>
            ${point.openingHours
                ? `<div class="small text-muted mt-1">${escapeHtml(point.openingHours)}</div>`
                : ""
            }
        `;

        button.addEventListener("click", async () => {
            await selectPoint({ point, selectedEl, selectedLabel, resultsListEl, onPickupPointChange });
        });

        resultsListEl?.appendChild(button);
    });
}

function initMap(points) {
    const mapEl = document.getElementById("pickup-map");
    if (!mapEl || typeof L === "undefined") {
        return;
    }

    // First valid point with coordinates
    const anchors = points.filter((p) => p.latitude && p.longitude);
    if (anchors.length === 0) {
        return;
    }

    if (map) {
        map.remove();
        map = null;
        markersLayer = null;
    }

    const first = anchors[0];
    map = L.map(mapEl).setView([parseFloat(first.latitude), parseFloat(first.longitude)], 14);

    L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        maxZoom: 19,
    }).addTo(map);

    markersLayer = L.layerGroup().addTo(map);

    const bounds = [];

    anchors.forEach((point) => {
        const lat = parseFloat(point.latitude);
        const lng = parseFloat(point.longitude);

        const marker = L.marker([lat, lng]).addTo(markersLayer);
        marker.bindPopup(`
            <strong>${escapeHtml(point.name ?? "")}</strong><br>
            ${escapeHtml(point.address ?? "")}<br>
            ${escapeHtml(point.postalCode ?? "")} ${escapeHtml(point.city ?? "")}
            ${point.openingHours ? `<br><small>${escapeHtml(point.openingHours)}</small>` : ""}
        `);

        marker.on("click", () => {
            // Highlight corresponding list item
            document.querySelectorAll("#pickup-results-list button").forEach((btn) => {
                btn.classList.remove("btn-primary");
                btn.classList.add("btn-outline-secondary");
            });
        });

        bounds.push([lat, lng]);
    });

    if (bounds.length > 1) {
        map.fitBounds(bounds, { padding: [20, 20] });
    }

    // Refresh map size after it becomes visible (Bootstrap d-none → visible transition)
    setTimeout(() => map?.invalidateSize(), 100);
}

async function selectPoint({ point, selectedEl, selectedLabel, resultsListEl, onPickupPointChange }) {
    const normalizedPoint = {
        id: String(point.id ?? ""),
        name: String(point.name ?? ""),
        address: String(point.address ?? ""),
        city: String(point.city ?? ""),
        postalCode: String(point.postalCode ?? ""),
    };

    try {
        await fetchJson("/api/pickup-points/select", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                Accept: "application/json",
            },
            body: JSON.stringify(normalizedPoint),
        });

        selectedPickupPoint = normalizedPoint;

        if (selectedLabel) {
            selectedLabel.textContent =
                `${normalizedPoint.name} — ${normalizedPoint.address}, ${normalizedPoint.postalCode} ${normalizedPoint.city}`;
        }

        selectedEl?.classList.remove("d-none");
        resultsListEl.innerHTML = "";
        document.getElementById("pickup-results")?.classList.add("d-none");

        if (map) {
            map.remove();
            map = null;
            markersLayer = null;
        }

        onPickupPointChange?.(normalizedPoint);
    } catch (error) {
        console.error("[PickupPoint] Erreur sélection :", error);
        showError(
            document.getElementById("pickup-error"),
            "Impossible d'enregistrer le point relais sélectionné."
        );
    }
}

async function clearPickupPoint(onPickupPointChange) {
    selectedPickupPoint = null;

    try {
        await fetchJson("/api/pickup-points/select", {
            method: "DELETE",
            headers: {
                Accept: "application/json",
            },
        });
    } catch (error) {
        console.warn("[PickupPoint] Suppression session point relais échouée :", error);
    }

    onPickupPointChange?.(null);
}

function resetPickupUi({ selectedEl, selectedLabel, errorEl, resultsEl, resultsListEl }) {
    selectedEl?.classList.add("d-none");

    if (selectedLabel) {
        selectedLabel.textContent = "";
    }

    hideError(errorEl);
    clearResults(resultsListEl);
    resultsEl?.classList.add("d-none");

    if (map) {
        map.remove();
        map = null;
        markersLayer = null;
    }
}

function toggleLoader(loaderEl, show) {
    loaderEl?.classList.toggle("d-none", !show);
}

function showError(errorEl, message) {
    if (!errorEl) {
        return;
    }

    errorEl.textContent = message;
    errorEl.classList.remove("d-none");
}

function hideError(errorEl) {
    if (!errorEl) {
        return;
    }

    errorEl.textContent = "";
    errorEl.classList.add("d-none");
}

function clearResults(resultsListEl) {
    if (resultsListEl) {
        resultsListEl.innerHTML = "";
    }
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
