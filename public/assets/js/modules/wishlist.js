import { fetchJson } from "../utils/fetch.js";
import { addFlashMessage, formatPrice } from "../utils/ui.js";

export function initWishlist() {
  document.addEventListener("click", onDocumentClick, { passive: false });
}

function isWishlistLink(link) {
  return (
    link.matches(".add-to-wishlist") ||
    link.matches(".wishlist_table .remove-to-wishlist")
  );
}

function getWishlistAction(pathname) {
  if (pathname.startsWith("/wishlist/add/")) return "add";
  if (pathname.startsWith("/wishlist/remove/")) return "remove";
  return null;
}

function escapeHtml(value) {
  const str = String(value ?? "");
  return str
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#039;");
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

  // ✅ Appelle le backend avec la bonne méthode HTTP
  await manageWishlistRequest({ url, action });
}

async function manageWishlistRequest({ url, action }) {
  const method = action === "add" ? "POST" : "DELETE";

  let wishlist;
  try {
    wishlist = await fetchJson(url.toString(), { method });
  } catch (e) {
    console.error(e);

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
  if (action === "remove") addFlashMessage("Supprimé des favoris !", "danger");

  displayWishlist(wishlist);
}

export function displayWishlist(wishlist = []) {
  const tbody = document.querySelector(".wishlist_table tbody");
  if (!tbody) return;

  if (!Array.isArray(wishlist)) return;

  // Si tu veux afficher un message “Aucun favori”
  if (wishlist.length === 0) {
    tbody.innerHTML = `
      <tr>
        <td colspan="5" class="text-center">
          Aucun favori pour le moment.
        </td>
      </tr>
    `;
    return;
  }

  tbody.innerHTML = "";

  for (const product of wishlist) {
    const id = Number(product?.id ?? 0);
    const title = escapeHtml(product?.title ?? "");
    const slug = escapeHtml(product?.slug ?? "");

    // image : si ton API renvoie un tableau de filenames
    const imgFile = escapeHtml(product?.image?.[0] ?? "");
    const imgSrc = imgFile ? `/assets/images/products/${imgFile}` : "";

    // prix : ton back semble renvoyer des centimes
    const priceCents = Number(product?.soldePrice ?? 0);
    const priceHtml = formatPrice(priceCents / 100);

    // 🔒 On évite d’injecter du HTML non échappé (title/slug/image)
    tbody.insertAdjacentHTML(
      "beforeend",
      `
      <tr>
        <td class="product-thumbnail">
          <a href="/produits-bio-Paris/${slug}">
            ${
              imgSrc
                ? `<img width="50" height="50" alt="product" src="${imgSrc}">`
                : ""
            }
          </a>
        </td>

        <td data-title="Product" class="product-name">
          <a href="/produits-bio-Paris/${slug}">${title}</a>
        </td>

        <td data-title="Price" class="product-price">
          ${priceHtml}
        </td>

        <td class="product add-to-cart">
          <a href="/cart/add/${id}" class="btn btn-fill-out add-to-cart">
            <i class="icon-basket-loaded"></i> Ajouter au panier
          </a>
        </td>

        <td>
          <a href="/wishlist/remove/${id}" class="remove-to-wishlist">
            <i class="ti-close"></i>
          </a>
        </td>
      </tr>
      `
    );
  }
}
