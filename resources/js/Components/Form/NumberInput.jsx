import { useId } from 'react';
import Field, { useBinding } from './Field';
import { formatCompact } from '../../lib/format';
import { digitsOnly } from '../../lib/text';

/**
 * Whole-number input that accepts Persian digits and shows thousands separators.
 * The form value is a plain number string ("12500000") or "" when empty.
 * `money` adds the Toman unit and a spelled-out hint ("۱۲٫۵ میلیون تومان").
 */
export default function NumberInput({ form = null, name, label, value, onChange, error, hint = null, required = false, unit = null, money = false, className = '', min = null, ...props }) {
    const id = useId();
    const binding = useBinding({ form, name, value, onChange, error });
    const raw = binding.value === null || binding.value === undefined ? '' : String(binding.value);
    const display = raw === '' ? '' : Number(raw).toLocaleString('en-US');
    const shownUnit = unit ?? (money ? 'تومان' : null);

    return (
        <Field
            label={label}
            htmlFor={id}
            error={binding.error}
            hint={hint ?? (money && raw !== '' && Number(raw) >= 1000 ? `≈ ${formatCompact(raw)} تومان` : null)}
            required={required}
            className={className}
        >
            <div className={`jl-input ${shownUnit ? 'has-unit' : ''}`}>
                <input
                    id={id}
                    name={name}
                    type="text"
                    inputMode="numeric"
                    dir="ltr"
                    className={`form-control text-start num ${binding.error ? 'is-invalid' : ''}`}
                    value={display}
                    onChange={(event) => {
                        const digits = digitsOnly(event.target.value).replace(/^0+(?=\d)/, '');
                        binding.onChange(digits === '' ? '' : digits);
                    }}
                    aria-invalid={Boolean(binding.error)}
                    required={required}
                    autoComplete="off"
                    {...props}
                />
                {shownUnit && <span className="jl-input-unit">{shownUnit}</span>}
            </div>
        </Field>
    );
}
