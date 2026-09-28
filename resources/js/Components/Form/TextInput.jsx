import { useId } from 'react';
import Field, { useBinding } from './Field';

/**
 * <TextInput form={form} name="title" label="عنوان" /> or controlled with value/onChange.
 * `ltr` for usernames, emails, URLs; `icon` for a leading icon; `unit` for a trailing unit.
 */
export default function TextInput({
    form = null,
    name,
    label,
    value,
    onChange,
    error,
    hint = null,
    required = false,
    type = 'text',
    ltr = false,
    icon = null,
    unit = null,
    className = '',
    inputClassName = '',
    ...props
}) {
    const id = useId();
    const binding = useBinding({ form, name, value, onChange, error });

    return (
        <Field label={label} htmlFor={id} error={binding.error} hint={hint} required={required} className={className}>
            <div className={`jl-input ${unit ? 'has-unit' : ''}`}>
                {icon && <i className={`bi ${icon} jl-input-icon`} />}
                <input
                    id={id}
                    name={name}
                    type={type}
                    dir={ltr ? 'ltr' : undefined}
                    className={`form-control ${ltr ? 'text-start' : ''} ${binding.error ? 'is-invalid' : ''} ${inputClassName}`}
                    value={binding.value ?? ''}
                    onChange={(event) => binding.onChange(event.target.value)}
                    aria-invalid={Boolean(binding.error)}
                    required={required}
                    {...props}
                />
                {unit && <span className="jl-input-unit">{unit}</span>}
            </div>
        </Field>
    );
}
