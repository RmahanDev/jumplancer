import { formatNumber } from '../../lib/format';

/**
 * Segmented tabs. items: [{ key, label, icon?, count? }]
 */
export default function Tabs({ items, active, onChange, className = '' }) {
    return (
        <div className={`jl-tabs ${className}`} role="tablist">
            {items.map((item) => (
                <button
                    key={item.key}
                    type="button"
                    role="tab"
                    aria-selected={active === item.key}
                    className={`jl-tab ${active === item.key ? 'active' : ''}`}
                    onClick={() => onChange(item.key)}
                >
                    {item.icon && <i className={`bi ${item.icon}`} />}
                    {item.label}
                    {item.count !== undefined && item.count !== null && <span className="jl-tab-count">{formatNumber(item.count)}</span>}
                </button>
            ))}
        </div>
    );
}
