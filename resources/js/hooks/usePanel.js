import { usePage } from '@inertiajs/react';

/** The signed-in user as shared by HandleInertiaRequests. */
export function useAuthUser() {
    return usePage().props.auth?.user ?? null;
}

/** Staff permission check on the client (the server enforces it again on every request). */
export function useCan() {
    const user = useAuthUser();

    return (permission) => Boolean(user && (user.isSuperAdmin || user.permissions?.includes(permission)));
}

/**
 * The link of a sidebar page by its route name, or null when the user cannot open it
 * (e.g. an admin without "finance.view" gets no link to the transactions page).
 */
export function useNavHref() {
    const navigation = usePage().props.panel?.navigation ?? [];

    return (routeName, query = null) => {
        const href = navigation.find((item) => item.key === routeName)?.href ?? null;

        if (!href || !query) {
            return href;
        }

        const params = new URLSearchParams(Object.entries(query).filter(([, value]) => value !== null && value !== undefined && value !== ''));

        return `${href}?${params.toString()}`;
    };
}
