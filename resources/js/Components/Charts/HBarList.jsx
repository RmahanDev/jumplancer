import { useEffect, useState } from 'react';
import { formatMoney, formatNumber } from '../../lib/format';

/**
 * Horizontal bars for comparing a few categories (one series, one hue): label and value on
 * top, the bar underneath. Bars grow in on mount.
 *
 * items: [{ key, label, value }]
 */
export default function HBarList({ items, money = false }) {
    const [ready, setReady] = useState(false);
    const max = Math.max(1, ...items.map((item) => Number(item.value) || 0));

    useEffect(() => {
        const frame = requestAnimationFrame(() => setReady(true));

        return () => cancelAnimationFrame(frame);
    }, []);

    return (
        <ul className="jl-hbars">
            {items.map((item) => (
                <li key={item.key ?? item.label} title={`${item.label}: ${money ? formatMoney(item.value) : formatNumber(item.value)}`}>
                    <span className="jl-hbar-label">{item.label}</span>
                    <span className="jl-hbar-value">{money ? formatMoney(item.value) : formatNumber(item.value)}</span>
                    <span className="jl-hbar-track">
                        <span className="jl-hbar-fill" style={{ width: ready ? `${((Number(item.value) || 0) / max) * 100}%` : 0 }} />
                    </span>
                </li>
            ))}
        </ul>
    );
}
