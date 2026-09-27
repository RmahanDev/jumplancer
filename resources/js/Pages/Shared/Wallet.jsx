import SignedAmount from '../../Components/Domain/Money';
import ModalForm from '../../Components/Form/ModalForm';
import NumberInput from '../../Components/Form/NumberInput';
import PageHeader from '../../Components/Panel/PageHeader';
import TableCard from '../../Components/Panel/TableCard';
import { StatusBadge } from '../../Components/UI/Badge';
import DataTable from '../../Components/UI/DataTable';
import EmptyState from '../../Components/UI/EmptyState';
import StatCard from '../../Components/UI/StatCard';
import { useCountUp } from '../../hooks/useCountUp';
import { useFilters } from '../../hooks/useFilters';
import { useModal } from '../../hooks/useModal';
import { formatCompact, formatDateTime, formatMoney, formatRelative } from '../../lib/format';
import { options } from '../../lib/labels';

const QUICK_AMOUNTS = [500_000, 1_000_000, 5_000_000, 10_000_000];

function DepositForm({ modal, sandbox, routes }) {
    return (
        <ModalForm
            open={modal.open}
            onClose={modal.close}
            title="شارژ کیف پول"
            subtitle="درگاه آزمایشی: بدون پرداخت واقعی، برای امتحان کردن امانت و قراردادها."
            icon="bi-wallet2"
            method="post"
            url={routes.deposit}
            initial={{ amount: '' }}
            transform={(data) => ({ amount: Number(data.amount || 0) })}
            submitLabel="پرداخت آزمایشی"
            submitIcon="bi-credit-card"
        >
            {(form) => (
                <div className="d-grid gap-3">
                    <NumberInput form={form} name="amount" label="مبلغ" money required data-autofocus />
                    <div className="d-flex flex-wrap gap-2">
                        {QUICK_AMOUNTS.map((amount) => (
                            <button key={amount} type="button" className={`jl-chip jl-chip-add ${Number(form.data.amount) === amount ? 'is-active' : ''}`} onClick={() => form.setData('amount', String(amount))}>
                                {formatCompact(amount)} تومان
                            </button>
                        ))}
                    </div>
                    <div className="small text-muted">
                        حداقل ۱۰ هزار و حداکثر {formatMoney(sandbox.max)} در هر شارژ.
                    </div>
                </div>
            )}
        </ModalForm>
    );
}

export default function Wallet({ wallet, totals, transactions, filters: initialFilters, types, sandbox, routes }) {
    const filters = useFilters(initialFilters);
    const depositor = useModal();
    const balance = useCountUp(wallet.balance);

    const columns = [
        {
            key: 'description',
            label: 'شرح',
            primary: true,
            render: (transaction) => (
                <div className="min-w-0">
                    <div className="fw-semibold text-truncate" style={{ maxWidth: 360 }}>
                        {transaction.description ?? '—'}
                    </div>
                    <StatusBadge group="transactionType" value={transaction.type} className="mt-1" />
                </div>
            ),
        },
        { key: 'amount', label: 'مبلغ', render: (transaction) => <SignedAmount amount={transaction.amount} /> },
        { key: 'status', label: 'وضعیت', render: (transaction) => <StatusBadge group="transactionStatus" value={transaction.status} /> },
        { key: 'created_at', label: 'زمان', render: (transaction) => <span title={formatDateTime(transaction.created_at)}>{formatRelative(transaction.created_at)}</span> },
    ];

    return (
        <>
            <PageHeader
                title="کیف پول"
                description="موجودی قابل استفاده، پول در امانت و همه‌ی تراکنش‌ها."
                primary={sandbox.enabled ? { label: 'شارژ کیف پول', icon: 'bi-plus-circle', onClick: () => depositor.show(null) } : null}
            />

            <div className="row g-3 mb-4">
                <div className="col-lg-5">
                    <section className="jl-card jl-wallet-hero h-100 jl-rise">
                        <div className="jl-card-body">
                            <div className="d-flex align-items-center justify-content-between">
                                <span className="jl-wallet-label">موجودی قابل استفاده</span>
                                <i className="bi bi-wallet2 fs-4" />
                            </div>
                            <div className="jl-wallet-balance" title={formatMoney(wallet.balance)}>
                                {formatMoney(balance, { unit: false })}
                                <span>تومان</span>
                            </div>
                            <div className="jl-wallet-held">
                                <i className="bi bi-safe2" /> {formatMoney(wallet.held_balance)} در امانت برای مرحله‌های در جریان
                            </div>
                        </div>
                    </section>
                </div>
                <div className="col-sm-6 col-lg-3 col-xl">
                    <StatCard label="کل ورودی‌ها" value={totals.income} icon="bi-arrow-down-left-circle" format="money" index={1} foot="شارژ، دریافت از امانت و بازگشت وجه" />
                </div>
                <div className="col-sm-6 col-lg-4 col-xl">
                    <StatCard label="کل خروجی‌ها" value={totals.spent} icon="bi-arrow-up-right-circle" tone="accent" format="money" index={2} foot="امانت، کارمزد و خرید پلن" />
                </div>
            </div>

            <TableCard
                meta={transactions.meta}
                toolbar={
                    <>
                        <select className="form-select" value={filters.filters.type ?? ''} onChange={(event) => filters.set('type', event.target.value || null)} aria-label="نوع تراکنش">
                            <option value="">همه‌ی تراکنش‌ها</option>
                            {options('transactionType', types).map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                        <span className="small text-muted ms-auto">مبلغ مثبت یعنی پولی که به کیف پولت آمده.</span>
                    </>
                }
            >
                <DataTable
                    columns={columns}
                    rows={transactions.data}
                    empty={
                        <EmptyState
                            icon="bi-receipt"
                            title="هنوز تراکنشی نداری"
                            description={sandbox.enabled ? 'با درگاه آزمایشی کیف پولت را شارژ کن و روند امانت را امتحان کن.' : 'اولین پرداخت یا دریافت اینجا نمایش داده می‌شود.'}
                            action={
                                sandbox.enabled && (
                                    <button type="button" className="btn btn-primary btn-sm" onClick={() => depositor.show(null)}>
                                        <i className="bi bi-plus-circle" /> شارژ کیف پول
                                    </button>
                                )
                            }
                        />
                    }
                />
            </TableCard>

            {sandbox.enabled && <DepositForm modal={depositor} sandbox={sandbox} routes={routes} />}
        </>
    );
}
