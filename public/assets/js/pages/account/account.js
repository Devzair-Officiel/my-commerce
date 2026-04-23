import { fetchJson } from "../../utils/fetch.js";
import { initAddressBook } from "./address.js";
import { showFlash } from "../../utils/flash.js";

function setHtml(id, html) {
  const el = document.getElementById(id);
  if (el) el.innerHTML = html;
}

// --- Infos compte (form editUserForm) ---
function initAccountInfoForm() {
  const form    = document.getElementById("editUserForm");
  const card    = document.getElementById("userinfo-card");
  const formWrap = document.getElementById("userinfo-form");
  const btnEdit   = document.getElementById("btn-edit-userinfo");
  const btnCancel = document.getElementById("btn-cancel-userinfo");
  if (!form) return;

  function showCard() {
    card?.classList.remove("d-none");
    formWrap?.classList.add("d-none");
  }

  function showForm() {
    card?.classList.add("d-none");
    formWrap?.classList.remove("d-none");
    setHtml("messageInfo", "");
  }

  function updateCard(data) {
    const civility  = form.querySelector("[name$='[civility]']")?.selectedOptions[0]?.text ?? "";
    const firstname = form.querySelector("[name$='[firstname]']")?.value ?? "";
    const lastname  = form.querySelector("[name$='[lastname]']")?.value ?? "";
    const email     = form.querySelector("[name$='[email]']")?.value ?? "";
    const phone     = form.querySelector("[name$='[phone]']")?.value;

    const fullname = [civility !== "Choisir votre civilité" ? civility : "", firstname, lastname]
      .filter(Boolean).join(" ");

    const elName  = document.getElementById("ui-fullname");
    const elEmail = document.getElementById("ui-email");
    const elPhone = document.getElementById("ui-phone");

    if (elName)  elName.textContent  = fullname || "—";
    if (elEmail) elEmail.textContent = email || "—";
    if (elPhone) elPhone.textContent = phone || "—";
  }

  function showCardFlash(message, type = "success") {
    const el = document.getElementById("userinfo-flash");
    if (!el) return;
    el.innerHTML = `<div class="nide-account__alert nide-account__alert--${type}" style="margin-top:.75rem">${message}</div>`;
    setTimeout(() => { el.innerHTML = ""; }, 5000);
  }

  btnEdit?.addEventListener("click", showForm);
  btnCancel?.addEventListener("click", showCard);

  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    setHtml("messageInfo", "");

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
        setHtml("messageInfo", `<div class="nide-account__alert nide-account__alert--error">Erreur lors de la mise à jour.</div>`);
        return;
      }

      if (data.status === "success") {
        updateCard(data);
        showCard();
        showCardFlash(data.message ?? "Informations mises à jour.", "success");
      } else if (data.status === "email_pending") {
        updateCard(data);
        showCard();
        showCardFlash(data.message, "success");
      } else {
        setHtml("messageInfo", `<div class="nide-account__alert nide-account__alert--error">${data.message ?? "Erreur."}</div>`);
      }
    } catch (e) {
      setHtml("messageInfo", `<div class="nide-account__alert nide-account__alert--error">${e.message}</div>`);
    }
  });
}

// --- Mot de passe --- //
function initPasswordFeatures() {
    const card     = document.getElementById("password-card");
    const formWrap = document.getElementById("password-form");
    const btnEdit   = document.getElementById("btn-edit-password");
    const btnCancel = document.getElementById("btn-cancel-password");

    function showPasswordCard() {
        card?.classList.remove("d-none");
        formWrap?.classList.add("d-none");
        document.getElementById("passwordForm")?.reset();
        setHtml("messageBox", "");
    }

    function showPasswordForm() {
        card?.classList.add("d-none");
        formWrap?.classList.remove("d-none");
        setHtml("messageBox", "");
    }

    function showPasswordFlash(message, type = "success") {
        const el = document.getElementById("password-flash");
        if (!el) return;
        el.innerHTML = `<div class="nide-account__alert nide-account__alert--${type}" style="margin-top:.75rem">${message}</div>`;
        setTimeout(() => { el.innerHTML = ""; }, 5000);
    }

    btnEdit?.addEventListener("click", showPasswordForm);
    btnCancel?.addEventListener("click", showPasswordCard);

    window.togglePasswordVisibility = function (groupId, toggleButton) {
        const group = document.getElementById(groupId);
        if (!group) return;
        const input = group.querySelector("input");
        if (!input) return;
        const useEl = toggleButton.querySelector("use");
        if (input.type === "password") {
            input.type = "text";
            if (useEl) useEl.setAttribute("href", "#icon-eye-off");
        } else {
            input.type = "password";
            if (useEl) useEl.setAttribute("href", "#icon-eye");
        }
    };

    window.submitPasswordChange = async function () {
        const currentPasswordInput  = document.getElementById("currentPassword");
        const newPasswordInput      = document.getElementById("newPassword");
        const confirmPasswordInput  = document.getElementById("confirmPassword");
        const submitButton          = document.querySelector('#passwordForm button[type="button"]:not(#btn-cancel-password)');

        const currentPassword       = currentPasswordInput?.value ?? "";
        const newPassword           = newPasswordInput?.value ?? "";
        const confirmPassword       = confirmPasswordInput?.value ?? "";
        const requiresCurrentPassword = currentPasswordInput !== null;

        setHtml("messageBox", "");

        if (requiresCurrentPassword && !currentPassword) {
            setHtml("messageBox", `<div class="nide-account__alert nide-account__alert--error">Veuillez saisir votre mot de passe actuel.</div>`);
            return;
        }
        if (!newPassword || !confirmPassword) {
            setHtml("messageBox", `<div class="nide-account__alert nide-account__alert--error">Veuillez remplir tous les champs requis.</div>`);
            return;
        }
        if (newPassword !== confirmPassword) {
            setHtml("messageBox", `<div class="nide-account__alert nide-account__alert--error">Les mots de passe ne correspondent pas.</div>`);
            return;
        }

        try {
            if (submitButton) submitButton.disabled = true;

            const payload = { newPassword, confirmPassword };
            if (requiresCurrentPassword) payload.currentPassword = currentPassword;

            const result = await fetchJson("/api/change-password", {
                method: "POST",
                csrfMetaName: "csrf-token-change-password",
                headers: {
                    "Content-Type": "application/json",
                    "X-Requested-With": "XMLHttpRequest",
                },
                body: JSON.stringify(payload),
            });

            showPasswordCard();
            showPasswordFlash(result.message);
        } catch (e) {
            setHtml("messageBox", `<div class="nide-account__alert nide-account__alert--error">${e.message ?? "Une erreur est survenue."}</div>`);
        } finally {
            if (submitButton) submitButton.disabled = false;
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
