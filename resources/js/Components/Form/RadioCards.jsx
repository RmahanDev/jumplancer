import { useId } from 'react';
import Field, { useBinding } from './Field';

/**
 * Single choice as option cards (clearer than a select when each choice needs a sentence).
 * options: [{ value, label, description?, icon?, disabled? }]
 */
export default function RadioCards({ form = null, name, label, value, onChange, error, hint = null, options = [], className = '', columns = null }) {
    const group = useId();
    const binding = useBinding({ form, name, value, onChange, error });

    return (
        <Field label={label} error={binding.error} hint={hint} className={className}>
            <div className="jl-option-grid" role="radiogroup" style={columns ? { gridTemplateColumns: `repeat(${columns}, minmax(0, 1fr))` } : undefined}>
                {options.map((option) => {
                    const checked = String(binding.value ?? '') === String(option.value);

                    return (
                        <label key={option.value} className={`jl-option ${checked ? 'is-checked' : ''} ${option.disabled ? 'is-disabled' : ''}`}>
                            <input
                                type="radio"
                                className="form-check-input"
                                name={`${name}-${group}`}
                                checked={checked}
                                disabled={option.disabled}
                                onChange={() => binding.onChange(option.value)}
                            />
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
