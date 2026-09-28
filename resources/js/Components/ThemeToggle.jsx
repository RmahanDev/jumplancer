import { useEffect, useState } from 'react';
import { toggleTheme, getThemePreference, onThemeChange } from '../lib/theme';

const LABELS = {
    light: { icon: 'bi-sun', text: 'تم روشن' },
    dark: { icon: 'bi-moon-stars', text: 'تم تیره' },
    auto: { icon: 'bi-circle-half', text: 'هماهنگ با سیستم' },
};

/**
 * Light / dark / system switch. Used in the dashboards' topbar and as a React island on the
 * Blade login pages (both render this same component).
 */
export default function ThemeToggle({ withLabel = false }) {
    const [preference, setPreference] = useState(getThemePreference);

    useEffect(() => onThemeChange(setPreference), []);

    const current = LABELS[preference] ?? LABELS.auto;

    return (
        <button
            type="button"
            className={withLabel ? 'btn btn-ghost btn-sm' : 'jl-icon-btn is-bordered'}
            onClick={() => toggleTheme()}
            aria-label={`تغییر تم (اکنون: ${current.text})`}
            data-tip={withLabel ? undefined : `${current.text} — کلید T`}
        >
            <i className={`bi ${current.icon}`} key={preference} style={{ animation: 'jl-pop .25s ease both' }} />
            {withLabel && <span>{current.text}</span>}
        </button>
    );
}
