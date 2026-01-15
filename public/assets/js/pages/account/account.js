import { fetchJson } from "../../utils/fetch.js";
import { initAddressBook } from "./address.js";
import { showFlash } from "../../utils/flash.js";

function setHtml(id, html) {
  const el = document.getElementById(id);
  if (el) el.innerHTML = html;
}

// --- Infos compte (form editUserForm) ---
function initAccountInfoForm() {
  const form = document.getElementById("editUserForm");
  if (!form) return;

  form.addEventListener("submit", async (event) => {
    event.preventDefault();

    try {
      const formData = new FormData(form);

      const response = await fetch("/account", {
        method: "POST",
        body: formData,
        credentials: "same-origin",
        headers: {
          "X-Requested-With": "XMLHttpRequest",
          "Accept": "application/json",
        },
      });

      const data = await response.json().catch(() => null);

      if (!response.ok || !data) {
        setHtml("messageInfo", `<div class="alert alert-danger">Erreur lors de la mise à jour.</div>`);
        return;
      }
      console.log('la data', data);
      console.log('la reponse', response);
      

      if (data.status === "success") {
        showFlash(data.message ?? "Opération réussie.", "success");
      } else {
        setHtml("messageInfo", `<div class="alert alert-danger">${data.message ?? "Erreur."}</div>`);
      }
    } catch (e) {
      setHtml("messageInfo", `<div class="alert alert-danger">${e.message}</div>`);
    }
  });
}

// --- Mot de passe ---
function initPasswordFeatures() {
  // rendre la fonction dispo pour onclick=""
  window.togglePasswordVisibility = function (groupId, toggleButton) {
    const group = document.getElementById(groupId);
    if (!group) return;

    // plus robuste que childNodes[1]
    const input = group.querySelector("input");
    if (!input) return;

    const icon = toggleButton.querySelector("i");
    if (input.type === "password") {
      input.type = "text";
      if (icon) {
        icon.classList.remove("fa-eye");
        icon.classList.add("fa-eye-slash");
      }
    } else {
      input.type = "password";
      if (icon) {
        icon.classList.remove("fa-eye-slash");
        icon.classList.add("fa-eye");
      }
    }
  };

  window.submitPasswordChange = async function () {
    const currentPassword = document.getElementById("currentPassword")?.value.trim() ?? "";
    const newPassword = document.getElementById("newPassword")?.value.trim() ?? "";
    const newPasswordConfirm = document.getElementById("newPasswordConfirm")?.value.trim() ?? "";

    if (!currentPassword || !newPassword || !newPasswordConfirm) {
      setHtml("messageBox", `<div class="alert alert-danger">Veuillez remplir tous les champs requis.</div>`);
      return;
    }

    if (newPassword !== newPasswordConfirm) {
      setHtml("messageBox", `<div class="alert alert-danger">Les mots de passe ne correspondent pas.</div>`);
      return;
    }

    try {
      const result = await fetchJson("/api/change-password", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-Requested-With": "XMLHttpRequest",
        },
        body: JSON.stringify({ currentPassword, newPassword }),
      });

      setHtml("messageBox", `<div class="alert alert-success">${result.message}</div>`);
      const form = document.getElementById("passwordForm");
      if (form) form.reset();
    } catch (e) {
      setHtml("messageBox", `<div class="alert alert-danger">${e.message}</div>`);
    }
  };
}

function initOrdersPaginationAjax() {
  const container = document.getElementById("orders-table-container");
  if (!container) return;

  container.addEventListener("click", async (e) => {
    const target = e.target.closest("a.page-link[data-page]");
    if (!target) return;
    e.preventDefault();

    const page = target.getAttribute("data-page");
    if (!page) return;

    // Optionnel : loader
    container.classList.add("opacity-50");
    container.style.pointerEvents = "none";

    try {
      const response = await fetch(`/account/orders/ajax?page=${page}`, {
        headers: { "X-Requested-With": "XMLHttpRequest" },
        credentials: "same-origin",
      });
      if (!response.ok) throw new Error("Erreur lors du chargement des commandes.");
      const html = await response.text();
      container.innerHTML = html;
    } catch (err) {
      container.innerHTML = `<div class="alert alert-danger">Erreur lors du chargement des commandes.</div>`;
    } finally {
      container.classList.remove("opacity-50");
      container.style.pointerEvents = "";
    }
  });
}

// --- Boot ---
document.addEventListener("DOMContentLoaded", () => {
  initAddressBook();
  initAccountInfoForm();
  initPasswordFeatures();
  initOrdersPaginationAjax();
});
