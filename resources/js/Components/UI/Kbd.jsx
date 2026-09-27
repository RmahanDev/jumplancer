import { describeCombo, isSequence } from '../../hooks/useHotkeys';

/** Renders a shortcut such as "mod+k" as <kbd>Ctrl</kbd><kbd>K</kbd>, or "g d" as G then D. */
export default function Kbd({ combo, className = '' }) {
    if (!combo) {
        return null;
    }

    const keys = describeCombo(combo);

    return (
        <span className={`d-inline-flex align-items-center gap-1 ${className}`} dir="ltr">
            {keys.map((key, index) => (
                <span key={`${key}-${index}`} className="d-inline-flex align-items-center gap-1">
                    {index > 0 && <span className="text-muted small">{isSequence(combo) ? 'سپس' : '+'}</span>}
                    <kbd>{key}</kbd>
                </span>
            ))}
        </span>
    );
}
