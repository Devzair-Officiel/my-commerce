// assets/js/pages/checkout/stripe.js
import { fetchJson } from "../../utils/fetch.js";

document.addEventListener("DOMContentLoaded", () => {
    // ----------------
    // UI / TOGGLE
    // ----------------
    const paymentToggleIcon = document.querySelector(".payment-methods i");
    const paypalMethodComponent = document.querySelector("#paypal-method");
    const stripeMethodComponent = document.querySelector("#stripe-method");

    let stripeMethod = true;
    let paypalMethod = false;

    if (paymentToggleIcon) {
        paymentToggleIcon.addEventListener("click", () => {
            stripeMethod = !stripeMethod;
            paypalMethod = !paypalMethod;

            if (stripeMethod) {
                paymentToggleIcon.className = "fa-solid fa-toggle-off";
                stripeMethodComponent?.classList.remove("d-none");
                paypalMethodComponent?.classList.add("d-none");
            } else {
                paymentToggleIcon.className = "fa-solid fa-toggle-on";
                stripeMethodComponent?.classList.add("d-none");
                paypalMethodComponent?.classList.remove("d-none");
            }
        });

        // Accessibilité : Enter / Space
        paymentToggleIcon.addEventListener("keydown", (e) => {
            if (e.key === "Enter" || e.key === " ") {
                e.preventDefault();
                paymentToggleIcon.click();
            }
        });
    }

    // ----------------
    // DATA FROM DOM
    // ----------------
    const mainContent = document.querySelector(".main_content");
    const stripePublicKey = mainContent?.dataset?.stripe_public_key ?? "";
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
    let displayPayBtn = false;

    const billingAddressSelect = document.querySelector('select[name="billing_address"]');
    const shippingAddressSelect = document.querySelector('select[name="shipping_address"]');
    const commentsTextarea = document.querySelector('textarea[name="comment"], textarea');

    // Dans ton template, .payment-button est un conteneur <div>, et le bouton est un <a>
    const payBtnContainer = document.querySelector(".payment-button");
    const payBtnLink = payBtnContainer?.querySelector("a,button") ?? null;

    const updateButton = () => {
        if (!payBtnContainer) return;
        displayPayBtn = Boolean(billingAddress) && Boolean(shippingAddress);
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

    // Patch adresses/comment au clic sur "Payer maintenant" (avant d'ouvrir réellement le paiement)
    // (Le modal s’ouvrira via data-bs-toggle si aucune erreur n’est levée.)
    if (payBtnLink) {
        payBtnLink.addEventListener("click", async (e) => {
            try {
                if (!orderId) throw new Error("orderId manquant.");
                if (!billingAddress || !shippingAddress) {
                    throw new Error("Adresse de facturation et de livraison requises.");
                }

                await fetchJson(`/api/order/${orderId}`, {
                    method: "PATCH",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify({
                        billing_address: billingAddress,
                        shipping_address: shippingAddress,
                        comment,
                    }),
                });
            } catch (error) {
                // Empêche l'ouverture de la modal si on ne peut pas sauvegarder l'ordre
                e.preventDefault();
                e.stopPropagation();
                console.error(error);
                showMessage(error?.message ?? "Impossible de mettre à jour la commande.");
            }
        });
    }

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

    // Initialise Stripe Elements uniquement quand la modal est réellement affichée
    modalEl?.addEventListener("shown.bs.modal", async () => {
        if (stripeInitialized) return;
        stripeInitialized = true;

        try {
            await initializeStripeElements();
        } catch (e) {
            console.error(e);
            showMessage(e?.message ?? "Impossible d'initialiser le paiement Stripe.");
        }
    });

    // Soumission du formulaire Stripe
    paymentForm.addEventListener("submit", handleSubmit);

    // Optionnel : checkStatus si tu reviens sur une page avec payment_intent_client_secret
    checkStatus().catch((e) => console.error(e));

    async function initializeStripeElements() {
        // Crée PaymentIntent côté serveur et récupère le clientSecret
        const data = await fetchJson(`/api/stripe/payment-intent/${orderId}`, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ items }),
        });

        const clientSecret = data?.clientSecret;
        if (!clientSecret) {
            showMessage("Impossible d'initialiser le paiement Stripe (clientSecret manquant).");
            return;
        }

        elements = stripe.elements({ clientSecret });

        // Link Authentication (email)
        const linkAuthenticationElement = elements.create("linkAuthentication");
        linkAuthenticationElement.mount("#link-authentication-element");
        linkAuthenticationElement.on("change", (event) => {
            emailAddress = event?.value?.email ?? "";
        });

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
