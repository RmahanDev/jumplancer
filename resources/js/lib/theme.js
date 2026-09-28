// Light / dark / system theme, stored per browser. The Blade partial theme-script applies it before paint.
const STORAGE_KEY = 'jl-theme';
const listeners = new Set();

export function getThemePreference() {
    try {
        return localStorage.getItem(STORAGE_KEY) || 'auto';
    } catch {
        return 'auto';
    }
}

export function resolveTheme(preference) {
    if (preference === 'light' || preference === 'dark') {
        return preference;
    }

    return window.matchMedia?.('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

export function applyTheme(preference) {
    const root = document.documentElement;
    root.setAttribute('data-bs-theme', resolveTheme(preference));
    root.setAttribute('data-theme-preference', preference);
}

export function setThemePreference(preference) {
    try {
        localStorage.setItem(STORAGE_KEY, preference);
    } catch {
        // Private mode: the choice lasts for this page only.
    }

    const root = document.documentElement;
    root.classList.add('jl-theme-transition');
    applyTheme(preference);
    window.setTimeout(() => root.classList.remove('jl-theme-transition'), 350);

    listeners.forEach((listener) => listener(preference));
}

/**
 * Flips what is on screen (light <-> dark), so every press shows a change even when the
 * preference was "auto". "Follow the system" stays available in the command palette.
 */
export function toggleTheme() {
    const next = resolveTheme(getThemePreference()) === 'dark' ? 'light' : 'dark';
    setThemePreference(next);

    return next;
}

export function onThemeChange(listener) {
    listeners.add(listener);

    return () => listeners.delete(listener);
}

// Follow the OS when the preference is "auto".
window.matchMedia?.('(prefers-color-scheme: dark)').addEventListener?.('change', () => {
    if (getThemePreference() === 'auto') {
        applyTheme('auto');
        listeners.forEach((listener) => listener('auto'));
    }
});
