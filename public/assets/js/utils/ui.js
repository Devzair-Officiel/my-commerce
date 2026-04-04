import { showFlash } from "./flash.js";

export function formatPrice(price) {
  return Intl.NumberFormat("en-US", { style: "currency", currency: "EUR" }).format(price);
}

export function addFlashMessage(message, status = "success", timeoutMs = 3000) {
 
  // Audio (peut échouer selon policy navigateur)
  try {
    const audio = new Audio("/assets/audios/success.wav");
    void audio.play();
  } catch {
    // ignore
  }
    
    showFlash(message, status, timeoutMs);

}
