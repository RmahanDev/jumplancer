// Input normalization for Persian keyboards.

/** "۰۹۱۲" / "٠٩١٢" -> "0912" */
export function toLatinDigits(value) {
    return String(value ?? '')
        .replace(/[۰-۹]/g, (digit) => String(digit.charCodeAt(0) - 0x06f0))
        .replace(/[٠-٩]/g, (digit) => String(digit.charCodeAt(0) - 0x0660));
}

/** Arabic "ي/ك" -> Persian "ی/ک", Latin digits, lower case: for client-side matching. */
export function normalizeForSearch(value) {
    return toLatinDigits(value)
        .replace(/ي/g, 'ی')
        .replace(/ك/g, 'ک')
        .replace(/‌/g, ' ')
        .toLowerCase()
        .trim();
}

/** Keep only digits (for amounts typed with separators or Persian digits). */
export function digitsOnly(value) {
    return toLatinDigits(value).replace(/[^\d]/g, '');
}

/** Latin slug suggestion for categories and skills (Persian names produce an empty slug). */
export function slugify(value) {
    return String(value ?? '')
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9\s-]/g, '')
        .replace(/[\s_]+/g, '-')
        .replace(/-+/g, '-')
        .replace(/^-|-$/g, '');
}

/** Replace the ":id" placeholder of a route template coming from the server. */
export function fillRoute(template, id) {
    return String(template).replace(':id', encodeURIComponent(id));
}
