// Jalali (Solar Hijri) <-> Gregorian conversion, the jalaali-js algorithm (Borkowski's breaks table).
// Used by the date picker; display formatting uses Intl's built-in Persian calendar.

const BREAKS = [-61, 9, 38, 199, 426, 686, 756, 818, 1111, 1181, 1210, 1635, 2060, 2097, 2192, 2262, 2324, 2394, 2456, 3178];

const div = (a, b) => ~~(a / b);
const mod = (a, b) => a - ~~(a / b) * b;

function jalCal(jy, withoutLeap) {
    const gy = jy + 621;
    let leapJ = -14;
    let jp = BREAKS[0];
    let jump = 0;

    if (jy < jp || jy >= BREAKS[BREAKS.length - 1]) {
        throw new RangeError(`Invalid Jalali year ${jy}`);
    }

    for (let i = 1; i < BREAKS.length; i += 1) {
        const jm = BREAKS[i];
        jump = jm - jp;

        if (jy < jm) {
            break;
        }

        leapJ = leapJ + div(jump, 33) * 8 + div(mod(jump, 33), 4);
        jp = jm;
    }

    let n = jy - jp;
    leapJ = leapJ + div(n, 33) * 8 + div(mod(n, 33) + 3, 4);

    if (mod(jump, 33) === 4 && jump - n === 4) {
        leapJ += 1;
    }

    const leapG = div(gy, 4) - div((div(gy, 100) + 1) * 3, 4) - 150;
    const march = 20 + leapJ - leapG;

    if (withoutLeap) {
        return { gy, march };
    }

    if (jump - n < 6) {
        n = n - jump + div(jump + 4, 33) * 33;
    }

    let leap = mod(mod(n + 1, 33) - 1, 4);

    if (leap === -1) {
        leap = 4;
    }

    return { leap, gy, march };
}

function g2d(gy, gm, gd) {
    const d = div((gy + div(gm - 8, 6) + 100100) * 1461, 4) + div(153 * mod(gm + 9, 12) + 2, 5) + gd - 34840408;

    return d - div(div(gy + 100100 + div(gm - 8, 6), 100) * 3, 4) + 752;
}

function d2g(jdn) {
    let j = 4 * jdn + 139361631;
    j = j + div(div(4 * jdn + 183187720, 146097) * 3, 4) * 4 - 3908;
    const i = div(mod(j, 1461), 4) * 5 + 308;
    const gd = div(mod(i, 153), 5) + 1;
    const gm = mod(div(i, 153), 12) + 1;
    const gy = div(j, 1461) - 100100 + div(8 - gm, 6);

    return { gy, gm, gd };
}

function j2d(jy, jm, jd) {
    const r = jalCal(jy, true);

    return g2d(r.gy, 3, r.march) + (jm - 1) * 31 - div(jm, 7) * (jm - 7) + jd - 1;
}

function d2j(jdn) {
    const { gy } = d2g(jdn);
    let jy = gy - 621;
    const r = jalCal(jy, false);
    const jdn1f = g2d(gy, 3, r.march);
    let k = jdn - jdn1f;

    if (k >= 0) {
        if (k <= 185) {
            return { jy, jm: 1 + div(k, 31), jd: mod(k, 31) + 1 };
        }

        k -= 186;
    } else {
        jy -= 1;
        k += 179;

        if (r.leap === 1) {
            k += 1;
        }
    }

    return { jy, jm: 7 + div(k, 30), jd: mod(k, 30) + 1 };
}

export function toJalali(gy, gm, gd) {
    return d2j(g2d(gy, gm, gd));
}

export function toGregorian(jy, jm, jd) {
    return d2g(j2d(jy, jm, jd));
}

export function isLeapJalaliYear(jy) {
    return jalCal(jy, false).leap === 0;
}

export function jalaliMonthLength(jy, jm) {
    if (jm <= 6) {
        return 31;
    }

    if (jm <= 11) {
        return 30;
    }

    return isLeapJalaliYear(jy) ? 30 : 29;
}

/** Weekday of a Jalali date, 0 = Saturday (the first day of the Persian week). */
export function jalaliWeekday(jy, jm, jd) {
    const { gy, gm, gd } = toGregorian(jy, jm, jd);

    return (new Date(gy, gm - 1, gd).getDay() + 1) % 7;
}

/** "2026-09-27" -> { jy, jm, jd } */
export function isoToJalali(iso) {
    const [gy, gm, gd] = iso.slice(0, 10).split('-').map(Number);

    return toJalali(gy, gm, gd);
}

/** { jy, jm, jd } -> "2026-09-27" */
export function jalaliToIso(jy, jm, jd) {
    const { gy, gm, gd } = toGregorian(jy, jm, jd);

    return `${gy}-${String(gm).padStart(2, '0')}-${String(gd).padStart(2, '0')}`;
}

export const JALALI_MONTHS = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];

export const JALALI_WEEKDAYS = ['ش', 'ی', 'د', 'س', 'چ', 'پ', 'ج'];
