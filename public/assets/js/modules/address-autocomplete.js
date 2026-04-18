/**
 * Autocomplete d'adresse via l'API Adresse du gouvernement français.
 * https://api-adresse.data.gouv.fr/
 *
 * Supporte deux modes :
 *  - id fixe (#street_input / #city_input / #code_postal_input) — page compte
 *  - classes (.address-street-input / .address-city-input / .address-code-postal-input) — checkout
 */
(function () {
    const API = "https://api-adresse.data.gouv.fr/search/";

    function initField(streetInput) {
        if (streetInput._addressAutocomplete) return;
        streetInput._addressAutocomplete = true;

        const container = streetInput.parentElement;
        let list = container.querySelector(".address-suggestions");
        if (!list) {
            list = document.createElement("ul");
            list.className = "address-suggestions list-unstyled";
            list.setAttribute("role", "listbox");
            list.setAttribute("aria-label", "Suggestions d'adresse");
            container.appendChild(list);
        }

        // Trouve les champs frères : priorité aux id, sinon aux classes dans le même formulaire
        const form = streetInput.closest("form");
        function findInput(id, cls) {
            return document.getElementById(id) || (form && form.querySelector("." + cls)) || null;
        }
        const cityInput      = findInput("city_input",       "address-city-input");
        const codePostalInput= findInput("code_postal_input","address-code-postal-input");
        const stateSelect    = form && form.querySelector("select[name='state']");

        let debounceTimer = null;
        let activeIndex = -1;

        streetInput.addEventListener("input", function () {
            clearTimeout(debounceTimer);
            const q = this.value.trim();
            if (q.length < 5) { hide(); return; }
            debounceTimer = setTimeout(() => fetch(q), 400);
        });

        streetInput.addEventListener("keydown", function (e) {
            const items = list.querySelectorAll("li");
            if (!items.length) return;
            if (e.key === "ArrowDown")  { e.preventDefault(); moveFocus(items, 1); }
            else if (e.key === "ArrowUp")   { e.preventDefault(); moveFocus(items, -1); }
            else if (e.key === "Enter" && activeIndex >= 0) { e.preventDefault(); items[activeIndex].click(); }
            else if (e.key === "Escape")    { hide(); }
        });

        document.addEventListener("click", function (e) {
            if (!list.contains(e.target) && e.target !== streetInput) hide();
        });

        function fetch(q) {
            const url = API + "?q=" + encodeURIComponent(q) + "&limit=6&autocomplete=1";
            window.fetch(url)
                .then(r => r.json())
                .then(data => render(data.features || []))
                .catch(() => hide());
        }

        function render(features) {
            list.innerHTML = "";
            activeIndex = -1;
            if (!features.length) { hide(); return; }

            features.forEach(function (feature) {
                const p = feature.properties;
                const li = document.createElement("li");
                li.setAttribute("role", "option");
                li.textContent = p.label;
                li.addEventListener("mousedown", e => e.preventDefault());
                li.addEventListener("click", function () {
                    // Reconstruit "numéro rue" proprement selon le type de résultat
                    const street = p.type === "housenumber" && p.housenumber
                        ? p.housenumber + " " + p.street
                        : (p.street || p.name || p.label);
                    streetInput.value = street;
                    if (cityInput)       cityInput.value       = p.city     || "";
                    if (codePostalInput) codePostalInput.value = p.postcode || "";
                    if (stateSelect)     stateSelect.value     = "FR";
                    hide();
                    if (codePostalInput) codePostalInput.focus();
                });
                list.appendChild(li);
            });

            list.style.display = "block";
        }

        function moveFocus(items, dir) {
            if (activeIndex >= 0) items[activeIndex].classList.remove("active");
            activeIndex = Math.max(0, Math.min(items.length - 1, activeIndex + dir));
            items[activeIndex].classList.add("active");
            items[activeIndex].scrollIntoView({ block: "nearest" });
        }

        function hide() {
            list.style.display = "none";
            list.innerHTML = "";
            activeIndex = -1;
        }
    }

    function initAll() {
        // id fixe (page compte)
        const byId = document.getElementById("street_input");
        if (byId) initField(byId);

        // classes (checkout, plusieurs formulaires possibles)
        document.querySelectorAll(".address-street-input").forEach(initField);
    }

    // Ré-init quand un formulaire est injecté dynamiquement (ex: formulaire compte ouvert au clic)
    const observer = new MutationObserver(function (mutations) {
        mutations.forEach(function (m) {
            m.addedNodes.forEach(function (node) {
                if (!(node instanceof Element)) return;
                const targets = node.id === "street_input"
                    ? [node]
                    : Array.from(node.querySelectorAll("#street_input, .address-street-input"));
                targets.forEach(initField);
            });
        });
    });

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", function () {
            initAll();
            observer.observe(document.body, { childList: true, subtree: true });
        });
    } else {
        initAll();
        observer.observe(document.body, { childList: true, subtree: true });
    }
})();
