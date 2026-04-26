import { fetchJson } from "../utils/fetch.js";
import { escapeHtml } from "../utils/html.js";

const DEBOUNCE_MS  = 280;
const MIN_CHARS    = 2;

function formatPrice(cents) {
    return Intl.NumberFormat("fr-FR", { style: "currency", currency: "EUR" }).format(cents / 100);
}


function buildDropdown() {
    const el = document.createElement("ul");
    el.id = "search-dropdown";
    el.className = "search-dropdown";
    el.setAttribute("role", "listbox");
    el.setAttribute("aria-label", "Suggestions de recherche");
    return el;
}

function renderResults(dropdown, results, term) {
    dropdown.innerHTML = "";

    if (results.length === 0) {
        dropdown.innerHTML = `<li class="search-dropdown__empty">Aucun résultat pour « ${escapeHtml(term)} »</li>`;
        dropdown.classList.add("is-open");
        return;
    }

    for (const product of results) {
        const src = product.thumb
            ? `/assets/images/products/${escapeHtml(product.thumb)}`
            : "/assets/images/placeholder.png";

        const li = document.createElement("li");
        li.className = "search-dropdown__item";
        li.setAttribute("role", "option");
        li.innerHTML = `
            <a href="/miels/${escapeHtml(product.slug)}" class="search-dropdown__link">
                <img src="${src}" alt="${escapeHtml(product.thumbAlt ?? product.title)}" class="search-dropdown__img" width="48" height="48">
                <span class="search-dropdown__info">
                    <span class="search-dropdown__title">${escapeHtml(product.title)}</span>
                    <span class="search-dropdown__price">${formatPrice(product.price)}</span>
                </span>
            </a>
        `;
        dropdown.appendChild(li);
    }

    // Lien "voir tous les résultats"
    const li = document.createElement("li");
    li.className = "search-dropdown__all";
    li.innerHTML = `<a href="/product/search?term=${encodeURIComponent(term)}" class="search-dropdown__all-link">Voir tous les résultats →</a>`;
    dropdown.appendChild(li);

    dropdown.classList.add("is-open");
}

function closeDropdown(dropdown) {
    dropdown.classList.remove("is-open");
    dropdown.innerHTML = "";
}

export function initSearch() {
    const wrap  = document.querySelector(".search_wrap");
    const input = wrap?.querySelector("input[name='term']");
    const form  = wrap?.querySelector("form");

    if (!wrap || !input || !form) return;

    // Le dropdown vit dans le form (position:relative) pour se positionner sous l'input
    const dropdown = buildDropdown();
    form.appendChild(dropdown);

    let timer = null;
    let lastTerm = "";
    let abortController = null;

    // Ferme si on clique ailleurs ou sur le bouton close de l'overlay
    document.addEventListener("click", (e) => {
        if (!wrap.contains(e.target)) {
            closeDropdown(dropdown);
        }
    });

    wrap.querySelector(".close-search")?.addEventListener("click", () => {
        closeDropdown(dropdown);
        input.value = "";
        lastTerm = "";
    });

    // Navigation clavier dans les suggestions
    input.addEventListener("keydown", (e) => {
        if (!dropdown.classList.contains("is-open")) return;

        const items = [...dropdown.querySelectorAll(".search-dropdown__link")];
        const focused = document.activeElement;
        const idx = items.indexOf(focused);

        if (e.key === "ArrowDown") {
            e.preventDefault();
            (items[idx + 1] ?? items[0])?.focus();
        } else if (e.key === "ArrowUp") {
            e.preventDefault();
            idx <= 0 ? input.focus() : items[idx - 1]?.focus();
        } else if (e.key === "Escape") {
            closeDropdown(dropdown);
            input.focus();
        }
    });

    input.addEventListener("input", () => {
        const term = input.value.trim();

        if (term === lastTerm) return;
        lastTerm = term;

        clearTimeout(timer);

        if (term.length < MIN_CHARS) {
            closeDropdown(dropdown);
            return;
        }

        timer = setTimeout(async () => {
            abortController?.abort();
            abortController = new AbortController();

            dropdown.innerHTML = `<li class="search-dropdown__loading" aria-live="polite">Recherche en cours…</li>`;
            dropdown.classList.add("is-open");

            try {
                const data = await fetchJson(`/api/search?term=${encodeURIComponent(term)}`);
                renderResults(dropdown, data.results ?? [], term);
            } catch (e) {
                if (e.name !== "AbortError") {
                    closeDropdown(dropdown);
                }
            }
        }, DEBOUNCE_MS);
    });

    // Ferme le dropdown quand on soumet le formulaire
    form.addEventListener("submit", () => {
        closeDropdown(dropdown);
    });
}
