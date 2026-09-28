import { useEffect, useRef } from 'react';

// Esc closes the dialog or drawer on top (only that one). This is the standard way out of an
// open dialog, not a keyboard shortcut: the panels have no global shortcuts.
const stack = [];

function onKeydown(event) {
    if (event.key !== 'Escape' || event.defaultPrevented || event.isComposing) {
        return;
    }

    const top = stack[stack.length - 1];

    if (top) {
        event.preventDefault();
        top.current(event);
    }
}

if (typeof window !== 'undefined') {
    window.addEventListener('keydown', onKeydown);
}

export function useEscape(handler, enabled = true) {
    const handlerRef = useRef(handler);
    handlerRef.current = handler;

    useEffect(() => {
        if (!enabled) {
            return undefined;
        }

        stack.push(handlerRef);

        return () => {
            const index = stack.lastIndexOf(handlerRef);

            if (index !== -1) {
                stack.splice(index, 1);
            }
        };
    }, [enabled]);
}
