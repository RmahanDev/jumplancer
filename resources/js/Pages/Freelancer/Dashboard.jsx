import { Link } from '@inertiajs/react';
import { useMemo } from 'react';
import ChartCard from '../../Components/Charts/ChartCard';
import ColumnChart from '../../Components/Charts/ColumnChart';
import PageHeader from '../../Components/Panel/PageHeader';
import { Badge, StatusBadge } from '../../Components/UI/Badge';
import EmptyState from '../../Components/UI/EmptyState';
import Meter from '../../Components/UI/Meter';
import StatCard from '../../Components/UI/StatCard';
import { useAuthUser, useNavHref } from '../../hooks/usePanel';
import { formatDate, formatMoney, formatNumber, formatRelative, formatShortDate } from '../../lib/format';
import { label } from '../../lib/labels';
import { budgetText } from '../../lib/project';

const CHECKLIST = {
    headline: { text: 'عنوان حرفه‌ای بنویس', route: 'profile' },
    bio: { text: 'درباره‌ی خودت بنویس', route: 'profile' },
    phone: { text: 'شماره‌ی موبایل را ثبت کن', route: 'profile' },
    field: { text: 'یک حوزه‌ی کاری فعال داشته باش', route: 'fields' },
    portfolio: { text: 'اولین نمونه‌کار را اضافه کن', route: 'portfolio' },
    proposal: { text: 'اولین پیشنهادت را بفرست', route: 'projects' },
};

function isoDay(date) {
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

/** Net income (released milestones minus fees) per week for the last 13 weeks. */
function weeklyEarnings(rows) {
    const today = new Date();
    today.setHours(12, 0, 0, 0);

    return Array.from({ length: 13 }, (_, index) => {
        const end = new Date(today);
        end.setDate(end.getDate() - (12 - index) * 7);
        const start = new Date(end);
        start.setDate(start.getDate() - 6);
        const from = isoDay(start);
        const to = isoDay(end);
        const value = rows.filter((row) => row.date >= from && row.date <= to).reduce((sum, row) => sum + Number(row.amount), 0);

        return {
            key: from,
            label: index % 3 === 0 || index === 12 ? formatShortDate(to) : '',
            tooltip: `${formatDate(from)} تا ${formatDate(to)}`,
            value: Math.max(0, value),
            highlight: index === 12,
        };
    });
}

export default function Dashboard({ stats, checklist, earnings, recommended, recentProposals, routes }) {
    const user = useAuthUser();
    const href = useNavHref();
    const weeks = useMemo(() => weeklyEarnings(earnings), [earnings]);
    const done = checklist.filter((item) => item.done).length;

    return (
        <>
            <PageHeader
                title={`سلام ${user?.name ?? ''}، آماده‌ی پروژه‌ی بعدی؟`}
                description="پیشنهادهایت، قراردادها و درآمدت در یک نگاه."
                actions={
                    <>
                        {stats.level && <StatusBadge group="level" value={stats.level} />}
                        <Link href={routes.projects} className="btn btn-primary">
                            <i className="bi bi-search" /> پیدا کردن پروژه
                        </Link>
                    </>
                }
            />

            <div className="row g-3 mb-4">
                <div className="col-6 col-xl">
                    <StatCard label="پیشنهادهای فعال" value={stats.active_proposals} icon="bi-send" href={routes.proposals} index={0} foot="در انتظار پاسخ یا در فهرست کوتاه" />
                </div>
                <div className="col-6 col-xl">
                    <StatCard label="قرارداد در جریان" value={stats.active_contracts} icon="bi-file-earmark-check" tone="accent" href={href('freelancer.contracts.index')} index={1} />
                </div>
                <div className="col-6 col-xl">
                    <StatCard label="پروژه‌ی تمام‌شده" value={stats.completed_contracts} icon="bi-trophy" index={2} />
                </div>
                <div className="col-6 col-xl">
                    <StatCard label="درآمد خالص" value={stats.earned} icon="bi-graph-up-arrow" format="money" tone="accent" index={3} foot="پس از کسر کارمزد پلتفرم" />
                </div>
                <div className="col-12 col-xl">
                    <StatCard label="موجودی کیف پول" value={stats.balance} icon="bi-wallet2" format="money" href={href('wallet.show')} index={4} />
                </div>
            </div>

            <div className="row g-4 mb-4">
                <div className="col-xl-4">
                    <section className="jl-card h-100 jl-rise jl-rise-2">
                        <header className="jl-card-header">
                            <div>
                                <h2>آمادگی برای پروژه‌ی واقعی</h2>
                                <p>امتیازی که از آزمون‌ها و بازخورد منتورها گرفته‌ای</p>
                            </div>
                            <span className="jl-hero-number fs-2 text-accent">{formatNumber(stats.readiness)}٪</span>
                        </header>
                        <div className="jl-card-body pt-0">
                            <Meter value={stats.readiness} tone="accent" label="امتیاز آمادگی" />
                            <div className="d-flex align-items-center justify-content-between mt-4 mb-1 small">
                                <strong>قدم‌های پروفایل</strong>
                                <span className="text-muted">
                                    {formatNumber(done)} از {formatNumber(checklist.length)} انجام شده
                                </span>
                            </div>
                            <ul className="jl-checklist">
                                {checklist.map((item) => (
                                    <li key={item.key} className={item.done ? 'is-done' : ''}>
                                        <i className={`bi ${item.done ? 'bi-check-circle-fill' : 'bi-circle'}`} />
                                        <span className="flex-grow-1">{CHECKLIST[item.key]?.text ?? item.key}</span>
                                        {!item.done && CHECKLIST[item.key] && (
                                            <Link href={routes[CHECKLIST[item.key].route]} className="btn btn-link btn-sm p-0 text-decoration-none">
                                                انجام بده
                                            </Link>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        </div>
                    </section>
                </div>

                <div className="col-xl-8">
                    <ChartCard
                        title="درآمد هفتگی"
                        subtitle="۱۳ هفته‌ی اخیر، پس از کسر کارمزد"
                        table={{ head: ['هفته', 'درآمد'], rows: weeks.map((week) => [week.tooltip, formatMoney(week.value)]) }}
                    >
                        {weeks.some((week) => week.value > 0) ? (
                            <ColumnChart data={weeks} money height={250} seriesLabel="درآمد" />
                        ) : (
                            <EmptyState compact icon="bi-graph-up" title="هنوز درآمدی ثبت نشده" description="با تحویل اولین مرحله، درآمدت اینجا رشد می‌کند." />
                        )}
                    </ChartCard>
                </div>
            </div>

            <div className="row g-4">
                <div className="col-xl-7">
                    <section className="jl-card h-100 jl-rise jl-rise-3">
                        <header className="jl-card-header">
                            <div>
                                <h2>پروژه‌های پیشنهادی برای تو</h2>
                                <p>از حوزه‌های فعالت، مناسب تازه‌کارها اول</p>
                            </div>
                            <Link href={routes.projects} className="btn btn-ghost btn-sm">
                                همه
                            </Link>
                        </header>
                        <div className="jl-card-body pt-0">
                            {recommended.length === 0 ? (
                                <EmptyState
                                    compact
                                    icon="bi-bullseye"
                                    title="پروژه‌ی مناسبی پیدا نشد"
                                    description="یک حوزه‌ی کاری فعال کن تا پروژه‌های مرتبط اینجا بیایند."
                                    action={
                                        <Link href={routes.fields} className="btn btn-soft btn-sm">
                                            حوزه‌های کاری
                                        </Link>
                                    }
                                />
                            ) : (
                                <ul className="jl-list">
                                    {recommended.map((project) => (
                                        <li key={project.id}>
                                            <div className="min-w-0 flex-grow-1">
                                                <Link href={`${routes.projects}?search=${encodeURIComponent(project.title)}`} className="fw-semibold text-body text-decoration-none d-block text-truncate">
                                                    {project.title}
                                                </Link>
                                                <div className="small text-muted text-truncate">
                                                    {project.category?.name} · {budgetText(project)} · {formatNumber(project.proposals_count ?? 0)} پیشنهاد
                                                </div>
                                            </div>
                                            {project.is_beginner_friendly && <Badge tone="success">تازه‌کار</Badge>}
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                    </section>
                </div>

                <div className="col-xl-5">
                    <section className="jl-card h-100 jl-rise jl-rise-4">
                        <header className="jl-card-header">
                            <div>
                                <h2>آخرین پیشنهادهای من</h2>
                                <p>وضعیت پاسخ کارفرماها</p>
                            </div>
                            <Link href={routes.proposals} className="btn btn-ghost btn-sm">
                                همه
                            </Link>
                        </header>
                        <div className="jl-card-body pt-0">
                            {recentProposals.length === 0 ? (
                                <EmptyState compact icon="bi-send" title="هنوز پیشنهادی نفرستاده‌ای" />
                            ) : (
                                <ul className="jl-list">
                                    {recentProposals.map((proposal) => (
                                        <li key={proposal.id}>
                                            <div className="min-w-0 flex-grow-1">
                                                <div className="fw-semibold text-truncate">{proposal.project?.title}</div>
                                                <div className="small text-muted">
                                                    {formatMoney(proposal.proposed_price)} · {formatRelative(proposal.created_at)}
                                                </div>
                                            </div>
                                            <StatusBadge group="proposalStatus" value={proposal.status} />
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                    </section>
                </div>
            </div>

            <p className="small text-muted mt-4 mb-0">
                <i className="bi bi-life-preserver" /> جایی گیر کرده‌ای؟{' '}
                <Link href={routes.tickets}>از منتورها کمک بخواه</Link> — سطح فعلی تو {label('level', stats.level)} است.
            </p>
        </>
    );
}
