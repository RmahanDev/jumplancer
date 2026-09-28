import ModalForm from '../../../Components/Form/ModalForm';
import NumberInput from '../../../Components/Form/NumberInput';
import Textarea from '../../../Components/Form/Textarea';
import PageHeader from '../../../Components/Panel/PageHeader';
import Pagination from '../../../Components/Panel/Pagination';
import { Person } from '../../../Components/UI/Avatar';
import { Badge, StatusBadge } from '../../../Components/UI/Badge';
import { confirm } from '../../../Components/UI/ConfirmDialog';
import EmptyState from '../../../Components/UI/EmptyState';
import Tabs from '../../../Components/UI/Tabs';
import { useFilters } from '../../../hooks/useFilters';
import { useModal } from '../../../hooks/useModal';
import { put } from '../../../lib/actions';
import { formatDateTime, formatRelative } from '../../../lib/format';
import { label } from '../../../lib/labels';
import { fillRoute } from '../../../lib/text';

function ProgramForm({ modal, routes }) {
    const ticket = modal.record;
    const fromContract = Boolean(ticket?.contract);

    return (
        <ModalForm
            open={modal.open}
            onClose={modal.close}
            title="شروع برنامه‌ی منتورینگ"
            subtitle={ticket ? `برای ${ticket.requester?.name} — «${ticket.subject}»` : null}
            icon="bi-rocket-takeoff"
            size="lg"
            method="post"
            url={routes.startProgram}
            initial={{ ticket_id: ticket?.id ?? '', goal: fromContract ? `همراهی در پروژه‌ی «${ticket.contract.project_title ?? ''}» تا تحویل موفق` : '', price: '' }}
            transform={(data) => ({ ...data, price: data.price === '' ? null : Number(data.price) })}
            submitLabel="شروع برنامه"
            submitIcon="bi-rocket-takeoff"
        >
            {(form) => (
                <div className="row g-3">
                    {fromContract && (
                        <div className="col-12">
                            <div className="jl-callout is-info small">
                                <i className="bi bi-file-earmark-check" /> این فریلنسر در پیشنهادش برای پروژه‌ی «{ticket.contract.project_title}» منتور خواسته و استخدام شده. با شروع برنامه، تو منتور این
                                قرارداد می‌شوی؛ هزینه‌ی منتورینگ از کارمزد قرارداد تأمین می‌شود.
                            </div>
                        </div>
                    )}
                    <Textarea form={form} name="goal" label="هدف برنامه" required rows={4} maxLength={2000} className="col-12" placeholder="مثلاً: تحویل اولین پروژه‌ی وردپرسی با کیفیت و گرفتن نظر مثبت از کارفرما" />
                    {!fromContract && (
                        <NumberInput
                            form={form}
                            name="price"
                            label="هزینه‌ی برنامه"
                            money
                            className="col-md-6"
                            hint="برای تازه‌کارهایی که منتورینگ رایگان دارند خودکار صفر می‌شود."
                        />
                    )}
                </div>
            )}
        </ModalForm>
    );
}

export default function Index({ tab, tickets, counts, routes }) {
    const filters = useFilters({ tab });
    const programStarter = useModal();
    const current = filters.filters.tab ?? 'queue';

    const act = (ticket, action) => put(fillRoute(routes.update, ticket.id), { action });

    const close = async (ticket) => {
        const ok = await confirm({
            title: 'بستن تیکت؟',
            message: `تیکت «${ticket.subject}» بسته می‌شود. اگر برنامه‌ی منتورینگ دارد، برنامه سر جایش می‌ماند.`,
            confirmLabel: 'بستن تیکت',
            tone: 'primary',
            icon: 'bi-check2-all',
        });

        if (ok) {
            act(ticket, 'close');
        }
    };

    return (
        <>
            <PageHeader
                title="تیکت‌ها"
                description="از صف مشترک تیکت بردار و تا حل شدن مشکل کنار فریلنسر تازه‌کار بمان. وقتی فریلنسری که در پیشنهادش منتور خواسته استخدام شود، تیکتش خودکار به صف می‌آید."
            />

            <Tabs
                className="mb-3"
                items={[
                    { key: 'queue', label: 'صف مشترک', icon: 'bi-inbox', count: counts.queue },
                    { key: 'mine', label: 'تیکت‌های من', icon: 'bi-person-check', count: counts.mine },
                ]}
                active={current}
                onChange={(next) => filters.apply({ tab: next })}
            />

            {tickets.data.length === 0 ? (
                <EmptyState
                    icon="bi-inbox"
                    title={current === 'queue' ? 'صف خالی است' : 'تیکتی برنداشته‌ای'}
                    description={current === 'queue' ? 'فعلاً همه‌ی تازه‌کارها منتور دارند. عالی!' : 'از صف مشترک یک تیکت بردار.'}
                />
            ) : (
                <div className="d-grid gap-3">
                    {tickets.data.map((ticket, index) => (
                        <article key={ticket.id} className={`jl-card jl-rise jl-rise-${Math.min(index + 1, 8)} ${ticket.status === 'closed' ? 'is-muted' : ''}`}>
                            <div className="jl-card-body">
                                <div className="d-flex flex-wrap align-items-start justify-content-between gap-3">
                                    <div className="jl-row-main">
                                        <div className="d-flex flex-wrap align-items-center gap-2 mb-1">
                                            <h2 className="h6 fw-bold mb-0">{ticket.subject}</h2>
                                            <StatusBadge group="ticketType" value={ticket.ticket_type} />
                                            <StatusBadge group="ticketStatus" value={ticket.status} />
                                            {ticket.contract && (
                                                <Badge tone="info" icon="bi-file-earmark-check">
                                                    منتورینگ پروژه‌ی استخدام‌شده
                                                </Badge>
                                            )}
                                            {ticket.programs_count > 0 && (
                                                <Badge tone="success" icon="bi-people">
                                                    برنامه دارد
                                                </Badge>
                                            )}
                                        </div>
                                        <div className="small text-muted mb-2" title={formatDateTime(ticket.created_at)}>
                                            {formatRelative(ticket.created_at)} · {label('ticketChannel', ticket.channel)}
                                            {ticket.channel === 'phone' && ticket.phone_number && (
                                                <>
                                                    {' · '}
                                                    <a href={`tel:${ticket.phone_number}`} className="ltr">
                                                        {ticket.phone_number}
                                                    </a>
                                                </>
                                            )}
                                        </div>
                                        <p className="mb-0 text-muted-2 jl-clamp-3">{ticket.message}</p>
                                    </div>
                                    <div className="d-flex flex-column align-items-end gap-2">
                                        <Person user={ticket.requester} role="freelancer" />
                                        <div className="d-flex flex-wrap gap-2 justify-content-end">
                                            {ticket.status === 'open' && !ticket.assigned_mentor_id && (
                                                <button type="button" className="btn btn-primary btn-sm" onClick={() => act(ticket, 'take')}>
                                                    <i className="bi bi-hand-index-thumb" /> برمی‌دارم
                                                </button>
                                            )}
                                            {ticket.status === 'assigned' && (
                                                <button type="button" className="btn btn-soft btn-sm" onClick={() => act(ticket, 'start')}>
                                                    <i className="bi bi-play" /> شروع پیگیری
                                                </button>
                                            )}
                                            {['assigned', 'in_progress'].includes(ticket.status) && ticket.programs_count === 0 && (
                                                <button type="button" className="btn btn-accent btn-sm" onClick={() => programStarter.show(ticket)}>
                                                    <i className="bi bi-rocket-takeoff" /> برنامه‌ی منتورینگ
                                                </button>
                                            )}
                                            {['assigned', 'in_progress'].includes(ticket.status) && (
                                                <button type="button" className="btn btn-ghost btn-sm" onClick={() => close(ticket)}>
                                                    <i className="bi bi-check2-all" /> بستن
                                                </button>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </article>
                    ))}
                </div>
            )}

            {tickets.meta?.last_page > 1 && (
                <div className="jl-card mt-3">
                    <Pagination meta={tickets.meta} />
                </div>
            )}

            <ProgramForm modal={programStarter} routes={routes} />
        </>
    );
}
