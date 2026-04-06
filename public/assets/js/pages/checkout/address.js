import { fetchJson } from "../../utils/fetch.js";
import { showFlash } from "../../utils/flash.js";

function getCsrfTokenAddress(root) {
    const fromData = root?.dataset?.addressCsrf;
    if (typeof fromData === "string" && fromData.trim() !== "") {
        return fromData.trim();
    }

    const fromMeta = document
        .querySelector('meta[name="csrf-token-address"]')
        ?.getAttribute("content") || "";

    return String(fromMeta).trim();
}

async function initCountries(select, countriesUrl) {
    select.innerHTML = "";

    const countries = await fetchJson(countriesUrl, { headers: {} });

    countries.forEach((country) => {
        const opt = document.createElement("option");
        opt.value = country.alpha2Code;
        opt.textContent = country.name;
        select.appendChild(opt);
    });

    if ([...select.options].some((o) => o.value === "FR")) {
        select.value = "FR";
    }
}

function buildAddressLabel(address) {
    return `${address.client_name ?? ""} (${address.street ?? ""} ${address.code_postal ?? ""} ${address.city ?? ""} ${address.state ?? ""})`
        .replace(/\s+/g, " ")
        .trim();
}

function addOption(select, address) {
    const opt = document.createElement("option");
    opt.value = String(address.id);
    opt.textContent = buildAddressLabel(address);
    select.appendChild(opt);

    return opt;
}

function findLastAddress(addresses) {
    if (!Array.isArray(addresses) || addresses.length === 0) {
        return null;
    }

    let last = null;

    for (const address of addresses) {
        const id = Number(address?.id);

        if (!Number.isFinite(id)) {
            continue;
        }

        if (!last || id > Number(last.id)) {
            last = address;
        }
    }

    return last;
}

function ensureOptionExists(select, address) {
    const existing = [...select.options].find(
        (option) => option.value === String(address.id)
    );

    if (existing) {
        existing.textContent = buildAddressLabel(address);

        return existing;
    }

    return addOption(select, address);
}

export function initCheckoutAddressInline() {
    const root = document.querySelector(".main_content");
    if (!root) return;

    const apiBase = root.dataset.addressApi;
    const countriesUrl = root.dataset.countriesUrl;

    if (!apiBase || !countriesUrl) return;

    const shippingSelect = document.getElementById("shipping_address");
    const billingSelect  = document.getElementById("billing_address");

    if (!shippingSelect || !billingSelect) return;

    // ── Formulaire livraison ────────────────────────────────────────────────
    const shippingBtn      = document.getElementById("checkout-add-address-btn");
    const shippingCard     = document.getElementById("checkout-add-address-form");
    const shippingForm     = document.getElementById("checkoutAddressForm");
    const shippingCancel   = document.getElementById("checkout-cancel-address");
    const shippingCountry  = document.getElementById("checkout-state");

    if (shippingBtn && shippingCard && shippingForm && shippingCancel && shippingCountry) {
        initCountries(shippingCountry, countriesUrl).catch(() => {
            showFlash("Impossible de charger la liste des pays.", "danger");
        });

        const openShipping = () => {
            shippingCard.classList.remove("d-none");
            shippingBtn.classList.add("d-none");
        };

        const closeShipping = () => {
            shippingCard.classList.add("d-none");
            shippingBtn.classList.remove("d-none");
            shippingForm.reset();
            if ([...shippingCountry.options].some((o) => o.value === "FR")) {
                shippingCountry.value = "FR";
            }
        };

        shippingBtn.addEventListener("click", (e) => { e.preventDefault(); openShipping(); });
        shippingCancel.addEventListener("click", closeShipping);

        shippingForm.addEventListener("submit", async (e) => {
            e.preventDefault();
            await submitAddressForm({
                root, apiBase,
                form: shippingForm,
                close: closeShipping,
                shippingSelect,
                billingSelect,
            });
        });
    }

    // ── Formulaire facturation ──────────────────────────────────────────────
    const billingBtn     = document.getElementById("checkout-add-billing-btn");
    const billingCard    = document.getElementById("checkout-add-billing-form");
    const billingForm    = document.getElementById("checkoutBillingAddressForm");
    const billingCancel  = document.getElementById("checkout-cancel-billing");
    const billingCountry = document.getElementById("checkout-billing-state");

    if (billingBtn && billingCard && billingForm && billingCancel && billingCountry) {
        initCountries(billingCountry, countriesUrl).catch(() => {
            showFlash("Impossible de charger la liste des pays.", "danger");
        });

        const openBilling = () => {
            billingCard.classList.remove("d-none");
            billingBtn.classList.add("d-none");
        };

        const closeBilling = () => {
            billingCard.classList.add("d-none");
            billingBtn.classList.remove("d-none");
            billingForm.reset();
            if ([...billingCountry.options].some((o) => o.value === "FR")) {
                billingCountry.value = "FR";
            }
        };

        billingBtn.addEventListener("click", (e) => { e.preventDefault(); openBilling(); });
        billingCancel.addEventListener("click", closeBilling);

        billingForm.addEventListener("submit", async (e) => {
            e.preventDefault();
            await submitAddressForm({
                root, apiBase,
                form: billingForm,
                addressType: "facturation",
                close: closeBilling,
                shippingSelect,
                billingSelect,
            });
        });
    }
}

async function submitAddressForm({ root, apiBase, form, addressType = null, close, shippingSelect, billingSelect }) {
    const formData = new FormData(form);
    const payload  = Object.fromEntries(formData.entries());

    for (const key of ["name", "client_name", "street", "code_postal", "city", "state", "more_details", "address_type"]) {
        if (typeof payload[key] === "string") payload[key] = payload[key].trim();
    }

    // Le formulaire billing n'a pas de champ address_type : on le force ici
    if (addressType) payload.address_type = addressType;

    const csrfToken = getCsrfTokenAddress(root);

    if (!csrfToken) {
        showFlash("CSRF token introuvable sur la page checkout.", "danger");
        return;
    }

    try {
        const result = await fetchJson(apiBase, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-Requested-With": "XMLHttpRequest",
                "X-CSRF-TOKEN": csrfToken,
            },
            body: JSON.stringify(payload),
        });

        if (!result?.isSuccess) {
            showFlash(result?.message ?? "Erreur lors de l'enregistrement.", "danger");
            return;
        }

        const address = Array.isArray(result.data) ? findLastAddress(result.data) : result.data;

        if (!address?.id) {
            showFlash("Adresse enregistrée, mais réponse API inattendue.", "warning");
            close();
            return;
        }

        ensureOptionExists(shippingSelect, address);
        ensureOptionExists(billingSelect, address);

        const type = payload.address_type;

        if (type === "facturation") {
            billingSelect.value = String(address.id);
        } else if (type === "livraison") {
            shippingSelect.value = String(address.id);
            if (!billingSelect.value) billingSelect.value = String(address.id);
        } else {
            if (!shippingSelect.value) shippingSelect.value = String(address.id);
            if (!billingSelect.value)  billingSelect.value  = String(address.id);
        }

        showFlash(result?.message ?? "Adresse ajoutée.", "success");
        close();

        shippingSelect.dispatchEvent(new Event("change", { bubbles: true }));
        billingSelect.dispatchEvent(new Event("change", { bubbles: true }));
    } catch (error) {
        showFlash(error?.message ?? "Erreur réseau.", "danger");
    }
}

// Initialisé depuis main.js via window.addEventListener("load")
