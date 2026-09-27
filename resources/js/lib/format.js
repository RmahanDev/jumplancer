// Persian display formatting: digits, Toman amounts, Jalali dates and relative times.
const numberFormat = new Intl.NumberFormat('fa-IR');
const compactFormat = new Intl.NumberFormat('fa-IR', { maximumFractionDigits: 1 });
const dateFormat = new Intl.DateTimeFormat('fa-IR-u-ca-persian', { year: 'numeric', month: 'long', day: 'numeric' });
const shortDateFormat = new Intl.DateTimeFormat('fa-IR-u-ca-persian', { month: 'short', day: 'numeric' });
const dateTimeFormat = new Intl.DateTimeFormat('fa-IR-u-ca-persian', { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' });
const timeFormat = new Intl.DateTimeFormat('fa-IR', { hour: '2-digit', minute: '2-digit' });
const monthFormat = new Intl.DateTimeFormat('fa-IR-u-ca-persian', { month: 'long' });
const weekdayFormat = new Intl.DateTimeFormat('fa-IR-u-ca-persian', { weekday: 'long' });
const relativeFormat = new Intl.RelativeTimeFormat('fa', { numeric: 'auto' });

const PERSIAN_DIGITS = '۰۱۲۳۴۵۶۷۸۹';

export function toPersianDigits(value) {
    return String(value ?? '').replace(/\d/g, (digit) => PERSIAN_DIGITS[digit]);
}

export function formatNumber(value) {
    if (value === null || value === undefined || value === '' || Number.isNaN(Number(value))) {
        return '—';
    }

    return numberFormat.format(Number(value));
}

export function formatMoney(value, { unit = true } = {}) {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    return unit ? `${formatNumber(value)} تومان` : formatNumber(value);
}

/** 12_500_000 -> "۱۲٫۵ میلیون" (for chart axes and tight spaces). */
export function formatCompact(value) {
    const number = Number(value ?? 0);
    const absolute = Math.abs(number);

    if (absolute >= 1e9) {
        return `${compactFormat.format(number / 1e9)} میلیارد`;
    }

    if (absolute >= 1e6) {
        return `${compactFormat.format(number / 1e6)} میلیون`;
    }

    if (absolute >= 1e3) {
        return `${compactFormat.format(number / 1e3)} هزار`;
    }

    return formatNumber(number);
}

function toDate(value) {
    if (!value) {
        return null;
    }

    // "2026-09-27" (a date without time) is a calendar day, not UTC midnight.
    const date = /^\d{4}-\d{2}-\d{2}$/.test(value) ? new Date(`${value}T12:00:00`) : new Date(value);

    return Number.isNaN(date.getTime()) ? null : date;
}

export function formatDate(value) {
    const date = toDate(value);

    return date ? dateFormat.format(date) : '—';
}

export function formatShortDate(value) {
    const date = toDate(value);

    return date ? shortDateFormat.format(date) : '—';
}

export function formatDateTime(value) {
    const date = toDate(value);

    return date ? dateTimeFormat.format(date) : '—';
}

export function formatTime(value) {
    const date = toDate(value);

    return date ? timeFormat.format(date) : '—';
}

export function formatMonth(value) {
    const date = toDate(value);

    return date ? monthFormat.format(date) : '—';
}

export function formatWeekday(value) {
    const date = toDate(value);

    return date ? weekdayFormat.format(date) : '—';
}

const RELATIVE_STEPS = [
    ['year', 60 * 60 * 24 * 365],
    ['month', 60 * 60 * 24 * 30],
    ['week', 60 * 60 * 24 * 7],
    ['day', 60 * 60 * 24],
    ['hour', 60 * 60],
    ['minute', 60],
];

/** "۳ ساعت پیش", "فردا", "۲ هفته بعد" */
export function formatRelative(value) {
    const date = toDate(value);

    if (!date) {
        return '—';
    }

    const seconds = Math.round((date.getTime() - Date.now()) / 1000);

    for (const [unit, size] of RELATIVE_STEPS) {
        if (Math.abs(seconds) >= size) {
            return relativeFormat.format(Math.round(seconds / size), unit);
        }
    }

    return 'همین حالا';
}

export function formatBytes(bytes) {
    const value = Number(bytes ?? 0);

    if (value >= 1024 * 1024) {
        return `${compactFormat.format(value / 1024 / 1024)} مگابایت`;
    }

    return `${compactFormat.format(value / 1024)} کیلوبایت`;
}

/** First letters of a Persian or Latin name for avatars. */
export function initials(name) {
    const parts = String(name ?? '').trim().split(/\s+/).filter(Boolean);

    if (parts.length === 0) {
        return '؟';
    }

    return parts.length === 1 ? parts[0].slice(0, 2) : `${parts[0][0]}‌${parts[parts.length - 1][0]}`;
}
