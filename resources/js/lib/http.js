/**
 * Small JSON requests outside Inertia visits (autosave, beacons). Sends Laravel's CSRF token:
 * the XSRF-TOKEN cookie when present (always current), otherwise the page's meta tag.
 */
function csrfHeaders() {
    const cookie = document.cookie.split('; ').find((item) => item.startsWith('XSRF-TOKEN='));

    if (cookie) {
        return { 'X-XSRF-TOKEN': decodeURIComponent(cookie.slice('XSRF-TOKEN='.length)) };
    }

    const meta = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    return meta ? { 'X-CSRF-TOKEN': meta } : {};
}

export async function sendJson(method, url, data = {}, { keepalive = false } = {}) {
    const response = await fetch(url, {
        method: method.toUpperCase(),
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...csrfHeaders(),
        },
        credentials: 'same-origin',
        body: JSON.stringify(data),
        keepalive,
    });

    const body = response.status === 204 ? null : await response.json().catch(() => null);

    return { ok: response.ok, status: response.status, data: body };
}
