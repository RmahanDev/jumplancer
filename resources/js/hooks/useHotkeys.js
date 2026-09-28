import { useEffect, useRef } from 'react';

// Global keyboard shortcuts.
// Keys are matched by physical position (event.code), so shortcuts work the same with the
// Persian keyboard layout active (pressing "K" types "ن" but its code is still "KeyK").
//
// Combos: "mod+k" (Ctrl or ⌘), "n", "shift+/" (?), "escape", "mod+enter", sequences "g d".
// The most recently registered handler wins, so an open modal takes precedence over the page.
// While a modal or the command palette is open, only shortcuts registered with
// { overlay: true } run: pressing "n" inside a dialog never opens a second one.

const registry = new Set();
let pendingPrefix = null;
let pendingTimer = null;
let overlays = 0;

/** Called by modals and the command palette while they are on screen. */
export function overlayOpened() {
    overlays += 1;

    return () => {
        overlays = Math.max(0, overlays - 1);
    };
}

export function hasOverlay() {
    return overlays > 0;
}

const NAMED_CODES = {
    Slash: '/',
    Escape: 'escape',
    Enter: 'enter',
    NumpadEnter: 'enter',
    Comma: ',',
    Period: '.',
    BracketLeft: '[',
    BracketRight: ']',
    Backquote: '`',
    Space: 'space',
    ArrowUp: 'up',
    ArrowDown: 'down',
    ArrowLeft: 'left',
    ArrowRight: 'right',
};

function tokenFor(event) {
    const { code } = event;
    let key;

    if (code.startsWith('Key')) {
        key = code.slice(3).toLowerCase();
    } else if (code.startsWith('Digit')) {
        key = code.slice(5);
    } else {
        key = NAMED_CODES[code] ?? code.toLowerCase();
    }

    const modifiers = [];

    if (event.ctrlKey || event.metaKey) {
        modifiers.push('mod');
    }

    if (event.altKey) {
        modifiers.push('alt');
    }

    if (event.shiftKey) {
        modifiers.push('shift');
    }

    return [...modifiers, key].join('+');
}

function isTyping(target) {
    return Boolean(target?.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(target?.tagName));
}

function clearPending() {
    pendingPrefix = null;
    window.clearTimeout(pendingTimer);
}

function handleKeydown(event) {
    if (event.defaultPrevented || event.isComposing || !event.code) {
        return;
    }

    const token = tokenFor(event);
    const typing = isTyping(event.target);
    const candidates = pendingPrefix ? [`${pendingPrefix} ${token}`, token] : [token];
    const entries = [...registry].reverse();

    for (const candidate of candidates) {
        for (const entry of entries) {
            if (!entry.enabled.current || !entry.combos.includes(candidate) || (typing && !entry.allowInInputs) || (overlays > 0 && !entry.overlay)) {
                continue;
            }

            event.preventDefault();
            clearPending();
            entry.handler.current(event);

            return;
        }
    }

    const startsSequence =
        !typing && overlays === 0 && entries.some((entry) => entry.enabled.current && entry.combos.some((combo) => combo.startsWith(`${token} `)));

    if (startsSequence) {
        pendingPrefix = token;
        window.clearTimeout(pendingTimer);
        pendingTimer = window.setTimeout(clearPending, 1200);
    } else {
        clearPending();
    }
}

if (typeof window !== 'undefined') {
    window.addEventListener('keydown', handleKeydown);
}

export function useHotkeys(combos, handler, { enabled = true, allowInInputs = false, overlay = false } = {}) {
    const handlerRef = useRef(handler);
    const enabledRef = useRef(enabled);
    handlerRef.current = handler;
    enabledRef.current = enabled;

    const key = [].concat(combos).filter(Boolean).join('|');

    useEffect(() => {
        if (!key) {
            return undefined;
        }

        const entry = { combos: key.split('|'), handler: handlerRef, enabled: enabledRef, allowInInputs, overlay };
        registry.add(entry);

        return () => registry.delete(entry);
    }, [key, allowInInputs, overlay]);
}

const isMac = typeof navigator !== 'undefined' && /Mac|iPhone|iPad/.test(navigator.platform ?? '');

/** "mod+k" -> ["Ctrl", "K"], "g d" -> ["G", "D"], "shift+/" -> ["?"] */
export function describeCombo(combo) {
    if (combo === 'shift+/') {
        return ['?'];
    }

    return combo.split(/[ +]/).map((part) => ({
        mod: isMac ? '⌘' : 'Ctrl',
        alt: isMac ? '⌥' : 'Alt',
        shift: 'Shift',
        escape: 'Esc',
        enter: 'Enter',
        up: '↑',
        down: '↓',
        left: '←',
        right: '→',
        space: 'Space',
    })[part] ?? part.toUpperCase());
}

export function isSequence(combo) {
    return combo.includes(' ');
}
