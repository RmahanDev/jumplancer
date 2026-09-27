import { useEffect, useRef, useState } from 'react';

const prefersReducedMotion = () => window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

/** Animates a number from its previous value to `target` (ease-out), for stat tiles. */
export function useCountUp(target, duration = 900) {
    const end = Number(target) || 0;
    const [value, setValue] = useState(prefersReducedMotion() ? end : 0);
    const from = useRef(prefersReducedMotion() ? end : 0);

    useEffect(() => {
        if (prefersReducedMotion() || from.current === end) {
            from.current = end;
            setValue(end);

            return undefined;
        }

        const start = from.current;
        const startedAt = performance.now();
        let frame;

        const tick = (now) => {
            const progress = Math.min(1, (now - startedAt) / duration);
            const eased = 1 - (1 - progress) ** 3;
            setValue(Math.round(start + (end - start) * eased));

            if (progress < 1) {
                frame = requestAnimationFrame(tick);
            } else {
                from.current = end;
            }
        };

        frame = requestAnimationFrame(tick);

        return () => cancelAnimationFrame(frame);
    }, [end, duration]);

    return value;
}
