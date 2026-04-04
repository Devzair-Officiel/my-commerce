import { showFlash } from "./flash.js";

// normalizeType est déjà dans flash.js — on lui délègue directement
document.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll(".server-flash").forEach((node) => {
        const message = node.dataset.message?.trim();
        if (!message) return;

        showFlash(message, node.dataset.label?.trim() ?? "info");
    });
});
