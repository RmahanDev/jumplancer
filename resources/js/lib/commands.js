import { useEffect, useRef } from 'react';

// Actions a page adds to the panel search (topbar), e.g. "کاربر جدید" or "خروجی تراکنش‌ها".
// Each hook call keeps a live reference, so commands always run with the page's latest state.
const sources = new Set();

/**
 * usePageCommands([{ id, label, icon, keywords?, run }])
 */
export function usePageCommands(commands) {
    const ref = useRef(commands);
    ref.current = commands;

    useEffect(() => {
        const source = () => ref.current ?? [];
        sources.add(source);

        return () => sources.delete(source);
    }, []);
}

export function getPageCommands() {
    return [...sources].flatMap((source) => source().filter(Boolean));
}
