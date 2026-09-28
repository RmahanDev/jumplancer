import ModalForm from '../../../Components/Form/ModalForm';
import RadioCards from '../../../Components/Form/RadioCards';
import Select from '../../../Components/Form/Select';
import PageHeader from '../../../Components/Panel/PageHeader';
import { Badge, StatusBadge } from '../../../Components/UI/Badge';
import { confirm } from '../../../Components/UI/ConfirmDialog';
import EmptyState from '../../../Components/UI/EmptyState';
import { useModal } from '../../../hooks/useModal';
import { destroy } from '../../../lib/actions';
import { formatDate, formatMoney } from '../../../lib/format';
import { label } from '../../../lib/labels';
import { fillRoute } from '../../../lib/text';

const LEVEL_TEXT = {
    beginner: 'تازه شروع کرده‌ام و پروژه‌ی واقعی کمی دارم',
    junior: 'چند پروژه‌ی کوچک انجام داده‌ام',
    intermediate: 'مستقل پروژه‌ی متوسط تحویل می‌دهم',
    senior: 'پروژه‌ی بزرگ و تیمی را هدایت می‌کنم',
};

function levelIndex(levels, level) {
    return levels.indexOf(level);
}

function FieldForm({ modal, categories, taken, levels, rules, isFirst, routes }) {
    return (
        <ModalForm
            open={modal.open}
            onClose={modal.close}
            title="ثبت حوزه‌ی کاری"
            subtitle="فقط برای پروژه‌های حوزه‌های فعال می‌توانی پیشنهاد بفرستی."
            icon="bi-bullseye"
            size="lg"
            method="post"
            url={routes.store}
            initial={{ category_id: '', claimed_level: 'beginner' }}
            submitLabel="ثبت حوزه"
        >
            {(form) => {
                const examByLevel = levelIndex(levels, form.data.claimed_level) >= levelIndex(levels, rules.exam_from_level);
                const examRequired = !isFirst || examByLevel;

                return (
                    <div className="d-grid gap-3">
                        <Select
                            form={form}
                            name="category_id"
                            label="حوزه"
                            required
                            options={categories.map((category) => ({ value: category.id, label: category.name, disabled: taken.includes(category.id) }))}
                        />
                        <RadioCards
                            form={form}
                            name="claimed_level"
                            label="سطح تو در این حوزه"
                            columns={2}
                            options={levels.map((level) => ({ value: level, label: label('level', level), description: LEVEL_TEXT[level] }))}
                        />
                        <div className={`alert ${examRequired ? 'alert-warning' : 'alert-success'} d-flex gap-2 mb-0`}>
                            <i className={`bi ${examRequired ? 'bi-clipboard-check' : 'bi-lightning-charge'} mt-1`} />
                            <div className="small">
                                {examRequired ? (
                                    <>
                                        <strong>آزمون ورودی لازم است.</strong>{' '}
                                        {isFirst
                                            ? `سطح «${label('level', rules.exam_from_level)}» و بالاتر آزمون دارد؛ آزمون حوزه‌ی اول رایگان است.`
                                            : `برای حوزه‌های بعدی آزمون لازم است؛ هزینه: ${rules.extra_field_fee === null || rules.extra_field_fee === undefined ? 'به‌زودی اعلام می‌شود' : formatMoney(rules.extra_field_fee)}.`}{' '}
                                        بعد از قبولی، حوزه فعال می‌شود.
                                    </>
                                ) : (
                                    <>
                                        <strong>بدون آزمون فعال می‌شود.</strong> می‌توانی همین حالا برای پروژه‌هایش پیشنهاد بفرستی.
                                    </>
                                )}
                            </div>
                        </div>
                    </div>
                );
            }}
        </ModalForm>
    );
}

export default function Index({ fields, categories, levels, rules, routes }) {
    const creator = useModal();
    const taken = fields.map((field) => field.category_id);

    const remove = async (field) => {
        const ok = await confirm({
            title: `حذف حوزه‌ی «${field.category?.name}»؟`,
            message: 'دیگر نمی‌توانی برای پروژه‌های این حوزه پیشنهاد بفرستی. برای برگشت باید دوباره ثبتش کنی.',
            confirmLabel: 'حذف حوزه',
        });

        if (ok) {
            destroy(fillRoute(routes.destroy, field.id));
        }
    };

    return (
        <>
            <PageHeader
                title="حوزه‌های کاری"
                description="حوزه‌ی اصلی‌ات را انتخاب کن؛ حوزه‌های بعدی با آزمون ورودی فعال می‌شوند."
                primary={{ label: 'حوزه‌ی جدید', icon: 'bi-plus-lg', onClick: () => creator.show(null), disabled: taken.length >= categories.length }}
            />

            {fields.length === 0 ? (
                <EmptyState
                    icon="bi-bullseye"
                    title="هنوز حوزه‌ای ثبت نکرده‌ای"
                    description="اولین حوزه، حوزه‌ی اصلی توست و اگر سطحت پایین‌تر از حد آزمون باشد بلافاصله فعال می‌شود."
                    action={
                        <button type="button" className="btn btn-primary btn-sm" onClick={() => creator.show(null)}>
                            <i className="bi bi-plus-lg" /> ثبت حوزه‌ی اصلی
                        </button>
                    }
                />
            ) : (
                <div className="row g-3">
                    {fields.map((field, index) => (
                        <div className="col-md-6 col-xl-4" key={field.id}>
                            <article className={`jl-card h-100 jl-field-card is-${field.status} jl-rise jl-rise-${Math.min(index + 1, 8)}`}>
                                <div className="jl-card-body d-flex flex-column gap-2 h-100">
                                    <div className="d-flex align-items-start justify-content-between gap-2">
                                        <div>
                                            <h2 className="h6 fw-bold mb-1">{field.category?.name}</h2>
                                            <div className="d-flex flex-wrap gap-1">
                                                {field.is_primary && (
                                                    <Badge tone="accent" icon="bi-star-fill">
                                                        حوزه‌ی اصلی
                                                    </Badge>
                                                )}
                                                <StatusBadge group="level" value={field.claimed_level} />
                                            </div>
                                        </div>
                                        <StatusBadge group="fieldStatus" value={field.status} />
                                    </div>
                                    <div className="small text-muted">
                                        {field.status === 'active' && (field.verified_at ? `فعال از ${formatDate(field.verified_at)}` : 'فعال')}
                                        {field.status === 'pending_exam' && (field.exam_fee_required ? 'منتظر آزمون (با هزینه)' : 'منتظر آزمون رایگان')}
                                        {field.status === 'rejected' && 'در آزمون قبول نشدی؛ می‌توانی بعداً دوباره تلاش کنی.'}
                                    </div>
                                    {!field.is_primary && (
                                        <div className="mt-auto">
                                            <button type="button" className="btn btn-ghost btn-sm text-danger" onClick={() => remove(field)}>
                                                <i className="bi bi-trash3" /> حذف حوزه
                                            </button>
                                        </div>
                                    )}
                                </div>
                            </article>
                        </div>
                    ))}
                </div>
            )}

            <FieldForm modal={creator} categories={categories} taken={taken} levels={levels} rules={rules} isFirst={fields.length === 0} routes={routes} />
        </>
    );
}
