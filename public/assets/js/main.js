import { displayCompare, displayCart, displayWishlist, formatPrice } from './library.js';

const safeJsonParse = (value, fallback = null) => {
  if (!value || typeof value !== "string") return fallback;
  try { return JSON.parse(value); } catch { return fallback; }
};

window.addEventListener("load", () => {
  // compare
  let mainContent = document.querySelector('.compare_container');
  const compare = safeJsonParse(mainContent?.dataset?.compare, null);
  displayCompare(compare);

  // wishlist
  mainContent = document.querySelector('.wishlist_content');
  const wishlist = safeJsonParse(mainContent?.dataset?.wishlist, null);
  displayWishlist(wishlist);

  // cart
  mainContent = document.querySelector('.cart_content');
  const cart = safeJsonParse(mainContent?.dataset?.cart, null);

  const carriers = safeJsonParse(mainContent?.dataset?.carriers, []);
  const form = document.querySelector(".carrier_form form");
  const select = document.querySelector(".carrier_form select");

  if (cart && select && Array.isArray(carriers)) {
    select.innerHTML = "";
    carriers.forEach((carrier) => {
      const selected = carrier.id == cart?.carrier?.id ? "selected" : "";
      select.innerHTML += `
        <option value="${carrier.id}" ${selected}>
          ${carrier.name} (${formatPrice(carrier.price / 100)})
        </option>
      `;
    });
  }

  const handleChange = async (event) => {
    event.preventDefault();
    const id = event.target.value;
    if (!id) return;

    const response = await fetch('/api/cart/update/carrier/' + id);
    const result = await response.json();

    if (result.isSuccess) {
      displayCart(result.data);
    }
  };

  form?.addEventListener('submit', (e) => e.preventDefault());
  select?.addEventListener('change', handleChange);

  displayCart(cart);
});
