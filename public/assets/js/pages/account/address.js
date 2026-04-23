import { fetchJson } from "../../utils/fetch.js";
import { showFlash } from "../../utils/flash.js";
import { escapeHtml } from "../../utils/html.js";

function renderRow(a) {
  const type    = escapeHtml(a.address_type ?? "");
  const isLivraison = type === "livraison";
  const typeLabel = isLivraison ? "Livraison" : "Facturation";
  const typeIcon  = isLivraison
    ? `<svg viewBox="0 0 20 20" fill="none"><path d="M3 10h10M13 7l3 3-3 3" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/><rect x="1" y="6" width="12" height="8" rx="1" stroke="currentColor" stroke-width="1.4"/></svg>`
    : `<svg viewBox="0 0 20 20" fill="none"><rect x="2" y="5" width="16" height="12" rx="1.5" stroke="currentColor" stroke-width="1.4"/><path d="M2 8h16" stroke="currentColor" stroke-width="1.4"/></svg>`;

  return `
    <div class="nide-addr-card" data-id="${escapeHtml(String(a.id))}">
      <div class="nide-addr-card__head">
        <span class="nide-addr-card__type nide-addr-card__type--${type}">
          ${typeIcon}${typeLabel}
        </span>
        <span class="nide-addr-card__name">${escapeHtml(a.name ?? "")}</span>
      </div>
      <div class="nide-addr-card__body">
        <p class="nide-addr-card__recipient">${escapeHtml(a.client_name ?? "")}</p>
        <p class="nide-addr-card__line">${escapeHtml(a.street ?? "")}</p>
        <p class="nide-addr-card__line">${escapeHtml(a.code_postal ?? "")} ${escapeHtml(a.city ?? "")}</p>
        ${a.state ? `<p class="nide-addr-card__line">${escapeHtml(a.state)}</p>` : ""}
      </div>
      <div class="nide-addr-card__actions">
        <a href="#" class="nide-addr-card__btn edit_address"
          data-id="${escapeHtml(String(a.id))}"
          data-address_type="${type}"
          data-client_name="${escapeHtml(a.client_name ?? "")}"
          data-name="${escapeHtml(a.name ?? "")}"
          data-street="${escapeHtml(a.street ?? "")}"
          data-code_postal="${escapeHtml(a.code_postal ?? "")}"
          data-city="${escapeHtml(a.city ?? "")}"
          data-state="${escapeHtml(a.state ?? "")}"
          data-more_details="${escapeHtml(a.more_details ?? "")}">
          <svg viewBox="0 0 20 20" fill="none"><path d="M14.5 3.5l2 2L7 15H5v-2L14.5 3.5z" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
          Modifier
        </a>
        <a href="#" class="nide-addr-card__btn nide-addr-card__btn--danger remove_address"
          data-id="${escapeHtml(String(a.id))}">
          <svg viewBox="0 0 20 20" fill="none"><path d="M5 7h10M8 7V5h4v2M9 10v4M11 10v4M6 7l1 9h6l1-9" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
          Supprimer
        </a>
      </div>
    </div>
  `;
}

export function initAddressBook() {
  const addFormWrapper = document.getElementById("add_form");
  const form = addFormWrapper?.querySelector("form");
  const addButton = document.getElementById("add_new_address");
  const addressDetails = document.getElementById("address_details");
  const tbody = document.getElementById("address-tbody");

  const page = document.getElementById("account-page");
  const apiBase = page?.dataset.addressApi || "/api/address";
  const countriesUrl = page?.dataset.countriesUrl || "/assets/data/countries.json";

  if (!addFormWrapper || !form || !addButton || !addressDetails || !tbody) return;

  let isUpdating = false;
  let currentId = null;

  async function initCountries() {
    const select = form.querySelector("select#state");
    if (!select) return;

    select.innerHTML = "";
    const countries = await fetchJson(countriesUrl, {
      // pas besoin de CSRF ici, c'est un GET public
      headers: {},
    });

    countries.forEach((c) => {
      const opt = document.createElement("option");
      opt.value = c.alpha2Code;
      opt.textContent = c.name;
      select.appendChild(opt);
    });
  }

  function openFormForCreate() {
    isUpdating = false;
    currentId = null;
    form.reset();

    const submitBtn = form.querySelector("button");
    if (submitBtn) submitBtn.textContent = "Ajouter une adresse";

    addFormWrapper.classList.remove("d-none");
    addressDetails.classList.add("d-none");
    addButton.textContent = "Annuler";
  }

  function openFormForEdit(dataset) {
    isUpdating = true;
    currentId = dataset.id;

    form.elements["name"].value = dataset.name || "";
    form.elements["client_name"].value = dataset.client_name || "";
    form.elements["address_type"].value = dataset.address_type || "";
    form.elements["street"].value = dataset.street || "";
    form.elements["city"].value = dataset.city || "";
    form.elements["code_postal"].value = dataset.code_postal || "";
    form.elements["state"].value = dataset.state || "";

    if (form.elements["more_details"]) {
      form.elements["more_details"].value = dataset.more_details || "";
    }

    const submitBtn = form.querySelector("button");
    if (submitBtn) submitBtn.textContent = "Modifier";

    addFormWrapper.classList.remove("d-none");
    addressDetails.classList.add("d-none");
    addButton.textContent = "Annuler";
  }


  function closeForm() {
    addFormWrapper.classList.add("d-none");
    addressDetails.classList.remove("d-none");
    addButton.textContent = "Ajouter une adresse";
    isUpdating = false;
    currentId = null;
  }

  function renderAddresses(addresses) {
    const list = Array.isArray(addresses) ? addresses : [addresses];
    tbody.innerHTML = list.map(renderRow).join("");
    closeForm();
  }

  function getCsrfTokenAddress() {
    return document.querySelector('meta[name="csrf-token-address"]')?.getAttribute("content") || "";
  }

  async function submitForm(event) {
    event.preventDefault();

    const formData = new FormData(form);
    const payload = Object.fromEntries(formData.entries());
    for (const k of ["name", "client_name", "street", "code_postal", "city", "state", "more_details", "address_type"]) {
      if (typeof payload[k] === "string") payload[k] = payload[k].trim();
    }

    const method = isUpdating ? "PUT" : "POST";
    const url = isUpdating ? `${apiBase}/${encodeURIComponent(currentId)}` : apiBase;

    try {
      const result = await fetchJson(url, {
        method,
        headers: {
          "Content-Type": "application/json",
          "X-Requested-With": "XMLHttpRequest",
          "X-CSRF-TOKEN": getCsrfTokenAddress(),
        },
        body: JSON.stringify(payload),
      });

      if (result?.isSuccess) {
        const wasUpdating = isUpdating; // snapshot AVANT renderAddresses()
        renderAddresses(result.data);

        showFlash(result.message ?? "Opération réussie.", "success");
      } else {
        showFlash(result?.message ?? "Erreur lors de l'enregistrement.", "danger");
      }
    } catch (e) {
      showFlash(e.message, "danger");
    }
  }

  async function removeAddress(id) {
    try {
      const result = await fetchJson(`${apiBase}/${encodeURIComponent(id)}`, {
        method: "DELETE",
        headers: {
          "X-Requested-With": "XMLHttpRequest",
          "X-CSRF-TOKEN": getCsrfTokenAddress(),
        },
      });

      if (result?.isSuccess) {
        renderAddresses(result.data);
        showFlash(result.message ?? "Opération réussie.", "success");
      } else {
        showFlash(result?.message ?? "Erreur lors de la suppression.", "danger");
      }
    } catch (e) {
      showFlash(e.message, "danger");
    }
  }

  // Le conteneur est maintenant la grille de cartes (div#address-tbody)
  const container = tbody;
  container.addEventListener("click", (e) => {
    const edit = e.target.closest("a.edit_address");
    if (edit) {
      e.preventDefault();
      openFormForEdit(edit.dataset);
      return;
    }

    const del = e.target.closest("a.remove_address");
    if (del) {
      e.preventDefault();
      const id = del.dataset.id;
      if (id) removeAddress(id);
    }
  });

  addButton.addEventListener("click", (e) => {
    e.preventDefault();
    if (addFormWrapper.classList.contains("d-none")) openFormForCreate();
    else closeForm();
  });

  form.addEventListener("submit", submitForm);

  initCountries();
}
