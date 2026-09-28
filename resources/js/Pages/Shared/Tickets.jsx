import ModalForm from '../../Components/Form/ModalForm';
import RadioCards from '../../Components/Form/RadioCards';
import TextInput from '../../Components/Form/TextInput';
import Textarea from '../../Components/Form/Textarea';
import PageHeader from '../../Components/Panel/PageHeader';
import Pagination from '../../Components/Panel/Pagination';
import { Person } from '../../Components/UI/Avatar';
import { Badge, StatusBadge } from '../../Components/UI/Badge';
import EmptyState from '../../Components/UI/EmptyState';
import Modal, { ModalBody } from '../../Components/UI/Modal';
import { useModal } from '../../hooks/useModal';
import { formatDateTime, formatNumber, formatRelative } from '../../lib/format';
import { label } from '../../lib/labels';

const TYPE_TEXT = {
    technical: { icon: 'bi-code-slash', description: 'گیر کرده‌ای، باگ داری یا نمی‌دانی کار را از کجا شروع کنی.' },
    motivational: { icon: 'bi-emoji-smile', description: 'انگیزه‌ات کم شده یا از شروع کار با مشتری واقعی نگرانی.' },
};

const CHANNEL_TEXT = {
    ticket: { icon: 'bi-chat-left-text', description: 'پاسخ مکتوب در همین پنل' },
    phone: { icon: 'bi-telephone', description: 'منتور با تو تماس می‌گیرد' },
};

const STEPS = ['open', 'assigned', 'in_progress', 'closed'];

function TicketForm({ modal, options: choices, routes }) {
    return (
        <ModalForm
            open={modal.open}
            onClose={modal.close}
            title="درخواست کمک از منتور"
            subtitle="منتورهای جامپ‌لنسر کنار تازه‌کارها هستند؛ هر سؤالی داری بپرس."
            icon="bi-life-preserver"
            size="lg"
            method="post"
            url={routes.store}
            initial={{ ticket_type: 'technical', channel: 'ticket', phone_number: '', subject: '', message: '' }}
            transform={(data) => ({ ...data, phone_number: data.channel === 'phone' ? data.phone_number : null })}
            submitLabel="ارسال درخواست"
            submitIcon="bi-send"
        >
            {(form) => (
                <div className="row g-3">
                    <RadioCards
                        form={form}
                        name="ticket_type"
                        label="چه کمکی لازم داری؟"
                        className="col-12"
                        columns={2}
                        options={choices.types.map((type) => ({ value: type, label: label('ticketType', type), ...TYPE_TEXT[type] }))}
                    />
                    <RadioCards
                        form={form}
                        name="channel"
                        label="از چه راهی؟"
                        className="col-12"
                        columns={2}
                        options={choices.channels.map((channel) => ({ value: channel, label: label('ticketChannel', channel), ...CHANNEL_TEXT[channel] }))}
                    />
                    {form.data.channel === 'phone' && (
                        <TextInput form={form} name="phone_number" label="شماره‌ی موبایل برای تماس" type="tel" required ltr icon="bi-phone" className="col-12" placeholder="09xxxxxxxxx" inputMode="numeric" />
                    )}
                    <TextInput form={form} name="subject" label="موضوع" required className="col-12" maxLength={200} placeholder="مثلاً خطای ۴۱۹ در فرم لاگین لاراول" />
                    <Textarea form={form} name="message" label="توضیح" required rows={5} minLength={10} maxLength={5000} className="col-12" placeholder="هر چه دقیق‌تر بنویسی، منتور سریع‌تر کمکت می‌کند." />
                </div>
            )}
        </ModalForm>
    );
}

function TicketDetails({ modal }) {
    const ticket = modal.record;
    const step = ticket ? STEPS.indexOf(ticket.status) : 0;

    return (
        <Modal open={modal.open} onClose={modal.close} title={ticket?.subject ?? ''} subtitle={ticket ? formatDateTime(ticket.created_at) : null} icon="bi-life-preserver" size="lg">
            {ticket && (
                <ModalBody>
                    <ol className="jl-steps mb-4">
                        {STEPS.map((status, index) => (
                            <li key={status} className={index < step ? 'is-done' : index === step ? 'is-current' : ''}>
                                <span className="jl-steps-dot">{index < step ? <i className="bi bi-check" /> : formatNumber(index + 1)}</span>
                                {label('ticketStatus', status)}
                            </li>
                        ))}
                    </ol>
                    <div className="d-flex flex-wrap gap-2 mb-3">
                        <StatusBadge group="ticketType" value={ticket.ticket_type} />
                        <Badge tone="secondary" icon={CHANNEL_TEXT[ticket.channel]?.icon}>
                            {label('ticketChannel', ticket.channel)}
                        </Badge>
                        {ticket.programs_count > 0 && (
                            <Badge tone="success" icon="bi-people">
                                برنامه‌ی منتورینگ شروع شده
                            </Badge>
                        )}
                    </div>
                    <div className="jl-text-block mb-3">{ticket.message}</div>
                    {ticket.assigned_mentor ? (
                        <div className="d-flex align-items-center gap-2">
                            <span className="small text-muted">منتور تو:</span>
                            <Person user={ticket.assigned_mentor} />
                        </div>
                    ) : (
                        <div className="small text-muted">
                            <i className="bi bi-hourglass-split" /> در صف واگذاری به منتور است؛ معمولاً کمتر از یک روز طول می‌کشد.
                        </div>
                    )}
                </ModalBody>
            )}
        </Modal>
    );
}

export default function Tickets({ tickets, options: choices, routes }) {
    const creator = useModal();
    const details = useModal();

    return (
        <>
            <PageHeader
                title="درخواست منتورینگ"
                description="سؤال فنی داری یا انگیزه لازم داری؟ یک منتور کنارت است."
                primary={{ label: 'درخواست جدید', icon: 'bi-plus-lg', onClick: () => creator.show(null) }}
            />

            {tickets.data.length === 0 ? (
                <EmptyState
                    title="هنوز درخواستی نفرستاده‌ای"
                    description="اولین قدم‌ها سخت‌اند؛ منتورها برای همین اینجا هستند."
                    action={
                        <button type="button" className="btn btn-primary btn-sm" onClick={() => creator.show(null)}>
                            <i className="bi bi-life-preserver" /> درخواست کمک
                        </button>
                    }
                />
            ) : (
                <div className="row g-3">
                    {tickets.data.map((ticket, index) => (
                        <div className="col-md-6 col-xl-4" key={ticket.id}>
                            <button type="button" className={`jl-card jl-card-hover h-100 w-100 text-start jl-rise jl-rise-${Math.min(index + 1, 8)}`} onClick={() => details.show(ticket)}>
                                <div className="jl-card-body d-flex flex-column h-100 gap-2">
                                    <div className="d-flex align-items-start justify-content-between gap-2">
                                        <span className="fw-bold jl-clamp-2">{ticket.subject}</span>
                                        <StatusBadge group="ticketStatus" value={ticket.status} />
                                    </div>
                                    <p className="small text-muted-2 jl-clamp-2 mb-0">{ticket.message}</p>
                                    <div className="mt-auto d-flex align-items-center justify-content-between gap-2 small text-muted">
                                        <span>
                                            <i className={`bi ${TYPE_TEXT[ticket.ticket_type]?.icon}`} /> {label('ticketType', ticket.ticket_type)}
                                        </span>
                                        <span>{ticket.assigned_mentor ? ticket.assigned_mentor.name : formatRelative(ticket.created_at)}</span>
                                    </div>
                                </div>
                            </button>
                        </div>
                    ))}
                </div>
            )}

            {tickets.meta?.last_page > 1 && (
                <div className="jl-card mt-3">
                    <Pagination meta={tickets.meta} />
                </div>
            )}

            <TicketForm modal={creator} options={choices} routes={routes} />
            <TicketDetails modal={details} />
        </>
    );
}
