
import { fetchJson } from "../utils/fetch.js";
import { addFlashMessage, formatPrice } from "../utils/ui.js";

function extractProductIdFromPath(pathname) {
    const parts = pathname.split("/").filter(Boolean); // ["cart","add","123","1"]
    const id = parts[2];
    return id && /^\d+$/.test(id) ? Number(id) : null;
}

function isCartPath(pathname) {
    return pathname.startsWith("/cart/add/") || pathname.startsWith("/cart/remove/");
}

export function initCart() {
    document.addEventListener("click", onDocumentClick, { passive: false });
}

export function initCarrierSelector({ cart, carriers }) {
    const form = document.querySelector(".carrier_form form");
    const select = document.querySelector(".carrier_form select");
    if (!select) return;

    if (Array.isArray(carriers) && carriers.length > 0) {
        renderCarrierOptions(select, carriers, cart);
    }

    form?.addEventListener("submit", (e) => e.preventDefault());

    select.addEventListener("change", async (event) => {
        const id = event.target.value;
        if (!id) return;

        try {
            const result = await fetchJson(`/api/cart/update/carrier/${id}`);

            if (result?.isSuccess && result?.data) {
                displayCart(result.data);
                await updateHeaderCart(result.data);
            } else {
                addFlashMessage(result?.message || "Impossible de mettre à jour le transporteur.", "danger");
            }
        } catch (e) {
            console.error(e);
            addFlashMessage(e?.message || "Erreur transporteur", "danger");
        }
    });
}

function renderCarrierOptions(select, carriers, cart) {
    const currentId = cart?.carrier?.id ?? null;
    select.innerHTML = "";

    for (const carrier of carriers) {
        const option = document.createElement("option");
        option.value = String(carrier.id);
        option.textContent = `${carrier.name} (${formatPrice((carrier.price ?? 0) / 100)})`;
        if (currentId != null && String(carrier.id) === String(currentId)) {
            option.selected = true;
        }
        select.appendChild(option);
    }
}

async function onDocumentClick(event) {
    const link = event.target.closest("a"); // remonte dans le DOM jusqu’au premier <a> parent
    if (!link) return; // Bon pour la perfer, C’est une sortie rapide (early return)

    const isCartLink =
        link.matches(".shop_cart_table tbody a") ||
        link.matches("a.add-to-cart, a.item_remove, a.btn-addtocart") ||
        link.matches(".compare_table .add-to-cart") ||
        link.matches(".wishlist_table .add-to-cart");

    if (!isCartLink) return;

    // Récupère proprement url.pathname, sans query string et sans bricoler avec split
    // ex : link.href = "https://site.fr/cart/add/123/1"
    let url;
    try {
        url = new URL(link.href, window.location.origin);
    } catch {
        return;
    }

    // ex : url.pathname = "/cart/add/123/1"
    if (!isCartPath(url.pathname)) return;

    event.preventDefault();
    await manageCartUrl(url);
}

async function manageCartUrl(url) {
    let cart;
    try {
        cart = await fetchJson(url.toString());
    } catch (e) {
        console.error("Cart request failed:", e);
        addFlashMessage(e?.message || "Erreur panier", "danger");
        return;
    }

    // Optionnel : pour message de stock
    const productId = extractProductIdFromPath(url.pathname);
    let product = null;
    if (productId) {
        try {
            product = await fetchJson(`/product/get/${productId}`);
        } catch {
            // ignore
        }
    }

    const isAdd = url.pathname.startsWith("/cart/add/");
    const isRemove = url.pathname.startsWith("/cart/remove/");

    if (isAdd) {
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

export function displayCart(cart) {
    if (!cart) return;

    const tbody = document.querySelector(".shop_cart_table tbody");
    if (!tbody) return;

    const cartSubTotalHt = document.querySelector(".cart_sub_total_ht_amount");
    const cartTaxe = document.querySelector(".cart_sub_total_taxe_amount");
    const cartShipping = document.querySelector(".cart_shipping_total_amount");
    const cartTotal = document.querySelector(".cart_total_amount");

    tbody.innerHTML = "";

    for (const item of cart.items ?? []) {
        const { product, quantity, sub_total, taxe, sub_total_ht } = item;

        const addButton =
            product?.stock > 0
                ? `<a href="/cart/add/${product.id}/1"><input type="button" value="+" class="plus"></a>`
                : `<div style="text-align:center;align-self:center;">
             <i class="fa fa-ban" title="Stock épuisé" style="font-size:34px;color:#999;"></i>
           </div>`;

        tbody.insertAdjacentHTML(
            "beforeend",
            `
            <tr>
                <td class="product-thumbnail">
                <a><img width="50" alt="product" src="/assets/images/products/${product.image?.[0] ?? ""}"></a>
                </td>
                <td data-title="Product" class="product-title">
                <a>${product.title ?? ""}</a>
                </td>
                <td data-title="Price" class="product-price">
                ${formatPrice((product.soldePrice ?? 0) / 100)}
                </td>
                <td data-title="Quantity" class="product-quantity">
                <div class="quantity">
                    <a href="/cart/remove/${product.id}/1">
                    <input type="button" value="-" class="minus">
                    </a>
                    <input type="text" name="quantity" value="${quantity ?? 0}" title="Qty" size="4" class="qty">
                    ${addButton}
                </div>
                </td>
                <td data-title="Total" class="product-subtotal">${formatPrice((taxe ?? 0) / 100)}</td>
                <td data-title="Total" class="product-subtotal">${formatPrice((sub_total_ht ?? 0) / 100)}</td>
                <td data-title="Total" class="product-subtotal">${formatPrice((sub_total ?? 0) / 100)}</td>
                <td data-title="Remove" class="product-remove">
                <a href="/cart/remove/${product.id}/${quantity ?? 0}">
                    <i class="ti-close"></i>
                </a>
                </td>
            </tr>
            `
        );
    }

    // règle shipping gratuite
    const subTotalTtc = (cart.sub_total ?? 0) / 100;
    if (subTotalTtc > 50 && cart.carrier && typeof cart.carrier.price === "number") {
        cart.carrier.price = 0;
    }

    if (cartSubTotalHt) cartSubTotalHt.textContent = formatPrice((cart.sub_total_ht ?? 0) / 100);
    if (cartTaxe) cartTaxe.textContent = formatPrice((cart.taxe ?? 0) / 100);
    if (cartShipping) cartShipping.textContent = formatPrice((cart.carrier?.price ?? 0) / 100);
    if (cartTotal) cartTotal.textContent = formatPrice(((cart.sub_total ?? 0) + (cart.carrier?.price ?? 0)) / 100);
}

export async function updateHeaderCart(cart = null) {
    if (!cart) {
        try {
            cart = await fetchJson("/cart/get");
        } catch (e) {
            console.error(e);
            return;
        }
    }

    const cartCount = document.querySelector(".cart_count");
    const cartList = document.querySelector(".cart_list");
    const cartPriceHt = document.querySelector(".cart_price_value_ht");
    const cartTaxe = document.querySelector(".cart_taxe_value");
    const cartPriceTtc = document.querySelector(".cart_price_value_ttc");

    if (cartCount) cartCount.textContent = String(cart?.cart_count ?? 0);
    if (cartPriceHt) cartPriceHt.textContent = formatPrice((cart.sub_total_ht ?? 0) / 100);
    if (cartTaxe) cartTaxe.textContent = formatPrice((cart.taxe ?? 0) / 100);
    if (cartPriceTtc) cartPriceTtc.textContent = formatPrice((cart.sub_total ?? 0) / 100);

    if (!cartList) return;

    cartList.innerHTML = "";

    for (const item of cart.items ?? []) {
        const { product, quantity } = item;
        const unit = (product.soldePrice ?? 0) / 100;
        const total = unit * (quantity ?? 0);

        cartList.insertAdjacentHTML(
            "beforeend",
            `
      <li>
        <a href="/cart/remove/${product.id}/${quantity ?? 0}" class="item_remove">
          <i class="ion-close"></i>
        </a>
        <a href="/produits-bio-paris/${product.slug ?? ""}">
          <img width="50" height="50" alt="cart_thumb" src="/assets/images/products/${product.image?.[0] ?? ""}">
          ${product.title ?? ""}
        </a>
        <span class="cart_quantity">
          ${quantity ?? 0} x
          <span class="cart_amount"><span class="price_symbole">${formatPrice(unit)}</span> =</span>
          <span class="cart_amount"><span class="price_symbole">${formatPrice(total)}</span></span>
        </span>
      </li>
      `
        );
    }
}
