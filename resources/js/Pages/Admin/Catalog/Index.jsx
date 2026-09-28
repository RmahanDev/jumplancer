import { useMemo, useState } from 'react';
import ModalForm from '../../../Components/Form/ModalForm';
import NumberInput from '../../../Components/Form/NumberInput';
import RadioCards from '../../../Components/Form/RadioCards';
import Select from '../../../Components/Form/Select';
import Switch from '../../../Components/Form/Switch';
import TextInput from '../../../Components/Form/TextInput';
import PageHeader from '../../../Components/Panel/PageHeader';
import { Badge } from '../../../Components/UI/Badge';
import { confirm } from '../../../Components/UI/ConfirmDialog';
import { DropdownDivider, DropdownItem } from '../../../Components/UI/Dropdown';
import EmptyState from '../../../Components/UI/EmptyState';
import RowActions from '../../../Components/UI/RowActions';
import SearchInput from '../../../Components/UI/SearchInput';
import { useModal } from '../../../hooks/useModal';
import { destroy } from '../../../lib/actions';
import { formatCompact, formatNumber } from '../../../lib/format';
import { label } from '../../../lib/labels';
import { fillRoute, normalizeForSearch, slugify } from '../../../lib/text';

function rangeText(range) {
    const min = range.min_amount ? formatCompact(range.min_amount) : '۰';
    const max = range.max_amount ? formatCompact(range.max_amount) : 'بی‌سقف';

    return `${label('budgetType', range.budget_type)}: ${min} تا ${max}`;
}

function BudgetChips({ ranges }) {
    if (!ranges?.length) {
        return <span className="small text-muted">بازه‌ی بودجه تعریف نشده (از دسته‌ی اصلی پیروی می‌کند)</span>;
    }

    return (
        <div className="d-flex flex-wrap gap-1">
            {ranges.map((range) => (
                <span key={range.budget_type} className={`jl-chip ${range.is_active ? '' : 'opacity-50'}`} title={range.is_active ? undefined : 'غیرفعال'}>
                    <i className="bi bi-cash-coin" />
                    {rangeText(range)}
                </span>
            ))}
        </div>
    );
}

function CategoryForm({ modal, categories, routes }) {
    const { category, parentId } = modal.record ?? {};
    const editing = Boolean(category);

    return (
        <ModalForm
            open={modal.open}
            onClose={modal.close}
            title={editing ? `ویرایش «${category.name}»` : parentId ? 'زیردسته‌ی جدید' : 'دسته‌ی جدید'}
            subtitle="دسته‌ها دو سطح دارند: دسته‌ی اصلی و زیردسته. مهارت‌ها و پروژه‌ها به زیردسته وصل می‌شوند."
            icon="bi-diagram-3"
            method={editing ? 'put' : 'post'}
            url={editing ? fillRoute(routes.update, category.id) : routes.store}
            initial={{
                name: category?.name ?? '',
                slug: category?.slug ?? '',
                parent_id: category?.parent_id ?? parentId ?? '',
                sort_order: category?.sort_order ?? 0,
            }}
            transform={(data) => ({ ...data, parent_id: data.parent_id || null, sort_order: data.sort_order === '' ? 0 : Number(data.sort_order) })}
        >
            {(form) => (
                <div className="row g-3">
                    <TextInput form={form} name="name" label="نام" required className="col-md-7" placeholder="مثلاً طراحی وب" />
                    <TextInput
                        form={form}
                        name="slug"
                        label="نامک (slug)"
                        required
                        ltr
                        className="col-md-5"
                        placeholder="web-design"
                        hint="حروف کوچک انگلیسی و خط تیره"
                        onBlur={() => !form.data.slug && form.setData('slug', slugify(form.data.name))}
                    />
                    <Select
                        form={form}
                        name="parent_id"
                        label="زیرمجموعه‌ی"
                        className="col-md-7"
                        placeholder="— دسته‌ی اصلی —"
                        options={categories.filter((item) => item.id !== category?.id).map((item) => ({ value: item.id, label: item.name }))}
                    />
                    <NumberInput form={form} name="sort_order" label="ترتیب نمایش" className="col-md-5" hint="عدد کوچک‌تر، بالاتر" />
                </div>
            )}
        </ModalForm>
    );
}

function SkillForm({ modal, subcategories, routes }) {
    const { skill, categoryId } = modal.record ?? {};
    const editing = Boolean(skill);

    return (
        <ModalForm
            open={modal.open}
            onClose={modal.close}
            title={editing ? `ویرایش مهارت «${skill.name}»` : 'مهارت جدید'}
            icon="bi-tags"
            size="sm"
            method={editing ? 'put' : 'post'}
            url={editing ? fillRoute(routes.skillUpdate, skill.id) : routes.skillStore}
            initial={{ category_id: skill?.category_id ?? categoryId ?? '', name: skill?.name ?? '', slug: skill?.slug ?? '' }}
        >
            {(form) => (
                <div className="d-grid gap-3">
                    <Select form={form} name="category_id" label="زیردسته" required options={subcategories} />
                    <TextInput form={form} name="name" label="نام مهارت" required placeholder="مثلاً Laravel" onBlur={() => !form.data.slug && form.setData('slug', slugify(form.data.name))} />
                    <TextInput form={form} name="slug" label="نامک (slug)" required ltr placeholder="laravel" />
                </div>
            )}
        </ModalForm>
    );
}

function BudgetForm({ modal, budgetTypes, routes }) {
    const category = modal.record;
    const rangeFor = (type) => category?.budget_ranges?.find((range) => range.budget_type === type);
    const first = rangeFor('fixed') ?? category?.budget_ranges?.[0];

    return (
        <ModalForm
            open={modal.open}
            onClose={modal.close}
            title={`بازه‌ی بودجه‌ی «${category?.name ?? ''}»`}
            subtitle="پروژه‌ی زیر حداقل ثبت نمی‌شود. بازه‌ی زیردسته بر بازه‌ی دسته‌ی اصلی مقدم است."
            icon="bi-cash-coin"
            method="put"
            url={category ? fillRoute(routes.budgetRange, category.id) : ''}
            initial={{
                budget_type: first?.budget_type ?? 'fixed',
                min_amount: first?.min_amount ?? '',
                max_amount: first?.max_amount ?? '',
                is_active: first?.is_active ?? true,
            }}
            transform={(data) => ({ ...data, min_amount: Number(data.min_amount || 0), max_amount: data.max_amount === '' ? null : Number(data.max_amount) })}
        >
            {(form) => (
                <div className="row g-3">
                    <RadioCards
                        name="budget_type"
                        label="نوع بودجه"
                        className="col-12"
                        columns={2}
                        value={form.data.budget_type}
                        error={form.errors.budget_type}
                        options={budgetTypes.map((type) => ({ value: type, label: label('budgetType', type), icon: type === 'hourly' ? 'bi-clock' : 'bi-cash' }))}
                        onChange={(type) => {
                            // Switching the type loads the range already saved for it.
                            const range = rangeFor(type);
                            form.setData({ budget_type: type, min_amount: range?.min_amount ?? '', max_amount: range?.max_amount ?? '', is_active: range?.is_active ?? true });
                        }}
                    />
                    <NumberInput form={form} name="min_amount" label="حداقل" money required className="col-md-6" />
                    <NumberInput form={form} name="max_amount" label="حداکثر" money className="col-md-6" hint="خالی یعنی بی‌سقف" />
                    <Switch form={form} name="is_active" className="col-12" label="فعال" description="غیرفعال یعنی این بازه موقتاً اعمال نمی‌شود." />
                </div>
            )}
        </ModalForm>
    );
}

export default function Index({ categories, budgetTypes, routes }) {
    const [search, setSearch] = useState('');
    const categoryEditor = useModal();
    const skillEditor = useModal();
    const budgetEditor = useModal();

    const subcategories = categories.flatMap((parent) => (parent.children ?? []).map((child) => ({ value: child.id, label: child.name, group: parent.name })));

    const visible = useMemo(() => {
        const query = normalizeForSearch(search);

        if (!query) {
            return categories;
        }

        const matches = (text) => normalizeForSearch(text).includes(query);

        return categories
            .map((parent) => ({
                ...parent,
                children: (parent.children ?? []).filter((child) => matches(parent.name) || matches(child.name) || (child.skills ?? []).some((skill) => matches(skill.name) || matches(skill.slug))),
            }))
            .filter((parent) => matches(parent.name) || parent.children.length > 0);
    }, [categories, search]);

    const removeCategory = async (category) => {
        const ok = await confirm({
            title: `حذف «${category.name}»؟`,
            message: 'فقط دسته‌ی خالی (بدون زیردسته، مهارت و پروژه) حذف می‌شود.',
            confirmLabel: 'حذف دسته',
        });

        if (ok) {
            destroy(fillRoute(routes.destroy, category.id));
        }
    };

    const removeSkill = async (skill) => {
        const ok = await confirm({
            title: `حذف مهارت «${skill.name}»؟`,
            message: 'از پروژه‌ها، پروفایل فریلنسرها و نمونه‌کارها هم برداشته می‌شود.',
            confirmLabel: 'حذف مهارت',
        });

        if (ok) {
            destroy(fillRoute(routes.skillDestroy, skill.id));
        }
    };

    const skillCount = categories.reduce((total, parent) => total + (parent.children ?? []).reduce((sum, child) => sum + (child.skills?.length ?? 0), 0), 0);

    return (
        <>
            <PageHeader
                title="دسته‌ها و مهارت‌ها"
                description={`${formatNumber(categories.length)} دسته‌ی اصلی، ${formatNumber(subcategories.length)} زیردسته و ${formatNumber(skillCount)} مهارت`}
                primary={{ label: 'دسته‌ی جدید', icon: 'bi-folder-plus', onClick: () => categoryEditor.show({}) }}
                actions={
                    <button type="button" className="btn btn-soft" onClick={() => skillEditor.show({})} disabled={subcategories.length === 0}>
                        <i className="bi bi-tag" /> مهارت جدید
                    </button>
                }
            />

            <div className="mb-3 jl-rise">
                <SearchInput value={search} onChange={setSearch} placeholder="جستجوی دسته یا مهارت..." />
            </div>

            {visible.length === 0 ? (
                <EmptyState title={search ? 'چیزی پیدا نشد' : 'هنوز دسته‌ای نساخته‌ای'} action={!search && <button type="button" className="btn btn-primary btn-sm" onClick={() => categoryEditor.show({})}>ساخت اولین دسته</button>} />
            ) : (
                <div className="d-grid gap-3">
                    {visible.map((parent, index) => (
                        <section key={parent.id} className={`jl-card jl-rise jl-rise-${Math.min(index + 1, 8)}`}>
                            <header className="jl-card-header">
                                <div className="min-w-0">
                                    <h2 className="d-flex align-items-center gap-2">
                                        <i className="bi bi-folder2-open text-accent" />
                                        {parent.name}
                                        <code className="small fw-normal">{parent.slug}</code>
                                    </h2>
                                    <p>
                                        {formatNumber(parent.children?.length ?? 0)} زیردسته · {formatNumber(parent.projects_count ?? 0)} پروژه‌ی مستقیم
                                    </p>
                                    <div className="mt-2">
                                        <BudgetChips ranges={parent.budget_ranges} />
                                    </div>
                                </div>
                                <div className="d-flex gap-1 align-self-start">
                                    <button type="button" className="btn btn-soft btn-sm" onClick={() => categoryEditor.show({ parentId: parent.id })}>
                                        <i className="bi bi-plus-lg" /> زیردسته
                                    </button>
                                    <RowActions>
                                        {(close) => (
                                            <>
                                                <DropdownItem icon="bi-pencil" onClick={() => { close(); categoryEditor.show({ category: parent }); }}>
                                                    ویرایش دسته
                                                </DropdownItem>
                                                <DropdownItem icon="bi-cash-coin" onClick={() => { close(); budgetEditor.show(parent); }}>
                                                    بازه‌ی بودجه
                                                </DropdownItem>
                                                <DropdownDivider />
                                                <DropdownItem icon="bi-trash3" danger onClick={() => { close(); removeCategory(parent); }}>
                                                    حذف
                                                </DropdownItem>
                                            </>
                                        )}
                                    </RowActions>
                                </div>
                            </header>

                            {(parent.children ?? []).length > 0 && (
                                <ul className="jl-subcategories">
                                    {parent.children.map((child) => (
                                        <li key={child.id}>
                                            <div className="d-flex flex-wrap align-items-start justify-content-between gap-2">
                                                <div className="jl-row-main">
                                                    <div className="fw-semibold">
                                                        {child.name} <code className="small fw-normal">{child.slug}</code>
                                                        <Badge tone="secondary" className="ms-2">
                                                            {formatNumber(child.projects_count ?? 0)} پروژه
                                                        </Badge>
                                                    </div>
                                                    <div className="mt-1">
                                                        <BudgetChips ranges={child.budget_ranges} />
                                                    </div>
                                                </div>
                                                <RowActions>
                                                    {(close) => (
                                                        <>
                                                            <DropdownItem icon="bi-tag" onClick={() => { close(); skillEditor.show({ categoryId: child.id }); }}>
                                                                افزودن مهارت
                                                            </DropdownItem>
                                                            <DropdownItem icon="bi-cash-coin" onClick={() => { close(); budgetEditor.show(child); }}>
                                                                بازه‌ی بودجه
                                                            </DropdownItem>
                                                            <DropdownItem icon="bi-pencil" onClick={() => { close(); categoryEditor.show({ category: child }); }}>
                                                                ویرایش
                                                            </DropdownItem>
                                                            <DropdownDivider />
                                                            <DropdownItem icon="bi-trash3" danger onClick={() => { close(); removeCategory(child); }}>
                                                                حذف
                                                            </DropdownItem>
                                                        </>
                                                    )}
                                                </RowActions>
                                            </div>
                                            <div className="d-flex flex-wrap gap-1 mt-2">
                                                {(child.skills ?? []).map((skill) => (
                                                    <span key={skill.id} className="jl-chip jl-chip-action">
                                                        <button type="button" className="jl-chip-main" onClick={() => skillEditor.show({ skill })} title="ویرایش مهارت">
                                                            {skill.name}
                                                        </button>
                                                        <button type="button" className="jl-chip-remove" onClick={() => removeSkill(skill)} aria-label={`حذف ${skill.name}`}>
                                                            <i className="bi bi-x" />
                                                        </button>
                                                    </span>
                                                ))}
                                                <button type="button" className="jl-chip jl-chip-add" onClick={() => skillEditor.show({ categoryId: child.id })}>
                                                    <i className="bi bi-plus" /> مهارت
                                                </button>
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </section>
                    ))}
                </div>
            )}

            <CategoryForm modal={categoryEditor} categories={categories} routes={routes} />
            <SkillForm modal={skillEditor} subcategories={subcategories} routes={routes} />
            <BudgetForm modal={budgetEditor} budgetTypes={budgetTypes} routes={routes} />
        </>
    );
}
