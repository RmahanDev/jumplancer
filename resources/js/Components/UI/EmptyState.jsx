/** Friendly empty state with a small illustration in the brand colors. */
export default function EmptyState({ title = 'چیزی برای نمایش نیست', description = null, action = null, icon = 'bi-inbox', compact = false }) {
    return (
        <div className={`jl-empty ${compact ? 'py-4' : ''}`}>
            {!compact && (
                <svg viewBox="0 0 160 120" aria-hidden="true">
                    <ellipse cx="80" cy="104" rx="58" ry="8" fill="var(--jl-surface-3)" />
                    <rect x="34" y="22" width="92" height="70" rx="14" fill="var(--jl-surface)" stroke="var(--jl-border-strong)" strokeWidth="2" />
                    <rect x="48" y="38" width="42" height="7" rx="3.5" fill="var(--jl-primary-soft)" />
                    <rect x="48" y="52" width="64" height="6" rx="3" fill="var(--jl-surface-3)" />
                    <rect x="48" y="64" width="52" height="6" rx="3" fill="var(--jl-surface-3)" />
                    <circle cx="118" cy="28" r="15" fill="var(--jl-accent)" />
                    <path d="M112 28h12M118 22v12" stroke="#fff" strokeWidth="3" strokeLinecap="round" />
                </svg>
            )}
            {compact && <i className={`bi ${icon} fs-2 text-muted`} />}
            <h3>{title}</h3>
            {description && <p>{description}</p>}
            {action && <div className="mt-2">{action}</div>}
        </div>
    );
}
