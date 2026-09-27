import { Link } from '@inertiajs/react';
import { useMemo } from 'react';
import ChartCard from '../../Components/Charts/ChartCard';
import ColumnChart from '../../Components/Charts/ColumnChart';
import PageHeader from '../../Components/Panel/PageHeader';
import { Person } from '../../Components/UI/Avatar';
import { Badge, StatusBadge } from '../../Components/UI/Badge';
import EmptyState from '../../Components/UI/EmptyState';
import StatCard from '../../Components/UI/StatCard';
import { useAuthUser } from '../../hooks/usePanel';
import { formatDate, formatDateTime, formatNumber, formatRelative, formatShortDate, formatTime, formatWeekday } from '../../lib/format';

/** Saturday of the week (Persian weeks start on Saturday), as YYYY-MM-DD. */
function weekStart(date) {
    const day = new Date(date);
    day.setHours(12, 0, 0, 0);
    day.setDate(day.getDate() - ((day.getDay() + 1) % 7));

    return `${day.getFullYear()}-${String(day.getMonth() + 1).padStart(2, '0')}-${String(day.getDate()).padStart(2, '0')}`;
}

function weeklySessions(dates) {
    const counts = dates.reduce((all, date) => {
        const key = weekStart(date);

        return { ...all, [key]: (all[key] ?? 0) + 1 };
    }, {});

    const current = weekStart(new Date());

    return Array.from({ length: 9 }, (_, index) => {
        const day = new Date(`${current}T12:00:00`);
        day.setDate(day.getDate() + (index - 7) * 7);
        const key = weekStart(day);

        return {
            key,
            label: index === 7 ? 'این هفته' : index === 8 ? 'هفته‌ی بعد' : formatShortDate(key),
            tooltip: `هفته‌ی ${formatDate(key)}`,
            value: counts[key] ?? 0,
            highlight: index === 7,
        };
    });
}

function Stars({ value }) {
    const rounded = Math.round(value);

    return (
        <span className="jl-rating" aria-label={`${formatNumber(value)} از ۵`}>
            {'★'.repeat(rounded)}
            <span className="opacity-25">{'★'.repeat(5 - rounded)}</span>
        </span>
    );
}

export default function Dashboard({ stats, upcoming, recentTickets, sessionDates, profile, routes }) {
    const user = useAuthUser();
    const weeks = useMemo(() => weeklySessions(sessionDates), [sessionDates]);

    const cards = [
        { key: 'queue', label: 'تیکت‌های صف', value: stats.queue, icon: 'bi-inbox', tone: 'accent', href: `${routes.tickets}?tab=queue`, foot: stats.queue ? 'تازه‌کارهایی که منتظر یک منتورند' : 'صف خالی است' },
        { key: 'my_tickets', label: 'تیکت‌های من', value: stats.my_tickets, icon: 'bi-person-check', href: `${routes.tickets}?tab=mine`, foot: 'واگذارشده یا در حال پیگیری' },
        {
            key: 'active_programs',
            label: 'برنامه‌های فعال',
            value: stats.active_programs,
            icon: 'bi-people',
            href: routes.programs,
            foot: profile.max_mentees ? `ظرفیت ${formatNumber(profile.max_mentees)} منتی هم‌زمان` : null,
        },
        { key: 'sessions_week', label: 'جلسه در ۷ روز آینده', value: stats.sessions_week, icon: 'bi-calendar-event', tone: 'accent', href: routes.programs },
        { key: 'pending_reviews', label: 'پیشنهاد منتظر بازبینی', value: stats.pending_reviews, icon: 'bi-chat-square-quote', href: routes.reviews, foot: 'قبل از ارسال به کارفرما نظر بده' },
    ];

    return (
        <>
            <PageHeader
                title={`سلام ${user?.name ?? ''}`}
                description={`${formatWeekday(new Date().toISOString())} ${formatDate(new Date().toISOString())} — امروز به کدام تازه‌کار کمک می‌کنی؟`}
                actions={
                    profile.is_verified ? (
                        <Badge tone="success" icon="bi-patch-check-fill">
                            منتور تأییدشده
                        </Badge>
                    ) : (
                        <Badge tone="warning" icon="bi-hourglass-split">
                            در انتظار تأیید پلتفرم
                        </Badge>
                    )
                }
            />

            <div className="row g-3 mb-4">
                {cards.map(({ key, ...card }, index) => (
                    <div className="col-6 col-lg-4 col-xxl" key={key}>
                        <StatCard {...card} index={index} />
                    </div>
                ))}
                <div className="col-6 col-lg-4 col-xxl">
                    <div className="jl-card jl-stat h-100 jl-rise jl-rise-6">
                        <div className="jl-stat-top">
                            <span className="jl-stat-label">میانگین امتیاز جلسه‌ها</span>
                            <span className="jl-stat-icon is-accent">
                                <i className="bi bi-star" />
                            </span>
                        </div>
                        <div className="jl-stat-value">{stats.avg_rating ? formatNumber(stats.avg_rating) : '—'}</div>
                        <div className="jl-stat-foot">{stats.avg_rating ? <Stars value={stats.avg_rating} /> : 'هنوز امتیازی ثبت نشده'}</div>
                    </div>
                </div>
            </div>

            <div className="row g-4">
                <div className="col-xl-7">
                    <ChartCard
                        title="جلسه‌های منتورینگ"
                        subtitle="در ۸ هفته‌ی گذشته و هفته‌ی آینده"
                        table={{ head: ['هفته', 'جلسه'], rows: weeks.map((week) => [week.tooltip, formatNumber(week.value)]) }}
                    >
                        <ColumnChart data={weeks} height={240} seriesLabel="جلسه" />
                    </ChartCard>
                </div>

                <div className="col-xl-5">
                    <section className="jl-card h-100 jl-rise jl-rise-3">
                        <header className="jl-card-header">
                            <div>
                                <h2>جلسه‌های پیش رو</h2>
                                <p>نزدیک‌ترین جلسه اول</p>
                            </div>
                            <Link href={routes.programs} className="btn btn-ghost btn-sm">
                                برنامه‌ها
                            </Link>
                        </header>
                        <div className="jl-card-body pt-0">
                            {upcoming.length === 0 ? (
                                <EmptyState compact icon="bi-calendar2-check" title="جلسه‌ای برنامه‌ریزی نشده" />
                            ) : (
                                <ul className="jl-list">
                                    {upcoming.map((session) => (
                                        <li key={session.id}>
                                            <div className="jl-date-tile">
                                                <span>{formatShortDate(session.scheduled_at)}</span>
                                                <strong>{formatTime(session.scheduled_at)}</strong>
                                            </div>
                                            <div className="min-w-0 flex-grow-1">
                                                <div className="fw-semibold text-truncate">{session.mentee?.name}</div>
                                                <div className="small text-muted">
                                                    <StatusBadge group="sessionType" value={session.session_type} /> · {formatNumber(session.duration_minutes)} دقیقه · {formatRelative(session.scheduled_at)}
                                                </div>
                                            </div>
                                            {session.meeting_link && (
                                                <a href={session.meeting_link} target="_blank" rel="noreferrer" className="btn btn-soft btn-sm" title={formatDateTime(session.scheduled_at)}>
                                                    <i className="bi bi-camera-video" /> ورود
                                                </a>
                                            )}
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                    </section>
                </div>

                <div className="col-12">
                    <section className="jl-card jl-rise jl-rise-4">
                        <header className="jl-card-header">
                            <div>
                                <h2>تیکت‌های اخیر</h2>
                                <p>تیکت‌های تو و تیکت‌های بدون منتور</p>
                            </div>
                            <Link href={routes.tickets} className="btn btn-ghost btn-sm">
                                همه‌ی تیکت‌ها
                            </Link>
                        </header>
                        <div className="jl-card-body pt-0">
                            {recentTickets.length === 0 ? (
                                <EmptyState compact icon="bi-inbox" title="تیکتی نیست" />
                            ) : (
                                <ul className="jl-list">
                                    {recentTickets.map((ticket) => (
                                        <li key={ticket.id}>
                                            <Person user={ticket.requester} size="sm" />
                                            <div className="min-w-0 flex-grow-1">
                                                <div className="fw-semibold text-truncate">{ticket.subject}</div>
                                                <div className="small text-muted">{formatRelative(ticket.created_at)}</div>
                                            </div>
                                            <StatusBadge group="ticketType" value={ticket.ticket_type} />
                                            <StatusBadge group="ticketStatus" value={ticket.status} />
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                    </section>
                </div>
            </div>
        </>
    );
}
