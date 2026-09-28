import SignedAmount from '../../../Components/Domain/Money';
import PageHeader from '../../../Components/Panel/PageHeader';
import TableCard from '../../../Components/Panel/TableCard';
import { Person } from '../../../Components/UI/Avatar';
import { StatusBadge } from '../../../Components/UI/Badge';
import DataTable from '../../../Components/UI/DataTable';
import EmptyState from '../../../Components/UI/EmptyState';
import Modal, { ModalBody } from '../../../Components/UI/Modal';
import SearchInput from '../../../Components/UI/SearchInput';
import StatCard from '../../../Components/UI/StatCard';
import { useFilters } from '../../../hooks/useFilters';
import { useModal } from '../../../hooks/useModal';
import { formatDateTime, formatNumber, formatRelative } from '../../../lib/format';
import { options } from '../../../lib/labels';

const SUMMARY = [
    ['deposits', 'شارژ کیف پول‌ها', 'bi-box-arrow-in-down', 'primary'],
    ['escrow', 'الان در امانت', 'bi-safe2', 'accent'],
    ['released', 'آزادشده برای فریلنسرها', 'bi-send-check', 'primary'],
    ['fees', 'کارمزد پلتفرم', 'bi-percent', 'accent'],
    ['plans', 'فروش پلن', 'bi-gem', 'primary'],
];

export default function Index({ transactions, filters: initialFilters, summary, options: choices }) {
    const filters = useFilters(initialFilters);
    const details = useModal();
    const row = details.record;

    const columns = [
        {
            key: 'description',
            label: 'شرح',
            primary: true,
            render: (transaction) => (
                <div className="min-w-0">
                    <div className="fw-semibold text-truncate" style={{ maxWidth: 320 }}>
                        {transaction.description ?? '—'}
                    </div>
                    <StatusBadge group="transactionType" value={transaction.type} className="mt-1" />
                </div>
            ),
        },
        { key: 'owner', label: 'کیف پول', render: (transaction) => <Person user={transaction.owner} size="sm" /> },
        { key: 'amount', label: 'مبلغ', render: (transaction) => <SignedAmount amount={transaction.amount} /> },
        { key: 'status', label: 'وضعیت', render: (transaction) => <StatusBadge group="transactionStatus" value={transaction.status} /> },
        {
            key: 'gateway_ref',
            label: 'مرجع',
            mobile: false,
            render: (transaction) => (transaction.gateway_ref ? <code className="small">{transaction.gateway_ref}</code> : <span className="text-muted">—</span>),
        },
        { key: 'created_at', label: 'زمان', className: 'text-nowrap', render: (transaction) => <span title={formatDateTime(transaction.created_at)}>{formatRelative(transaction.created_at)}</span> },
    ];

    return (
        <>
            <PageHeader title="تراکنش‌ها" description="دفتر کل پلتفرم؛ ردیف‌ها هرگز ویرایش نمی‌شوند. مبلغ مثبت یعنی ورود پول به کیف پول." />

            <div className="row g-3 mb-4">
                {SUMMARY.map(([key, label, icon, tone], index) => (
                    <div className="col-6 col-lg" key={key}>
                        <StatCard label={label} value={summary[key]} icon={icon} tone={tone} format="money" index={index} />
                    </div>
                ))}
            </div>

            <TableCard
                meta={transactions.meta}
                toolbar={
                    <>
                        <SearchInput value={filters.filters.search} onChange={filters.search} placeholder="نام کاربر، شرح یا کد مرجع..." className="jl-toolbar-search" />
                        <select className="form-select" value={filters.filters.type ?? ''} onChange={(event) => filters.set('type', event.target.value || null)} aria-label="نوع تراکنش">
                            <option value="">همه‌ی انواع</option>
                            {options('transactionType', choices.types).map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                        <select className="form-select" value={filters.filters.status ?? ''} onChange={(event) => filters.set('status', event.target.value || null)} aria-label="وضعیت">
                            <option value="">همه‌ی وضعیت‌ها</option>
                            {options('transactionStatus', choices.statuses).map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                        {filters.active && (
                            <button type="button" className="btn btn-link btn-sm text-decoration-none" onClick={filters.reset}>
                                حذف فیلترها
                            </button>
                        )}
                    </>
                }
            >
                <DataTable
                    columns={columns}
                    rows={transactions.data}
                    onRowClick={(transaction) => details.show(transaction)}
                    empty={<EmptyState title="تراکنشی پیدا نشد" description="فیلترها را تغییر بده." />}
                />
            </TableCard>

            <Modal open={details.open} onClose={details.close} title="جزئیات تراکنش" subtitle={row ? `شناسه‌ی ${formatNumber(row.id)}` : null} icon="bi-receipt">
                {row && (
                    <ModalBody>
                        <dl className="jl-details">
                            <dt>نوع</dt>
                            <dd>
                                <StatusBadge group="transactionType" value={row.type} />
                            </dd>
                            <dt>مبلغ</dt>
                            <dd>
                                <SignedAmount amount={row.amount} />
                            </dd>
                            <dt>وضعیت</dt>
                            <dd>
                                <StatusBadge group="transactionStatus" value={row.status} />
                            </dd>
                            <dt>کیف پول</dt>
                            <dd>
                                <Person user={row.owner} size="sm" />
                            </dd>
                            <dt>شرح</dt>
                            <dd>{row.description ?? '—'}</dd>
                            <dt>درگاه</dt>
                            <dd className="ltr text-start">{row.gateway ?? '—'}</dd>
                            <dt>کد مرجع</dt>
                            <dd className="ltr text-start">{row.gateway_ref ?? '—'}</dd>
                            <dt>قرارداد</dt>
                            <dd>{row.contract_id ? `#${formatNumber(row.contract_id)}` : '—'}</dd>
                            <dt>زمان</dt>
                            <dd>{formatDateTime(row.created_at)}</dd>
                        </dl>
                    </ModalBody>
                )}
            </Modal>
        </>
    );
}
