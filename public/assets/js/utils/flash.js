function normalizeType(type) {
    switch (type) {
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

function getIcon(type) {
    switch (type) {
        case "success":
            return "✓";
        case "danger":
            return "!";
        case "warning":
            return "!";
        case "info":
        default:
            return "i";
    }
}

export function showFlash(message, type = "info", options = {}) {
    const normalizedType = normalizeType(type);
    const container =
        document.getElementById("notification-container") ||
        document.querySelector(".notification");

    if (!container || !message) {
        return;
    }

    const duration = Number.isFinite(options.duration) ? options.duration : 5000;

    const item = document.createElement("div");
    item.className = `app-notification app-notification--${normalizedType}`;
    item.setAttribute("role", "alert");

    item.innerHTML = `
        <div class="app-notification__icon" aria-hidden="true">${getIcon(normalizedType)}</div>
        <div class="app-notification__content">
            <div class="app-notification__message"></div>
        </div>
        <button type="button" class="app-notification__close" aria-label="Fermer">×</button>
    `;

    const messageNode = item.querySelector(".app-notification__message");
    const closeBtn = item.querySelector(".app-notification__close");

    if (messageNode) {
        messageNode.textContent = String(message);
    }

    let removed = false;
    let timeoutId = null;

    const removeNotification = () => {
        if (removed) {
            return;
        }

        removed = true;
        item.classList.add("is-leaving");

        window.setTimeout(() => {
            item.remove();
        }, 250);
    };

    closeBtn?.addEventListener("click", removeNotification);

    item.addEventListener("mouseenter", () => {
        if (timeoutId) {
            window.clearTimeout(timeoutId);
            timeoutId = null;
        }
    });

    item.addEventListener("mouseleave", () => {
        if (!removed) {
            timeoutId = window.setTimeout(removeNotification, 1800);
        }
    });

    container.appendChild(item);

    requestAnimationFrame(() => {
        item.classList.add("is-visible");
    });

    timeoutId = window.setTimeout(removeNotification, duration);
}
