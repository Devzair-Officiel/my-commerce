/**
 * Sélecteur de points relais Colissimo — Leaflet + OpenStreetMap.
 */

const LEAFLET_CSS = "https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css";
const LEAFLET_JS  = "https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js";
const DAYS_FR     = ["lundi","mardi","mercredi","jeudi","vendredi","samedi","dimanche"];
const DAYS_SHORT  = ["Lun","Mar","Mer","Jeu","Ven","Sam","Dim"];

let leafletLoaded = false;

async function loadLeaflet() {
    if (leafletLoaded) return;
    if (!document.querySelector(`link[href="${LEAFLET_CSS}"]`)) {
        const link = document.createElement("link");
        link.rel = "stylesheet"; link.href = LEAFLET_CSS;
        document.head.appendChild(link);
    }
    if (!window.L) {
        await new Promise((res, rej) => {
            const s = document.createElement("script");
            s.src = LEAFLET_JS; s.onload = res; s.onerror = rej;
            document.body.appendChild(s);
        });
    }
    leafletLoaded = true;
}

async function fetchPoints(zipCode, city = "") {
    const url = `/api/pickup-points/search?zipCode=${encodeURIComponent(zipCode)}&city=${encodeURIComponent(city)}`;
    const res = await fetch(url, { headers: { Accept: "application/json" } });
    if (!res.ok) throw new Error("Erreur serveur " + res.status);
    const data = await res.json();
    if (data.error) throw new Error(data.error);
    return data;
}

function esc(s) {
    return String(s ?? "")
        .replaceAll("&","&amp;").replaceAll("<","&lt;")
        .replaceAll(">","&gt;").replaceAll('"',"&quot;");
}

function formatDist(m) {
    return m < 1000 ? `${m} m` : `${(m/1000).toFixed(1)} km`;
}

function capitalize(s) {
    return String(s).toLowerCase().replace(/\b\w/g, c => c.toUpperCase());
}

function parseHours(val) {
    if (!val || !val.trim()) return null;
    const cleaned = val.trim().replace(/00:00-00:00/g, "").replace(/\s+/g, " ").trim();
    if (!cleaned) return null;
    return cleaned.replace(/-/g, "–").replace(/\s+/g, " / ");
}

function renderTodayHours(hours, todayIdx) {
    const h = parseHours(hours[DAYS_FR[todayIdx]]);
    const day = DAYS_SHORT[todayIdx];
    if (!h) return `<span class="pup-today-closed"><strong>${day}</strong> — <span class="pup-hours-closed">Fermé</span></span>`;
    return `<span class="pup-today-open"><strong>${day}</strong> — ${h}</span>`;
}

function renderHours(hours, todayIdx) {
    const rows = DAYS_FR.map((d, i) => {
        const h = parseHours(hours[d]);
        const isToday = i === todayIdx;
        return `<tr class="${isToday ? "pup-hours-today" : ""}">
            <td class="pup-hours-day">${DAYS_SHORT[i]}</td>
            <td class="pup-hours-val">${h ?? '<span class="pup-hours-closed">Fermé</span>'}</td>
        </tr>`;
    }).join("");
    return `<table class="pup-hours-table">${rows}</table>`;
}

function createIcon(active) {
    const size = active ? 30 : 21;
    return L.icon({
        iconUrl:    "/assets/images/colissimo.png",
        iconSize:   [size, Math.round(size * 25 / 21)],
        iconAnchor: [Math.round(size / 2), Math.round(size * 25 / 21)],
        popupAnchor:[0, -Math.round(size * 25 / 21)],
        className:  active ? "pup-marker-active" : "pup-marker",
    });
}

// ── Zip autocomplete ───────────────────────────────────────────────────────

function initZipAutocomplete(input, onSelect) {
    const wrap = input.parentElement;
    wrap.style.position = "relative";

    const dropdown = document.createElement("ul");
    dropdown.className = "pup-ac-list";
    wrap.appendChild(dropdown);

    let debounceTimer = null;
    let currentQuery  = "";

    const hide = () => { dropdown.innerHTML = ""; dropdown.style.display = "none"; };
    hide();

    input.addEventListener("input", () => {
        const q = input.value.trim();
        clearTimeout(debounceTimer);
        if (q.length < 2) { hide(); return; }
        debounceTimer = setTimeout(() => suggest(q), 250);
    });

    input.addEventListener("keydown", e => {
        const items = dropdown.querySelectorAll(".pup-ac-item");
        const active = dropdown.querySelector(".pup-ac-item--active");
        if (e.key === "ArrowDown") {
            e.preventDefault();
            const next = active ? active.nextElementSibling : items[0];
            active?.classList.remove("pup-ac-item--active");
            next?.classList.add("pup-ac-item--active");
        } else if (e.key === "ArrowUp") {
            e.preventDefault();
            const prev = active?.previousElementSibling ?? items[items.length - 1];
            active?.classList.remove("pup-ac-item--active");
            prev?.classList.add("pup-ac-item--active");
        } else if (e.key === "Escape") {
            hide();
        }
    });

    document.addEventListener("click", e => {
        if (!wrap.contains(e.target)) hide();
    });

    async function suggest(q) {
        currentQuery = q;
        try {
            const res = await fetch(
                `https://api-adresse.data.gouv.fr/search/?q=${encodeURIComponent(q)}&limit=6`,
                { headers: { Accept: "application/json" } }
            );
            const data = await res.json();
            if (q !== currentQuery) return;

            const features = data.features ?? [];
            if (!features.length) { hide(); return; }

            dropdown.innerHTML = features.map(f => {
                const p     = f.properties;
                const label = p.label ?? "";
                const city  = p.city  ?? "";
                const zip   = p.postcode ?? "";
                return `<li class="pup-ac-item" data-zip="${esc(zip)}" data-city="${esc(city)}" role="option">
                    <span class="pup-ac-label">${esc(label)}</span>
                </li>`;
            }).join("");

            dropdown.querySelectorAll(".pup-ac-item").forEach(li => {
                li.addEventListener("mousedown", e => {
                    e.preventDefault();
                    hide();
                    onSelect(li.dataset.zip, li.dataset.city);
                });
            });

            dropdown.style.display = "";
        } catch { hide(); }
    }
}

async function geolocateUser() {
    return new Promise((resolve) => {
        if (!navigator.geolocation) { resolve(null); return; }
        navigator.geolocation.getCurrentPosition(
            pos => resolve({ lat: pos.coords.latitude, lng: pos.coords.longitude }),
            ()  => resolve(null),
            { timeout: 6000, maximumAge: 60000 }
        );
    });
}

async function reverseGeocode(lat, lng) {
    try {
        const res = await fetch(
            `https://api-adresse.data.gouv.fr/reverse/?lon=${lng}&lat=${lat}`,
            { headers: { Accept: "application/json" } }
        );
        const data = await res.json();
        const p = data.features?.[0]?.properties;
        if (!p?.postcode) return null;
        return { zip: p.postcode, city: p.city ?? "" };
    } catch { return null; }
}

export async function initPickupMap(containerEl, { zipCode, city = "", onSelect, modalEl }) {
    const todayIdx = (() => { const d = new Date().getDay(); return d === 0 ? 6 : d - 1; })();
    const isMobile = window.innerWidth < 768;

    containerEl.innerHTML = `<div class="pup-loading">
        <div class="spinner-border" style="color:#E87722;width:2rem;height:2rem;" role="status"></div>
        <span style="font-size:13px;color:#888">Localisation en cours…</span>
    </div>`;

    let map        = null;
    let markers    = [];
    let userMarker = null;
    let selectedId = null;
    let currentPoints = [];

    if (modalEl) {
        modalEl.addEventListener("shown.bs.modal", () => {
            setTimeout(() => map?.invalidateSize(), 50);
            setTimeout(() => map?.invalidateSize(), 300);
        }, { once: false });
    }

    // Tenter la géolocalisation, sinon fallback sur le zipCode du dataset
    let initZip  = zipCode;
    let initCity = city;
    let userCoords = null;

    const geo = await geolocateUser();
    if (geo) {
        userCoords = geo;
        const resolved = await reverseGeocode(geo.lat, geo.lng);
        if (resolved) {
            initZip  = resolved.zip;
            initCity = resolved.city;
        }
    }

    try {
        const [points] = await Promise.all([fetchPoints(initZip, initCity), loadLeaflet()]);
        currentPoints = points;
        buildUI(points, initZip);
    } catch (e) {
        console.error("[PickupMap]", e);
        containerEl.innerHTML = `<div class="pup-error">
            <span>Impossible de charger les points relais</span>
            <small style="color:#bbb">${esc(e.message)}</small>
        </div>`;
        return;
    }

    function buildUI(initialPoints, resolvedZip = "") {
        containerEl.innerHTML = `
            <div class="pup-wrap">
                <div class="pup-sidebar">
                    <div class="pup-search-bar">
                        <input class="pup-zip" id="pup-zip-input" name="pup_zipcode" type="text"
                               value="${esc(resolvedZip)}" placeholder="Code postal ou ville"
                               maxlength="60" aria-label="Code postal ou ville" autocomplete="off">
                        <button type="button" class="pup-search-btn" aria-label="Rechercher">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg>
                        </button>
                    </div>
                    <div class="pup-list" role="list"></div>
                </div>
                <div class="pup-map-wrap">
                    <div class="pup-map" id="pup-leaflet-map"></div>
                </div>
            </div>`;

        const listEl    = containerEl.querySelector(".pup-list");
        const mapEl     = containerEl.querySelector("#pup-leaflet-map");
        const zipInput  = containerEl.querySelector(".pup-zip");
        const searchBtn = containerEl.querySelector(".pup-search-btn");
        const sidebar   = containerEl.querySelector(".pup-sidebar");
        const mapWrap   = containerEl.querySelector(".pup-map-wrap");


        // Init map
        map = L.map(mapEl, { zoomControl: true, attributionControl: false });
        L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", { maxZoom: 18 }).addTo(map);
        L.control.attribution({ prefix: false, position: "bottomright" }).addTo(map);
        setTimeout(() => map.invalidateSize(), 100);
        setTimeout(() => map.invalidateSize(), 400);

        // Bouton "Rechercher dans cette zone"
        const zoneBtn = document.createElement("button");
        zoneBtn.type = "button";
        zoneBtn.className = "pup-zone-btn";
        zoneBtn.textContent = "Rechercher dans cette zone";
        zoneBtn.style.display = "none";
        mapWrap.appendChild(zoneBtn);

        let moveTimer = null;
        let initialLoad = true;
        map.on("moveend zoomend", () => {
            if (initialLoad) return;
            clearTimeout(moveTimer);
            moveTimer = setTimeout(() => { zoneBtn.style.display = ""; }, 400);
        });

        zoneBtn.addEventListener("click", async () => {
            zoneBtn.disabled = true;
            zoneBtn.textContent = "Recherche…";
            const center = map.getCenter();
            try {
                const res = await fetch(
                    `https://api-adresse.data.gouv.fr/reverse/?lon=${center.lng}&lat=${center.lat}`,
                    { headers: { Accept: "application/json" } }
                );
                const geo = await res.json();
                const props = geo.features?.[0]?.properties;
                if (!props?.postcode) throw new Error("Zone non reconnue");
                const newZip  = String(props.postcode);
                const newCity = props.city ?? props.municipality ?? "";
                zipInput.value = newZip;
                const pts = await fetchPoints(newZip, newCity);
                currentPoints  = pts;
                selectedId     = null;
                updateResults(pts, listEl);
            } catch (e) {
                zoneBtn.textContent = "Zone non reconnue";
                setTimeout(() => { zoneBtn.textContent = "Rechercher dans cette zone"; zoneBtn.disabled = false; }, 2000);
                return;
            }
            zoneBtn.style.display = "none";
            zoneBtn.disabled = false;
            zoneBtn.textContent = "Rechercher dans cette zone";
        });

        // Marqueur position utilisateur
        if (userCoords) {
            userMarker = L.circleMarker([userCoords.lat, userCoords.lng], {
                radius: 7, color: "#2563eb", fillColor: "#3b82f6",
                fillOpacity: 0.9, weight: 2,
            }).addTo(map).bindPopup("Votre position");
        }

        // Premier rendu liste + marqueurs
        updateResults(initialPoints, listEl);
        setTimeout(() => { initialLoad = false; }, 1000);

        const spinSvg   = `<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="animation:spin .6s linear infinite"><circle cx="12" cy="12" r="10" stroke-dasharray="30 10"/></svg>`;
        const searchSvg = `<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg>`;

        const doSearchWithCity = async (zip, city = "") => {
            if (!zip || zip.length < 3) return;
            searchBtn.disabled = true;
            searchBtn.innerHTML = spinSvg;
            try {
                const pts = await fetchPoints(zip, city);
                currentPoints = pts;
                selectedId = null;
                updateResults(pts, listEl);
            } catch {
                zipInput.style.borderColor = "#f87171";
                setTimeout(() => zipInput.style.borderColor = "", 2000);
            } finally {
                searchBtn.disabled = false;
                searchBtn.innerHTML = searchSvg;
            }
        };

        const doSearch = () => doSearchWithCity(zipInput.value.trim());

        searchBtn.addEventListener("click", doSearch);
        zipInput.addEventListener("keydown", e => { if (e.key === "Enter") { e.preventDefault(); doSearch(); } });
        initZipAutocomplete(zipInput, (zip, city) => {
            zipInput.value = zip;
            doSearchWithCity(zip, city);
        });

        function updateResults(points, listEl) {
            // Vider les anciens marqueurs
            markers.forEach(({ m }) => m.remove());
            markers = [];

            if (!points.length) {
                listEl.innerHTML = `<div class="pup-error" style="height:auto;padding:20px">Aucun point relais trouvé.</div>`;
                return;
            }

            // Nouveaux marqueurs
            const valid = points.filter(p => p.lat && p.lng);
            markers = valid.map(p => {
                const m = L.marker([p.lat, p.lng], { icon: createIcon(false), title: p.name }).addTo(map);
                m.on("click", () => selectPoint(p, listEl));
                return { m, id: p.id };
            });

            if (valid.length) {
                map.fitBounds(valid.map(p => [p.lat, p.lng]), { padding: [30, 30], maxZoom: 14 });
            }

            // Nouvelle liste
            listEl.innerHTML = points.map((p, i) => {
                const name = capitalize(p.name);
                const addr = `${p.address ? capitalize(p.address) + ", " : ""}${p.postalCode} ${capitalize(p.city)}`;
                return `<div class="pup-item" role="listitem" tabindex="0" data-id="${esc(p.id)}" data-idx="${i}">
                    <div class="pup-item-top">
                        <span class="pup-item-name">${esc(name)}</span>
                        ${p.distance ? `<span class="pup-item-dist">${formatDist(p.distance)}</span>` : ""}
                    </div>
                    <div class="pup-item-addr">${esc(addr)}</div>
                    <div class="pup-item-hours">
                        <div class="pup-hours-today-line">${renderTodayHours(p.hours, todayIdx)}</div>
                        <div class="pup-hours-details">
                            <div class="pup-hours-body" style="display:none">${renderHours(p.hours, todayIdx)}</div>
                        </div>
                    </div>
                    <button type="button" class="pup-select-btn">✓ Choisir ce point</button>
                </div>`;
            }).join("");

            listEl.querySelectorAll(".pup-item").forEach(el => {
                el.addEventListener("click", e => {
                    if (e.target.closest(".pup-select-btn")) {
                        onSelect(currentPoints[+el.dataset.idx]);
                        return;
                    }
                    selectPoint(currentPoints[+el.dataset.idx], listEl);
                });
                el.addEventListener("keydown", e => {
                    if (e.key === "Enter") selectPoint(currentPoints[+el.dataset.idx], listEl);
                });
            });
        }

        function selectPoint(point, listEl) {
            selectedId = point.id;
            markers.forEach(({ m, id }) => m.setIcon(createIcon(id === selectedId)));
            listEl.querySelectorAll(".pup-item").forEach(el =>
                el.classList.toggle("pup-item--active", el.dataset.id === selectedId)
            );
            const activeEl = listEl.querySelector(".pup-item--active");
            if (activeEl) {
                activeEl.scrollIntoView({ block: "nearest", behavior: "smooth" });
                const body = activeEl.querySelector(".pup-hours-body");
                if (body) body.style.display = "";
            }
            listEl.querySelectorAll(".pup-item:not(.pup-item--active) .pup-hours-body")
                  .forEach(b => b.style.display = "none");
            const found = markers.find(({ id }) => id === selectedId);
            if (found) map.setView(found.m.getLatLng(), 15, { animate: true });
        }
    }
}
