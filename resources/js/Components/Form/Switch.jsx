import { useId } from 'react';
import { useBinding } from './Field';

/** A whole-row switch with a title and a short explanation. */
export default function Switch({ form = null, name, label, description = null, value, onChange, error, className = '', disabled = false }) {
    const id = useId();
    const binding = useBinding({ form, name, value, onChange, error });

    return (
        <div className={className}>
            <label className="jl-switch" htmlFor={id}>
                <span className="jl-switch-text">
                    <strong>{label}</strong>
                    {description && <small>{description}</small>}
                </span>
                <span className="form-check form-switch m-0 p-0">
                    <input
                        id={id}
                        type="checkbox"
                        role="switch"
                        className="form-check-input"
                        checked={Boolean(binding.value)}
                        onChange={(event) => binding.onChange(event.target.checked)}
                        disabled={disabled}
                    />
                </span>
            </label>
            {binding.error && <div className="invalid-feedback d-block">{binding.error}</div>}
        </div>
    );
}
