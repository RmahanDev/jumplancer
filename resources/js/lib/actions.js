import { router } from '@inertiajs/react';
import { toast } from '../Components/UI/Toaster';

/**
 * A one-click action outside a form (delete, approve, retry ...). The page keeps its scroll
 * position, success messages arrive as flash toasts, and a validation error (e.g. "only an
 * empty category can be deleted") is shown as an error toast instead of being lost.
 */
export function send(method, url, data = {}, options = {}) {
    const { onError, ...rest } = options;

    router.visit(url, {
        method,
        data,
        preserveScroll: true,
        preserveState: true,
        ...rest,
        onError: (errors) => {
            const first = Object.values(errors)[0];

            if (first) {
                toast.error(Array.isArray(first) ? first[0] : first);
            }

            onError?.(errors);
        },
    });
}

export const post = (url, data, options) => send('post', url, data, options);
export const put = (url, data, options) => send('put', url, data, options);
export const destroy = (url, options) => send('delete', url, {}, options);
