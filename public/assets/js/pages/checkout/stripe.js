import { fetchJson } from "../../utils/fetch.js";
import { showFlash } from "../../utils/flash.js";

document.addEventListener("DOMContentLoaded", () => {
    const mainContent = document.querySelector(".main_content");
    if (!mainContent) return;

    const stripePublicKey = mainContent.dataset?.stripePublicKey ?? "";
    const orderId = mainContent.dataset?.orderid ?? "";

    let cart = { items: [] };

    try {
        cart = JSON.parse(mainContent.dataset?.cart ?? "{}");
    } catch (error) {
        console.error("Invalid cart JSON in data-cart", error);
    }

    const items = Array.isArray(cart?.items) ? cart.items : [];

    const billingAddressSelect = document.querySelector('select[name="billing_address"]');
    const shippingAddressSelect = document.querySelector('select[name="shipping_address"]');
    const commentsTextarea = document.querySelector('textarea[name="comment"]');

    const payBtnContainer = document.querySelector(".payment-button");
    const payBtnLink = document.getElementById("open-payment-modal");

    let billingAddress = billingAddressSelect?.value ?? "";
    let shippingAddress = shippingAddressSelect?.value ?? "";
    let comment = commentsTextarea?.value ?? "";

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

    commentsTextarea?.addEventListener("input", (event) => {
        comment = event.target?.value ?? "";
    });

    updateButton();

    if (!stripePublicKey || !orderId) return;
    if (typeof Stripe === "undefined") return;

    const paymentForm = document.querySelector("#payment-form");
    if (!paymentForm) return;

    const modalEl = document.getElementById("staticBackdrop");
    if (!modalEl) return;

    const stripe = Stripe(stripePublicKey);

    let elements = null;
    let emailAddress = "";
    let stripeInitialized = false;

    if (payBtnLink) {
        payBtnLink.addEventListener("click", async (event) => {
            event.preventDefault();

            try {
                if (!orderId) {
                    throw new Error("orderId manquant.");
                }

                if (!billingAddressSelect?.value || !shippingAddressSelect?.value) {
                    throw new Error("Adresse de facturation et de livraison requises.");
                }

                const orderUpdate = await fetchJson(`/api/order/${orderId}`, {
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

                if (!orderUpdate?.ok) {
                    throw new Error("Impossible d'enregistrer la commande avant paiement.");
                }

                await ensureStripeReady();

                const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
                modal.show();
            } catch (error) {
                console.error(error);
                showMessage(error?.message ?? "Impossible de préparer le paiement.");
                showFlash(error?.message ?? "Impossible de préparer le paiement.", "danger");
            }
        });
    }

    paymentForm.addEventListener("submit", handleSubmit);

    checkStatus().catch((error) => console.error(error));

    async function ensureStripeReady() {
        if (stripeInitialized && elements) {
            return;
        }

        const paymentElementContainer = document.querySelector("#payment-element");
        const linkAuthContainer = document.querySelector("#link-authentication-element");

        if (paymentElementContainer) {
            paymentElementContainer.innerHTML = "";
        }

        if (linkAuthContainer) {
            linkAuthContainer.innerHTML = "";
        }

        await initializeStripeElements();
        stripeInitialized = true;
    }

    async function initializeStripeElements() {
        if (!billingAddressSelect?.value || !shippingAddressSelect?.value) {
            throw new Error("Sélectionnez les adresses avant de payer.");
        }

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
        } catch (error) {
            throw new Error(error?.message ?? "Erreur lors de la création du paiement Stripe.");
        }

        const clientSecret = data?.clientSecret;

        if (!clientSecret) {
            console.error("Stripe intent response:", data);
            throw new Error("Impossible d'initialiser le paiement Stripe.");
        }

        elements = stripe.elements({ clientSecret });

        const paymentElement = elements.create("payment", { layout: "tabs" });
        paymentElement.mount("#payment-element");
    }

    async function handleSubmit(event) {
        event.preventDefault();

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
        const clientSecret = new URLSearchParams(window.location.search)
            .get("payment_intent_client_secret");

        if (!clientSecret) {
            return;
        }

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

        window.setTimeout(() => {
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
