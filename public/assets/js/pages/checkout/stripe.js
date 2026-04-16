import { fetchJson } from "../../utils/fetch.js";
import { showFlash } from "../../utils/flash.js";
import { initPickupPointSelector, getSelectedPickupPoint } from "./pickup-point.js";

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

    let billingAddress  = billingAddressSelect?.value ?? "";
    let shippingAddress = shippingAddressSelect?.value ?? "";
    let comment         = commentsTextarea?.value ?? "";
    let isPickupMode    = false;

    const getSelectedCarrierId = () => {
        const checked = document.querySelector(".carrier-radio:checked");
        return checked?.value ?? null;
    };

    const updateButton = () => {
        if (!payBtnContainer) return;

        const carrierSelected = Boolean(getSelectedCarrierId());
        const shippingOk      = isPickupMode ? Boolean(getSelectedPickupPoint()) : Boolean(shippingAddress);
        const ready           = carrierSelected && Boolean(billingAddress) && shippingOk;

        payBtnContainer.classList.toggle("d-none", !ready);

        const hint = document.getElementById("checkout-hint");
        if (hint) {
            if (!carrierSelected) {
                hint.textContent = "Choisissez un mode de livraison pour continuer.";
                hint.classList.remove("d-none");
            } else if (!billingAddress) {
                hint.textContent = "Choisissez une adresse de facturation pour continuer.";
                hint.classList.remove("d-none");
            } else if (!shippingOk) {
                hint.textContent = isPickupMode
                    ? "Choisissez un point relais pour continuer."
                    : "Choisissez une adresse de livraison pour continuer.";
                hint.classList.remove("d-none");
            } else {
                hint.classList.add("d-none");
            }
        }
    };

    billingAddressSelect?.addEventListener("change", (e) => {
        billingAddress = e.target?.value ?? "";
        updateButton();
    });

    shippingAddressSelect?.addEventListener("change", (e) => {
        shippingAddress = e.target?.value ?? "";
        updateButton();
    });

    commentsTextarea?.addEventListener("input", (e) => {
        comment = e.target?.value ?? "";
    });

    // ── Mise à jour dynamique des totaux selon le carrier sélectionné ──────
    const updateShippingTotals = () => {
        const checkedLabel = document.querySelector(".carrier-radio:checked")?.closest(".carrier-option");
        if (!checkedLabel) return;

        const priceCents    = parseInt(checkedLabel.dataset.price ?? "0", 10);
        const isFree        = priceCents === 0;
        const subTotal      = cart.sub_total ?? 0;
        const totalWithShip = subTotal + priceCents;

        const shippingLabel = document.getElementById("checkout-shipping-label");
        const shippingPrice = document.getElementById("checkout-shipping-price");
        const totalEl       = document.getElementById("checkout-total");
        const payTotalEl    = document.getElementById("checkout-pay-total");

        const fmt = (cents) =>
            new Intl.NumberFormat("fr-FR", { style: "currency", currency: "EUR" }).format(cents / 100);

        if (shippingLabel) shippingLabel.textContent = isFree ? "Livraison offerte" : "Frais de livraison";
        if (shippingPrice) shippingPrice.textContent = isFree ? "Offert" : fmt(priceCents);
        if (totalEl)       totalEl.textContent       = fmt(totalWithShip);
        if (payTotalEl)    payTotalEl.textContent     = fmt(totalWithShip);
    };

    // On écoute le click sur le label (pas change sur le radio) car pickup-point.js
    // fait radio.checked = true manuellement, ce qui ne déclenche pas l'event "change".
    document.querySelectorAll(".carrier-option").forEach((label) => {
        label.addEventListener("click", () => {
            // Légère attente pour que pickup-point.js ait coché le radio en premier
            requestAnimationFrame(() => {
                updateShippingTotals();
                updateButton();
            });
        });
    });

    // Initialiser avec le carrier déjà coché au chargement
    updateShippingTotals();

    const togglePhoneSection = (isPickup) => {
        const phoneSection = document.getElementById("pickup-phone-section");
        const phoneInput   = document.getElementById("pickup-phone");
        if (phoneSection) phoneSection.classList.toggle("d-none", !isPickup);
        if (phoneInput)   phoneInput.required = isPickup;
    };

    initPickupPointSelector({
        onPickupPointChange: (_point) => updateButton(),
        onShippingModeChange: (isPickup) => {
            isPickupMode = isPickup;
            togglePhoneSection(isPickup);
            updateButton();
        },
    });

    // Appliquer l'état initial si un carrier avec pickup est déjà sélectionné
    const checkedCarrier = document.querySelector(".carrier-radio:checked");
    if (checkedCarrier) {
        const initialIsPickup = checkedCarrier.closest(".carrier-option")?.dataset?.hasPickup === "true";
        if (initialIsPickup) {
            isPickupMode = true;
            togglePhoneSection(true);
        }
    }

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

                if (!billingAddressSelect?.value) {
                    throw new Error("Adresse de facturation requise.");
                }

                const pickupPoint = getSelectedPickupPoint();

                if (isPickupMode && !pickupPoint) {
                    throw new Error("Veuillez choisir un point relais.");
                }

                const phoneInput = document.getElementById("pickup-phone");
                const phoneValue = phoneInput?.value?.trim() ?? "";
                if (isPickupMode && !phoneValue) {
                    phoneInput?.focus();
                    document.getElementById("pickup-phone-error")?.classList.remove("d-none");
                    throw new Error("Veuillez saisir votre numéro de portable pour la livraison en point relais.");
                }
                if (isPickupMode) {
                    document.getElementById("pickup-phone-error")?.classList.add("d-none");
                }

                if (!isPickupMode && !shippingAddressSelect?.value) {
                    throw new Error("Adresse de livraison requise.");
                }

                const orderUpdate = await fetchJson(`/api/order/${orderId}`, {
                    method: "PATCH",
                    headers: {
                        "Content-Type": "application/json",
                        "X-Requested-With": "XMLHttpRequest",
                    },
                    body: JSON.stringify({
                        billing_address:  billingAddressSelect.value,
                        // En mode point relais, on envoie null pour l'adresse de livraison
                        shipping_address: isPickupMode ? null : (shippingAddressSelect?.value ?? null),
                        comment,
                        pickup_point: pickupPoint ?? null,
                        carrier_id: getSelectedCarrierId(),
                        phone: isPickupMode ? (document.getElementById("pickup-phone")?.value?.trim() ?? null) : null,
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
        const shippingOk = isPickupMode ? Boolean(getSelectedPickupPoint()) : Boolean(shippingAddressSelect?.value);
        if (!billingAddressSelect?.value || !shippingOk) {
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
