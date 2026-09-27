import { useId } from 'react';
import Field, { useBinding } from './Field';
import { formatNumber } from '../../lib/format';

/** Multi-line input with a live character counter when `maxLength` is set. */
export default function Textarea({ form = null, name, label, value, onChange, error, hint = null, required = false, rows = 4, maxLength = null, minLength = null, className = '', ...props }) {
    const id = useId();
    const binding = useBinding({ form, name, value, onChange, error });
    const length = String(binding.value ?? '').length;

    const counter = maxLength ? (
        <span className={`small ${length > maxLength ? 'text-danger' : 'text-muted'}`}>
            {formatNumber(length)} / {formatNumber(maxLength)}
        </span>
    ) : null;

    return (
        <Field label={label} htmlFor={id} error={binding.error} hint={hint ?? (minLength ? `حداقل ${formatNumber(minLength)} کاراکتر` : null)} required={required} className={className} extra={counter}>
            <textarea
                id={id}
                name={name}
                rows={rows}
                className={`form-control ${binding.error ? 'is-invalid' : ''}`}
                value={binding.value ?? ''}
                onChange={(event) => binding.onChange(event.target.value)}
                aria-invalid={Boolean(binding.error)}
                required={required}
                {...props}
            />
        </Field>
    );
}
