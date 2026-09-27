import ModalForm from '../../../Components/Form/ModalForm';
import RadioCards from '../../../Components/Form/RadioCards';
import Textarea from '../../../Components/Form/Textarea';
import PageHeader from '../../../Components/Panel/PageHeader';
import TableCard from '../../../Components/Panel/TableCard';
import { Person } from '../../../Components/UI/Avatar';
import { Badge, StatusBadge } from '../../../Components/UI/Badge';
import DataTable from '../../../Components/UI/DataTable';
import EmptyState from '../../../Components/UI/EmptyState';
import Modal, { ModalBody } from '../../../Components/UI/Modal';
import { ContractProgress, MilestoneList } from '../../../Components/Domain/Contract';
import Tabs from '../../../Components/UI/Tabs';
import { useFilters } from '../../../hooks/useFilters';
import { useModal } from '../../../hooks/useModal';
import { formatDate, formatMoney, formatNumber, formatRelative } from '../../../lib/format';
import { options } from '../../../lib/labels';
import { fillRoute } from '../../../lib/text';

function ContractDetails({ modal }) {
    const contract = modal.record;

    return (
        <Modal open={modal.open} onClose={modal.close} title={contract?.project?.title ?? 'جزئیات قرارداد'} subtitle={contract ? `قرارداد #${formatNumber(contract.id)}` : null} icon="bi-file-earmark-text" size="lg">
            {contract && (
                <ModalBody>
                    <div className="row g-3 mb-3">
                        <div className="col-sm-6">
                            <div className="small text-muted mb-1">کارفرما</div>
                            <Person user={contract.employer} />
                        </div>
                        <div className="col-sm-6">
                            <div className="small text-muted mb-1">فریلنسر</div>
                            <Person user={contract.freelancer} />
                        </div>
                    </div>
                    <dl className="jl-details mb-3">
                        <dt>مبلغ قرارداد</dt>
                        <dd className="jl-money">{formatMoney(contract.amount)}</dd>
                        <dt>کارمزد پلتفرم</dt>
                        <dd>{formatNumber(contract.fee_percent)}٪</dd>
                        <dt>منتورینگ</dt>
                        <dd>
                            {contract.mentorship_included ? (
                                <>
                                    <Badge tone="info">دارد</Badge> {contract.is_free_mentorship && <Badge tone="success">رایگان (تازه‌کار)</Badge>} {contract.mentor && <Person user={contract.mentor} size="sm" />}
                                </>
                            ) : (
                                'ندارد'
                            )}
                        </dd>
                        <dt>وضعیت</dt>
                        <dd>
                            <StatusBadge group="contractStatus" value={contract.status} />
                        </dd>
                        <dt>شروع</dt>
                        <dd>{formatDate(contract.started_at ?? contract.created_at)}</dd>
                        {contract.completed_at && (
                            <>
                                <dt>پایان</dt>
                                <dd>{formatDate(contract.completed_at)}</dd>
                            </>
                        )}
                    </dl>
                    <h3 className="h6 fw-bold">مرحله‌ها</h3>
                    <MilestoneList milestones={contract.milestones} />
                </ModalBody>
            )}
        </Modal>
    );
}

function DisputeForm({ modal, routes }) {
    const dispute = modal.record;

    return (
        <ModalForm
            open={modal.open}
            onClose={modal.close}
            title="رسیدگی به اختلاف"
            subtitle={dispute?.contract?.project_title}
            icon="bi-shield-exclamation"
            tone="danger"
            size="lg"
            method="put"
            url={dispute ? fillRoute(routes.disputeUpdate, dispute.id) : ''}
            initial={{ status: dispute?.status === 'open' ? 'under_review' : (dispute?.status ?? 'under_review'), resolution_note: dispute?.resolution_note ?? '' }}
            submitLabel="ثبت تصمیم"
        >
            {(form) =>
                dispute && (
                    <div className="d-grid gap-3">
                        <div className="row g-3">
                            <div className="col-sm-6">
                                <div className="small text-muted mb-1">کارفرما</div>
                                <Person user={dispute.contract?.employer} />
                            </div>
                            <div className="col-sm-6">
                                <div className="small text-muted mb-1">فریلنسر</div>
                                <Person user={dispute.contract?.freelancer} />
                            </div>
                        </div>
                        <div>
                            <div className="small text-muted mb-1">
                                دلیل اختلاف از طرف {dispute.initiator?.name} ({formatRelative(dispute.created_at)}) — مبلغ قرارداد {formatMoney(dispute.contract?.amount)}
                            </div>
                            <div className="jl-text-block">{dispute.reason}</div>
                        </div>
                        <RadioCards
                            form={form}
                            name="status"
                            label="تصمیم"
                            columns={3}
                            options={[
                                { value: 'under_review', label: 'در حال بررسی', icon: 'bi-hourglass-split', description: 'طرفین می‌بینند که پرونده دست ماست' },
                                { value: 'resolved', label: 'حل شد', icon: 'bi-check2-circle', description: 'قرارداد به حالت فعال برمی‌گردد' },
                                { value: 'rejected', label: 'رد اختلاف', icon: 'bi-x-circle', description: 'ادعا پذیرفته نشد' },
                            ]}
                        />
                        <Textarea
                            form={form}
                            name="resolution_note"
                            label="یادداشت تصمیم (برای هر دو طرف)"
                            required={form.data.status !== 'under_review'}
                            rows={4}
                            maxLength={2000}
                            placeholder="نتیجه‌ی بررسی و تکلیف مبلغ امانت را روشن بنویس."
                        />
                    </div>
                )
            }
        </ModalForm>
    );
}

export default function Index({ tab, filters: initialFilters, contracts, disputes, counts, options: choices, routes }) {
    const filters = useFilters({ tab, status: initialFilters.status });
    const details = useModal();
    const resolver = useModal();

    const switchTab = (next) => filters.apply({ tab: next, status: null });

    const contractColumns = [
        {
            key: 'project',
            label: 'قرارداد',
            primary: true,
            render: (contract) => (
                <div className="min-w-0">
                    <div className="fw-semibold text-truncate" style={{ maxWidth: 320 }}>
                        {contract.project?.title}
                    </div>
                    <div className="small text-muted">#{formatNumber(contract.id)}</div>
                </div>
            ),
        },
        {
            key: 'parties',
            label: 'کارفرما ← فریلنسر',
            render: (contract) => (
                <div className="d-flex align-items-center gap-2 small">
                    <span className="text-truncate">{contract.employer?.name}</span>
                    <i className="bi bi-arrow-left text-muted" />
                    <span className="text-truncate">{contract.freelancer?.name}</span>
                </div>
            ),
        },
        { key: 'amount', label: 'مبلغ', render: (contract) => <span className="jl-money small">{formatMoney(contract.amount)}</span> },
        { key: 'progress', label: 'پرداخت‌شده', render: (contract) => <ContractProgress contract={contract} /> },
        {
            key: 'status',
            label: 'وضعیت',
            render: (contract) => (
                <div className="d-flex flex-wrap gap-1">
                    <StatusBadge group="contractStatus" value={contract.status} />
                    {contract.open_disputes_count > 0 && (
                        <Badge tone="danger" icon="bi-exclamation-triangle">
                            {formatNumber(contract.open_disputes_count)} اختلاف
                        </Badge>
                    )}
                </div>
            ),
        },
        { key: 'started_at', label: 'شروع', render: (contract) => formatDate(contract.started_at ?? contract.created_at) },
    ];

    const disputeColumns = [
        {
            key: 'contract',
            label: 'پروژه',
            primary: true,
            render: (dispute) => (
                <div className="min-w-0">
                    <div className="fw-semibold text-truncate" style={{ maxWidth: 300 }}>
                        {dispute.contract?.project_title}
                    </div>
                    <div className="small text-muted">
                        {dispute.contract?.employer?.name} ← {dispute.contract?.freelancer?.name}
                    </div>
                </div>
            ),
        },
        { key: 'initiator', label: 'ثبت‌کننده', render: (dispute) => <Person user={dispute.initiator} size="sm" /> },
        {
            key: 'reason',
            label: 'دلیل',
            render: (dispute) => (
                <span className="small text-muted-2 d-inline-block text-truncate" style={{ maxWidth: 280 }} title={dispute.reason}>
                    {dispute.reason}
                </span>
            ),
        },
        { key: 'status', label: 'وضعیت', render: (dispute) => <StatusBadge group="disputeStatus" value={dispute.status} /> },
        { key: 'created_at', label: 'ثبت', render: (dispute) => formatRelative(dispute.created_at) },
    ];

    const isContracts = filters.filters.tab !== 'disputes';
    const page = isContracts ? contracts : disputes;

    return (
        <>
            <PageHeader title="قراردادها و اختلاف‌ها" description={`${formatNumber(counts.active_contracts)} قرارداد فعال از ${formatNumber(counts.contracts)} قرارداد`} />

            <Tabs
                className="mb-3"
                items={[
                    { key: 'contracts', label: 'قراردادها', icon: 'bi-file-earmark-text', count: counts.contracts },
                    { key: 'disputes', label: 'اختلاف‌ها', icon: 'bi-shield-exclamation', count: counts.open_disputes },
                ]}
                active={isContracts ? 'contracts' : 'disputes'}
                onChange={switchTab}
            />

            <TableCard
                meta={page?.meta}
                toolbar={
                    <>
                        <select className="form-select" value={filters.filters.status ?? ''} onChange={(event) => filters.set('status', event.target.value || null)} aria-label="وضعیت">
                            <option value="">همه‌ی وضعیت‌ها</option>
                            {(isContracts ? options('contractStatus', choices.contractStatuses) : options('disputeStatus', choices.disputeStatuses)).map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                        <span className="small text-muted ms-auto">{isContracts ? 'برای دیدن مرحله‌ها روی قرارداد کلیک کن.' : 'پرونده‌های باز همیشه اول‌اند.'}</span>
                    </>
                }
            >
                {isContracts ? (
                    <DataTable
                        columns={contractColumns}
                        rows={contracts?.data ?? []}
                        onRowClick={(contract) => details.show(contract)}
                        empty={<EmptyState title="قراردادی نیست" description="وقتی کارفرما پیشنهادی را بپذیرد، قرارداد اینجا ظاهر می‌شود." />}
                    />
                ) : (
                    <DataTable
                        columns={disputeColumns}
                        rows={disputes?.data ?? []}
                        onRowClick={(dispute) => resolver.show(dispute)}
                        rowClassName={(dispute) => (['open', 'under_review'].includes(dispute.status) ? 'is-highlight' : '')}
                        empty={<EmptyState icon="bi-emoji-smile" title="اختلافی ثبت نشده" description="خیالت راحت؛ همه‌ی قراردادها آرام پیش می‌روند." />}
                        actions={(dispute) => (
                            <button type="button" className="btn btn-soft btn-sm" onClick={() => resolver.show(dispute)}>
                                {['open', 'under_review'].includes(dispute.status) ? 'رسیدگی' : 'مشاهده'}
                            </button>
                        )}
                    />
                )}
            </TableCard>

            <ContractDetails modal={details} />
            <DisputeForm modal={resolver} routes={routes} />
        </>
    );
}
