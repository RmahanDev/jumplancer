import { useMemo, useState } from 'react';
import { axisScale, isRtl, useChartWidth } from './useChartWidth';
import { formatCompact, formatDate, formatMoney, formatNumber, formatShortDate } from '../../lib/format';

/**
 * Single-series trend over time (line + 10% area wash), with a crosshair that snaps to the
 * nearest day and a tooltip. Time runs from the inline start (right in RTL) to the end.
 *
 * data: [{ date: "YYYY-MM-DD", value }] — one point per day, or per week with period="week"
 * (then `date` is the first day of the week).
 */
export default function AreaChart({ data, height = 220, money = false, seriesLabel = 'مقدار', period = 'day' }) {
    const [ref, width] = useChartWidth();
    const [active, setActive] = useState(null);
    const rtl = isRtl();

    const layout = useMemo(() => {
        const axisWidth = 52;
        const top = 12;
        const bottom = height - 28;
        const start = rtl ? width - axisWidth : axisWidth;
        const end = rtl ? 8 : width - 8;
        const count = Math.max(data.length - 1, 1);
        const values = data.map((point) => Number(point.value) || 0);
        const scale = axisScale(Math.max(...values), 4, values.every(Number.isInteger));
        const x = (index) => start + ((end - start) * index) / count;
        const y = (value) => bottom - ((bottom - top) * (Number(value) || 0)) / scale.max;
        const points = data.map((point, index) => [x(index), y(point.value)]);
        const line = points.map(([px, py], index) => `${index === 0 ? 'M' : 'L'}${px.toFixed(1)},${py.toFixed(1)}`).join(' ');
        const area = points.length ? `${line} L${points[points.length - 1][0].toFixed(1)},${bottom} L${points[0][0].toFixed(1)},${bottom} Z` : '';
        const ticks = scale.ticks.map((value) => ({ value, y: y(value) }));
        const labelEvery = Math.max(1, Math.ceil(data.length / Math.max(2, Math.floor(width / 110))));

        return { axisWidth, top, bottom, start, end, x, y, points, line, area, ticks, labelEvery };
    }, [data, width, height, rtl, money]);

    const format = (value) => (money ? formatMoney(value) : formatNumber(value));

    const pick = (clientX, rect) => {
        const offset = clientX - rect.left;
        const ratio = (offset - layout.start) / (layout.end - layout.start);
        const index = Math.round(ratio * (data.length - 1));
        setActive(Math.max(0, Math.min(data.length - 1, index)));
    };

    if (!data?.length) {
        return null;
    }

    const point = active !== null ? layout.points[active] : null;
    const tooltipLeft = point ? Math.min(Math.max(point[0] - 70, 0), width - 150) : 0;

    return (
        <div
            className="jl-chart"
            ref={ref}
            tabIndex={0}
            role="img"
            aria-label={`نمودار ${seriesLabel} در ${formatNumber(data.length)} ${period === 'week' ? 'هفته' : 'روز'}`}
            onPointerLeave={() => setActive(null)}
            onBlur={() => setActive(null)}
            onKeyDown={(event) => {
                const forward = rtl ? 'ArrowLeft' : 'ArrowRight';
                const backward = rtl ? 'ArrowRight' : 'ArrowLeft';

                if (event.key === forward || event.key === backward) {
                    event.preventDefault();
                    const delta = event.key === forward ? 1 : -1;
                    setActive((index) => Math.max(0, Math.min(data.length - 1, (index ?? data.length - 1) + delta)));
                }
            }}
        >
            <svg viewBox={`0 0 ${width} ${height}`} width={width} height={height}>
                <g className="jl-grid">
                    {layout.ticks.map((tick) => (
                        <line key={tick.value} x1={Math.min(layout.start, layout.end)} x2={Math.max(layout.start, layout.end)} y1={tick.y} y2={tick.y} />
                    ))}
                </g>
                {layout.ticks.map((tick) => (
                    <text key={`label-${tick.value}`} className="jl-axis-label" x={rtl ? width - 4 : 4} y={tick.y + 4} textAnchor={rtl ? 'end' : 'start'}>
                        {tick.value === 0 ? '۰' : formatCompact(tick.value)}
                    </text>
                ))}
                <path className="jl-area" d={layout.area} />
                <path className="jl-line" d={layout.line} />
                {data.map((item, index) =>
                    index % layout.labelEvery === 0 || index === data.length - 1 ? (
                        <text key={item.date} className="jl-axis-label" x={layout.x(index)} y={height - 8} textAnchor="middle">
                            {formatShortDate(item.date)}
                        </text>
                    ) : null,
                )}
                <line className="jl-axis-line" x1={Math.min(layout.start, layout.end)} x2={Math.max(layout.start, layout.end)} y1={layout.bottom} y2={layout.bottom} />
                {point && (
                    <>
                        <line className="jl-crosshair" x1={point[0]} x2={point[0]} y1={layout.top} y2={layout.bottom} />
                        <circle className="jl-dot" cx={point[0]} cy={point[1]} r={5} />
                    </>
                )}
                {layout.points.length > 0 && active === null && (
                    <circle className="jl-dot" cx={layout.points[layout.points.length - 1][0]} cy={layout.points[layout.points.length - 1][1]} r={4.5} />
                )}
                <rect
                    x={0}
                    y={0}
                    width={width}
                    height={height}
                    fill="transparent"
                    onPointerMove={(event) => pick(event.clientX, event.currentTarget.ownerSVGElement.getBoundingClientRect())}
                    onPointerDown={(event) => pick(event.clientX, event.currentTarget.ownerSVGElement.getBoundingClientRect())}
                />
            </svg>
            {point && (
                <div className="jl-chart-tooltip" style={{ left: tooltipLeft, top: Math.max(point[1] - 64, 0) }}>
                    <span className="jl-tt-value">{format(data[active].value)}</span>
                    <span className="jl-tt-label">
                        <span className="jl-tt-key" />
                        {seriesLabel} · {period === 'week' ? `هفته‌ی ${formatShortDate(data[active].date)}` : formatDate(data[active].date)}
                    </span>
                </div>
            )}
        </div>
    );
}
