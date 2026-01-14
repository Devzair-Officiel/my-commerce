import { fetchJson } from "../../utils/fetch.js";
import { showFlash } from "../../utils/flash.js";

function escapeHtml(str) {
  return String(str)
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#039;");
}

function renderRow(a) {
  // a est en snake_case (API stable)
  return `
    <tr>
      <td>${a.id ?? ""}</td>
      <td>${escapeHtml(`${a.street ?? ""} ${a.code_postal ?? ""} ${a.city ?? ""} ${a.state ?? ""}`.trim())}</td>
      <td>
        <a href="#"
           class="btn btn-fill-out btn-sm edit_address"
           data-id="${escapeHtml(a.id)}"
           data-address_type="${escapeHtml(a.address_type ?? "")}"
           data-client_name="${escapeHtml(a.client_name ?? "")}"
           data-name="${escapeHtml(a.name ?? "")}"
           data-street="${escapeHtml(a.street ?? "")}"
           data-code_postal="${escapeHtml(a.code_postal ?? "")}"
           data-city="${escapeHtml(a.city ?? "")}"
           data-state="${escapeHtml(a.state ?? "")}"
           data-more_details="${escapeHtml(a.more_details ?? "")}"
        >Modifier</a>
      </td>
      <td>
        <a href="#"
           class="btn btn-fill-out btn-sm remove_address"
           data-id="${escapeHtml(a.id)}"
        >Supprimer</a>
      </td>
    </tr>
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

  tbody.addEventListener("click", (e) => {
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
