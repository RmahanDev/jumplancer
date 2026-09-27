/** Progress meter: the track is a lighter step of the same hue as the fill. */
export default function Meter({ value, max = 100, tone = 'primary', label = null }) {
    const percent = max > 0 ? Math.max(0, Math.min(100, (Number(value) / Number(max)) * 100)) : 0;

    return (
        <div className={`jl-meter ${tone === 'accent' ? 'is-accent' : ''}`} role="progressbar" aria-valuenow={Math.round(percent)} aria-valuemin={0} aria-valuemax={100} aria-label={label ?? undefined}>
            <span style={{ width: `${percent}%` }} />
        </div>
    );
}
