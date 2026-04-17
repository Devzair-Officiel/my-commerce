import { fetchJson } from "../utils/fetch.js";
import { addFlashMessage, formatPrice } from "../utils/ui.js";

// ── Helpers ────────────────────────────────────────────────────────────────────

/** Extrait l'id produit depuis /cart/add/{id}/1 ou /cart/remove/{id}/1 */
function extractProductId(pathname) {
    const id = pathname.split("/").filter(Boolean)[2];
    return id && /^\d+$/.test(id) ? Number(id) : null;
}

function isCartPath(pathname) {
    return pathname.startsWith("/cart/add/") || pathname.startsWith("/cart/remove/");
}

function productImageSrc(product) {
    const file = product.image?.[0]?.filename;
    return file ? `/assets/images/products/${file}` : "";
}

function productImageAlt(product) {
    return product.image?.[0]?.alt ?? "";
}

/** Bouton +1 ou icône "stock épuisé" */
function renderAddButton(productId, qty, stock) {
    const canAdd = Number.isFinite(stock) ? qty < stock : true;

    if (canAdd) {
        return `<a href="/cart/add/${productId}/1"><input type="button" value="+" class="plus"></a>`;
    }

    return `<div class="cart_stock_blocked" style="text-align:center;align-self:center;" title="Stock épuisé" aria-label="Stock épuisé">
                <i class="fa fa-ban" style="font-size:28px;color:#999;"></i>
            </div>`;
}

// ── Ligne du tableau panier ────────────────────────────────────────────────────

function renderCartRow(item) {
    const { product, quantity, sub_total, taxe, sub_total_ht } = item;
    const qty   = Number(quantity ?? 0);
    const stock = Number(product?.stock ?? 0);
    const price = (product.isOnSale ? product.soldePrice : product.regularPrice) ?? 0;

    return `
        <tr>
            <td class="product-thumbnail">
                <a href="/produits/${product.slug ?? ""}">
                    <img width="50" alt="${productImageAlt(product)}" src="${productImageSrc(product)}">
                </a>
            </td>
            <td data-title="Produit" class="product-title">
                <a>${product.title ?? ""}</a>
            </td>
            <td data-title="Prix" class="product-price">
                ${formatPrice(price / 100)}
            </td>
            <td data-title="Quantité" class="product-quantity">
                <div class="quantity">
                    <a href="/cart/remove/${product.id}/1">
                        <input type="button" value="-" class="minus">
                    </a>
                    <input type="text" name="quantity" value="${qty}" title="Qty" size="4" class="qty">
                    ${renderAddButton(product.id, qty, stock)}
                </div>
            </td>
            <td data-title="Taxe"     class="product-subtotal">${formatPrice((taxe        ?? 0) / 100)}</td>
            <td data-title="Total HT" class="product-subtotal">${formatPrice((sub_total_ht ?? 0) / 100)}</td>
            <td data-title="Total TTC" class="product-subtotal">${formatPrice((sub_total   ?? 0) / 100)}</td>
            <td data-title="Supprimer" class="product-remove">
                <a href="/cart/remove/${product.id}/${qty}">
                    <i class="ti-close"></i>
                </a>
            </td>
        </tr>
    `;
}

// ── Item mini-panier (header dropdown) ────────────────────────────────────────

function renderMiniCartItem({ product, quantity }) {
    const qty    = quantity ?? 0;
    const canAdd = typeof product.stock === "number" ? qty < product.stock : true;

    return `
        <li class="mini_cart_item">
            <a href="/produits/${product.slug ?? ""}" class="mini_cart_link">
                <img width="50" height="50" alt="${productImageAlt(product)}" src="${productImageSrc(product)}">
                <span class="mini_cart_title">${product.title ?? ""}</span>
            </a>
            <div class="mini_cart_controls">
                <a href="/cart/remove/${product.id}/1" class="mini_cart_minus" aria-label="Diminuer">
                    <button type="button" class="mini_btn" aria-hidden="true">−</button>
                </a>
                <input class="mini_qty" name="quantity" type="text" value="${qty}" readonly aria-label="Quantité">
                ${canAdd
                    ? `<a href="/cart/add/${product.id}/1" class="mini_cart_plus" aria-label="Augmenter">
                           <button type="button" class="mini_btn" aria-hidden="true">+</button>
                       </a>`
                    : `<span class="mini_btn mini_btn--disabled" aria-label="Stock épuisé">+</span>`
                }
                <a href="/cart/remove/${product.id}/${qty}" class="mini_cart_remove item_remove" aria-label="Retirer">
                    <svg width="25" height="25" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path class="remove-icon-bottom" d="M11.5 9v4.25M8.5 9v4.25M5.75 12.2V6h8.5c0 2.421 0 3.779 0 6.2 0 .853 0 1.447-.038 1.91-.037.453-.106.714-.207.911a2.498 2.498 0 0 1-.983 1.017c-.197.1-.458.17-.911.207-.463.037-1.057.038-1.91.038h-.4c-.853 0-1.447 0-1.91-.038-.453-.037-.714-.106-.911-.207a2.498 2.498 0 0 1-.984-1.017c-.1-.197-.17-.458-.207-.911C5.75 13.647 5.75 13.053 5.75 12.2z" stroke="currentColor" stroke-width="var(--icon-stroke-width)" stroke-linecap="round"></path>
                        <path class="remove-icon-top" d="M4.25 6h11.5M8 5.25a2 2 0 1 1 4 0" stroke="currentColor" stroke-width="var(--icon-stroke-width)" stroke-linecap="round" stroke-linejoin="round"></path>
                    </svg>
                </a>
            </div>
        </li>
    `;
}

// ── Gestion des clics (délégation sur document) ───────────────────────────────

export function initCart() {
    document.addEventListener("click", onDocumentClick, { passive: false });
}

async function onDocumentClick(event) {
    const link = event.target.closest("a");
    if (!link) return;

    let url;
    try {
        url = new URL(link.href, window.location.origin);
    } catch {
        return;
    }

    if (!isCartPath(url.pathname)) return;

    event.preventDefault();
    await handleCartRequest(url);
}

async function handleCartRequest(url) {
    let cart;
    try {
        cart = await fetchJson(url.toString());
    } catch (e) {
        console.error("[Cart] Erreur requête :", e);
        addFlashMessage(e?.message ?? "Erreur panier", "danger");
        return;
    }

    const isAdd    = url.pathname.startsWith("/cart/add/");
    const isRemove = url.pathname.startsWith("/cart/remove/");

    if (isAdd) {
        // Vérifie le stock réel du produit avant d'afficher le message
        const productId = extractProductId(url.pathname);
        const product   = productId ? await fetchJson(`/product/get/${productId}`).catch(() => null) : null;

        if (product && typeof product.stock === "number" && product.stock <= 0 && cart?.error) {
            addFlashMessage(cart.error, "danger");
        } else {
            addFlashMessage("Ajouté au panier !");
        }
    } else if (isRemove) {
        addFlashMessage("Supprimé du panier !", "danger");
    }

    displayCart(cart);
    await updateHeaderCart(cart);
}

// ── Affichage ─────────────────────────────────────────────────────────────────

/** Met à jour le tableau panier (page /cart) */
export function displayCart(cart) {
    if (!cart) return;

    const tbody = document.querySelector(".shop_cart_table tbody");
    if (!tbody) return;

    tbody.innerHTML = (cart.items ?? []).map(renderCartRow).join("");

    setText(".cart_sub_total_ht_amount", formatPrice((cart.sub_total_ht ?? 0) / 100));
    setText(".cart_sub_total_taxe_amount", formatPrice((cart.taxe ?? 0) / 100));
    setText(".cart_total_amount", formatPrice((cart.sub_total ?? 0) / 100));
}

/** Met à jour le mini-panier dans le header */
export async function updateHeaderCart(cart = null) {
    if (!cart) {
        try {
            cart = await fetchJson("/cart/get");
        } catch (e) {
            console.error("[Cart] Impossible de charger le panier header :", e);
            return;
        }
    }

    setText(".cart_count", String(cart?.cart_count ?? 0));
    setText(".cart_price_value_ht",  formatPrice((cart.sub_total_ht ?? 0) / 100));
    setText(".cart_taxe_value",       formatPrice((cart.taxe         ?? 0) / 100));
    setText(".cart_price_value_ttc",  formatPrice((cart.sub_total    ?? 0) / 100));

    const cartList = document.querySelector(".cart_list");
    if (!cartList) return;

    cartList.innerHTML = (cart.items ?? []).map(renderMiniCartItem).join("");
}

// ── Utilitaire local ──────────────────────────────────────────────────────────

function setText(selector, value) {
    const el = document.querySelector(selector);
    if (el) el.textContent = value;
}
