import { fetchJson } from "../utils/fetch.js";
import { addFlashMessage, formatPrice } from "../utils/ui.js";
import { escapeHtml, getThumbFilename } from "../utils/html.js";

export function initWishlist() {
  document.addEventListener("click", onDocumentClick, { passive: false });
}

function isWishlistLink(link) {
  return (
    link.matches(".add-to-wishlist") ||
    link.matches(".wishlist-grid .remove-to-wishlist")
  );
}

function getWishlistAction(pathname) {
  if (pathname.startsWith("/wishlist/add/")) return "add";
  if (pathname.startsWith("/wishlist/remove/")) return "remove";
  return null;
}


async function onDocumentClick(event) {
  const link = event.target.closest("a");
  if (!link || !isWishlistLink(link)) return;

  let url;
  try {
    url = new URL(link.href, window.location.origin);
  } catch {
    return;
  }

  const action = getWishlistAction(url.pathname);
  if (!action) return;

  event.preventDefault();

  await manageWishlistRequest({ url, action });
}

async function manageWishlistRequest({ url, action }) {
  const method = action === "add" ? "POST" : "DELETE";

  let wishlist;
  try {
    wishlist = await fetchJson(url.toString(), { method });
  } catch (e) {
    addFlashMessage(e?.message || "Erreur favoris", "danger");
    return;
  }

  if (wishlist?.message === "Non connecté") {
    window.location.href = "/login";
    return;
  }

  if (!Array.isArray(wishlist)) {
    addFlashMessage("Réponse favoris inattendue", "danger");
    return;
  }

  if (action === "add") addFlashMessage("Ajouté aux favoris !");
  if (action === "remove") addFlashMessage("Supprimé des favoris !");

  displayWishlist(wishlist);
}

export function displayWishlist(wishlist = []) {
  const grid = document.querySelector(".wishlist-grid");
  if (!grid) return;

  if (!Array.isArray(wishlist) || wishlist.length === 0) {
    grid.innerHTML = `
      <div class="col-12 text-center py-5">
        <svg class="icon" aria-hidden="true" style="width:3rem;height:3rem;color:#ddd;"><use href="#icon-heart"></use></svg>
        <p class="mt-3 text-muted">Votre liste de favoris est vide.</p>
        <a href="/" class="btn btn-fill-out mt-2">Découvrir nos miels</a>
      </div>
    `;
    return;
  }

  grid.innerHTML = "";

  for (const product of wishlist) {
    const id = Number(product?.id ?? 0);
    const title = escapeHtml(product?.title ?? "");
    const slug = escapeHtml(product?.slug ?? "");
    const description = escapeHtml(product?.description ?? "");

    const imgFile = product.images?.[0]?.filename
      ? escapeHtml(product.images[0].filename)
      : null;
    const imgThumb = imgFile ? getThumbFilename(imgFile) : null;
    const imgSrc = imgThumb
      ? `/assets/images/products/${imgThumb}`
      : "/assets/images/placeholder.png";
    const alt = escapeHtml(product.images?.[0]?.alt ?? title);

    const isOnSale = !!product.isOnSale;
    const priceCents = Number(isOnSale ? product.soldePrice : product.regularPrice);
    const regularCents = Number(product.regularPrice);
    const priceHtml = formatPrice(priceCents / 100);
    const regularHtml = isOnSale ? formatPrice(regularCents / 100) : "";

    const inStock = Number(product.stock ?? 0) > 0;
    const stockBadge = inStock
      ? `<span class="wishlist-card__badge wishlist-card__badge--stock">En stock</span>`
      : `<span class="wishlist-card__badge wishlist-card__badge--out">Rupture</span>`;

    const saleBadge = isOnSale
      ? `<span class="wishlist-card__badge wishlist-card__badge--sale">Promo</span>`
      : "";

    grid.insertAdjacentHTML(
      "beforeend",
      `
      <div class="col-lg-3 col-md-4 col-sm-6" data-product-id="${id}">
        <div class="wishlist-card">
          <a href="/produits/${slug}" class="wishlist-card__img-wrap">
            <img
              src="${imgSrc}"
              alt="${alt}"
              width="300" height="300"
              loading="lazy"
              decoding="async"
            >
            <div class="wishlist-card__badges">
              ${saleBadge}
              ${stockBadge}
            </div>
          </a>

          <div class="wishlist-card__body">
            <h3 class="wishlist-card__title">
              <a href="/produits/${slug}">${title}</a>
            </h3>

            ${description ? `<p class="wishlist-card__desc">${description}</p>` : ""}

            <div class="wishlist-card__price">
              <span class="wishlist-card__price--current">${priceHtml}</span>
              ${isOnSale ? `<span class="wishlist-card__price--old">${regularHtml}</span>` : ""}
            </div>

            <div class="wishlist-card__actions">
              <a
                href="/cart/add/${id}"
                class="btn btn-fill-out btn-sm add-to-cart ${!inStock ? "disabled" : ""}"
                ${!inStock ? 'aria-disabled="true"' : ""}
              >
                <svg class="icon" aria-hidden="true"><use href="#icon-cart"></use></svg> Panier
              </a>
              <a
                href="/wishlist/remove/${id}"
                class="wishlist-card__remove remove-to-wishlist"
                aria-label="Retirer des favoris"
                title="Retirer des favoris"
              >
                <svg class="icon" aria-hidden="true"><use href="#icon-trash"></use></svg>
              </a>
            </div>
          </div>
        </div>
      </div>
      `
    );
  }
}
