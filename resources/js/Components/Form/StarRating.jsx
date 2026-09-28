import { useState } from 'react';
import Field, { useBinding } from './Field';
import { formatNumber } from '../../lib/format';

const WORDS = ['', 'ضعیف', 'متوسط', 'خوب', 'خیلی خوب', 'عالی'];

/** 1–5 stars; arrow keys work too. The form value is a number. */
export default function StarRating({ form = null, name, label, value, onChange, error, hint = null, className = '' }) {
    const binding = useBinding({ form, name, value, onChange, error });
    const [hover, setHover] = useState(0);
    const current = Number(binding.value) || 0;
    const shown = hover || current;

    return (
        <Field label={label} error={binding.error} hint={hint} className={className}>
            <div
                className="jl-stars"
                role="radiogroup"
                aria-label={label}
                onMouseLeave={() => setHover(0)}
                onKeyDown={(event) => {
                    const forward = document.documentElement.dir === 'rtl' ? 'ArrowLeft' : 'ArrowRight';
                    const backward = document.documentElement.dir === 'rtl' ? 'ArrowRight' : 'ArrowLeft';

                    if (event.key === forward || event.key === backward) {
                        event.preventDefault();
                        binding.onChange(Math.max(1, Math.min(5, current + (event.key === forward ? 1 : -1))));
                    }
                }}
            >
                {[1, 2, 3, 4, 5].map((star) => (
                    <button
                        key={star}
                        type="button"
                        role="radio"
                        aria-checked={current === star}
                        aria-label={`${formatNumber(star)} ستاره — ${WORDS[star]}`}
                        tabIndex={current === star || (current === 0 && star === 1) ? 0 : -1}
                        className={`jl-star ${star <= shown ? 'is-on' : ''}`}
                        onMouseEnter={() => setHover(star)}
                        onClick={() => binding.onChange(star)}
                    >
                        <i className={`bi ${star <= shown ? 'bi-star-fill' : 'bi-star'}`} />
                    </button>
                ))}
                <span className="jl-stars-word">{WORDS[shown]}</span>
            </div>
        </Field>
    );
}
