import { useId } from 'react';
import Field, { useBinding } from './Field';
import JalaliDateInput from './JalaliDateInput';

function localParts(value) {
    if (!value) {
        return { date: '', time: '' };
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return { date: '', time: '' };
    }

    const pad = (number) => String(number).padStart(2, '0');

    return {
        date: `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`,
        time: `${pad(date.getHours())}:${pad(date.getMinutes())}`,
    };
}

/**
 * Jalali date + time in the viewer's time zone. The form value is an ISO string in UTC
 * ("2026-10-01T14:30:00.000Z"), so the server stores the right moment whatever the time zone.
 */
export default function DateTimeInput({ form = null, name, label, value, onChange, error, hint = null, required = false, min = null, className = '' }) {
    const id = useId();
    const binding = useBinding({ form, name, value, onChange, error });
    const parts = localParts(binding.value);

    const emit = (date, time) => {
        if (!date) {
            binding.onChange('');

            return;
        }

        const [year, month, day] = date.split('-').map(Number);
        const [hours, minutes] = (time || '10:00').split(':').map(Number);
        binding.onChange(new Date(year, month - 1, day, hours, minutes).toISOString());
    };

    return (
        <Field label={label} htmlFor={id} error={binding.error} hint={hint} required={required} className={className}>
            <div className="row g-2">
                <div className="col-7">
                    <JalaliDateInput value={parts.date} onChange={(date) => emit(date, parts.time)} min={min} required={required} />
                </div>
                <div className="col-5">
                    <div className="jl-input">
                        <i className="bi bi-clock jl-input-icon" />
                        <input
                            id={id}
                            type="time"
                            dir="ltr"
                            className={`form-control text-start ${binding.error ? 'is-invalid' : ''}`}
                            value={parts.time}
                            onChange={(event) => emit(parts.date, event.target.value)}
                            disabled={!parts.date}
                            step={300}
                        />
                    </div>
                </div>
            </div>
        </Field>
    );
}
