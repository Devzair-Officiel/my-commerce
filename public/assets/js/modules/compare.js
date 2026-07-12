import { fetchJson } from "../utils/fetch.js";
import { addFlashMessage } from "../utils/ui.js";

function isComparePath(pathname) {
  return pathname.startsWith("/compare/add/") || pathname.startsWith("/compare/remove/");
}

export function initCompare() {
  document.addEventListener("click", onDocumentClick, { passive: false });
}

async function onDocumentClick(event) {
  const link = event.target.closest("a");
  if (!link) return;

  const isCompareLink =
    link.matches(".add-to-compare") ||
    link.matches(".compare-card__remove") ||
    link.matches(".nide-compare__remove");

  if (!isCompareLink) return;

  let url;
  try {
    url = new URL(link.href, window.location.origin);
  } catch {
    return;
  }

  if (!isComparePath(url.pathname)) return;

  event.preventDefault();
  await manageCompareUrl(url);
}

async function manageCompareUrl(url) {
  try {
    await fetchJson(url.toString());
  } catch (e) {
    addFlashMessage(e?.message || "Erreur comparatif", "danger");
    return;
  }

  const isAdd = url.pathname.startsWith("/compare/add/");
  const isRemove = url.pathname.startsWith("/compare/remove/");

  if (isAdd) addFlashMessage("Ajouté au comparatif !");
  if (isRemove) addFlashMessage("Supprimé du comparatif !");

  // Si on est sur la page comparateur, on recharge pour refléter l'état à jour.
  if (window.location.pathname === "/compare") {
    window.location.reload();
  }
}

