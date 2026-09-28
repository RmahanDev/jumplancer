import { useMemo, useState } from 'react';
import { axisScale, isRtl, useChartWidth } from './useChartWidth';
import { formatCompact, formatMoney, formatNumber } from '../../lib/format';

/**
 * Columns for one series (weeks, months ...): thin bars with a 4px rounded top, the hovered
 * or focused bar shows a tooltip, and only the highlighted/last bar carries a value label.
 *
 * data: [{ key, label, value, highlight?, tooltip? }] — an empty label keeps the axis readable when
 * there are many columns (the tooltip still names the column through `tooltip`).
 */
export default function ColumnChart({ data, height = 220, money = false, seriesLabel = 'مقدار' }) {
    const [ref, width] = useChartWidth();
    const [active, setActive] = useState(null);
    const rtl = isRtl();

    const layout = useMemo(() => {
        const axisWidth = 52;
        const top = 18;
        const bottom = height - 28;
        const plotStart = rtl ? width - axisWidth : axisWidth;
        const plotEnd = rtl ? 8 : width - 8;
        const band = Math.abs(plotEnd - plotStart) / Math.max(data.length, 1);
        const barWidth = Math.min(24, band * 0.56);
        const values = data.map((item) => Number(item.value) || 0);
        const scale = axisScale(Math.max(...values), 2, values.every(Number.isInteger));
        const center = (index) => (rtl ? plotStart - band * (index + 0.5) : plotStart + band * (index + 0.5));
        const y = (value) => bottom - ((bottom - top) * (Number(value) || 0)) / scale.max;
        const ticks = scale.ticks.map((value) => ({ value, y: y(value) }));

        return { top, bottom, plotStart, plotEnd, band, barWidth, center, y, ticks };
    }, [data, width, height, rtl, money]);

    const format = (value) => (money ? formatMoney(value) : formatNumber(value));
    const labelIndex = data.findIndex((item) => item.highlight) >= 0 ? data.findIndex((item) => item.highlight) : data.length - 1;

    const barPath = (x, yTop) => {
        const half = layout.barWidth / 2;
        const heightPx = layout.bottom - yTop;
        const radius = Math.min(4, heightPx);

        if (heightPx <= 0) {
            return '';
        }

        return `M${x - half},${layout.bottom} V${yTop + radius} Q${x - half},${yTop} ${x - half + radius},${yTop} H${x + half - radius} Q${x + half},${yTop} ${x + half},${yTop + radius} V${layout.bottom} Z`;
    };

    return (
        <div className="jl-chart" ref={ref} onPointerLeave={() => setActive(null)}>
            <svg viewBox={`0 0 ${width} ${height}`} width={width} height={height} role="img" aria-label={`نمودار ستونی ${seriesLabel}`}>
                <g className="jl-grid">
                    {layout.ticks.map((tick) => (
                        <line key={tick.value} x1={Math.min(layout.plotStart, layout.plotEnd)} x2={Math.max(layout.plotStart, layout.plotEnd)} y1={tick.y} y2={tick.y} />
                    ))}
                </g>
                {layout.ticks.map((tick) => (
                    <text key={`label-${tick.value}`} className="jl-axis-label" x={rtl ? width - 4 : 4} y={tick.y + 4} textAnchor={rtl ? 'end' : 'start'}>
                        {tick.value === 0 ? '۰' : formatCompact(tick.value)}
                    </text>
                ))}
                {data.map((item, index) => {
                    const x = layout.center(index);
                    const yTop = layout.y(item.value);

                    return (
                        <g key={item.key ?? item.label}>
                            <rect
                                className="jl-bar-hit"
                                x={x - layout.band / 2}
                                y={layout.top}
                                width={layout.band}
                                height={layout.bottom - layout.top}
                                tabIndex={0}
                                aria-label={`${item.tooltip ?? item.label}: ${format(item.value)}`}
                                onPointerEnter={() => setActive(index)}
                                onFocus={() => setActive(index)}
                                onBlur={() => setActive(null)}
                            />
                            <path className={`jl-bar ${item.highlight ? 'is-accent' : ''}`} d={barPath(x, yTop)} />
                            {index === labelIndex && Number(item.value) > 0 && (
                                <text className="jl-value-label" x={x} y={yTop - 6} textAnchor="middle">
                                    {formatCompact(item.value)}
                                </text>
                            )}
                            {item.label && (
                                <text className="jl-axis-label" x={x} y={height - 8} textAnchor="middle">
                                    {item.label}
                                </text>
                            )}
                        </g>
                    );
                })}
                <line className="jl-axis-line" x1={Math.min(layout.plotStart, layout.plotEnd)} x2={Math.max(layout.plotStart, layout.plotEnd)} y1={layout.bottom} y2={layout.bottom} />
            </svg>
            {active !== null && data[active] && (
                <div
                    className="jl-chart-tooltip"
                    style={{
                        left: Math.min(Math.max(layout.center(active) - 70, 0), width - 150),
                        top: Math.max(layout.y(data[active].value) - 70, 0),
                    }}
                >
                    <span className="jl-tt-value">{format(data[active].value)}</span>
                    <span className="jl-tt-label">
                        <span className="jl-tt-key" style={data[active].highlight ? { background: 'var(--jl-chart-2)' } : undefined} />
                        {seriesLabel} · {data[active].tooltip ?? data[active].label}
                    </span>
                </div>
            )}
        </div>
    );
}
