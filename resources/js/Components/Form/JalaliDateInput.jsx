import { useEffect, useId, useMemo, useRef, useState } from 'react';
import Field, { useBinding } from './Field';
import { formatDate, toPersianDigits } from '../../lib/format';
import { JALALI_MONTHS, JALALI_WEEKDAYS, isoToJalali, jalaliMonthLength, jalaliToIso, jalaliWeekday, toJalali } from '../../lib/jalali';

function todayIso() {
    const now = new Date();

    return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
}

/** Month grid of the Jalali calendar. value/min/max are Gregorian "YYYY-MM-DD" strings. */
export function JalaliCalendar({ value, onSelect, min = null, max = null, onClear = null }) {
    const today = todayIso();
    const start = isoToJalali(value || today);
    const [view, setView] = useState({ jy: start.jy, jm: start.jm });

    const days = useMemo(() => {
        const offset = jalaliWeekday(view.jy, view.jm, 1);
        const length = jalaliMonthLength(view.jy, view.jm);

        return [...Array(offset).fill(null), ...Array.from({ length }, (_, index) => index + 1)];
    }, [view]);

    const move = (delta) => {
        setView(({ jy, jm }) => {
            const index = jy * 12 + (jm - 1) + delta;

            return { jy: Math.floor(index / 12), jm: (index % 12) + 1 };
        });
    };

    return (
        <div>
            <div className="jl-datepicker-head">
                <button type="button" className="jl-icon-btn" onClick={() => move(-1)} aria-label="ماه قبل">
                    <i className="bi bi-chevron-right" />
                </button>
                <strong>
                    {JALALI_MONTHS[view.jm - 1]} {toPersianDigits(view.jy)}
                </strong>
                <button type="button" className="jl-icon-btn" onClick={() => move(1)} aria-label="ماه بعد">
                    <i className="bi bi-chevron-left" />
                </button>
            </div>
            <div className="jl-datepicker-grid">
                {JALALI_WEEKDAYS.map((weekday) => (
                    <span key={weekday} className="jl-dp-weekday">
                        {weekday}
                    </span>
                ))}
                {days.map((day, index) => {
                    if (day === null) {
                        return <span key={`blank-${index}`} />;
                    }

                    const iso = jalaliToIso(view.jy, view.jm, day);
                    const disabled = (min && iso < min) || (max && iso > max);

                    return (
                        <button
                            key={iso}
                            type="button"
                            className={`${iso === value ? 'is-selected' : ''} ${iso === today ? 'is-today' : ''}`}
                            disabled={Boolean(disabled)}
                            onClick={() => onSelect(iso)}
                            aria-label={formatDate(iso)}
                            aria-pressed={iso === value}
                        >
                            {toPersianDigits(day)}
                        </button>
                    );
                })}
            </div>
            <div className="jl-datepicker-foot">
                <button
                    type="button"
                    className="btn btn-soft btn-sm"
                    onClick={() => {
                        const now = toJalali(new Date().getFullYear(), new Date().getMonth() + 1, new Date().getDate());
                        setView({ jy: now.jy, jm: now.jm });

                        if ((!min || today >= min) && (!max || today <= max)) {
                            onSelect(today);
                        }
                    }}
                >
                    امروز
                </button>
                {onClear && (
                    <button type="button" className="btn btn-ghost btn-sm" onClick={onClear}>
                        پاک کردن
                    </button>
                )}
            </div>
        </div>
    );
}

/**
 * Date field that shows and picks Jalali dates while the form keeps Gregorian "YYYY-MM-DD"
 * (what Laravel's date rules expect).
 */
export default function JalaliDateInput({ form = null, name, label, value, onChange, error, hint = null, required = false, min = null, max = null, className = '', placeholder = 'انتخاب تاریخ' }) {
    const id = useId();
    const binding = useBinding({ form, name, value, onChange, error });
    const [open, setOpen] = useState(false);
    const rootRef = useRef(null);

    useEffect(() => {
        if (!open) {
            return undefined;
        }

        const onPointer = (event) => !rootRef.current?.contains(event.target) && setOpen(false);
        const onKey = (event) => {
            if (event.key === 'Escape') {
                event.stopPropagation();
                setOpen(false);
            }
        };

        document.addEventListener('mousedown', onPointer);
        document.addEventListener('keydown', onKey, true);

        return () => {
            document.removeEventListener('mousedown', onPointer);
            document.removeEventListener('keydown', onKey, true);
        };
    }, [open]);

    const current = binding.value ? String(binding.value).slice(0, 10) : '';

    return (
        <Field label={label} htmlFor={id} error={binding.error} hint={hint} required={required} className={className}>
            <div className="jl-input position-relative" ref={rootRef}>
                <i className="bi bi-calendar3 jl-input-icon" />
                <input
                    id={id}
                    readOnly
                    className={`form-control cursor-pointer ${binding.error ? 'is-invalid' : ''}`}
                    value={current ? formatDate(current) : ''}
                    placeholder={placeholder}
                    onClick={() => setOpen((state) => !state)}
                    onKeyDown={(event) => {
                        if (event.key === 'Enter' || event.key === ' ' || event.key === 'ArrowDown') {
                            event.preventDefault();
                            setOpen(true);
                        }
                    }}
                    aria-haspopup="dialog"
                    aria-expanded={open}
                    aria-invalid={Boolean(binding.error)}
                />
                {open && (
                    <div className="jl-datepicker" role="dialog" aria-label="انتخاب تاریخ">
                        <JalaliCalendar
                            value={current}
                            min={min}
                            max={max}
                            onSelect={(iso) => {
                                binding.onChange(iso);
                                setOpen(false);
                            }}
                            onClear={required ? null : () => {
                                binding.onChange('');
                                setOpen(false);
                            }}
                        />
                    </div>
                )}
            </div>
        </Field>
    );
}
