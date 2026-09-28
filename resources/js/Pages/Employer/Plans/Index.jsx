import { Link, router } from '@inertiajs/react';
import PostingBanner from '../../../Components/Domain/PostingBanner';
import PageHeader from '../../../Components/Panel/PageHeader';
import { Badge, StatusBadge } from '../../../Components/UI/Badge';
import { confirm } from '../../../Components/UI/ConfirmDialog';
import EmptyState from '../../../Components/UI/EmptyState';
import Meter from '../../../Components/UI/Meter';
import { post } from '../../../lib/actions';
import { formatDate, formatMoney, formatNumber, formatRelative } from '../../../lib/format';
import { fillRoute } from '../../../lib/text';

export default function Index({ plans, subscriptions, free, next, balance, routes }) {
    const buy = async (plan) => {
        if (Number(plan.price) > Number(balance)) {
            const ok = await confirm({
                title: 'موجودی کافی نیست',
                message: `برای خرید «${plan.name}» ${formatMoney(Number(plan.price) - Number(balance))} دیگر لازم داری.`,
                confirmLabel: 'شارژ کیف پول',
                tone: 'primary',
                icon: 'bi-wallet2',
            });

            if (ok) {
                router.visit(routes.wallet);
            }

            return;
        }

        const ok = await confirm({
            title: `خرید پلن «${plan.name}»؟`,
            message: `${formatMoney(plan.price)} از کیف پولت کم می‌شود و پلن بلافاصله فعال می‌شود.`,
            confirmLabel: 'خرید',
            tone: 'primary',
            icon: 'bi-gem',
        });

        if (ok) {
            post(fillRoute(routes.subscribe, plan.id));
        }
    };

    return (
        <>
            <PageHeader
                title="پلن و اشتراک"
                description="پروژه‌های اول رایگان‌اند؛ بعد از آن با خرید پلن، پروژه‌های بیشتری منتشر کن."
                actions={
                    <Link href={routes.wallet} className="btn btn-ghost">
                        <i className="bi bi-wallet2" /> موجودی: {formatMoney(balance)}
                    </Link>
                }
            />

            <PostingBanner next={next} plansUrl="#plans" secondFreeUntil={free.second_free_until} subscription={subscriptions.find((subscription) => subscription.status === 'active')} />

            <div className="row g-3 mb-4">
                <div className="col-md-6">
                    <section className="jl-card h-100 jl-rise">
                        <div className="jl-card-body">
                            <h2 className="h6 fw-bold">پروژه‌های رایگان</h2>
                            <div className="d-flex justify-content-between small mb-1">
                                <span className="text-muted">استفاده‌شده</span>
                                <strong>
                                    {formatNumber(Math.min(free.used, free.limit))} از {formatNumber(free.limit)}
                                </strong>
                            </div>
                            <Meter value={free.used} max={Math.max(free.limit, 1)} tone="accent" label="پروژه‌های رایگان استفاده‌شده" />
                            <ul className="jl-checklist mt-3 mb-0">
                                <li className={free.used >= 1 ? 'is-done' : ''}>
                                    <i className={`bi ${free.used >= 1 ? 'bi-check-circle-fill' : 'bi-circle'}`} />
                                    پروژه‌ی اول {free.first_free_project_at && <span className="text-muted small">({formatDate(free.first_free_project_at)})</span>}
                                </li>
                                <li className={free.used >= 2 ? 'is-done' : ''}>
                                    <i className={`bi ${free.used >= 2 ? 'bi-check-circle-fill' : 'bi-circle'}`} />
                                    پروژه‌ی دوم
                                    {free.used < 2 && free.second_free_until && (
                                        <span className="text-muted small">
                                            {' '}
                                            (رایگان تا {formatDate(free.second_free_until)} — {formatRelative(free.second_free_until)})
                                        </span>
                                    )}
                                </li>
                            </ul>
                        </div>
                    </section>
                </div>
                <div className="col-md-6">
                    <section className="jl-card h-100 jl-rise jl-rise-2">
                        <div className="jl-card-body">
                            <h2 className="h6 fw-bold">اشتراک‌های من</h2>
                            {subscriptions.length === 0 ? (
                                <p className="text-muted small mb-0">هنوز پلنی نخریده‌ای.</p>
                            ) : (
                                <ul className="jl-list">
                                    {subscriptions.map((subscription) => (
                                        <li key={subscription.id}>
                                            <div className="min-w-0 flex-grow-1">
                                                <div className="fw-semibold">{subscription.plan?.name}</div>
                                                <div className="small text-muted">
                                                    {formatNumber(subscription.projects_used)} از {subscription.plan?.project_quota ? formatNumber(subscription.plan.project_quota) : 'نامحدود'} پروژه
                                                    {subscription.expires_at && ` · تا ${formatDate(subscription.expires_at)}`}
                                                </div>
                                            </div>
                                            <StatusBadge group="subscriptionStatus" value={subscription.status} />
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                    </section>
                </div>
            </div>

            <h2 id="plans" className="h5 fw-bold mb-3">
                پلن‌ها
            </h2>
            {plans.length === 0 ? (
                <EmptyState icon="bi-gem" title="فعلاً پلنی برای خرید نیست" />
            ) : (
                <div className="row g-3">
                    {plans.map((plan, index) => (
                        <div className="col-md-6 col-xl-4" key={plan.id}>
                            <article className={`jl-card jl-plan h-100 jl-rise jl-rise-${Math.min(index + 1, 8)} ${index === 1 ? 'is-featured' : ''}`}>
                                <div className="jl-card-body d-flex flex-column h-100">
                                    <div className="d-flex align-items-start justify-content-between">
                                        <h3 className="h5 fw-bold mb-1">{plan.name}</h3>
                                        {index === 1 && <Badge tone="accent">پیشنهاد ما</Badge>}
                                    </div>
                                    <div className="jl-plan-price">
                                        {formatMoney(plan.price, { unit: false })}
                                        <span>تومان</span>
                                    </div>
                                    <ul className="jl-checklist mb-3">
                                        <li>
                                            <i className="bi bi-check-circle-fill" />
                                            {plan.project_quota ? `انتشار ${formatNumber(plan.project_quota)} پروژه` : 'انتشار پروژه‌ی نامحدود'}
                                        </li>
                                        <li>
                                            <i className="bi bi-check-circle-fill" />
                                            {plan.duration_days ? `اعتبار ${formatNumber(plan.duration_days)} روزه` : 'بدون تاریخ انقضا'}
                                        </li>
                                        <li>
                                            <i className="bi bi-check-circle-fill" />
                                            بررسی و انتشار پروژه توسط ادمین
                                        </li>
                                    </ul>
                                    {plan.description && <p className="small text-muted-2">{plan.description}</p>}
                                    <button type="button" className={`btn mt-auto ${index === 1 ? 'btn-accent' : 'btn-primary'}`} onClick={() => buy(plan)}>
                                        <i className="bi bi-gem" /> خرید پلن
                                    </button>
                                </div>
                            </article>
                        </div>
                    ))}
                </div>
            )}
        </>
    );
}
