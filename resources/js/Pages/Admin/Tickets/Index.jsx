import ModalForm from '../../../Components/Form/ModalForm';
import RadioCards from '../../../Components/Form/RadioCards';
import Select from '../../../Components/Form/Select';
import PageHeader from '../../../Components/Panel/PageHeader';
import TableCard from '../../../Components/Panel/TableCard';
import { Person } from '../../../Components/UI/Avatar';
import { Badge, StatusBadge } from '../../../Components/UI/Badge';
import DataTable from '../../../Components/UI/DataTable';
import EmptyState from '../../../Components/UI/EmptyState';
import SearchInput from '../../../Components/UI/SearchInput';
import Tabs from '../../../Components/UI/Tabs';
import { useFilters } from '../../../hooks/useFilters';
import { useModal } from '../../../hooks/useModal';
import { formatDateTime, formatNumber, formatRelative } from '../../../lib/format';
import { label, options } from '../../../lib/labels';
import { fillRoute } from '../../../lib/text';

function TicketForm({ modal, mentors, statuses, routes }) {
    const ticket = modal.record;

    return (
        <ModalForm
            open={modal.open}
            onClose={modal.close}
            title={ticket?.subject ?? 'تیکت'}
            subtitle={ticket ? `${label('ticketType', ticket.ticket_type)} · ${label('ticketChannel', ticket.channel)} · ${formatRelative(ticket.created_at)}` : null}
            icon="bi-life-preserver"
            size="lg"
            method="put"
            url={ticket ? fillRoute(routes.update, ticket.id) : ''}
            initial={{ assigned_mentor_id: ticket?.assigned_mentor_id ?? '', status: ticket?.status === 'open' ? 'assigned' : (ticket?.status ?? 'assigned') }}
            transform={(data) => ({ ...data, assigned_mentor_id: data.assigned_mentor_id || null })}
            submitLabel="ثبت"
        >
            {(form) =>
                ticket && (
                    <div className="d-grid gap-3">
                        <div className="d-flex align-items-center justify-content-between gap-2">
                            <Person user={ticket.requester} />
                            {ticket.channel === 'phone' && ticket.phone_number && (
                                <a href={`tel:${ticket.phone_number}`} className="btn btn-soft btn-sm ltr">
                                    <i className="bi bi-telephone" /> {ticket.phone_number}
                                </a>
                            )}
                        </div>
                        <div className="jl-text-block">{ticket.message}</div>
                        <Select
                            form={form}
                            name="assigned_mentor_id"
                            label="منتور مسئول"
                            placeholder="— بدون منتور —"
                            options={mentors.map((mentor) => ({ value: mentor.id, label: `${mentor.name} (${formatNumber(mentor.load)} تیکت در جریان)` }))}
                            hint="منتورهای کم‌کارتر را انتخاب کن تا پاسخ‌ها سریع‌تر برسد."
                        />
                        <RadioCards
                            form={form}
                            name="status"
                            label="وضعیت"
                            columns={2}
                            options={statuses.map((status) => ({
                                value: status,
                                label: label('ticketStatus', status),
                                icon: { open: 'bi-inbox', assigned: 'bi-person-check', in_progress: 'bi-chat-dots', closed: 'bi-check2-all' }[status],
                            }))}
                        />
                    </div>
                )
            }
        </ModalForm>
    );
}

export default function Index({ tickets, filters: initialFilters, mentors, options: choices, routes }) {
    const filters = useFilters(initialFilters);
    const editor = useModal();

    const columns = [
        {
            key: 'subject',
            label: 'موضوع',
            primary: true,
            render: (ticket) => (
                <div className="min-w-0">
                    <div className="fw-semibold text-truncate" style={{ maxWidth: 280 }}>
                        {ticket.subject}
                    </div>
                    <div className="small text-muted d-flex gap-2 flex-wrap mt-1">
                        <StatusBadge group="ticketType" value={ticket.ticket_type} />
                        {ticket.contract && (
                            <Badge tone="info" icon="bi-file-earmark-check">
                                از استخدام
                            </Badge>
                        )}
                        {ticket.channel === 'phone' && (
                            <Badge tone="secondary" icon="bi-telephone">
                                تماس تلفنی
                            </Badge>
                        )}
                    </div>
                </div>
            ),
        },
        { key: 'requester', label: 'درخواست‌دهنده', render: (ticket) => <Person user={ticket.requester} size="sm" /> },
        {
            key: 'mentor',
            label: 'منتور',
            render: (ticket) => (ticket.assigned_mentor ? <Person user={ticket.assigned_mentor} size="sm" /> : <Badge tone="warning">بدون منتور</Badge>),
        },
        { key: 'status', label: 'وضعیت', render: (ticket) => <StatusBadge group="ticketStatus" value={ticket.status} /> },
        { key: 'created_at', label: 'ثبت', className: 'text-nowrap', render: (ticket) => <span title={formatDateTime(ticket.created_at)}>{formatRelative(ticket.created_at)}</span> },
    ];

    return (
        <>
            <PageHeader title="تیکت‌های منتورینگ" description="درخواست‌های فنی و انگیزشی تازه‌کارها؛ تیکت‌های در صف اول‌اند. با کلیک روی هر تیکت منتور تعیین کن." />

            <TableCard
                meta={tickets.meta}
                toolbar={
                    <>
                        <SearchInput value={filters.filters.search} onChange={filters.search} placeholder="جستجوی موضوع..." className="jl-toolbar-search" />
                        <Tabs
                            items={[{ key: '', label: 'همه' }, ...choices.statuses.map((status) => ({ key: status, label: label('ticketStatus', status) }))]}
                            active={filters.filters.status ?? ''}
                            onChange={(status) => filters.set('status', status || null)}
                        />
                        <select className="form-select" value={filters.filters.type ?? ''} onChange={(event) => filters.set('type', event.target.value || null)} aria-label="نوع">
                            <option value="">همه‌ی انواع</option>
                            {options('ticketType', choices.types).map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                    </>
                }
            >
                <DataTable
                    columns={columns}
                    rows={tickets.data}
                    onRowClick={(ticket) => editor.show(ticket)}
                    rowClassName={(ticket) => (ticket.status === 'open' ? 'is-highlight' : '')}
                    empty={<EmptyState icon="bi-inbox" title="تیکتی نیست" description="وقتی فریلنسر یا کارفرمایی درخواست کمک بدهد اینجا نمایش داده می‌شود." />}
                    actions={(ticket) => (
                        <button type="button" className="btn btn-soft btn-sm" onClick={() => editor.show(ticket)}>
                            {ticket.status === 'open' ? 'واگذاری' : 'مدیریت'}
                        </button>
                    )}
                />
            </TableCard>

            <TicketForm modal={editor} mentors={mentors} statuses={choices.statuses} routes={routes} />
        </>
    );
}
