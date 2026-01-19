// assets/js/pages/checkout/stripe.js
import { fetchJson } from "../../utils/fetch.js";
import { showFlash } from "../../utils/flash.js";

document.addEventListener("DOMContentLoaded", () => {
    // ----------------
    // DATA FROM DOM
    // ----------------
    const mainContent = document.querySelector(".main_content");

    // IMPORTANT: dataset transforme data-stripe_public_key => stripePublicKey
    const stripePublicKey = mainContent?.dataset?.stripePublicKey ?? "";
    const orderId = mainContent?.dataset?.orderid ?? "";

    let cart = { items: [] };
    try {
        cart = JSON.parse(mainContent?.dataset?.cart ?? "{}");
    } catch (e) {
        console.error("Invalid cart JSON in data-cart", e);
    }
    const items = cart?.items ?? [];

    // ----------------
    // ADDRESS / COMMENT + SHOW PAY BUTTON
    // ----------------
    let billingAddress = "";
    let shippingAddress = "";
    let comment = "";

    const billingAddressSelect = document.querySelector('select[name="billing_address"]');
    const shippingAddressSelect = document.querySelector('select[name="shipping_address"]');
    const commentsTextarea = document.querySelector('textarea[name="comment"], textarea');

    const payBtnContainer = document.querySelector(".payment-button");
    const payBtnLink = document.getElementById("open-payment-modal");

    const updateButton = () => {
        if (!payBtnContainer) return;
        const displayPayBtn = Boolean(billingAddress) && Boolean(shippingAddress);
        payBtnContainer.classList.toggle("d-none", !displayPayBtn);
    };

    billingAddressSelect?.addEventListener("change", (event) => {
        billingAddress = event.target?.value ?? "";
        updateButton();
    });

    shippingAddressSelect?.addEventListener("change", (event) => {
        shippingAddress = event.target?.value ?? "";
        updateButton();
    });

    commentsTextarea?.addEventListener("change", (event) => {
        comment = event.target?.value ?? "";
    });

    // ----------------
    // STRIPE COMPONENT
    // ----------------
    if (!stripePublicKey || !orderId) return;
    if (typeof Stripe === "undefined") return;

    const paymentForm = document.querySelector("#payment-form");
    if (!paymentForm) return;

    const modalEl = document.getElementById("staticBackdrop");
    const stripe = Stripe(stripePublicKey);

    let elements = null;
    let emailAddress = "";
    let stripeInitialized = false;

    // Empêche l'ouverture de la modal si on ne peut pas sauvegarder l'ordre
    if (payBtnLink) {
        payBtnLink.addEventListener("click", async (e) => {
            e.preventDefault();

            try {
                if (!orderId) throw new Error("orderId manquant.");
                if (!billingAddressSelect?.value || !shippingAddressSelect?.value) {
                    throw new Error("Adresse de facturation et de livraison requises.");
                }

                // 1) Sauvegarde order (adresses + commentaire) AVANT paiement
                await fetchJson(`/api/order/${orderId}`, {
                    method: "PATCH",
                    headers: {
                        "Content-Type": "application/json",
                        "X-Requested-With": "XMLHttpRequest",
                    },
                    body: JSON.stringify({
                        billing_address: billingAddressSelect.value,
                        shipping_address: shippingAddressSelect.value,
                        comment,
                    }),
                });

                // 2) Prépare Stripe Elements AVANT d’ouvrir la modale
                await ensureStripeReady();

                // 3) Ouvre la modale quand tout est prêt
                // Bootstrap 5 global "bootstrap" (dispo si bootstrap.min.js est chargé)
                const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
                modal.show();
            } catch (error) {
                console.error(error);
                showMessage(error?.message ?? "Impossible de préparer le paiement.");
                showFlash(error?.message ?? "Impossible de préparer le paiement.", "danger");
            }
        });
    }


    // Soumission du formulaire Stripe
    paymentForm.addEventListener("submit", handleSubmit);

    // Optionnel : checkStatus si tu reviens sur une page avec payment_intent_client_secret
    checkStatus().catch((e) => console.error(e));

    async function ensureStripeReady() {
        if (stripeInitialized && elements) return;

        // reset visuel éventuel
        const pe = document.querySelector("#payment-element");
        const la = document.querySelector("#link-authentication-element");
        if (pe) pe.innerHTML = "";
        if (la) la.innerHTML = "";

        await initializeStripeElements();
        stripeInitialized = true;
    }

    async function initializeStripeElements() {
        // Optionnel mais utile : si l'utilisateur ouvre le modal sans avoir rempli les adresses,
        // on bloque ici aussi.
        if (!billingAddressSelect?.value || !shippingAddressSelect?.value) {
            throw new Error("Sélectionnez les adresses avant de payer.");
        }

        // Crée PaymentIntent côté serveur et récupère le clientSecret
        let data;
        try {
            data = await fetchJson(`/api/stripe/payment-intent/${orderId}`, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-Requested-With": "XMLHttpRequest",
                },
                body: JSON.stringify({ items }),
            });
        } catch (err) {
            // fetchJson peut throw sur 4xx/5xx selon ton implémentation
            throw new Error(err?.message ?? "Erreur lors de la création du paiement Stripe.");
        }

        const clientSecret = data?.clientSecret;
        if (!clientSecret) {
            // affiche la réponse pour debug
            console.error("Stripe intent response:", data);
            throw new Error("Impossible d'initialiser le paiement Stripe (clientSecret manquant).");
        }

        elements = stripe.elements({ clientSecret });

        // Link Authentication (email)
        // const linkAuthenticationElement = elements.create("linkAuthentication");
        // linkAuthenticationElement.mount("#link-authentication-element");
        // linkAuthenticationElement.on("change", (event) => {
        //     emailAddress = event?.value?.email ?? "";
        // });

        // Payment Element
        const paymentElement = elements.create("payment", { layout: "tabs" });
        paymentElement.mount("#payment-element");
    }

    async function handleSubmit(e) {
        e.preventDefault();

        if (!elements) {
            showMessage("Le formulaire de paiement n'est pas prêt. Réessayez.");
            return;
        }

        setLoading(true);

        try {
            const { error } = await stripe.confirmPayment({
                elements,
                confirmParams: {
                    return_url: `${window.location.origin}/stripe/payment/success`,
                    receipt_email: emailAddress || undefined,
                },
            });

            if (error) {
                if (error.type === "card_error" || error.type === "validation_error") {
                    showMessage(error.message);
                } else {
                    showMessage("Une erreur inattendue est survenue.");
                }
            }
        } finally {
            setLoading(false);
        }
    }

    async function checkStatus() {
        const clientSecret = new URLSearchParams(window.location.search).get("payment_intent_client_secret");
        if (!clientSecret) return;

        const { paymentIntent } = await stripe.retrievePaymentIntent(clientSecret);

        switch (paymentIntent?.status) {
            case "succeeded":
                showMessage("Paiement réussi !");
                break;
            case "processing":
                showMessage("Votre paiement est en cours de traitement.");
                break;
            case "requires_payment_method":
                showMessage("Paiement refusé, merci de réessayer.");
                break;
            default:
                showMessage("Quelque chose s'est mal passé.");
                break;
        }
    }

    function showMessage(messageText) {
        const messageContainer = document.querySelector("#payment-message");
        if (!messageContainer) return;

        messageContainer.classList.remove("hidden");
        messageContainer.textContent = String(messageText ?? "");

        setTimeout(() => {
            messageContainer.classList.add("hidden");
            messageContainer.textContent = "";
        }, 4000);
    }

    function setLoading(isLoading) {
        const submitBtn = document.querySelector("#submit");
        const spinner = document.querySelector("#spinner");
        const buttonText = document.querySelector("#button-text");

        if (!submitBtn || !spinner || !buttonText) return;

        submitBtn.disabled = isLoading;
        spinner.classList.toggle("hidden", !isLoading);
        buttonText.classList.toggle("hidden", isLoading);
    }
});
