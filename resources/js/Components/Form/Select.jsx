import { useId } from 'react';
import Field, { useBinding } from './Field';

/**
 * options: [{ value, label, group? }] — options with a `group` are rendered inside <optgroup>s.
 */
export default function Select({ form = null, name, label, value, onChange, error, hint = null, required = false, options = [], placeholder = 'انتخاب کنید', allowEmpty = true, className = '', ...props }) {
    const id = useId();
    const binding = useBinding({ form, name, value, onChange, error });
    const groups = options.some((option) => option.group)
        ? Object.entries(options.reduce((all, option) => ({ ...all, [option.group ?? '']: [...(all[option.group ?? ''] ?? []), option] }), {}))
        : null;

    const renderOption = (option) => (
        <option key={option.value} value={option.value} disabled={option.disabled}>
            {option.label}
        </option>
    );

    return (
        <Field label={label} htmlFor={id} error={binding.error} hint={hint} required={required} className={className}>
            <select
                id={id}
                name={name}
                className={`form-select ${binding.error ? 'is-invalid' : ''}`}
                value={binding.value ?? ''}
                onChange={(event) => binding.onChange(event.target.value)}
                aria-invalid={Boolean(binding.error)}
                required={required}
                {...props}
            >
                {allowEmpty && <option value="">{placeholder}</option>}
                {groups
                    ? groups.map(([group, items]) => (
                          <optgroup key={group} label={group}>
                              {items.map(renderOption)}
                          </optgroup>
                      ))
                    : options.map(renderOption)}
            </select>
        </Field>
    );
}
