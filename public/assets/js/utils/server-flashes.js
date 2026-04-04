import { showFlash } from "./flash.js";

function normalizeLabel(label) {
    switch (label) {
        case "error":
        case "danger":
        case "verify_email_error":
            return "danger";
        case "success":
            return "success";
        case "warning":
            return "warning";
        case "info":
            return "info";
        default:
            return "info";
    }
}

document.addEventListener("DOMContentLoaded", () => {
    const flashNodes = document.querySelectorAll(".server-flash");

    flashNodes.forEach((node) => {
        const message = node.dataset.message?.trim();
        const label = normalizeLabel(node.dataset.label?.trim());

        if (!message) {
            return;
        }

        showFlash(message, label);
    });
});
