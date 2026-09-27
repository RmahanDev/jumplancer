import ModalForm from '../../../Components/Form/ModalForm';
import NumberInput from '../../../Components/Form/NumberInput';
import Switch from '../../../Components/Form/Switch';
import TextInput from '../../../Components/Form/TextInput';
import Textarea from '../../../Components/Form/Textarea';
import PageHeader from '../../../Components/Panel/PageHeader';
import { Badge } from '../../../Components/UI/Badge';
import { confirm } from '../../../Components/UI/ConfirmDialog';
import EmptyState from '../../../Components/UI/EmptyState';
import { useModal } from '../../../hooks/useModal';
import { destroy, put } from '../../../lib/actions';
import { formatMoney, formatNumber } from '../../../lib/format';
import { fillRoute } from '../../../lib/text';

function PlanForm({ modal, routes }) {
    const plan = modal.record;
    const editing = Boolean(plan);

    return (
        <ModalForm
            open={modal.open}
            onClose={modal.close}
            title={editing ? `ویرایش پلن «${plan.name}»` : 'پلن جدید'}
            subtitle="کارفرما بعد از تمام شدن پروژه‌های رایگان، با خرید پلن پروژه‌ی جدید منتشر می‌کند."
            icon="bi-gem"
            method={editing ? 'put' : 'post'}
            url={editing ? fillRoute(routes.update, plan.id) : routes.store}
            initial={{
                name: plan?.name ?? '',
                price: plan?.price ?? '',
                project_quota: plan?.project_quota ?? '',
                duration_days: plan?.duration_days ?? '',
                description: plan?.description ?? '',
                is_active: plan?.is_active ?? true,
            }}
            transform={(data) => ({
                ...data,
                price: data.price === '' ? null : Number(data.price),
                project_quota: data.project_quota === '' ? null : Number(data.project_quota),
                duration_days: data.duration_days === '' ? null : Number(data.duration_days),
            })}
        >
            {(form) => (
                <div className="row g-3">
                    <TextInput form={form} name="name" label="نام پلن" required className="col-md-6" placeholder="مثلاً پلن رشد" />
                    <NumberInput form={form} name="price" label="قیمت" money required className="col-md-6" />
                    <NumberInput form={form} name="project_quota" label="سهمیه‌ی پروژه" unit="پروژه" className="col-md-6" hint="خالی یعنی نامحدود" />
                    <NumberInput form={form} name="duration_days" label="مدت اعتبار" unit="روز" className="col-md-6" hint="خالی یعنی بدون انقضا" />
                    <Textarea form={form} name="description" label="توضیح کوتاه" rows={3} maxLength={1000} className="col-12" />
                    <Switch form={form} name="is_active" className="col-12" label="فعال و قابل خرید" description="پلن غیرفعال برای خرید جدید نمایش داده نمی‌شود؛ اشتراک‌های فعلی سر جایشان می‌مانند." />
                </div>
            )}
        </ModalForm>
    );
}

export default function Index({ plans, routes }) {
    const editor = useModal();

    const toggle = (plan) =>
        put(fillRoute(routes.update, plan.id), {
            name: plan.name,
            price: plan.price,
            project_quota: plan.project_quota,
            duration_days: plan.duration_days,
            description: plan.description,
            is_active: !plan.is_active,
        });

    const remove = async (plan) => {
        const ok = await confirm({
            title: `حذف پلن «${plan.name}»؟`,
            message: plan.subscriptions_count ? 'این پلن مشترک دارد و حذف نمی‌شود؛ به‌جایش غیرفعالش کن.' : 'این پلن هنوز فروخته نشده و کامل حذف می‌شود.',
            confirmLabel: 'حذف پلن',
        });

        if (ok) {
            destroy(fillRoute(routes.destroy, plan.id));
        }
    };

    return (
        <>
            <PageHeader title="پلن‌های کارفرما" description="پروژه‌ی اول و دوم رایگان است؛ بعد از آن کارفرما از سهمیه‌ی پلن استفاده می‌کند." primary={{ label: 'پلن جدید', icon: 'bi-plus-lg', onClick: () => editor.show(null) }} />

            {plans.length === 0 ? (
                <EmptyState title="هنوز پلنی تعریف نشده" description="اولین پلن را بساز تا کارفرماها بتوانند پروژه‌های بیشتری منتشر کنند." />
            ) : (
                <div className="row g-3">
                    {plans.map((plan, index) => (
                        <div className="col-md-6 col-xl-4" key={plan.id}>
                            <article className={`jl-card jl-plan h-100 jl-rise jl-rise-${Math.min(index + 1, 8)} ${plan.is_active ? '' : 'is-muted'}`}>
                                <div className="jl-card-body d-flex flex-column h-100">
                                    <div className="d-flex align-items-start justify-content-between gap-2">
                                        <h2 className="h5 fw-bold mb-1">{plan.name}</h2>
                                        {plan.is_active ? <Badge tone="success">فعال</Badge> : <Badge tone="secondary">غیرفعال</Badge>}
                                    </div>
                                    <div className="jl-plan-price">
                                        {formatMoney(plan.price, { unit: false })}
                                        <span>تومان</span>
                                    </div>
                                    <ul className="jl-checklist mb-3">
                                        <li>
                                            <i className="bi bi-check-circle-fill" />
                                            {plan.project_quota ? `${formatNumber(plan.project_quota)} پروژه` : 'پروژه‌ی نامحدود'}
                                        </li>
                                        <li>
                                            <i className="bi bi-check-circle-fill" />
                                            {plan.duration_days ? `اعتبار ${formatNumber(plan.duration_days)} روزه` : 'بدون تاریخ انقضا'}
                                        </li>
                                        <li>
                                            <i className="bi bi-people" />
                                            {formatNumber(plan.subscriptions_count ?? 0)} خرید تا امروز
                                        </li>
                                    </ul>
                                    {plan.description && <p className="small text-muted-2">{plan.description}</p>}
                                    <div className="d-flex gap-2 mt-auto">
                                        <button type="button" className="btn btn-soft btn-sm flex-grow-1" onClick={() => editor.show(plan)}>
                                            <i className="bi bi-pencil" /> ویرایش
                                        </button>
                                        <button type="button" className="btn btn-ghost btn-sm" onClick={() => toggle(plan)}>
                                            {plan.is_active ? 'غیرفعال کن' : 'فعال کن'}
                                        </button>
                                        <button type="button" className="jl-icon-btn text-danger" onClick={() => remove(plan)} aria-label={`حذف ${plan.name}`} data-tip="حذف">
                                            <i className="bi bi-trash3" />
                                        </button>
                                    </div>
                                </div>
                            </article>
                        </div>
                    ))}
                </div>
            )}

            <PlanForm modal={editor} routes={routes} />
        </>
    );
}
