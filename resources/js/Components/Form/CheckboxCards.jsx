import Field, { useBinding } from './Field';

/**
 * Multi-select as option cards. options: [{ value, label, description?, icon?, disabled?, disabledReason? }]
 * The form value is an array of the selected values.
 */
export default function CheckboxCards({ form = null, name, label, value, onChange, error, hint = null, options = [], className = '', columns = null }) {
    const binding = useBinding({ form, name, value, onChange, error });
    const selected = new Set((binding.value ?? []).map(String));

    const toggle = (optionValue) => {
        const key = String(optionValue);
        const next = selected.has(key) ? [...selected].filter((item) => item !== key) : [...selected, key];
        const typed = options.filter((option) => next.includes(String(option.value))).map((option) => option.value);
        binding.onChange(typed);
    };

    const enabled = options.filter((option) => !option.disabled);
    const lockedSelection = options.filter((option) => option.disabled && selected.has(String(option.value))).map((option) => option.value);
    const allSelected = enabled.length > 0 && enabled.every((option) => selected.has(String(option.value)));

    const selectAll = (
        <button
            type="button"
            className="btn btn-link btn-sm p-0 text-decoration-none"
            onClick={() => binding.onChange(allSelected ? lockedSelection : [...lockedSelection, ...enabled.map((option) => option.value)])}
        >
            {allSelected ? 'برداشتن همه' : 'انتخاب همه'}
        </button>
    );

    return (
        <Field label={label} error={binding.error} hint={hint} className={className} extra={options.length > 2 ? selectAll : null}>
            <div className="jl-option-grid" style={columns ? { gridTemplateColumns: `repeat(${columns}, minmax(0, 1fr))` } : undefined}>
                {options.map((option) => {
                    const checked = selected.has(String(option.value));

                    return (
                        <label
                            key={option.value}
                            className={`jl-option ${checked ? 'is-checked' : ''} ${option.disabled ? 'is-disabled' : ''}`}
                            title={option.disabled ? option.disabledReason : undefined}
                        >
                            <input type="checkbox" className="form-check-input" checked={checked} disabled={option.disabled} onChange={() => toggle(option.value)} />
                            <span className="min-w-0">
                                <strong>
                                    {option.icon && <i className={`bi ${option.icon} ms-1 text-primary-emphasis-soft`} />} {option.label}
                                </strong>
                                {option.description && <small>{option.description}</small>}
                            </span>
                        </label>
                    );
                })}
            </div>
        </Field>
    );
}
