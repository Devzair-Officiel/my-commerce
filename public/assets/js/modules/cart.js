
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

    // const isCartLink =
    //     link.matches(".shop_cart_table tbody a") ||
    //     link.matches("a.add-to-cart, a.item_remove, a.btn-addtocart") ||
    //     link.matches(".compare_table .add-to-cart") ||
    //     link.matches(".wishlist_table .add-to-cart");
    //     link.matches(".cart_dropdown a.mini_cart_plus, .cart_dropdown a.mini_cart_minus");


    // if (!isCartLink) return;

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

        const stock = Number(product?.stock ?? 0);
        const qty = Number(quantity ?? 0);

        const firstImage = product.image?.[0];
        const src = firstImage?.filename ? `/assets/images/products/${firstImage.filename}` : "";
        const alt = firstImage?.alt ?? "";

        // si stock non numérique, on autorise (mais le serveur bloquera)
        const canAdd = Number.isFinite(stock) ? qty < stock : true;

        const addButton = canAdd
            ? `<a href="/cart/add/${product.id}/1"><input type="button" value="+" class="plus"></a>`
            : `<div class="cart_stock_blocked" style="text-align:center;align-self:center;" title="Stock épuisé" aria-label="Stock épuisé">
            <i class="fa fa-ban" style="font-size:28px;color:#999;"></i>
            </div>`;


        tbody.insertAdjacentHTML(
            "beforeend",
            `
            <tr>
                <td class="product-thumbnail">
                    <a href="/product/${product.slug ?? ""}" >
                        <img width="50" alt="${alt}" src="${src}">
                    </a>
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

        const firstImage = product.image?.[0];
        const src = firstImage?.filename ? `/assets/images/products/${firstImage.filename}` : "";
        const alt = firstImage?.alt ?? "";

        const canAdd = (typeof product.stock === "number")
            ? (quantity ?? 0) < product.stock
            : true; // si stock non fourni, on autorise (à valider côté backend)

        cartList.insertAdjacentHTML(
            "beforeend",
            `
                <li class="mini_cart_item">
                    <a href="/product/${product.slug ?? ""}" class="mini_cart_link">
                        <img width="50" height="50" alt="${alt}" src="${src}">
                        <span class="mini_cart_title">${product.title ?? ""}</span>
                    </a>

                    <div class="mini_cart_controls">
                        <a href="/cart/remove/${product.id}/1" class="mini_cart_minus" aria-label="Diminuer">
                            <button type="button" class="mini_btn" aria-hidden="true">−</button>
                        </a>

                        <input class="mini_qty" name="quantity" type="text" value="${quantity ?? 0}" readonly>

                        <a href="/cart/add/${product.id}/1" class="mini_cart_plus" aria-label="Augmenter">
                            <button type="button" class="mini_btn" aria-hidden="true">+</button>
                        </a>
                    

                        <a href="/cart/remove/${product.id}/${quantity ?? 0}" class="mini_cart_remove item_remove" aria-label="Retirer">
                            <svg width="25" height="25" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path class="remove-icon-bottom" d="M11.5 9v4.25M8.5 9v4.25M5.75 12.2V6h8.5c0 2.421 0 3.779 0 6.2 0 .853 0 1.447-.038 1.91-.037.453-.106.714-.207.911a2.498 2.498 0 0 1-.983 1.017c-.197.1-.458.17-.911.207-.463.037-1.057.038-1.91.038h-.4c-.853 0-1.447 0-1.91-.038-.453-.037-.714-.106-.911-.207a2.498 2.498 0 0 1-.984-1.017c-.1-.197-.17-.458-.207-.911C5.75 13.647 5.75 13.053 5.75 12.2z" stroke="currentColor" stroke-width="var(--icon-stroke-width)" stroke-linecap="round"></path>
                                <path class="remove-icon-top" d="M4.25 6h11.5M8 5.25a2 2 0 1 1 4 0" stroke="currentColor" stroke-width="var(--icon-stroke-width)" stroke-linecap="round" stroke-linejoin="round"></path>
                            </svg>
                        </a>
                    </div>
                </li>
            `
        );

    }
}
