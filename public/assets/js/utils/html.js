/**
 * Escape a value for safe insertion into HTML.
 * @param {*} value
 * @returns {string}
 */
export function escapeHtml(value) {
  const str = String(value ?? "");
  return str
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#039;");
}

/**
 * Derive the thumbnail filename from a full filename.
 * e.g. "photo.jpg" → "photo-thumb.jpg"
 * @param {string} filename
 * @returns {string}
 */
export function getThumbFilename(filename) {
  return filename.replace(/(\.[^.]+)$/, "-thumb$1");
}
