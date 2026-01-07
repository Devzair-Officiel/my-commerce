
export function formatPrice(price) {
  return Intl.NumberFormat("en-US", { style: "currency", currency: "EUR" }).format(price);
}

export function addFlashMessage(message, status = "success") {
  const container = document.querySelector(".notification");
  if (!container) return;

  container.insertAdjacentHTML(
    "beforeend",
    `<div class="alert alert-${status}" role="alert"></div>`
  );

  // Évite l'injection HTML
  const last = container.lastElementChild;
  if (last) last.textContent = message;

  // Audio (peut échouer selon policy navigateur)
  try {
    const audio = new Audio("/assets/audios/success.wav");
    void audio.play();
  } catch {
    // ignore
  }

  window.setTimeout(() => {
    container.innerHTML = "";
  }, 2000);
}
