import DateTimeInput from '../../../Components/Form/DateTimeInput';
import ModalForm from '../../../Components/Form/ModalForm';
import NumberInput from '../../../Components/Form/NumberInput';
import RadioCards from '../../../Components/Form/RadioCards';
import Select from '../../../Components/Form/Select';
import TextInput from '../../../Components/Form/TextInput';
import Textarea from '../../../Components/Form/Textarea';
import PageHeader from '../../../Components/Panel/PageHeader';
import Pagination from '../../../Components/Panel/Pagination';
import { Person } from '../../../Components/UI/Avatar';
import { Badge, StatusBadge } from '../../../Components/UI/Badge';
import { confirm } from '../../../Components/UI/ConfirmDialog';
import { DropdownDivider, DropdownItem } from '../../../Components/UI/Dropdown';
import EmptyState from '../../../Components/UI/EmptyState';
import RowActions from '../../../Components/UI/RowActions';
import Tabs from '../../../Components/UI/Tabs';
import { useFilters } from '../../../hooks/useFilters';
import { useModal } from '../../../hooks/useModal';
import { destroy } from '../../../lib/actions';
import { formatDate, formatDateTime, formatMoney, formatNumber, formatRelative } from '../../../lib/format';
import { label, options } from '../../../lib/labels';
import { fillRoute } from '../../../lib/text';

function ProgramForm({ modal, statuses, routes }) {
    const program = modal.record;

    return (
        <ModalForm
            open={modal.open}
            onClose={modal.close}
            title="ویرایش برنامه"
            subtitle={program ? `منتی: ${program.mentee?.name}` : null}
            icon="bi-people"
            method="put"
            url={program ? fillRoute(routes.update, program.id) : ''}
            initial={{ status: program?.status ?? 'active', goal: program?.goal ?? '' }}
        >
            {(form) => (
                <div className="d-grid gap-3">
                    <RadioCards
                        form={form}
                        name="status"
                        label="وضعیت"
                        columns={2}
                        options={statuses.map((status) => ({
                            value: status,
                            label: label('programStatus', status),
                            icon: { active: 'bi-play-circle', paused: 'bi-pause-circle', completed: 'bi-flag', cancelled: 'bi-x-circle' }[status],
                        }))}
                    />
                    <Textarea form={form} name="goal" label="هدف برنامه" required rows={4} maxLength={2000} />
                </div>
            )}
        </ModalForm>
    );
}

function SessionForm({ modal, sessionTypes, routes }) {
    const { program, session } = modal.record ?? {};
    const editing = Boolean(session);

    return (
        <ModalForm
            open={modal.open}
            onClose={modal.close}
            title={editing ? 'ویرایش جلسه' : 'جلسه‌ی جدید'}
            subtitle={program ? `با ${program.mentee?.name}` : null}
            icon="bi-calendar-plus"
            size="lg"
            method={editing ? 'put' : 'post'}
            url={editing ? fillRoute(routes.sessionUpdate, session.id) : program ? fillRoute(routes.sessionStore, program.id) : ''}
            initial={{
                scheduled_at: session?.scheduled_at ?? '',
                duration_minutes: session?.duration_minutes ?? 45,
                session_type: session?.session_type ?? 'technical',
                meeting_link: session?.meeting_link ?? '',
                ...(editing ? { status: session.status, mentor_notes: session.mentor_notes ?? '' } : {}),
            }}
            transform={(data) => ({ ...data, duration_minutes: Number(data.duration_minutes || 0), meeting_link: data.meeting_link || null })}
            submitLabel={editing ? 'ذخیره' : 'برنامه‌ریزی جلسه'}
        >
            {(form) => (
                <div className="row g-3">
                    <DateTimeInput form={form} name="scheduled_at" label="زمان جلسه" required className="col-md-8" min={editing ? null : new Date().toISOString().slice(0, 10)} />
                    <NumberInput form={form} name="duration_minutes" label="مدت" unit="دقیقه" required className="col-md-4" />
                    <RadioCards
                        form={form}
                        name="session_type"
                        label="نوع جلسه"
                        className="col-12"
                        columns={3}
                        options={sessionTypes.map((type) => ({ value: type, label: label('sessionType', type), icon: { technical: 'bi-code-slash', motivational: 'bi-emoji-smile', review: 'bi-search' }[type] }))}
                    />
                    <TextInput form={form} name="meeting_link" label="لینک جلسه‌ی آنلاین" ltr icon="bi-camera-video" className="col-12" placeholder="https://meet.google.com/..." />
                    {editing && (
                        <>
                            <Select form={form} name="status" label="وضعیت" required allowEmpty={false} className="col-md-4" options={options('sessionStatus')} />
                            <Textarea form={form} name="mentor_notes" label="یادداشت منتور" rows={3} maxLength={5000} className="col-md-8" placeholder="چه گذشت و قدم بعدی چیست؟" />
                        </>
                    )}
                </div>
            )}
        </ModalForm>
    );
}

export default function Index({ programs, filters: initialFilters, options: choices, routes }) {
    const filters = useFilters(initialFilters);
    const programEditor = useModal();
    const sessionEditor = useModal();

    const removeSession = async (session) => {
        const ok = await confirm({ title: 'حذف جلسه؟', message: `جلسه‌ی ${formatDateTime(session.scheduled_at)} حذف می‌شود.`, confirmLabel: 'حذف جلسه' });

        if (ok) {
            destroy(fillRoute(routes.sessionDestroy, session.id));
        }
    };

    return (
        <>
            <PageHeader title="برنامه‌های منتورینگ" description="هر برنامه از یک تیکت شروع می‌شود؛ جلسه‌ها را برنامه‌ریزی کن و بعد از هر جلسه یادداشت بگذار." />

            <Tabs
                className="mb-3"
                items={[{ key: '', label: 'همه' }, ...choices.statuses.map((status) => ({ key: status, label: label('programStatus', status) }))]}
                active={filters.filters.status ?? ''}
                onChange={(status) => filters.set('status', status || null)}
            />

            {programs.data.length === 0 ? (
                <EmptyState title="برنامه‌ای نیست" description="از صفحه‌ی تیکت‌ها، روی تیکتی که برداشته‌ای «برنامه‌ی منتورینگ» را بزن." />
            ) : (
                <div className="row g-3">
                    {programs.data.map((program, index) => {
                        const upcoming = (program.sessions ?? []).filter((session) => session.status === 'scheduled');

                        return (
                            <div className="col-xl-6" key={program.id}>
                                <article className={`jl-card h-100 jl-rise jl-rise-${Math.min(index + 1, 8)}`}>
                                    <header className="jl-card-header">
                                        <div className="min-w-0">
                                            <Person user={program.mentee} role="freelancer" meta={program.ticket?.subject ?? 'منتی فریلنسر'} />
                                        </div>
                                        <div className="d-flex align-items-center gap-1">
                                            <StatusBadge group="programStatus" value={program.status} />
                                            <RowActions>
                                                {(close) => (
                                                    <>
                                                        <DropdownItem
                                                            icon="bi-calendar-plus"
                                                            disabled={program.status !== 'active'}
                                                            onClick={() => {
                                                                close();
                                                                sessionEditor.show({ program });
                                                            }}
                                                        >
                                                            جلسه‌ی جدید
                                                        </DropdownItem>
                                                        <DropdownDivider />
                                                        <DropdownItem
                                                            icon="bi-pencil"
                                                            onClick={() => {
                                                                close();
                                                                programEditor.show(program);
                                                            }}
                                                        >
                                                            ویرایش وضعیت و هدف
                                                        </DropdownItem>
                                                    </>
                                                )}
                                            </RowActions>
                                        </div>
                                    </header>
                                    <div className="jl-card-body">
                                        <div className="d-flex flex-wrap gap-1 mb-2">
                                            {program.is_free_mentorship ? (
                                                <Badge tone="success" icon="bi-gift">
                                                    رایگان (تازه‌کار)
                                                </Badge>
                                            ) : program.price ? (
                                                <Badge tone="secondary">{formatMoney(program.price)}</Badge>
                                            ) : null}
                                            <Badge tone="secondary" icon="bi-calendar3">
                                                از {formatDate(program.started_at)}
                                            </Badge>
                                        </div>
                                        <p className="small text-muted-2 mb-3">{program.goal}</p>

                                        <div className="d-flex align-items-center justify-content-between mb-2">
                                            <h3 className="h6 fw-bold mb-0">جلسه‌ها ({formatNumber(program.sessions_count ?? 0)})</h3>
                                            {program.status === 'active' && (
                                                <button type="button" className="btn btn-soft btn-sm" onClick={() => sessionEditor.show({ program })}>
                                                    <i className="bi bi-plus-lg" /> جلسه
                                                </button>
                                            )}
                                        </div>
                                        {(program.sessions ?? []).length === 0 ? (
                                            <p className="small text-muted mb-0">هنوز جلسه‌ای برنامه‌ریزی نشده.</p>
                                        ) : (
                                            <ul className="jl-session-list">
                                                {program.sessions.map((session) => (
                                                    <li key={session.id} className={`is-${session.status}`}>
                                                        <div className="min-w-0 flex-grow-1">
                                                            <div className="fw-semibold small">
                                                                {formatDateTime(session.scheduled_at)}
                                                                <span className="text-muted fw-normal"> · {formatNumber(session.duration_minutes)} دقیقه</span>
                                                            </div>
                                                            <div className="d-flex flex-wrap gap-1 mt-1">
                                                                <StatusBadge group="sessionType" value={session.session_type} />
                                                                <StatusBadge group="sessionStatus" value={session.status} />
                                                                {session.mentee_rating && <span className="jl-rating small">{'★'.repeat(session.mentee_rating)}</span>}
                                                            </div>
                                                            {session.mentor_notes && <div className="small text-muted mt-1 jl-clamp-2">{session.mentor_notes}</div>}
                                                        </div>
                                                        <div className="d-flex gap-1">
                                                            {session.meeting_link && session.status === 'scheduled' && (
                                                                <a href={session.meeting_link} target="_blank" rel="noreferrer" className="jl-icon-btn" aria-label="ورود به جلسه" data-tip="ورود به جلسه">
                                                                    <i className="bi bi-camera-video" />
                                                                </a>
                                                            )}
                                                            <button type="button" className="jl-icon-btn" onClick={() => sessionEditor.show({ program, session })} aria-label="ویرایش جلسه" data-tip="ویرایش / ثبت نتیجه">
                                                                <i className="bi bi-pencil" />
                                                            </button>
                                                            {session.status === 'scheduled' && (
                                                                <button type="button" className="jl-icon-btn text-danger" onClick={() => removeSession(session)} aria-label="حذف جلسه" data-tip="حذف">
                                                                    <i className="bi bi-trash3" />
                                                                </button>
                                                            )}
                                                        </div>
                                                    </li>
                                                ))}
                                            </ul>
                                        )}
                                        {upcoming.length > 0 && <div className="small text-muted mt-2">جلسه‌ی بعدی {formatRelative(upcoming[0].scheduled_at)}</div>}
                                    </div>
                                </article>
                            </div>
                        );
                    })}
                </div>
            )}

            {programs.meta?.last_page > 1 && (
                <div className="jl-card mt-3">
                    <Pagination meta={programs.meta} />
                </div>
            )}

            <ProgramForm modal={programEditor} statuses={choices.statuses} routes={routes} />
            <SessionForm modal={sessionEditor} sessionTypes={choices.sessionTypes} routes={routes} />
        </>
    );
}
