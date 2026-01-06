// public/assets/js/wishlist.js

import { fetchJson } from "../utiles/fetch.js";
import { addFlashMessage, formatPrice } from "../utiles/ui.js";

function isWishlistPath(pathname) {
  return pathname.startsWith("/wishlist/add/") || pathname.startsWith("/wishlist/remove/");
}

export function initWishlist() {
  document.addEventListener("click", onDocumentClick, { passive: false });
}

async function onDocumentClick(event) {
  const link = event.target.closest("a");
  if (!link) return;

  const isWishlistLink =
    link.matches(".add-to-wishlist") ||
    link.matches(".wishlist_table .remove-to-wishlist");

  if (!isWishlistLink) return;

  let url;
  try {
    url = new URL(link.href, window.location.origin);
  } catch {
    return;
  }

  if (!isWishlistPath(url.pathname)) return;

  event.preventDefault();
  await manageWishlistUrl(url);
}

async function manageWishlistUrl(url) {
  let wishlist;
  try {
    wishlist = await fetchJson(url.toString());
  } catch (e) {
    console.error(e);
    addFlashMessage(e?.message || "Erreur favoris", "danger");
    return;
  }

  // Ton backend renvoie { message: "Non connecté" } dans certains cas
  if (wishlist?.message === "Non connecté") {
    window.location.href = "/login";
    return;
  }

  const isAdd = url.pathname.startsWith("/wishlist/add/");
  const isRemove = url.pathname.startsWith("/wishlist/remove/");

  if (isAdd) addFlashMessage("Ajouté aux favoris !");
  if (isRemove) addFlashMessage("Supprimé des favoris !", "danger");

  displayWishlist(wishlist);
}

export function displayWishlist(wishlist = null) {
  const tbody = document.querySelector(".wishlist_table tbody");
  if (!tbody) return;

  if (!Array.isArray(wishlist)) {
    // si ton endpoint renvoie autre chose (objet), adapte ici
    return;
  }

  tbody.innerHTML = "";

  for (const product of wishlist) {
    tbody.insertAdjacentHTML(
      "beforeend",
      `
      <tr>
        <td class="product-thumbnail">
          <a href="#">
            <img width="50" height="50" alt="product"
              src="/assets/images/products/${product.image?.[0] ?? ""}">
          </a>
        </td>

        <td data-title="Product" class="product-name">
          <a href="/produits-bio-Paris/${product.slug ?? ""}">
            ${product.title ?? ""}
          </a>
        </td>

        <td data-title="Price" class="product-price">
          ${formatPrice((product.soldePrice ?? 0) / 100)}
        </td>

        <td class="product add-to-cart">
          <a href="/cart/add/${product.id}" class="btn btn-fill-out add-to-cart">
            <i class="icon-basket-loaded"></i> Ajouter au panier
          </a>
        </td>

        <td>
          <a href="/wishlist/remove/${product.id}" class="remove-to-wishlist">
            <i class="ti-close"></i>
          </a>
        </td>
      </tr>
      `
    );
  }
}
