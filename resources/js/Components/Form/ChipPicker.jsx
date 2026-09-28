import Field, { useBinding } from './Field';
import { formatNumber } from '../../lib/format';

/**
 * Multi-select as toggle chips (skills, tags). The form value is an array of option values.
 * options: [{ value, label }]
 */
export default function ChipPicker({ form = null, name, label, value, onChange, error, hint = null, options = [], max = null, emptyText = 'گزینه‌ای برای انتخاب نیست.', className = '' }) {
    const binding = useBinding({ form, name, value, onChange, error });
    const selected = (binding.value ?? []).map(String);

    const toggle = (optionValue) => {
        const key = String(optionValue);
        const next = selected.includes(key) ? selected.filter((item) => item !== key) : [...selected, key];
        const limited = max ? next.slice(-max) : next;
        binding.onChange(options.filter((option) => limited.includes(String(option.value))).map((option) => option.value));
    };

    const counter = max ? (
        <span className="small text-muted">
            {formatNumber(selected.length)} / {formatNumber(max)}
        </span>
    ) : null;

    return (
        <Field label={label} error={binding.error} hint={hint} className={className} extra={counter}>
            {options.length === 0 ? (
                <div className="small text-muted">{emptyText}</div>
            ) : (
                <div className="d-flex flex-wrap gap-2" role="group" aria-label={label}>
                    {options.map((option) => {
                        const active = selected.includes(String(option.value));

                        return (
                            <button key={option.value} type="button" className={`jl-chip jl-chip-toggle ${active ? 'is-active' : ''}`} onClick={() => toggle(option.value)} aria-pressed={active}>
                                {active && <i className="bi bi-check2" />}
                                {option.label}
                            </button>
                        );
                    })}
                </div>
            )}
        </Field>
    );
}
