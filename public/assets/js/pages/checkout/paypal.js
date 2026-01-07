document.addEventListener("DOMContentLoaded", () => {
    if (!window.paypal) return;

    const mainContent = document.querySelector(".main_content");
    const orderId = mainContent?.dataset?.orderid ?? "";

    window.paypal.Buttons({
        async createOrder() {
            try {
                const response = await fetch("/api/paypal/orders", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify({ orderId })
                });

                const orderData = await response.json();

                if (orderData.id) {
                    return orderData.id;
                }

                const errorDetail = orderData?.details?.[0];
                const errorMessage = errorDetail
                    ? `${errorDetail.issue} ${errorDetail.description} (${orderData.debug_id})`
                    : JSON.stringify(orderData);

                throw new Error(errorMessage);
            } catch (error) {
                console.error(error);
                resultMessage(`Could not initiate PayPal Checkout...<br><br>${error}`);
            }
        },

        async onApprove(data, actions) {
            try {
                const response = await fetch(`/api/orders/${data.orderID}/capture`, {
                    method: "POST",
                    headers: { "Content-Type": "application/json" }
                });

                const orderData = await response.json();
                const errorDetail = orderData?.details?.[0];

                if (errorDetail?.issue === "INSTRUMENT_DECLINED") {
                    return actions.restart();
                }

                if (errorDetail) {
                    throw new Error(`${errorDetail.description} (${orderData.debug_id})`);
                }

                if (!orderData.purchase_units) {
                    throw new Error(JSON.stringify(orderData));
                }

                window.location.href = window.location.origin + "/paypal/payment/success";
            } catch (error) {
                console.error(error);
                resultMessage(`Sorry, your transaction could not be processed...<br><br>${error}`);
            }
        }
    }).render("#paypal-button-container");

    function resultMessage(message) {
        const container = document.querySelector("#result-message");
        if (!container) return;
        container.innerHTML = message;
    }
});
