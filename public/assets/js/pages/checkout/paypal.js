import { fetchJson } from "../../utils/fetch.js";

document.addEventListener("DOMContentLoaded", () => {
    if (!window.paypal) return;

    const mainContent = document.querySelector(".main_content");
    const orderId = mainContent?.dataset?.orderid ?? "";

    if (!orderId) {
        resultMessage("OrderId manquant : impossible d'initialiser PayPal.");
        return;
    }

    window.paypal
        .Buttons({
            async createOrder() {
                try {
                    // ✅ Utilise fetchJson (CSRF + gestion erreurs)
                    const orderData = await fetchJson("/api/paypal/orders", {
                        method: "POST",
                        headers: { "Content-Type": "application/json" },
                        body: JSON.stringify({ orderId }),
                    });

                    if (orderData?.id) {
                        return orderData.id;
                    }

                    // Fallback message si le backend renvoie une structure inattendue
                    const errorDetail = orderData?.details?.[0];
                    const errorMessage = errorDetail
                        ? `${errorDetail.issue} ${errorDetail.description} (${orderData.debug_id ?? "no_debug_id"})`
                        : `Réponse inattendue: ${JSON.stringify(orderData)}`;

                    throw new Error(errorMessage);
                } catch (error) {
                    console.error(error);
                    resultMessage(
                        `Could not initiate PayPal Checkout...<br><br>${escapeHtml(
                            error?.message ?? String(error)
                        )}`
                    );
                    // PayPal attend que createOrder retourne un orderId ; on relance une erreur
                    throw error;
                }
            },

            async onApprove(data, actions) {
                try {
                    if (!data?.orderID) {
                        throw new Error("orderID manquant dans la réponse PayPal.");
                    }

                    const orderData = await fetchJson(`/api/orders/${data.orderID}/capture`, {
                        method: "POST",
                        headers: { "Content-Type": "application/json" },
                    });

                    const errorDetail = orderData?.details?.[0];

                    if (errorDetail?.issue === "INSTRUMENT_DECLINED") {
                        return actions.restart();
                    }

                    if (errorDetail) {
                        throw new Error(
                            `${errorDetail.description ?? "Erreur PayPal"} (${orderData.debug_id ?? "no_debug_id"})`
                        );
                    }

                    if (!orderData?.purchase_units) {
                        throw new Error(`Réponse inattendue: ${JSON.stringify(orderData)}`);
                    }

                    window.location.href = `${window.location.origin}/paypal/payment/success`;
                } catch (error) {
                    console.error(error);
                    resultMessage(
                        `Sorry, your transaction could not be processed...<br><br>${escapeHtml(
                            error?.message ?? String(error)
                        )}`
                    );
                }
            },
        })
        .render("#paypal-button-container");

    function resultMessage(message) {
        const container = document.querySelector("#result-message");
        if (!container) return;
        container.innerHTML = message;
    }

    // Sécurise l'injection HTML dans resultMessage()
    function escapeHtml(str) {
        return String(str)
            .replaceAll("&", "&amp;")
            .replaceAll("<", "&lt;")
            .replaceAll(">", "&gt;")
            .replaceAll('"', "&quot;")
            .replaceAll("'", "&#039;");
    }
});
