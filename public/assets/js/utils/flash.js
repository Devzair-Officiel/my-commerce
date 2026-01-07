function getContainer() {
    return document.querySelector(".notification");
}

function escapeHtml(str) {
    return String(str)
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&#039;");
}

/**
 * type: "success" | "danger" | "warning" | "info"
 */
export function showFlash(message, type = "success", timeoutMs = 3500) {
    const container = getContainer();
    if (!container) return;

    const el = document.createElement("div");
    el.className = `alert alert-${type}`;
    el.style.minWidth = "280px";
    el.style.boxShadow = "0 6px 20px rgba(0,0,0,0.15)";
    el.style.marginBottom = "10px";
    el.innerHTML = escapeHtml(message);

    container.appendChild(el);

    window.setTimeout(() => {
        el.remove();
    }, timeoutMs);
}
