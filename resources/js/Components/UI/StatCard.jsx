import { Link } from '@inertiajs/react';
import { useCountUp } from '../../hooks/useCountUp';
import { formatCompact, formatNumber } from '../../lib/format';

/**
 * Stat tile: label, animated value, icon and a short footnote. Links to its detail page when `href` is set.
 * format: "number" | "money" (value in Toman, shown compact with the unit).
 */
export default function StatCard({ label, value, icon = 'bi-graph-up', tone = 'primary', foot = null, href = null, format = 'number', index = 0 }) {
    const animated = useCountUp(value ?? 0);
    const display = format === 'money' ? formatCompact(animated) : formatNumber(animated);

    const content = (
        <>
            <div className="jl-stat-top">
                <span className="jl-stat-label">{label}</span>
                <span className={`jl-stat-icon ${tone !== 'primary' ? `is-${tone}` : ''}`}>
                    <i className={`bi ${icon}`} />
                </span>
            </div>
            <div className="jl-stat-value" title={format === 'money' ? `${formatNumber(value)} تومان` : undefined}>
                {display}
                {/* A real space: without it the last letter would join the unit ("میلیونـتومان"). */}
                {format === 'money' && <> <span className="jl-stat-unit">تومان</span></>}
            </div>
            {foot && <div className="jl-stat-foot">{foot}</div>}
        </>
    );

    const className = `jl-card jl-stat jl-rise jl-rise-${Math.min(index + 1, 8)} ${href ? 'jl-card-hover' : ''}`;

    return href ? (
        <Link href={href} className={className} prefetch>
            {content}
        </Link>
    ) : (
        <div className={className}>{content}</div>
    );
}
