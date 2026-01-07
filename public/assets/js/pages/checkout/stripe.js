document.addEventListener("DOMContentLoaded", () => {
    const paymentToggleIcon = document.querySelector(".payment-methods i");
    const paypalMethodComponent = document.querySelector("#paypal-method");
    const stripeMethodComponent = document.querySelector("#stripe-method");

    let stripeMethod = true;
    let paypalMethod = false;

    const mainContent = document.querySelector(".main_content");
    const cart = JSON.parse(mainContent?.dataset?.cart ?? "[]");

    const stripePublicKey = mainContent?.dataset?.stripe_public_key ?? "";
    const orderId = mainContent?.dataset?.orderid ?? "";

    let billingAddress = "";
    let shippingAddress = "";
    let comment = "";
    let displayPayBtn = false;

    const billingAddressSelect = document.querySelector('select[name="billing_address"]');
    const shippingAddressSelect = document.querySelector('select[name="shipping_address"]');
    const commentsTextarea = document.querySelector('textarea[name="comment"], textarea');
    const payBtn = document.querySelector(".payment-button");

    const updateButton = () => {
        if (!payBtn) return;

        displayPayBtn = Boolean(billingAddress) && Boolean(shippingAddress);
        payBtn.classList.toggle("d-none", !displayPayBtn);
    };

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
    }

    billingAddressSelect?.addEventListener("change", (event) => {
        billingAddress = event.target.value;
        updateButton();
    });

    shippingAddressSelect?.addEventListener("change", (event) => {
        shippingAddress = event.target.value;
        updateButton();
    });

    commentsTextarea?.addEventListener("change", (event) => {
        comment = event.target.value;
        updateButton();
    });

    if (payBtn) {
        payBtn.addEventListener("click", async () => {
            const response = await fetch("/api/order", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ billing_address: billingAddress, shipping_address: shippingAddress, comment, orderId })
            });

            const result = await response.json();
            console.log({ result });
        });
    }

    // ----------------
    // STRIPE COMPONENT
    // ----------------

    // Si tu n'es pas sur Stripe (ex: tu affiches PayPal), tu peux ignorer l'init Stripe,
    // mais ici je laisse l'init si le DOM Stripe existe.
    if (!stripePublicKey || !orderId) return;
    if (typeof Stripe === "undefined") return;

    const paymentForm = document.querySelector("#payment-form");
    if (!paymentForm) return;

    const stripe = Stripe(stripePublicKey);
    const items = cart?.items ?? [];

    let elements;
    let emailAddress = "";

    initialize();
    checkStatus();

    paymentForm.addEventListener("submit", handleSubmit);

    async function initialize() {
        const response = await fetch("/api/stripe/payment-intent/" + orderId, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ items })
        });

        const data = await response.json();
        const clientSecret = data.clientSecret;

        if (!clientSecret) {
            showMessage("Impossible d'initialiser le paiement Stripe.");
            return;
        }

        elements = stripe.elements({ clientSecret });

        const linkAuthenticationElement = elements.create("linkAuthentication");
        linkAuthenticationElement.mount("#link-authentication-element");

        const paymentElement = elements.create("payment", { layout: "tabs" });
        paymentElement.mount("#payment-element");
    }

    async function handleSubmit(e) {
        e.preventDefault();
        setLoading(true);

        const { error } = await stripe.confirmPayment({
            elements,
            confirmParams: {
                return_url: window.location.origin + "/stripe/payment/success",
                receipt_email: emailAddress
            }
        });

        if (error) {
            if (error.type === "card_error" || error.type === "validation_error") {
                showMessage(error.message);
            } else {
                showMessage("Une erreur inattendue est survenue.");
            }
        }

        setLoading(false);
    }

    async function checkStatus() {
        const clientSecret = new URLSearchParams(window.location.search)
            .get("payment_intent_client_secret");

        if (!clientSecret) return;

        const { paymentIntent } = await stripe.retrievePaymentIntent(clientSecret);

        switch (paymentIntent.status) {
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
        messageContainer.textContent = messageText;

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
