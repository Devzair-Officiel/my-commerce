import { initCompare } from "./modules/compare.js";
import { displayWishlist, initWishlist } from "./modules/wishlist.js";
import { initCart, displayCart, updateHeaderCart } from "./modules/cart.js";
import { initSearch } from "./modules/search.js";
import { initCookieConsent, reopenCookieConsent } from "./modules/cookie-consent.js";

function safeJsonParse(value, fallback = null) {
  if (!value || typeof value !== "string") return fallback;
  try {
    return JSON.parse(value);
  } catch {
    return fallback;
  }
}

// Exposer globalement pour le lien "Gérer mes cookies" du footer
window.reopenCookieConsent = reopenCookieConsent;

// ── Drawer mobile ─────────────────────────────────────────────────────────────
function initMobileDrawer() {
  const burger   = document.getElementById("nideBurger");
  const drawer   = document.getElementById("nideDrawer");
  const overlay  = document.getElementById("nideOverlay");
  const closeBtn = document.getElementById("nideDrawerClose");
  if (!burger || !drawer || !overlay) return;

  // Déplacer hors du header pour éviter tout conflit de stacking context
  document.body.appendChild(overlay);
  document.body.appendChild(drawer);

  function openDrawer() {
    drawer.classList.add("is-open");
    overlay.classList.add("is-open");
    burger.classList.add("is-open");
    burger.setAttribute("aria-expanded", "true");
    drawer.setAttribute("aria-hidden", "false");
    document.body.style.overflow = "hidden";
  }

  function closeDrawer() {
    drawer.classList.remove("is-open");
    overlay.classList.remove("is-open");
    burger.classList.remove("is-open");
    burger.setAttribute("aria-expanded", "false");
    drawer.setAttribute("aria-hidden", "true");
    document.body.style.overflow = "";
  }

  burger.addEventListener("click", () =>
    drawer.classList.contains("is-open") ? closeDrawer() : openDrawer()
  );
  overlay.addEventListener("click", closeDrawer);
  if (closeBtn) closeBtn.addEventListener("click", closeDrawer);
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape" && drawer.classList.contains("is-open")) closeDrawer();
  });

  // Sous-menus accordéon
  drawer.querySelectorAll(".nide-nav-drawer__toggle").forEach((btn) => {
    const sub = btn.nextElementSibling;
    if (!sub) return;
    btn.addEventListener("click", () => {
      const isOpen = btn.getAttribute("aria-expanded") === "true";
      btn.setAttribute("aria-expanded", isOpen ? "false" : "true");
      isOpen ? sub.setAttribute("hidden", "") : sub.removeAttribute("hidden");
    });
  });
}

window.addEventListener("load", async () => {
  initMobileDrawer();
  initCart();
  initCompare();
  initWishlist();
  initSearch();
  initCookieConsent();

  // Wishlist dataset
  const wishlistContainer = document.querySelector(".wishlist_content");
  if (wishlistContainer) {
    const wishlist = safeJsonParse(wishlistContainer.dataset?.wishlist, []);
    displayWishlist(Array.isArray(wishlist) ? wishlist : []);
  }

  // Cart dataset
  const cartContainer = document.querySelector(".cart_content, .nide-cart-page");
  const cart = safeJsonParse(cartContainer?.dataset?.cart, null);

  displayCart(cart);
  await updateHeaderCart(cart);
});
