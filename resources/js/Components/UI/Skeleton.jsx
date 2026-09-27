/** Shimmering placeholder while deferred props load. */
export default function Skeleton({ height = 16, width = '100%', className = '', radius = null }) {
    return <span className={`jl-skeleton ${className}`} style={{ height, width, borderRadius: radius ?? undefined }} aria-hidden="true" />;
}

export function ChartSkeleton({ height = 220 }) {
    return (
        <div className="d-flex flex-column gap-2" aria-busy="true" aria-label="در حال بارگذاری نمودار">
            <Skeleton height={height} radius="0.75rem" />
            <div className="d-flex gap-2">
                <Skeleton height={10} width="20%" />
                <Skeleton height={10} width="15%" />
            </div>
        </div>
    );
}
