function getCsrfToken(metaName) {
    return document
        .querySelector(`meta[name="${metaName}"]`)
        ?.getAttribute("content") ?? null;
}

export async function fetchJson(input, options = {}) {
    const {
        csrfMetaName = null,
        headers = {},
        ...fetchOptions
    } = options;

    const csrfToken = csrfMetaName ? getCsrfToken(csrfMetaName) : null;

    const mergedHeaders = {
        Accept: "application/json",
        ...headers,
    };

    if (csrfToken) {
        mergedHeaders["X-CSRF-TOKEN"] = csrfToken;
    }

    const response = await fetch(input, {
        credentials: "same-origin",
        ...fetchOptions,
        headers: mergedHeaders,
    });

    let data = null;

    try {
        data = await response.json();
    } catch {
        data = null;
    }

    if (!response.ok) {
        const message =
            (data && (data.error || data.message)) ||
            `HTTP ${response.status} ${response.statusText}`;

        throw new Error(message);
    }

    return data;
}
