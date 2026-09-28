import FileInput from '../../../Components/Form/FileInput';
import ModalForm from '../../../Components/Form/ModalForm';
import TextInput from '../../../Components/Form/TextInput';
import Textarea from '../../../Components/Form/Textarea';
import PageHeader from '../../../Components/Panel/PageHeader';
import TableCard from '../../../Components/Panel/TableCard';
import { Person } from '../../../Components/UI/Avatar';
import { StatusBadge } from '../../../Components/UI/Badge';
import DataTable from '../../../Components/UI/DataTable';
import EmptyState from '../../../Components/UI/EmptyState';
import SearchInput from '../../../Components/UI/SearchInput';
import Tabs from '../../../Components/UI/Tabs';
import UserName from '../../../Components/UI/UserName';
import { useFilters } from '../../../hooks/useFilters';
import { useModal } from '../../../hooks/useModal';
import { formatDateTime, formatMoney, formatRelative } from '../../../lib/format';
import { fillRoute } from '../../../lib/text';

function CardDetails({ request }) {
    return (
        <dl className="jl-details mb-0">
            <dt>مبلغ</dt>
            <dd className="jl-money">{formatMoney(request.amount)}</dd>
            <dt>شماره‌ی کارت</dt>
            <dd>
                <span className="ltr d-inline-block num fw-semibold">{request.card_number.replace(/(\d{4})(?=\d)/g, '$1-')}</span>
                <button type="button" className="btn btn-link btn-sm py-0" onClick={() => navigator.clipboard?.writeText(request.card_number)}>
                    <i className="bi bi-clipboard" /> کپی
                </button>
            </dd>
            <dt>بانک</dt>
            <dd>{request.bank_name ?? 'نامشخص'}</dd>
            <dt>نام صاحب کارت</dt>
            <dd>{request.holder_name}</dd>
            <dt>موبایل کارت / حساب</dt>
            <dd>
                <span className="ltr d-inline-block">{request.card_phone ?? '—'}</span> / <span className="ltr d-inline-block">{request.user?.phone ?? '—'}</span>
                {request.card_phone && request.user?.phone && request.card_phone !== request.user.phone && (
                    <span className="text-danger small d-block">
                        <i className="bi bi-exclamation-triangle" /> موبایل کارت با موبایل حساب یکی نیست
                    </span>
                )}
            </dd>
        </dl>
    );
}

function PayForm({ modal, routes }) {
    const request = modal.record;

    return (
        <ModalForm
            open={modal.open}
            onClose={modal.close}
            title="ثبت پرداخت"
            subtitle={request ? `بازگشت وجه ${request.user?.name}` : null}
            icon="bi-bank"
            tone="success"
            size="lg"
            method="post"
            url={request ? fillRoute(routes.pay, request.id) : ''}
            forceFormData
            initial={{ tracking_code: '', receipt: [] }}
            transform={(data) => ({ tracking_code: data.tracking_code, receipt: data.receipt?.[0] ?? null })}
            submitLabel="پرداخت شد"
            submitIcon="bi-check2-circle"
            submitTone="success"
        >
            {(form) =>
                request && (
                    <div className="d-grid gap-3">
                        <div className="jl-callout is-warning small">
                            <i className="bi bi-shield-check" /> قبل از واریز بررسی کن نام صاحب کارت با نام کاربر یکی باشد و کارت با موبایل حساب او ثبت شده باشد.
                        </div>
                        <CardDetails request={request} />
                        <TextInput form={form} name="tracking_code" label="شماره‌ی پیگیری بانک" required ltr icon="bi-hash" placeholder="140307011234" hint="همان شماره‌ی پیگیری یا ارجاعی که بانک بعد از واریز می‌دهد." autoComplete="off" />
                        <FileInput
                            form={form}
                            name="receipt"
                            label="تصویر رسید پرداخت"
                            accept="image/jpeg,image/png,image/webp"
                            max={1}
                            note="الزامی — تصویر JPG، PNG یا WebP تا ۵ مگابایت"
                        />
                    </div>
                )
            }
        </ModalForm>
    );
}

function RejectForm({ modal, routes }) {
    const request = modal.record;

    return (
        <ModalForm
            open={modal.open}
            onClose={modal.close}
            title="رد درخواست بازگشت وجه"
            subtitle={request ? `${formatMoney(request.amount)} — ${request.user?.name}` : null}
            icon="bi-x-circle"
            tone="danger"
            method="put"
            url={request ? fillRoute(routes.reject, request.id) : ''}
            initial={{ rejection_reason: '' }}
            submitLabel="رد درخواست"
            submitTone="danger"
        >
            {(form) => (
                <div className="d-grid gap-3">
                    <Textarea
                        form={form}
                        name="rejection_reason"
                        label="دلیل (به کاربر نمایش داده می‌شود)"
                        required
                        rows={3}
                        maxLength={500}
                        placeholder="مثلاً: نام صاحب کارت با نام حساب یکی نیست؛ کارت به نام خودت ثبت کن."
                    />
                    <div className="small text-muted">
                        <i className="bi bi-arrow-counterclockwise" /> مبلغ به موجودی قابل استفاده‌ی کاربر برمی‌گردد.
                    </div>
                </div>
            )}
        </ModalForm>
    );
}

export default function Index({ withdrawals, filters: initialFilters, counts, pendingTotal, routes }) {
    const filters = useFilters(initialFilters);
    const payer = useModal();
    const rejecter = useModal();
    const total = Object.values(counts).reduce((sum, count) => sum + Number(count), 0);

    const columns = [
        {
            key: 'user',
            label: 'کاربر',
            primary: true,
            render: (request) => <Person user={request.user} size="sm" meta={<span className="ltr">{request.user?.phone ?? '—'}</span>} />,
        },
        { key: 'amount', label: 'مبلغ', render: (request) => <span className="fw-bold num">{formatMoney(request.amount)}</span> },
        {
            key: 'card',
            label: 'کارت',
            render: (request) => (
                <div className="small">
                    <div className="ltr d-inline-block num">{request.card_masked}</div>
                    <div className="text-muted">{request.bank_name ?? 'بانک نامشخص'}</div>
                </div>
            ),
        },
        {
            key: 'status',
            label: 'وضعیت',
            render: (request) => (
                <div className="d-flex flex-column align-items-start gap-1">
                    <StatusBadge group="withdrawalStatus" value={request.status} />
                    {request.status === 'paid' && (
                        <span className="small text-muted">
                            پیگیری: <span className="ltr d-inline-block">{request.tracking_code}</span>
                            {request.receipt_url && (
                                <a href={request.receipt_url} target="_blank" rel="noreferrer" className="ms-2" onClick={(event) => event.stopPropagation()}>
                                    رسید
                                </a>
                            )}
                        </span>
                    )}
                    {request.status === 'rejected' && <span className="small text-danger">{request.rejection_reason}</span>}
                </div>
            ),
        },
        {
            key: 'created_at',
            label: 'ثبت',
            render: (request) => (
                <div className="small">
                    <span title={formatDateTime(request.created_at)}>{formatRelative(request.created_at)}</span>
                    {request.processor && (
                        <div className="text-muted d-flex align-items-center gap-1">
                            توسط <UserName user={request.processor} />
                        </div>
                    )}
                </div>
            ),
        },
    ];

    return (
        <>
            <PageHeader
                title="درخواست‌های بازگشت وجه"
                description={`واریز موجودی کیف پول کاربران به کارت بانکی‌شان. ${counts.pending ? `${formatMoney(pendingTotal)} در صف پرداخت است.` : 'درخواست در انتظاری نیست.'}`}
            />

            <TableCard
                meta={withdrawals.meta}
                toolbar={
                    <>
                        <SearchInput value={filters.filters.search} onChange={filters.search} placeholder="نام، موبایل، شماره‌ی کارت یا پیگیری..." className="jl-toolbar-search" />
                        <Tabs
                            items={[
                                { key: 'pending', label: 'در انتظار پرداخت', icon: 'bi-hourglass-split', count: counts.pending ?? 0 },
                                { key: 'paid', label: 'پرداخت‌شده', icon: 'bi-check2-circle', count: counts.paid ?? 0 },
                                { key: 'rejected', label: 'ردشده', icon: 'bi-x-circle', count: counts.rejected ?? 0 },
                                { key: 'cancelled', label: 'لغو کاربر', count: counts.cancelled ?? 0 },
                                { key: 'all', label: 'همه', count: total },
                            ]}
                            active={filters.filters.status}
                            onChange={(status) => filters.set('status', status)}
                            className="w-100"
                        />
                    </>
                }
            >
                <DataTable
                    columns={columns}
                    rows={withdrawals.data}
                    onRowClick={(request) => request.status === 'pending' && payer.show(request)}
                    rowClassName={(request) => (request.status === 'pending' ? 'is-highlight' : '')}
                    empty={<EmptyState icon="bi-bank" title="درخواستی در این فهرست نیست" description="کاربران از صفحه‌ی کیف پول درخواست بازگشت وجه می‌دهند." />}
                    actions={(request) =>
                        request.status === 'pending' ? (
                            <div className="d-flex gap-1 justify-content-end">
                                <button type="button" className="btn btn-primary btn-sm" onClick={() => payer.show(request)}>
                                    <i className="bi bi-check2-circle" /> پرداخت
                                </button>
                                <button type="button" className="btn btn-soft-danger btn-sm" onClick={() => rejecter.show(request)}>
                                    رد
                                </button>
                            </div>
                        ) : null
                    }
                />
            </TableCard>

            <PayForm modal={payer} routes={routes} />
            <RejectForm modal={rejecter} routes={routes} />
        </>
    );
}
