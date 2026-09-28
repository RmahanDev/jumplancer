import { Link } from '@inertiajs/react';
import ChartCard from '../../Components/Charts/ChartCard';
import HBarList from '../../Components/Charts/HBarList';
import PageHeader from '../../Components/Panel/PageHeader';
import { Person } from '../../Components/UI/Avatar';
import { StatusBadge } from '../../Components/UI/Badge';
import EmptyState from '../../Components/UI/EmptyState';
import StatCard from '../../Components/UI/StatCard';
import PostingBanner from '../../Components/Domain/PostingBanner';
import { useAuthUser } from '../../hooks/usePanel';
import { formatMoney, formatNumber } from '../../lib/format';
import { label } from '../../lib/labels';

export default function Dashboard({ stats, projectsByStatus, posting, recentProposals, needsAttention, routes }) {
    const user = useAuthUser();

    return (
        <>
            <PageHeader
                title={`سلام ${user?.name ?? ''}`}
                description="پروژه‌ها، پیشنهادهای رسیده و پرداخت‌هایت در یک نگاه."
                actions={
                    <Link href={`${routes.projects}?create=1`} className="btn btn-primary">
                        <i className="bi bi-plus-lg" /> ثبت پروژه
                    </Link>
                }
            />

            <PostingBanner next={posting.next} plansUrl={routes.plans} />

            <div className="row g-3 mb-4">
                <div className="col-6 col-xl-3">
                    <StatCard label="پروژه‌های باز" value={stats.open_projects} icon="bi-kanban" href={routes.projects} index={0} foot={stats.pending_review ? `${formatNumber(stats.pending_review)} پروژه در انتظار بررسی` : 'در حال دریافت پیشنهاد'} />
                </div>
                <div className="col-6 col-xl-3">
                    <StatCard label="پیشنهاد جدید" value={stats.new_proposals} icon="bi-inboxes" tone="accent" href={routes.proposals} index={1} foot="منتظر پاسخ تو" />
                </div>
                <div className="col-6 col-xl-3">
                    <StatCard
                        label="قرارداد فعال"
                        value={stats.active_contracts}
                        icon="bi-file-earmark-check"
                        href={routes.contracts}
                        index={2}
                        foot={stats.awaiting_release ? `${formatNumber(stats.awaiting_release)} مرحله منتظر تأیید و پرداخت` : 'مرحله‌ای منتظر تأیید نیست'}
                    />
                </div>
                <div className="col-6 col-xl-3">
                    <StatCard label="پرداخت‌شده به فریلنسرها" value={stats.paid} icon="bi-send-check" tone="accent" format="money" index={3} />
                </div>
            </div>

            <div className="row g-4 mb-4">
                <div className="col-lg-4">
                    <section className="jl-card jl-wallet-hero h-100 jl-rise jl-rise-2">
                        <div className="jl-card-body">
                            <div className="d-flex align-items-center justify-content-between">
                                <span className="jl-wallet-label">موجودی کیف پول</span>
                                <i className="bi bi-wallet2 fs-4" />
                            </div>
                            <div className="jl-wallet-balance">
                                {formatMoney(stats.balance, { unit: false })}
                                <span>تومان</span>
                            </div>
                            <div className="jl-wallet-held">
                                <i className="bi bi-safe2" /> {formatMoney(stats.escrow)} در امانت
                            </div>
                            <Link href={routes.wallet} className="btn btn-light btn-sm mt-3">
                                <i className="bi bi-plus-circle" /> شارژ و تراکنش‌ها
                            </Link>
                        </div>
                    </section>
                </div>
                <div className="col-lg-8">
                    <ChartCard title="پروژه‌های من" subtitle="بر اساس وضعیت">
                        {Object.keys(projectsByStatus).length === 0 ? (
                            <EmptyState compact icon="bi-kanban" title="هنوز پروژه‌ای ثبت نکرده‌ای" />
                        ) : (
                            <HBarList items={Object.entries(projectsByStatus).map(([status, value]) => ({ key: status, label: label('projectStatus', status), value }))} />
                        )}
                    </ChartCard>
                </div>
            </div>

            <div className="row g-4">
                <div className="col-xl-7">
                    <section className="jl-card h-100 jl-rise jl-rise-3">
                        <header className="jl-card-header">
                            <div>
                                <h2>پیشنهادهای تازه</h2>
                                <p>در انتظار پاسخ یا در فهرست کوتاه</p>
                            </div>
                            <Link href={routes.proposals} className="btn btn-ghost btn-sm">
                                همه
                            </Link>
                        </header>
                        <div className="jl-card-body pt-0">
                            {recentProposals.length === 0 ? (
                                <EmptyState compact icon="bi-inboxes" title="پیشنهاد تازه‌ای نیست" />
                            ) : (
                                <ul className="jl-list">
                                    {recentProposals.map((proposal) => (
                                        <li key={proposal.id}>
                                            <Person user={proposal.freelancer} meta={proposal.freelancer?.level ? label('level', proposal.freelancer.level) : null} />
                                            <div className="min-w-0 flex-grow-1 text-truncate small text-muted">{proposal.project?.title}</div>
                                            <span className="jl-money small">{formatMoney(proposal.proposed_price)}</span>
                                            <StatusBadge group="proposalStatus" value={proposal.status} />
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
                                <h2>پیش‌نویس‌ها</h2>
                                <p>برای انتشار، کاملشان کن و بفرست</p>
                            </div>
                            <Link href={`${routes.projects}?status=draft`} className="btn btn-ghost btn-sm">
                                همه
                            </Link>
                        </header>
                        <div className="jl-card-body pt-0">
                            {needsAttention.length === 0 ? (
                                <EmptyState compact icon="bi-check2-all" title="پیش‌نویسی نداری" />
                            ) : (
                                <ul className="jl-list">
                                    {needsAttention.map((project) => (
                                        <li key={project.id}>
                                            <div className="min-w-0 flex-grow-1">
                                                <div className="fw-semibold text-truncate">{project.title}</div>
                                                <div className="small text-muted text-truncate">
                                                    {project.review_note ? (
                                                        <span className="text-warning-emphasis">
                                                            <i className="bi bi-arrow-return-right" /> {project.review_note}
                                                        </span>
                                                    ) : (
                                                        project.category?.name
                                                    )}
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
            </div>
        </>
    );
}
