import { fetchJson } from "../../utils/fetch.js";
import { showFlash } from "../../utils/flash.js";

function getCsrfTokenAddress(root) {
    // 1) priorite: data attribute (fiable)
    const fromData = root?.dataset?.addressCsrf;
    if (typeof fromData === "string" && fromData.trim() !== "") return fromData.trim();

    // 2) fallback: meta (si ton layout le rend)
    const fromMeta = document.querySelector('meta[name="csrf-token-address"]')?.getAttribute("content") || "";
    return String(fromMeta).trim();
}

async function initCountries(select, countriesUrl) {
    select.innerHTML = "";
    const countries = await fetchJson(countriesUrl, { headers: {} });

    countries.forEach((c) => {
        const opt = document.createElement("option");
        opt.value = c.alpha2Code;
        opt.textContent = c.name;
        select.appendChild(opt);
    });

    if ([...select.options].some((o) => o.value === "FR")) select.value = "FR";
}

function addOption(select, a) {
    const opt = document.createElement("option");
    opt.value = String(a.id);

    const label = `${a.client_name ?? ""} (${a.street ?? ""} ${a.code_postal ?? ""} ${a.city ?? ""} ${a.state ?? ""})`
        .replace(/\s+/g, " ")
        .trim();

    opt.textContent = label;
    select.appendChild(opt);
    return opt;
}

function findLastAddress(addresses) {
    // Ton API renvoie un tableau d'adresses: on prend la plus grande id (adresse la plus recente en pratique)
    if (!Array.isArray(addresses) || addresses.length === 0) return null;

    let last = null;
    for (const a of addresses) {
        const id = Number(a?.id);
        if (!Number.isFinite(id)) continue;
        if (!last || id > Number(last.id)) last = a;
    }
    return last;
}

export function initCheckoutAddressInline() {
    const root = document.querySelector(".main_content");
    if (!root) return;

    const apiBase = root.dataset.addressApi;
    const countriesUrl = root.dataset.countriesUrl;
    if (!apiBase || !countriesUrl) return;

    const btn = document.getElementById("checkout-add-address-btn");
    const card = document.getElementById("checkout-add-address-form");
    const form = document.getElementById("checkoutAddressForm");
    const cancel = document.getElementById("checkout-cancel-address");
    const countrySelect = document.getElementById("checkout-state");

    const shippingSelect = document.getElementById("shipping_address");
    const billingSelect = document.getElementById("billing_address");

    if (!btn || !card || !form || !cancel || !countrySelect || !shippingSelect || !billingSelect) return;

    initCountries(countrySelect, countriesUrl).catch(() => {
        showFlash("Impossible de charger la liste des pays.", "danger");
    });

    function open() {
        card.classList.remove("d-none");
        btn.classList.add("d-none");
    }

    function close() {
        card.classList.add("d-none");
        btn.classList.remove("d-none");
        form.reset();
        if ([...countrySelect.options].some((o) => o.value === "FR")) countrySelect.value = "FR";
    }

    btn.addEventListener("click", (e) => {
        e.preventDefault();
        open();
    });

    cancel.addEventListener("click", () => close());

    form.addEventListener("submit", async (e) => {
        e.preventDefault();

        const formData = new FormData(form);
        const payload = Object.fromEntries(formData.entries());

        for (const k of ["name", "client_name", "street", "code_postal", "city", "state", "more_details", "address_type"]) {
            if (typeof payload[k] === "string") payload[k] = payload[k].trim();
        }

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

            // Ton API renvoie un tableau: on prend la plus recente (id max)
            const address = Array.isArray(result.data) ? findLastAddress(result.data) : result.data;

            if (!address?.id) {
                showFlash("Adresse enregistrée, mais réponse API inattendue.", "warning");
                close();
                return;
            }

            const exists = (sel) => [...sel.options].some((o) => o.value === String(address.id));
            if (!exists(shippingSelect)) addOption(shippingSelect, address);
            if (!exists(billingSelect)) addOption(billingSelect, address);

            if (address.address_type === "livraison") shippingSelect.value = String(address.id);
            if (address.address_type === "facturation") billingSelect.value = String(address.id);

            if (!billingSelect.value) billingSelect.value = String(address.id);
            if (!shippingSelect.value) shippingSelect.value = String(address.id);

            showFlash(result.message ?? "Adresse ajoutée.", "success");
            close();

            shippingSelect.dispatchEvent(new Event("change", { bubbles: true }));
            billingSelect.dispatchEvent(new Event("change", { bubbles: true }));
        } catch (err) {
            showFlash(err?.message ?? "Erreur réseau.", "danger");
        }
    });
}
