import { useEffect, useRef, useState } from 'react';

/** Width of the chart container (charts redraw on resize instead of stretching). */
export function useChartWidth(fallback = 600) {
    const ref = useRef(null);
    const [width, setWidth] = useState(fallback);

    useEffect(() => {
        const element = ref.current;

        if (!element) {
            return undefined;
        }

        const update = () => setWidth(Math.max(240, Math.floor(element.getBoundingClientRect().width)));
        update();

        const observer = new ResizeObserver(update);
        observer.observe(element);

        return () => observer.disconnect();
    }, []);

    return [ref, width];
}

/** 1, 2 or 5 × 10^n closest to `value` (the classic "nice number" for axis steps). */
function niceStep(value) {
    const exponent = Math.floor(Math.log10(value));
    const fraction = value / 10 ** exponent;
    const nice = fraction < 1.5 ? 1 : fraction < 3 ? 2 : fraction < 7 ? 5 : 10;

    return nice * 10 ** exponent;
}

/**
 * Axis maximum and ticks on round steps (۱ میلیون، ۲ میلیون … rather than ۱٫۳ / ۳٫۸ میلیون),
 * about `segments` of them. Whole-number data (counts, Toman amounts) never gets fractional
 * ticks, so an axis never reads "۰٫۵ عضو".
 */
export function axisScale(dataMax, segments, integer = false) {
    const top = dataMax > 0 ? dataMax : 1;
    const step = integer ? Math.max(1, Math.round(niceStep(top / segments))) : niceStep(top / segments);
    const max = Math.max(step, Math.ceil(top / step) * step);
    const ticks = Array.from({ length: Math.round(max / step) + 1 }, (_, index) => Number((index * step).toPrecision(12)));

    return { max, ticks };
}

export const isRtl = () => typeof document !== 'undefined' && document.documentElement.dir === 'rtl';
