import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

/**
 * Table filters kept in the URL (so they survive reloads and can be shared).
 * Text search is debounced; selects, tabs and sorting apply immediately.
 */
export function useFilters(initial, { only, debounce = 350 } = {}) {
    const [filters, setFilters] = useState(initial);
    const timer = useRef(null);

    useEffect(() => () => window.clearTimeout(timer.current), []);

    function visit(next) {
        const query = Object.fromEntries(
            Object.entries(next).filter(([, value]) => value !== null && value !== undefined && value !== '' && value !== false),
        );

        router.get(window.location.pathname, query, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            ...(only ? { only } : {}),
        });
    }

    function apply(next, { immediate = true } = {}) {
        setFilters(next);
        window.clearTimeout(timer.current);

        if (immediate) {
            visit(next);
        } else {
            timer.current = window.setTimeout(() => visit(next), debounce);
        }
    }

    function set(key, value, options = { immediate: true }) {
        apply({ ...filters, [key]: value }, options);
    }

    function search(value) {
        set('search', value, { immediate: false });
    }

    function reset() {
        const cleared = Object.fromEntries(Object.keys(filters).map((key) => [key, null]));
        apply(cleared);
    }

    const active = Object.values(filters).some((value) => value !== null && value !== undefined && value !== '' && value !== false);

    return { filters, set, apply, search, reset, active };
}
