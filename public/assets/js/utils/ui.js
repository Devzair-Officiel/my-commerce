import { showFlash } from "./flash.js";

export function formatPrice(price) {
  return Intl.NumberFormat("en-US", { style: "currency", currency: "EUR" }).format(price);
}

export function addFlashMessage(message, status = "success", durationMs = 5000) {
  try {
    const audio = new Audio("/assets/audios/success.wav");
    void audio.play();
  } catch {
    // ignore
  }

  showFlash(message, status, { duration: durationMs });
}
