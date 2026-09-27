import { useEffect, useState } from 'react';

const LEVELS = [
    { label: 'خیلی ضعیف', color: 'var(--bs-danger)' },
    { label: 'ضعیف', color: 'var(--bs-danger)' },
    { label: 'متوسط', color: 'var(--jl-accent)' },
    { label: 'خوب', color: 'var(--bs-success)' },
    { label: 'عالی', color: 'var(--bs-success)' },
];

function score(password) {
    if (!password) {
        return -1;
    }

    let points = 0;
    points += password.length >= 8 ? 1 : 0;
    points += password.length >= 12 ? 1 : 0;
    points += /[a-z]/i.test(password) && /\d/.test(password) ? 1 : 0;
    points += /[A-Z]/.test(password) && /[a-z]/.test(password) ? 1 : 0;
    points += /[^A-Za-z0-9]/.test(password) ? 1 : 0;

    return Math.min(4, points);
}

/**
 * Password strength meter. As a React island on the Blade sign-up form it watches an input by
 * id (`inputId`); inside React forms it takes the value directly (`password`).
 * The server rule (Password::defaults) is at least 8 characters with letters and numbers.
 */
export default function PasswordStrength({ inputId = null, password = null }) {
    const [watched, setValue] = useState('');
    const value = password ?? watched;

    useEffect(() => {
        if (!inputId) {
            return undefined;
        }

        const input = document.getElementById(inputId);

        if (!input) {
            return undefined;
        }

        const update = () => setValue(input.value);
        input.addEventListener('input', update);

        return () => input.removeEventListener('input', update);
    }, [inputId]);

    const level = score(value);

    if (level < 0) {
        return <div className="jl-strength-label">حداقل ۸ کاراکتر، شامل حرف و عدد</div>;
    }

    return (
        <div aria-live="polite">
            <div className="jl-strength">
                {[0, 1, 2, 3].map((index) => (
                    <span key={index} style={index < Math.max(1, level) ? { background: LEVELS[level].color } : undefined} />
                ))}
            </div>
            <div className="jl-strength-label">قدرت رمز: {LEVELS[level].label}</div>
        </div>
    );
}
