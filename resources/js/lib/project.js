import { formatCompact, formatMoney } from './format';

/** "۵٬۰۰۰٬۰۰۰ تا ۸٬۰۰۰٬۰۰۰ تومان" / "۲۵۰٬۰۰۰ تومان (ساعتی)" / "توافقی" */
export function budgetText(project) {
    const unit = project.budget_type === 'hourly' ? ' (ساعتی)' : '';

    if (project.budget_min && project.budget_max && project.budget_min !== project.budget_max) {
        return `${formatMoney(project.budget_min, { unit: false })} تا ${formatMoney(project.budget_max)}${unit}`;
    }

    const single = project.budget_max ?? project.budget_min;

    return single ? `${formatMoney(single)}${unit}` : 'توافقی';
}

/** Short budget for tight table cells: "۲٫۵ تا ۴ میلیون تومان" (full text belongs in a title). */
export function budgetShort(project) {
    const unit = project.budget_type === 'hourly' ? ' ساعتی' : '';
    const [min, max] = [project.budget_min, project.budget_max];

    if (min && max && min !== max) {
        const [minText, maxText] = [formatCompact(min), formatCompact(max)];
        const [minNumber, minWord] = minText.split(' ');
        const [maxNumber, maxWord] = maxText.split(' ');

        return minWord && minWord === maxWord ? `${minNumber} تا ${maxNumber} ${maxWord} تومان${unit}` : `${minText} تا ${maxText} تومان${unit}`;
    }

    const single = max ?? min;

    return single ? `${formatCompact(single)} تومان${unit}` : 'توافقی';
}

/**
 * Sub-categories grouped under their parent, for <Select> options.
 * Projects must sit in a sub-category (`subcategoriesOnly`); other forms, such as the
 * portfolio, may also use a main category that has no sub-categories yet.
 */
export function categoryOptions(categories, { subcategoriesOnly = false } = {}) {
    return categories.flatMap((parent) => {
        const children = parent.children ?? [];

        if (children.length > 0) {
            return children.map((child) => ({ value: child.id, label: child.name, group: parent.name }));
        }

        return subcategoriesOnly ? [] : [{ value: parent.id, label: parent.name, group: 'بدون زیردسته' }];
    });
}
