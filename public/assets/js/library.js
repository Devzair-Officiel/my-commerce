export const formatPrice = (price) => {
    return Intl.NumberFormat('en-US', { style: 'currency', currency: 'EUR' })
        .format(price);
}

export const addFlashMessage = (message, status = "success") => {
    const container = document.querySelector(".notification");
    if (!container) {
        console.warn("Zone .notification absente du DOM.");
        return;
    }

    const text = `
        <div class="alert alert-${status}" role="alert">
            ${message}
        </div>
    `;

    const audio = new Audio("/assets/audios/success.wav");
    audio.play();

    container.innerHTML += text;

    setTimeout(() => {
        container.innerHTML = "";
    }, 2000);
};


export const fetchData = async (requestUrl) => {
    let response = await fetch(requestUrl)
    return await response.json()
}


export const manageCartLink = async (event) => {
    event.preventDefault();

    let link = event.target.href ? event.target : event.target.parentNode
    let requestUrl = link.href
    const cart = await fetchData(requestUrl)

    let productId = requestUrl.split('/')[5];
    let product = null;
    
    try {
    product = await fetchData("/product/get/" + productId);
    if (!product) throw new Error("Produit introuvable");
    } catch (e) {
    console.error("Erreur lors de la récupération du produit :", e);
    }

    if (requestUrl.search('/cart/add/') != -1 && product.stock > 0) {
        // add to cart
        if (product) {
            addFlashMessage(`Ajouté au panier !`)
        } else {
            addFlashMessage("Ajouté au panier !")
        }
    } 
    if (requestUrl.search('/cart/remove/') != -1) {
        // remove from cart
        if (product) {
            addFlashMessage(`Supprimé du panier !`, "danger")
        } else {
            addFlashMessage("Produit supprimé du panier !", "danger")
        }
    }
    if(product.stock == 0 && cart.error) {
        addFlashMessage(cart.error)
    }
    displayCart(cart)
    updateHeaderCart()
}

export const manageCompareLink = async (event) => {
    event.preventDefault();
    console.log("manageCompareLink");
    let link = event.target.href ? event.target : event.target.parentNode
    let requestUrl = link.href     
    const compare = await fetchData(requestUrl)

    let productId = requestUrl.split('/')[5];
    let product = await fetchData("/product/get/" + productId)

    if (requestUrl.search('/compare/add/') != -1) {
        // add to list
        if (product) {
            addFlashMessage(`Ajouté au comparatif !`)
        } else {
            addFlashMessage("Pas de produit !")
        }
    }
    if (requestUrl.search('/compare/remove/') != -1) {
        // remove from list
        if (product) {
            addFlashMessage(`Supprimé du comparatif !`, "danger")
        } else {
            addFlashMessage("Pas de produit !", "danger")
        }
    }

    displayCompare()
}

export const manageWishListLink = async (event) => {
    event.preventDefault();
    console.log("manageWishListLink");
    let link = event.target.href ? event.target : event.target.parentNode
    let requestUrl = link.href 

    const wishlist = await fetchData(requestUrl)
    console.log(wishlist);

    // Si l'utilisateur n'est pas connecté
    if(wishlist.message === "Non connecté") {
        return location.href = "/login";
    }

    let productId = requestUrl.split('/')[5];
    let product = await fetchData("/product/get/" + productId)

    if (requestUrl.search('/wishlist/add/') != -1) {
        // add to cart
        if (product) {
            addFlashMessage(`Ajouté au favoris !`)
        } else {
            addFlashMessage("Ajouté au favoris !")
        }
    }
    if (requestUrl.search('/wishlist/remove/') != -1) {
        // remove from cart
        if (product) {
            addFlashMessage(`Supprimé des favoris !`, "danger")
        } else {
            addFlashMessage("Supprimé des favoris !", "danger")
        }
    }


    displayWishlist(wishlist)
}


export const displayCompare = async (compare = null) => {
    
    let tbody = document.querySelector('table.compare_table tbody')
    if (tbody) {

        if (!compare) {
            compare = await fetchData("/compare/get")
        }

        if (compare) {
            let imageContainer = document.querySelector('table.compare_table tbody tr.pr_image')
            imageContainer.innerHTML = ""
            let nameContainer = document.querySelector('table.compare_table tbody tr.pr_title')
            nameContainer.innerHTML = ""
            let priceContainer = document.querySelector('table.compare_table tbody tr.pr_price')
            priceContainer.innerHTML = ""
            let addToCart = document.querySelector('table.compare_table tbody tr.pr_add_to_cart')
            addToCart.innerHTML = ""
            let romoveFromCart = document.querySelector('table.compare_table tbody tr.pr_remove')
            romoveFromCart.innerHTML = ""
            compare.forEach((product) => {
                imageContainer.innerHTML += `
                <td class="row_img">
                <img src="/assets/images/products/${product.image[0]}" width="500" alt="compare image ${product.image[0].alt}">
                </td> 
                `
                nameContainer.innerHTML += `
                <td class="product_name">
                    <a href="/produits-bio-paris/${product.slug}">${product.title}</a>
                </td>
                `
                priceContainer.innerHTML += `
                <td class="product_price">
                <span class="price">${formatPrice(product.soldePrice / 100)}</span></td>
                `
                addToCart.innerHTML += `
                <td class="row_btn">
                <a href="/cart/add/${product.id}" 
                class="btn btn-fill-out add-to-cart"><i
                class="icon-basket-loaded"></i> Ajouter au panier</a>
                </td>
                `
                romoveFromCart.innerHTML += `
                <td class="row_remove">
                    <a href="/compare/remove/${product.id}" class="remove_compare_item">
                        <span>Retirer de la liste </span> <i class="fa fa-times"></i>
                    </a>
                </td>
                `
                
            });
        }
    }
    addCompareEventListener()
}

export const addCompareEventListener = () => {
    const links = document.querySelectorAll(".add-to-compare, .compare_table .remove_compare_item")
    console.log({ links });
    links.forEach(link => {
        link.addEventListener("click", manageCompareLink)
    });

    const cartLinks = document.querySelectorAll(".compare_table .add-to-cart");
    cartLinks.forEach(link => {
        link.addEventListener("click", manageCartLink);
    });
}

export const addWishListEventListenerToLink = () => {
    const links = document.querySelectorAll(".add-to-wishlist, .wishlist_table .remove-to-wishlist")

    links.forEach(link => {
        link.addEventListener("click", manageWishListLink)
    });

    const cartLinks = document.querySelectorAll(".wishlist_table .add-to-cart");
    cartLinks.forEach(link => {
        link.addEventListener("click", manageCartLink);
    });
}

export const addCartEventListenerToLink = () => {
    let links = document.querySelectorAll('.shop_cart_table tbody a')
    links.forEach((link) => {
        link.addEventListener("click", manageCartLink)
    })

    let add_to_cart_links = document.querySelectorAll('a.add-to-cart, a.item_remove,  a.btn-addtocart')
    add_to_cart_links.forEach((link) => {
        link.addEventListener("click", manageCartLink)
    })
}

export const displayCart = (cart = null) => {
    updateHeaderCart(cart)
    addCartEventListenerToLink()
    if (!cart) {
        return
    }

    let tbody = document.querySelector('.shop_cart_table tbody')
    let cart_sub_total_ht_amount = document.querySelector('.cart_sub_total_ht_amount')
    let cart_sub_total_taxe_amount = document.querySelector('.cart_sub_total_taxe_amount')
    let cart_shipping_total_amount = document.querySelector('.cart_shipping_total_amount')
    let cart_total_amount = document.querySelector('.cart_total_amount')   
    
    if (tbody) {
        tbody.innerHTML = ""
        cart.items.forEach((item) => {
            let { product, quantity, sub_total, taxe, sub_total_ht } = item;
            let addButton = product.stock > 0 ?
                `<a href="/cart/add/${product.id}/1">
                    <input type="button" value="+" class="plus">
                </a>` :
                `<div style="text-align: center; width: 100%; align-self: center;">
                    <i class="fa fa-ban" title="Stock épuisé" style="font-size: 34px; color: #999;"></i>
                </div>`; 
            let content = `
                <tr>
                    <td class="product-thumbnail"><a><img width="50" alt="${product.image[0].alt}"
                        src="/assets/images/products/${product.image[0]}"></a>
                    </td>
                    <td data-title="Product" class="product-title">
                        <a>${product.title}</a>
                    </td>
                    <td data-title="Price" class="product-price">
                        ${formatPrice(product.soldePrice / 100)}
                    </td>
                    <td data-title="Quantity" class="product-quantity">
                        <div class="quantity">
                            <a href="/cart/remove/${product.id}/1">
                                <input type="button" value="-" class="minus">
                            </a>
                            <input type="text" name="quantity" value="${quantity}" title="Qty" size="4" class="qty">
                            ${addButton}
                        </div>
                    </td>
                    <td data-title="Total" class="product-subtotal">
                        ${formatPrice(taxe / 100)} 
                    </td>
                    <td data-title="Total" class="product-subtotal">
                        ${formatPrice(sub_total_ht / 100)} 
                    </td>
                    <td data-title="Total" class="product-subtotal">
                        ${formatPrice(sub_total / 100)} 
                    </td>
                    <td data-title="Remove" class="product-remove">
                        <a href="/cart/remove/${product.id}/${item.quantity}">
                            <i class="ti-close"></i>
                        </a>
                    </td>
                </tr>
             `
            tbody.innerHTML += content
        });

        if(cart.sub_total/100 > 50) {
            cart.carrier.price = 0;
        }

        cart_sub_total_ht_amount.innerHTML = formatPrice(cart.sub_total_ht / 100)
        cart_sub_total_taxe_amount.innerHTML = formatPrice(cart.taxe / 100)
        cart_shipping_total_amount.innerHTML = formatPrice(cart.carrier.price/100)
        cart_total_amount.innerHTML = formatPrice( (cart.sub_total + cart.carrier.price) / 100)

    }
    addCartEventListenerToLink()

}

export const displayWishlist = (wishlist = null) => {

    addWishListEventListenerToLink()
    if (!wishlist) {
        return;
    }

    let tbody = document.querySelector('.wishlist_table tbody')

    if (tbody) {
        tbody.innerHTML = ""
        wishlist.forEach((product) => {
            let content = `
            <tr>
            <td class="product-thumbnail">
                <a href="#">
                    <img width="50" height="50" alt="${product.image[0].alt}"
                        src="/assets/images/products/${product.image[0]}">
                </a>
            </td>
            <td data-title="Product" class="product-name"><a href="/produits-bio-Paris/${product.slug}">
                    ${product.title}
                </a>
            </td>
            <td data-title="Price" class="product-price">
                ${formatPrice(product.soldePrice / 100)}
            </td>
            <td class="product add-to-cart">
                <a href="/cart/add/${product.id}" class="btn btn-fill-out">
                    <i class="icon-basket-loaded"></i> Ajouter au panier
                </a>
            </td>
            <td >
                <a href="/wishlist/remove/${product.id}" class="remove-to-wishlist">
                    <i class="ti-close"></i>
                </a>
            </td>
        </tr>
             `
            tbody.innerHTML += content
        });


    }
    addWishListEventListenerToLink()

}

export const updateHeaderCart = async (cart = null) => {
    let cart_count = document.querySelector(".cart_count")
    let cart_list = document.querySelector(".cart_list")
    let cart_price_value_ht = document.querySelector(".cart_price_value_ht")
    let cart_taxe_value = document.querySelector(".cart_taxe_value")
    let cart_price_value_ttc = document.querySelector(".cart_price_value_ttc")
    if (!cart) {
        // cart not found
        cart = await fetchData("/cart/get")
    }

    // cart data found
    cart_count ? cart_count.innerHTML = cart?.cart_count : null
    cart_price_value_ht ? cart_price_value_ht.innerHTML = isNaN(cart.sub_total_ht) ? '' : formatPrice(cart.sub_total_ht / 100) : null
    cart_taxe_value ? cart_taxe_value.innerHTML = isNaN(cart.taxe) ? '' : formatPrice(cart.taxe / 100) : null
    cart_price_value_ttc ? cart_price_value_ttc.innerHTML = formatPrice(cart.sub_total / 100) : null

    if (cart_list) {
        cart_list.innerHTML = ""
        cart.items.forEach(item => {
            let { product, quantity, sub_total } = item
            cart_list.innerHTML += `
                <li>
                <a href="/cart/remove/${product.id}/${quantity}" class="item_remove">
                    <i class="ion-close"></i>
                </a>
                <a href="/produits-bio-paris/${product.slug}">
                    <img width="50" height="50" alt="${product.image[0].alt}" src="/assets/images/products/${product.image[0]}">
                    ${product.title}
                </a>
                <span class="cart_quantity"> ${quantity} x
                    <span class="cart_amount">
                        <span class="price_symbole">${formatPrice(product.soldePrice / 100)}</span> =
                    </span>
                    <span class="cart_amount">
                        <span class="price_symbole">${formatPrice(product.soldePrice * quantity / 100)}</span>
                    </span>
                </span>
            </li>
                `
        })

    }

    addCartEventListenerToLink()

}