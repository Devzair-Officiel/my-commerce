import { displayCompare, initCompare } from "./modules/compare.js";
import { displayWishlist, initWishlist } from "./modules/wishlist.js";
import { initCart, displayCart, initCarrierSelector, updateHeaderCart } from "./modules/cart.js";
import { initCheckoutAddressInline } from "./pages/checkout/address.js";
import { initSearch } from "./modules/search.js";

function safeJsonParse(value, fallback = null) {
  if (!value || typeof value !== "string") return fallback;
  try {
    return JSON.parse(value);
  } catch {
    return fallback;
  }
}

window.addEventListener("load", async () => {
  // Init listeners (1 fois chacun)
  initCart();
  initCompare();
  initWishlist();
  initCheckoutAddressInline();
  initSearch();

  // Compare dataset
  const compareContainer = document.querySelector(".compare_container");
  if (compareContainer) {
    const compare = safeJsonParse(compareContainer.dataset?.compare, []);
    await displayCompare(Array.isArray(compare) ? compare : []);
  }

  // Wishlist dataset
  const wishlistContainer = document.querySelector(".wishlist_content");
  if (wishlistContainer) {
    const wishlist = safeJsonParse(wishlistContainer.dataset?.wishlist, []);
    displayWishlist(Array.isArray(wishlist) ? wishlist : []);
  }

  // Cart dataset + carriers
  const cartContainer = document.querySelector(".cart_content");
  const cart = safeJsonParse(cartContainer?.dataset?.cart, null);
  const carriers = safeJsonParse(cartContainer?.dataset?.carriers, []);

  initCarrierSelector({ cart, carriers });
  displayCart(cart);

  // Si tu veux être sûr que le mini-cart est à jour même sans dataset :
  await updateHeaderCart(cart);
});