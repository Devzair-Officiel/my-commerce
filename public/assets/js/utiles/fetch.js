/**
 * Fetch JSON with basic error handling.
 * Throws an Error on non-2xx responses or invalid JSON.
 */
export async function fetchJson(input, options = {}) {
  const response = await fetch(input, {
    credentials: "same-origin",
    headers: {
      "Accept": "application/json",
      ...(options.headers ?? {}),
    },
    ...options,
  });

  // Try to read JSON even on errors (often your API returns {error: "..."}).
  let data = null;
  try {
    data = await response.json();
  } catch {
    // ignore JSON parse error
  }

  if (!response.ok) {
    const message =
      (data && (data.error || data.message)) ||
      `HTTP ${response.status} ${response.statusText}`;
    throw new Error(message);
  }

  return data;
}
