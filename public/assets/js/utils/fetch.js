function getCsrfToken() {
  return document
    .querySelector('meta[name="csrf-token-address"]')
    ?.getAttribute("content");
}

export async function fetchJson(input, options = {}) {
  const csrfToken = getCsrfToken();

  const mergedHeaders = {
    "Accept": "application/json",
    ...(csrfToken ? { "X-CSRF-TOKEN": csrfToken } : {}),
    ...(options.headers ?? {}),
  };

  const response = await fetch(input, {
    credentials: "same-origin",
    ...options,
    headers: mergedHeaders, // IMPORTANT: après ...options pour ne pas être écrasé
  });

  let data = null;
  try {
    data = await response.json();
  } catch {}

  if (!response.ok) {
    const message =
      (data && (data.error || data.message)) ||
      `HTTP ${response.status} ${response.statusText}`;
    throw new Error(message);
  }

  return data;
}
