// public/assets/js/compare.js

import { fetchJson } from "../utils/fetch.js";
import { addFlashMessage, formatPrice } from "../utils/ui.js";

function isComparePath(pathname) {
  return pathname.startsWith("/compare/add/") || pathname.startsWith("/compare/remove/");
}

export function initCompare() {
  document.addEventListener("click", onDocumentClick, { passive: false });
}

async function onDocumentClick(event) {
  const link = event.target.closest("a");
  if (!link) return;

  // Tes sélecteurs existants
  const isCompareLink =
    link.matches(".add-to-compare") ||
    link.matches(".compare_table .remove_compare_item");

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
    compare = await fetchJson(url.toString()); // ton endpoint retourne probablement la liste
  } catch (e) {
    console.error(e);
    addFlashMessage(e?.message || "Erreur comparatif", "danger");
    return;
  }

  const isAdd = url.pathname.startsWith("/compare/add/");
  const isRemove = url.pathname.startsWith("/compare/remove/");

  if (isAdd) addFlashMessage("Ajouté au comparatif !");
  if (isRemove) addFlashMessage("Supprimé du comparatif !", "danger");

  await displayCompare(compare);
}

export async function displayCompare(compare = null) {
  const tbody = document.querySelector("table.compare_table tbody");
  if (!tbody) return;

  if (!compare) {
    try {
      compare = await fetchJson("/compare/get");
    } catch (e) {
      console.error(e);
      return;
    }
  }

  const imageRow = tbody.querySelector("tr.pr_image");
  const titleRow = tbody.querySelector("tr.pr_title");
  const priceRow = tbody.querySelector("tr.pr_price");
  const addToCartRow = tbody.querySelector("tr.pr_add_to_cart");
  const removeRow = tbody.querySelector("tr.pr_remove");

  if (!imageRow || !titleRow || !priceRow || !addToCartRow || !removeRow) return;

  imageRow.innerHTML = "";
  titleRow.innerHTML = "";
  priceRow.innerHTML = "";
  addToCartRow.innerHTML = "";
  removeRow.innerHTML = "";

  for (const product of compare ?? []) {
    const firstImage = product.images?.[0];
    const src = firstImage?.filename ? `/assets/images/products/${firstImage.filename}` : "";
    const alt = firstImage?.alt ?? "";
    
    imageRow.insertAdjacentHTML(
      "beforeend",
      `
      <td class="row_img">
        <img src="${src}" width="500" alt="${alt}">
      </td>
      `
    );

    titleRow.insertAdjacentHTML(
      "beforeend",
      `
      <td class="product_name">
        <a href="/products/${product.slug ?? ""}">${product.title ?? ""}</a>
      </td>
      `
    );

    priceRow.insertAdjacentHTML(
      "beforeend",
      `
      <td class="product_price">
        <span class="price">${formatPrice((product.soldePrice ?? 0) / 100)}</span>
      </td>
      `
    );

    addToCartRow.insertAdjacentHTML(
      "beforeend",
      `
      <td class="row_btn">
        <a href="/cart/add/${product.id}" class="btn btn-fill-out add-to-cart">
          <i class="icon-basket-loaded"></i> Ajouter au panier
        </a>
      </td>
      `
    );

    removeRow.insertAdjacentHTML(
      "beforeend",
      `
      <td class="row_remove">
        <a href="/compare/remove/${product.id}" class="remove_compare_item">
          <span>Retirer de la liste </span> <i class="fa fa-times"></i>
        </a>
      </td>
      `
    );
  }
}
