import { Link } from '@inertiajs/react';
import AreaChart from '../../Components/Charts/AreaChart';
import ChartCard from '../../Components/Charts/ChartCard';
import ColumnChart from '../../Components/Charts/ColumnChart';
import HBarList from '../../Components/Charts/HBarList';
import PageHeader from '../../Components/Panel/PageHeader';
import { Person } from '../../Components/UI/Avatar';
import { StatusBadge } from '../../Components/UI/Badge';
import EmptyState from '../../Components/UI/EmptyState';
import { ChartSkeleton } from '../../Components/UI/Skeleton';
import StatCard from '../../Components/UI/StatCard';
import { useAuthUser, useNavHref } from '../../hooks/usePanel';
import { formatDate, formatMoney, formatNumber, formatRelative, formatShortDate, formatWeekday } from '../../lib/format';
import { label } from '../../lib/labels';

function greeting() {
    const hour = new Date().getHours();

    if (hour < 5) {
        return 'شب بخیر';
    }

    if (hour < 12) {
        return 'صبح بخیر';
    }

    if (hour < 17) {
        return 'روز بخیر';
    }

    return 'عصر بخیر';
}

function sumLast(series, days) {
    return series.slice(-days).reduce((total, point) => total + (Number(point.value) || 0), 0);
}

export default function Dashboard({ stats, charts, recent }) {
    const user = useAuthUser();
    const href = useNavHref();
    const today = new Date().toISOString();

    const cards = [
        { key: 'users', label: 'کاربران', value: stats.users, icon: 'bi-people', href: href('admin.users.index'), foot: `${formatNumber(stats.new_users_week)} عضو جدید در ۷ روز اخیر` },
        {
            key: 'open_projects',
            label: 'پروژه‌های باز',
            value: stats.open_projects,
            icon: 'bi-kanban',
            tone: 'accent',
            href: href('admin.projects.index', stats.pending_projects ? { status: 'pending_review' } : null),
            foot: stats.pending_projects ? `${formatNumber(stats.pending_projects)} پروژه منتظر بررسی توست` : 'صف بررسی خالی است',
        },
        { key: 'active_contracts', label: 'قراردادهای فعال', value: stats.active_contracts, icon: 'bi-file-earmark-check', href: href('admin.contracts.index'), foot: stats.open_disputes ? `${formatNumber(stats.open_disputes)} اختلاف باز` : 'بدون اختلاف باز' },
        { key: 'escrow', label: 'پول در امانت', value: stats.escrow, icon: 'bi-safe2', tone: 'accent', format: 'money', href: href('admin.transactions.index'), foot: 'مبلغ بلوکه‌شده‌ی مرحله‌های تأمین‌شده' },
        { key: 'revenue', label: 'درآمد پلتفرم', value: stats.revenue, icon: 'bi-graph-up-arrow', format: 'money', href: href('admin.transactions.index'), foot: 'کارمزدها و فروش پلن از ابتدا' },
        { key: 'open_tickets', label: 'تیکت‌های در صف', value: stats.open_tickets, icon: 'bi-life-preserver', tone: 'accent', href: href('admin.tickets.index', { status: 'open' }), foot: 'درخواست‌های منتورینگ بدون منتور' },
    ];

    const signups = charts?.signups ?? [];
    const revenue = charts?.revenue ?? [];

    return (
        <>
            <PageHeader
                title={`${greeting()}، ${user?.name ?? ''}`}
                description={`${formatWeekday(today)} ${formatDate(today)} — خلاصه‌ی وضعیت بازارگاه در یک نگاه.`}
                actions={
                    href('admin.projects.index') && stats.pending_projects > 0 ? (
                        <Link href={href('admin.projects.index', { status: 'pending_review' })} className="btn btn-accent">
                            <i className="bi bi-hourglass-split" /> بررسی {formatNumber(stats.pending_projects)} پروژه
                        </Link>
                    ) : null
                }
            />

            <div className="row g-3 mb-4">
                {cards.map(({ key, ...card }, index) => (
                    <div className="col-6 col-lg-4 col-xxl-2" key={key}>
                        <StatCard {...card} index={index} />
                    </div>
                ))}
            </div>

            <div className="row g-4 mb-4">
                <div className="col-xl-8">
                    <ChartCard
                        title="درآمد پلتفرم"
                        subtitle={charts ? `هفتگی در ۶ ماه اخیر · ۳۰ روز گذشته: ${formatMoney(charts.revenueLast30)}` : '۶ ماه اخیر'}
                        table={charts ? { head: ['شروع هفته', 'درآمد'], rows: revenue.filter((point) => point.value > 0).map((point) => [formatDate(point.date), formatMoney(point.value)]) } : null}
                    >
                        {charts ? <AreaChart data={revenue} money period="week" seriesLabel="درآمد" height={260} /> : <ChartSkeleton height={260} />}
                    </ChartCard>
                </div>
                <div className="col-xl-4">
                    <ChartCard
                        title="عضویت‌های جدید"
                        subtitle={charts ? `${formatNumber(sumLast(signups, 30))} عضو در ۳۰ روز اخیر` : '۳۰ روز اخیر'}
                        table={charts ? { head: ['روز', 'عضو جدید'], rows: signups.map((point) => [formatDate(point.date), formatNumber(point.value)]) } : null}
                    >
                        {charts ? (
                            <ColumnChart
                                height={260}
                                seriesLabel="عضو جدید"
                                data={signups.map((point, index) => ({
                                    key: point.date,
                                    label: index % 7 === signups.length % 7 || index === signups.length - 1 ? formatShortDate(point.date) : '',
                                    tooltip: formatDate(point.date),
                                    value: point.value,
                                    highlight: index === signups.length - 1,
                                }))}
                            />
                        ) : (
                            <ChartSkeleton height={260} />
                        )}
                    </ChartCard>
                </div>
            </div>

            <div className="row g-4 mb-4">
                <div className="col-lg-6">
                    <ChartCard title="پروژه‌ها بر اساس وضعیت" subtitle="همه‌ی پروژه‌های ثبت‌شده">
                        {charts ? (
                            <HBarList items={Object.entries(charts.projectsByStatus).map(([status, value]) => ({ key: status, label: label('projectStatus', status), value }))} />
                        ) : (
                            <ChartSkeleton height={180} />
                        )}
                    </ChartCard>
                </div>
                <div className="col-lg-6">
                    <ChartCard title="کاربران بر اساس نقش" subtitle="هر کاربر می‌تواند چند نقش داشته باشد">
                        {charts ? (
                            <HBarList items={Object.entries(charts.usersByRole).map(([role, value]) => ({ key: role, label: label('role', role), value }))} />
                        ) : (
                            <ChartSkeleton height={180} />
                        )}
                    </ChartCard>
                </div>
            </div>

            <div className="row g-4">
                <div className="col-xl-4">
                    <section className="jl-card h-100 jl-rise jl-rise-3">
                        <header className="jl-card-header">
                            <div>
                                <h2>منتظر بررسی</h2>
                                <p>قدیمی‌ترین پروژه‌ها اول</p>
                            </div>
                            {href('admin.projects.index') && (
                                <Link href={href('admin.projects.index', { status: 'pending_review' })} className="btn btn-ghost btn-sm">
                                    همه
                                </Link>
                            )}
                        </header>
                        <div className="jl-card-body pt-0">
                            {recent.pendingProjects.length === 0 ? (
                                <EmptyState compact icon="bi-check2-all" title="صف بررسی خالی است" />
                            ) : (
                                <ul className="jl-list">
                                    {recent.pendingProjects.map((project) => (
                                        <li key={project.id}>
                                            <div className="min-w-0 flex-grow-1">
                                                <div className="fw-semibold text-truncate">{project.title}</div>
                                                <div className="small text-muted text-truncate">
                                                    {project.employer?.name} · {project.category?.name} · {formatRelative(project.submitted_at ?? project.created_at)}
                                                </div>
                                            </div>
                                            <StatusBadge group="projectStatus" value={project.status} />
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                    </section>
                </div>

                <div className="col-xl-4">
                    <section className="jl-card h-100 jl-rise jl-rise-4">
                        <header className="jl-card-header">
                            <div>
                                <h2>اختلاف‌های باز</h2>
                                <p>قراردادهایی که داوری می‌خواهند</p>
                            </div>
                            {href('admin.contracts.index') && (
                                <Link href={href('admin.contracts.index', { tab: 'disputes' })} className="btn btn-ghost btn-sm">
                                    همه
                                </Link>
                            )}
                        </header>
                        <div className="jl-card-body pt-0">
                            {recent.disputes.length === 0 ? (
                                <EmptyState compact icon="bi-emoji-smile" title="اختلاف بازی نیست" />
                            ) : (
                                <ul className="jl-list">
                                    {recent.disputes.map((dispute) => (
                                        <li key={dispute.id}>
                                            <div className="min-w-0 flex-grow-1">
                                                <div className="fw-semibold text-truncate">{dispute.contract?.project_title ?? `قرارداد #${dispute.contract?.id}`}</div>
                                                <div className="small text-muted text-truncate">
                                                    {dispute.initiator?.name} · {formatRelative(dispute.created_at)}
                                                </div>
                                            </div>
                                            <StatusBadge group="disputeStatus" value={dispute.status} />
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                    </section>
                </div>

                <div className="col-xl-4">
                    <section className="jl-card h-100 jl-rise jl-rise-5">
                        <header className="jl-card-header">
                            <div>
                                <h2>تازه‌واردها</h2>
                                <p>آخرین عضویت‌ها</p>
                            </div>
                            {href('admin.users.index') && (
                                <Link href={href('admin.users.index')} className="btn btn-ghost btn-sm">
                                    همه
                                </Link>
                            )}
                        </header>
                        <div className="jl-card-body pt-0">
                            <ul className="jl-list">
                                {recent.users.map((member) => (
                                    <li key={member.id}>
                                        <div className="min-w-0 flex-grow-1">
                                            <Person user={member} />
                                        </div>
                                        <span className="small text-muted text-nowrap">{formatRelative(member.created_at)}</span>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    </section>
                </div>
            </div>
        </>
    );
}
