import { fetchJson } from "../utils/fetch.js";
import { addFlashMessage, formatPrice } from "../utils/ui.js";
import { escapeHtml, getThumbFilename } from "../utils/html.js";

function isComparePath(pathname) {
  return pathname.startsWith("/compare/add/") || pathname.startsWith("/compare/remove/");
}

export function initCompare() {
  document.addEventListener("click", onDocumentClick, { passive: false });
}

async function onDocumentClick(event) {
  const link = event.target.closest("a");
  if (!link) return;

  const isCompareLink =
    link.matches(".add-to-compare") ||
    link.matches(".compare-card__remove");

  if (!isCompareLink) return;

  let url;
  try {
    url = new URL(link.href, window.location.origin);
  } catch {
    return;
  }

  if (!isComparePath(url.pathname)) return;

  event.preventDefault();
  await manageCompareUrl(url);
}

async function manageCompareUrl(url) {
  let compare;
  try {
    compare = await fetchJson(url.toString());
  } catch (e) {
    addFlashMessage(e?.message || "Erreur comparatif", "danger");
    return;
  }

  const isAdd = url.pathname.startsWith("/compare/add/");
  const isRemove = url.pathname.startsWith("/compare/remove/");

  if (isAdd) addFlashMessage("Ajouté au comparatif !");
  if (isRemove) addFlashMessage("Supprimé du comparatif !");

  displayCompare(compare);
}

export function displayCompare(compare = []) {
  const grid = document.querySelector(".compare-grid");
  if (!grid) return;

  if (!Array.isArray(compare) || compare.length === 0) {
    grid.innerHTML = `
      <div class="text-center py-5">
        <svg class="icon" aria-hidden="true" style="width:3rem;height:3rem;color:#ddd;"><use href="#icon-shuffle"></use></svg>
        <p class="mt-3 text-muted">Aucun produit à comparer pour le moment.</p>
        <a href="/" class="btn btn-fill-out mt-2">Découvrir nos miels</a>
      </div>
    `;
    return;
  }

  // Lignes de comparaison à afficher
  const rows = [
    { key: "image",       label: null },
    { key: "title",       label: null },
    { key: "price",       label: "Prix" },
    { key: "description", label: "Description" },
    { key: "origin",      label: "Origine" },
    { key: "weight",      label: "Poids" },
    { key: "stock",       label: "Disponibilité" },
    { key: "actions",     label: null },
  ];

  // Pre-compute per-product data once (not once per row)
  const products = compare.map((product) => {
    const id    = Number(product?.id ?? 0);
    const title = escapeHtml(product?.title ?? "");
    const slug  = escapeHtml(product?.slug ?? "");

    const imgFile  = product.images?.[0]?.filename ?? null;
    const imgThumb = imgFile ? getThumbFilename(imgFile) : null;
    const imgSrc   = imgThumb
      ? `/assets/images/products/${imgThumb}`
      : "/assets/images/placeholder.png";
    const alt      = escapeHtml(product.images?.[0]?.alt ?? title);

    const isOnSale     = !!product.isOnSale;
    const priceCents   = Number(isOnSale ? product.soldePrice : product.regularPrice);
    const regularCents = Number(product.regularPrice);
    const inStock      = Number(product.stock ?? 0) > 0;

    return { id, title, slug, imgSrc, alt, isOnSale, priceCents, regularCents, inStock, product };
  });

  let html = `<div class="compare-table">`;

  for (const row of rows) {
    const isImage   = row.key === "image";
    const isTitle   = row.key === "title";
    const isActions = row.key === "actions";
    const hasLabel  = row.label !== null;

    html += `<div class="compare-row compare-row--${row.key}">`;

    if (hasLabel) {
      html += `<div class="compare-row__label">${escapeHtml(row.label)}</div>`;
    } else {
      html += `<div class="compare-row__label compare-row__label--empty"></div>`;
    }

    for (const { id, title, slug, imgSrc, alt, isOnSale, priceCents, regularCents, inStock, product } of products) {
      let cell = "";

      if (isImage) {
        const saleBadge = isOnSale
          ? `<span class="compare-badge compare-badge--sale">Promo</span>`
          : "";
        cell = `
          <div class="compare-cell compare-cell--image">
            <a href="/produits/${slug}" class="compare-cell__img-wrap">
              <img src="${imgSrc}" alt="${alt}" width="260" height="260" loading="lazy" decoding="async">
              ${saleBadge}
            </a>
          </div>`;
      } else if (isTitle) {
        cell = `
          <div class="compare-cell compare-cell--title">
            <h3><a href="/produits/${slug}">${title}</a></h3>
          </div>`;
      } else if (row.key === "price") {
        const priceHtml   = formatPrice(priceCents / 100);
        const regularHtml = isOnSale ? formatPrice(regularCents / 100) : "";
        cell = `
          <div class="compare-cell">
            <span class="compare-price">${priceHtml}</span>
            ${isOnSale ? `<span class="compare-price--old">${regularHtml}</span>` : ""}
          </div>`;
      } else if (row.key === "description") {
        cell = `<div class="compare-cell compare-cell--desc">${escapeHtml(product.description ?? "—")}</div>`;
      } else if (row.key === "origin") {
        cell = `<div class="compare-cell">${escapeHtml(product.origin ?? "—")}</div>`;
      } else if (row.key === "weight") {
        cell = `<div class="compare-cell">${product.weight ? escapeHtml(product.weight) + " g" : "—"}</div>`;
      } else if (row.key === "stock") {
        cell = inStock
          ? `<div class="compare-cell"><span class="compare-badge compare-badge--stock">En stock</span></div>`
          : `<div class="compare-cell"><span class="compare-badge compare-badge--out">Rupture</span></div>`;
      } else if (isActions) {
        cell = `
          <div class="compare-cell compare-cell--actions">
            <a href="/cart/add/${id}" class="btn btn-fill-out btn-sm add-to-cart ${!inStock ? "disabled" : ""}">
              <svg class="icon" aria-hidden="true"><use href="#icon-cart"></use></svg> Panier
            </a>
            <a href="/compare/remove/${id}" class="compare-card__remove" aria-label="Retirer">
              <svg class="icon" aria-hidden="true"><use href="#icon-trash"></use></svg>
            </a>
          </div>`;
      }

      html += cell;
    }

    html += `</div>`; // .compare-row
  }

  html += `</div>`; // .compare-table

  grid.innerHTML = html;
}
