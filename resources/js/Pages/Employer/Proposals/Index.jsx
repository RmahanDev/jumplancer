import { Link } from '@inertiajs/react';
import ModalForm from '../../../Components/Form/ModalForm';
import PageHeader from '../../../Components/Panel/PageHeader';
import Pagination from '../../../Components/Panel/Pagination';
import { Person } from '../../../Components/UI/Avatar';
import { Badge, StatusBadge } from '../../../Components/UI/Badge';
import { confirm } from '../../../Components/UI/ConfirmDialog';
import EmptyState from '../../../Components/UI/EmptyState';
import Meter from '../../../Components/UI/Meter';
import Tabs from '../../../Components/UI/Tabs';
import { useFilters } from '../../../hooks/useFilters';
import { useModal } from '../../../hooks/useModal';
import { put } from '../../../lib/actions';
import { formatMoney, formatNumber, formatRelative } from '../../../lib/format';
import { label } from '../../../lib/labels';
import { fillRoute } from '../../../lib/text';

const TAB_STATUSES = ['shortlisted', 'pending', 'accepted', 'rejected', 'withdrawn'];

/** Deposit held when hiring: a share of the proposal price, rounded up (same rule as the server). */
function depositFor(price, percent) {
    return Math.ceil((Number(price) * percent) / 100);
}

function HireForm({ modal, routes, hiring }) {
    const proposal = modal.record;
    const deposit = proposal ? depositFor(proposal.proposed_price, hiring.depositPercent) : 0;
    const missing = Math.max(0, deposit - hiring.balance);

    return (
        <ModalForm
            open={modal.open}
            onClose={modal.close}
            title={proposal ? `استخدام ${proposal.freelancer?.name}` : 'استخدام'}
            subtitle={proposal?.project?.title}
            icon="bi-person-check"
            tone="success"
            size="lg"
            method="post"
            url={proposal ? fillRoute(routes.hire, proposal.id) : ''}
            initial={{ accept_deposit_terms: false }}
            submitLabel={`پرداخت ${formatMoney(deposit)} امانت و استخدام`}
            submitIcon="bi-shield-lock"
        >
            {(form) =>
                proposal && (
                    <div className="d-grid gap-3">
                        <dl className="jl-details">
                            <dt>مبلغ پیشنهادی فریلنسر</dt>
                            <dd className="jl-money">{formatMoney(proposal.proposed_price)}</dd>
                            <dt>زمان تحویل</dt>
                            <dd>{formatNumber(proposal.delivery_days)} روز</dd>
                            <dt>سطح فریلنسر</dt>
                            <dd>{proposal.freelancer?.level ? <StatusBadge group="level" value={proposal.freelancer.level} /> : '—'}</dd>
                            <dt>منتور</dt>
                            <dd>
                                {proposal.mentorship_requested ? (
                                    <Badge tone="info" icon="bi-mortarboard">
                                        فریلنسر منتور خواسته؛ یک منتور همراهش می‌شود
                                    </Badge>
                                ) : (
                                    'بدون منتور'
                                )}
                            </dd>
                        </dl>

                        <section className="jl-deposit">
                            <div className="jl-deposit-head">
                                <i className="bi bi-shield-lock" />
                                <div>
                                    <div className="small text-muted">امانت حسن انجام کار ({formatNumber(hiring.depositPercent)}٪ مبلغ پیشنهادی)</div>
                                    <div className="jl-deposit-amount">{formatMoney(deposit)}</div>
                                </div>
                            </div>
                            <ul className="jl-deposit-rules">
                                <li>همین حالا این مبلغ از کیف پولت برداشته و در امانت جامپ‌لنسر نگه داشته می‌شود؛ خرج اولین مرحله‌های پروژه همین پول است.</li>
                                <li>اگر فریلنسر به نظر کارشناس ما پایین‌تر از حد انتظار کار کند، کل مبلغ امانت به کیف پولت برمی‌گردد.</li>
                                <li>اگر بدون دلیل نخواهی به فریلنسر پرداخت کنی، پول تا تشخیص کارشناس در امانت می‌ماند.</li>
                                <li>بعد از تحویل درست پروژه، باقی‌مانده‌ی مبلغ را در قالب مرحله‌ها پرداخت می‌کنی؛ امانت مصرف‌نشده هم به کیف پولت برمی‌گردد.</li>
                            </ul>
                            <div className="d-flex flex-wrap justify-content-between gap-2 small">
                                <span>
                                    موجودی کیف پول: <strong>{formatMoney(hiring.balance)}</strong>
                                </span>
                                {missing > 0 && (
                                    <Link href={routes.wallet} className="text-danger fw-semibold">
                                        <i className="bi bi-exclamation-circle" /> {formatMoney(missing)} کم داری — شارژ کیف پول
                                    </Link>
                                )}
                            </div>
                        </section>

                        <div>
                            <label className="form-check d-flex gap-2 align-items-start">
                                <input
                                    type="checkbox"
                                    className={`form-check-input mt-1 ${form.errors.accept_deposit_terms ? 'is-invalid' : ''}`}
                                    checked={Boolean(form.data.accept_deposit_terms)}
                                    onChange={(event) => form.setData('accept_deposit_terms', event.target.checked)}
                                />
                                <span className="form-check-label small">شرایط امانت حسن انجام کار را خواندم و می‌پذیرم.</span>
                            </label>
                            {form.errors.accept_deposit_terms && <div className="invalid-feedback d-block">{form.errors.accept_deposit_terms}</div>}
                        </div>
                        <div className="small text-muted">
                            <i className="bi bi-info-circle" /> بعد از استخدام، پیشنهادهای دیگر این پروژه رد می‌شوند و قرارداد ساخته می‌شود.
                        </div>
                    </div>
                )
            }
        </ModalForm>
    );
}

export default function Index({ proposals, filters: initialFilters, projects, hiring, routes }) {
    const filters = useFilters(initialFilters);
    const hirer = useModal();

    const setStatus = (proposal, status) => put(fillRoute(routes.update, proposal.id), { status });

    const reject = async (proposal) => {
        const ok = await confirm({
            title: 'رد پیشنهاد؟',
            message: `پیشنهاد ${proposal.freelancer?.name} رد می‌شود. به او خبر می‌دهیم تا روی پروژه‌های دیگر وقت بگذارد.`,
            confirmLabel: 'رد پیشنهاد',
        });

        if (ok) {
            setStatus(proposal, 'rejected');
        }
    };

    return (
        <>
            <PageHeader title="پیشنهادهای دریافتی" description="پیشنهادها را مقایسه کن، بهترین‌ها را به فهرست کوتاه ببر و یکی را استخدام کن." />

            <div className="jl-card mb-3 jl-rise">
                <div className="jl-toolbar border-0">
                    <select className="form-select" style={{ maxWidth: 320 }} value={filters.filters.project ?? ''} onChange={(event) => filters.set('project', event.target.value || null)} aria-label="پروژه">
                        <option value="">همه‌ی پروژه‌ها</option>
                        {projects.map((project) => (
                            <option key={project.id} value={project.id}>
                                {project.title}
                            </option>
                        ))}
                    </select>
                    <Tabs
                        items={[{ key: '', label: 'همه' }, ...TAB_STATUSES.map((status) => ({ key: status, label: label('proposalStatus', status) }))]}
                        active={filters.filters.status ?? ''}
                        onChange={(status) => filters.set('status', status || null)}
                    />
                </div>
            </div>

            {proposals.data.length === 0 ? (
                <EmptyState icon="bi-inboxes" title="پیشنهادی نیست" description="وقتی پروژه‌ات منتشر شود، فریلنسرها پیشنهادشان را اینجا می‌فرستند." />
            ) : (
                <div className="row g-3">
                    {proposals.data.map((proposal, index) => {
                        const open = ['pending', 'shortlisted'].includes(proposal.status);

                        return (
                            <div className="col-xl-6" key={proposal.id}>
                                <article className={`jl-card h-100 jl-rise jl-rise-${Math.min(index + 1, 8)} ${proposal.status === 'shortlisted' ? 'is-starred' : ''} ${open ? '' : 'is-muted'}`}>
                                    <div className="jl-card-body d-flex flex-column gap-3 h-100">
                                        <div className="d-flex align-items-start justify-content-between gap-2">
                                            <Person user={proposal.freelancer} role="freelancer" meta={proposal.freelancer?.level ? label('level', proposal.freelancer.level) : null} />
                                            <StatusBadge group="proposalStatus" value={proposal.status} />
                                        </div>
                                        <div className="small text-muted">
                                            برای «{proposal.project?.title}» · {formatRelative(proposal.created_at)}
                                        </div>
                                        <div className="d-flex flex-wrap gap-2">
                                            <span className="jl-chip">
                                                <i className="bi bi-cash" /> {formatMoney(proposal.proposed_price)}
                                            </span>
                                            <span className="jl-chip">
                                                <i className="bi bi-clock" /> {formatNumber(proposal.delivery_days)} روز
                                            </span>
                                            {proposal.mentorship_requested && (
                                                <span className="jl-chip">
                                                    <i className="bi bi-mortarboard" /> با منتور
                                                </span>
                                            )}
                                        </div>
                                        {proposal.freelancer?.readiness_score !== null && proposal.freelancer?.readiness_score !== undefined && (
                                            <div>
                                                <div className="d-flex justify-content-between small mb-1">
                                                    <span className="text-muted">امتیاز آمادگی</span>
                                                    <strong>{formatNumber(proposal.freelancer.readiness_score)}٪</strong>
                                                </div>
                                                <Meter value={proposal.freelancer.readiness_score} tone="accent" />
                                            </div>
                                        )}
                                        <details className="jl-cover-letter">
                                            <summary>متن پیشنهاد</summary>
                                            <div className="jl-text-block mt-2">{proposal.cover_letter}</div>
                                        </details>
                                        {proposal.mentor_feedback && (
                                            <div className="jl-feedback">
                                                <i className="bi bi-mortarboard" />
                                                <div className="small">
                                                    <strong>نظر منتور:</strong> {proposal.mentor_feedback}
                                                </div>
                                            </div>
                                        )}
                                        <div className="mt-auto d-flex flex-wrap gap-2">
                                            {open && (
                                                <>
                                                    <button type="button" className="btn btn-primary btn-sm" onClick={() => hirer.show(proposal)}>
                                                        <i className="bi bi-person-check" /> استخدام
                                                    </button>
                                                    {proposal.status === 'pending' ? (
                                                        <button type="button" className="btn btn-soft btn-sm" onClick={() => setStatus(proposal, 'shortlisted')}>
                                                            <i className="bi bi-star" /> فهرست کوتاه
                                                        </button>
                                                    ) : (
                                                        <button type="button" className="btn btn-soft btn-sm" onClick={() => setStatus(proposal, 'pending')}>
                                                            <i className="bi bi-star-fill text-accent" /> خارج از فهرست کوتاه
                                                        </button>
                                                    )}
                                                    <button type="button" className="btn btn-ghost btn-sm text-danger" onClick={() => reject(proposal)}>
                                                        رد
                                                    </button>
                                                </>
                                            )}
                                            {proposal.status === 'accepted' && (
                                                <Link href={routes.contracts} className="btn btn-soft btn-sm">
                                                    <i className="bi bi-file-earmark-check" /> رفتن به قرارداد
                                                </Link>
                                            )}
                                            {proposal.status === 'accepted' && (
                                                <Badge tone="success" icon="bi-check2">
                                                    استخدام شد
                                                </Badge>
                                            )}
                                        </div>
                                    </div>
                                </article>
                            </div>
                        );
                    })}
                </div>
            )}

            {proposals.meta?.last_page > 1 && (
                <div className="jl-card mt-3">
                    <Pagination meta={proposals.meta} />
                </div>
            )}

            <HireForm modal={hirer} routes={routes} hiring={hiring} />
        </>
    );
}
