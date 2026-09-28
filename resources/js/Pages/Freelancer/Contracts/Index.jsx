import { ContractProgress, DepositNote, MilestoneList, OpenDisputeNote } from '../../../Components/Domain/Contract';
import DisputeForm from '../../../Components/Domain/DisputeForm';
import PageHeader from '../../../Components/Panel/PageHeader';
import Pagination from '../../../Components/Panel/Pagination';
import { Person } from '../../../Components/UI/Avatar';
import { Badge, StatusBadge } from '../../../Components/UI/Badge';
import { confirm } from '../../../Components/UI/ConfirmDialog';
import EmptyState from '../../../Components/UI/EmptyState';
import Tabs from '../../../Components/UI/Tabs';
import { useFilters } from '../../../hooks/useFilters';
import { useModal } from '../../../hooks/useModal';
import { post } from '../../../lib/actions';
import { formatDate, formatMoney, formatNumber } from '../../../lib/format';
import { label, options } from '../../../lib/labels';
import { fillRoute } from '../../../lib/text';

export default function Index({ contracts, filters: initialFilters, routes }) {
    const filters = useFilters(initialFilters);
    const disputer = useModal();

    const deliver = async (milestone) => {
        const ok = await confirm({
            title: `تحویل «${milestone.title}»؟`,
            message: 'به کارفرما خبر می‌دهیم که کار این مرحله آماده است. بعد از تأیید او، مبلغ از امانت به کیف پولت می‌آید.',
            confirmLabel: 'تحویل می‌دهم',
            tone: 'primary',
            icon: 'bi-box-seam',
        });

        if (ok) {
            post(fillRoute(routes.submit, milestone.id));
        }
    };

    return (
        <>
            <PageHeader title="قراردادها" description="مرحله‌هایی که مبلغشان در امانت است را تحویل بده؛ پرداخت بعد از تأیید کارفرما انجام می‌شود." />

            <Tabs
                className="mb-3"
                items={[{ key: '', label: 'همه' }, ...options('contractStatus').map((option) => ({ key: option.value, label: option.label }))]}
                active={filters.filters.status ?? ''}
                onChange={(status) => filters.set('status', status || null)}
            />

            {contracts.data.length === 0 ? (
                <EmptyState title="قراردادی نداری" description="وقتی کارفرما پیشنهادت را بپذیرد، قرارداد اینجا ساخته می‌شود." />
            ) : (
                <div className="d-grid gap-3">
                    {contracts.data.map((contract, index) => (
                        <article key={contract.id} className={`jl-card jl-rise jl-rise-${Math.min(index + 1, 8)}`}>
                            <header className="jl-card-header">
                                <div className="min-w-0">
                                    <h2 className="text-truncate">{contract.project?.title}</h2>
                                    <p>
                                        قرارداد #{formatNumber(contract.id)} · شروع {formatDate(contract.started_at ?? contract.created_at)} · کارمزد {formatNumber(contract.fee_percent)}٪
                                    </p>
                                </div>
                                <div className="d-flex flex-wrap gap-1 align-items-center">
                                    {contract.mentorship_included && (
                                        <Badge tone="info" icon="bi-mortarboard">
                                            {contract.is_free_mentorship ? 'منتورینگ رایگان' : 'با منتور'}
                                        </Badge>
                                    )}
                                    <StatusBadge group="contractStatus" value={contract.status} />
                                </div>
                            </header>
                            <div className="jl-card-body">
                                <div className="row g-3 mb-3">
                                    <div className="col-md-4">
                                        <div className="small text-muted mb-1">کارفرما</div>
                                        <Person user={contract.employer} role="employer" />
                                    </div>
                                    <div className="col-md-4">
                                        <div className="small text-muted mb-1">منتور من</div>
                                        {contract.mentor ? (
                                            <Person user={contract.mentor} role="mentor" />
                                        ) : (
                                            <span className="text-muted small">
                                                {contract.mentorship_included ? 'درخواستت در صف منتورهاست؛ به‌زودی یک منتور همراهت می‌شود.' : 'در پیشنهاد، منتور نخواستی.'}
                                            </span>
                                        )}
                                    </div>
                                    <div className="col-md-4">
                                        <div className="small text-muted mb-1">دریافتی تا امروز</div>
                                        <ContractProgress contract={contract} />
                                    </div>
                                </div>
                                <div className="d-grid gap-2 mb-3">
                                    <OpenDisputeNote dispute={contract.open_dispute} />
                                    <DepositNote contract={contract} viewer="freelancer" />
                                </div>
                                <MilestoneList
                                    milestones={contract.milestones}
                                    actions={(milestone) =>
                                        milestone.status === 'funded' && contract.status === 'active' ? (
                                            <button type="button" className="btn btn-primary btn-sm" onClick={() => deliver(milestone)}>
                                                <i className="bi bi-box-seam" /> تحویل
                                            </button>
                                        ) : milestone.status === 'pending' ? (
                                            <span className="small text-muted">منتظر تأمین مبلغ توسط کارفرما</span>
                                        ) : null
                                    }
                                />
                                <div className="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-2">
                                    <span className="small text-muted">
                                        مبلغ کل: {formatMoney(contract.amount)} · وضعیت: {label('contractStatus', contract.status)}
                                    </span>
                                    {contract.status === 'active' && (
                                        <button type="button" className="btn btn-ghost btn-sm text-danger" onClick={() => disputer.show({ contract })}>
                                            <i className="bi bi-shield-exclamation" /> درخواست بررسی کارشناس
                                        </button>
                                    )}
                                </div>
                            </div>
                        </article>
                    ))}
                </div>
            )}

            <DisputeForm modal={disputer} url={routes.dispute} viewer="freelancer" />

            {contracts.meta?.last_page > 1 && (
                <div className="jl-card mt-3">
                    <Pagination meta={contracts.meta} />
                </div>
            )}
        </>
    );
}
