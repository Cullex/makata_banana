// Tiny fetch wrapper (replaces axios to keep the bundle lean).
function xsrf() {
    const m = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);
    return m ? decodeURIComponent(m[1]) : '';
}

export class ApiError extends Error {
    constructor(status, data) {
        super(data?.message || `Request failed (${status})`);
        this.status = status;
        this.data = data;
        this.errors = data?.errors || {};
    }
}

let onUnauthorized = () => {};
export const setUnauthorizedHandler = (fn) => (onUnauthorized = fn);

async function request(method, url, body, { params, signal } = {}) {
    let full = '/api/' + url.replace(/^\//, '');
    if (params) {
        const qs = new URLSearchParams(Object.entries(params).filter(([, v]) => v !== '' && v != null));
        if ([...qs].length) full += '?' + qs;
    }
    const res = await fetch(full, {
        method,
        signal,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': xsrf(),
            ...(body ? { 'Content-Type': 'application/json' } : {}),
        },
        body: body ? JSON.stringify(body) : undefined,
    });
    const data = res.status === 204 ? null : await res.json().catch(() => null);
    if (!res.ok) {
        if (res.status === 401) onUnauthorized();
        throw new ApiError(res.status, data);
    }
    return data;
}

export const api = {
    get: (url, params, opts) => request('GET', url, null, { ...opts, params }),
    post: (url, body) => request('POST', url, body || {}),
    patch: (url, body) => request('PATCH', url, body || {}),
    delete: (url) => request('DELETE', url),
};
