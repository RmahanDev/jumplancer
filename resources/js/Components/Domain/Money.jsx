import { formatMoney } from '../../lib/format';

/**
 * A ledger amount with its direction: "+۲٬۰۰۰٬۰۰۰" in green for money in, "−۵۰۰٬۰۰۰" for money out.
 * The sign is spelled out (not only colored) so it reads the same without color.
 */
export default function SignedAmount({ amount, unit = true }) {
    const value = Number(amount) || 0;
    const sign = value > 0 ? '+' : value < 0 ? '−' : '';

    return (
        <span className={`jl-money jl-signed ${value > 0 ? 'is-in' : value < 0 ? 'is-out' : ''}`} dir="rtl">
            <span dir="ltr">{sign}</span>
            {formatMoney(Math.abs(value), { unit })}
        </span>
    );
}
