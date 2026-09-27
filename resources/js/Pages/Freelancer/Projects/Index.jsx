import { Link } from '@inertiajs/react';
import { useMemo } from 'react';
import ProposalForm from '../../../Components/Domain/ProposalForm';
import PageHeader from '../../../Components/Panel/PageHeader';
import Pagination from '../../../Components/Panel/Pagination';
import { Person } from '../../../Components/UI/Avatar';
import { Badge } from '../../../Components/UI/Badge';
import EmptyState from '../../../Components/UI/EmptyState';
import SearchInput from '../../../Components/UI/SearchInput';
import Tabs from '../../../Components/UI/Tabs';
import { useFilters } from '../../../hooks/useFilters';
import { useModal } from '../../../hooks/useModal';
import { formatDate, formatNumber, formatRelative } from '../../../lib/format';
import { options } from '../../../lib/labels';
import { budgetText } from '../../../lib/project';

export default function Index({ projects, filters: initialFilters, categories, activeFields, routes }) {
    const filters = useFilters(initialFilters);
    const proposer = useModal();

    // Work fields are top-level categories; projects sit in sub-categories.
    const parentOf = useMemo(
        () => Object.fromEntries(categories.flatMap((parent) => [[parent.id, parent.id], ...(parent.children ?? []).map((child) => [child.id, parent.id])])),
        [categories],
    );
    const canBid = (project) => activeFields.includes(parentOf[project.category_id] ?? project.category_id);

    return (
        <>
            <PageHeader
                title="پیدا کردن پروژه"
                description="پروژه‌های باز حوزه‌های فعالت؛ پروژه‌های مناسب تازه‌کار با منتور همراه‌اند."
                actions={
                    <Link href={routes.fields} className="btn btn-ghost">
                        <i className="bi bi-bullseye" /> حوزه‌های کاری من
                    </Link>
                }
            />

            <div className="jl-card mb-3 jl-rise">
                <div className="jl-toolbar border-0">
                    <SearchInput value={filters.filters.search} onChange={filters.search} placeholder="عنوان یا شرح پروژه..." className="jl-toolbar-search" />
                    <select className="form-select" value={filters.filters.category ?? ''} onChange={(event) => filters.set('category', event.target.value || null)} aria-label="دسته">
                        <option value="">همه‌ی دسته‌ها</option>
                        {categories.map((parent) => (
                            <optgroup key={parent.id} label={parent.name}>
                                <option value={parent.id}>همه‌ی {parent.name}</option>
                                {(parent.children ?? []).map((child) => (
                                    <option key={child.id} value={child.id}>
                                        {child.name}
                                    </option>
                                ))}
                            </optgroup>
                        ))}
                    </select>
                    <select className="form-select" value={filters.filters.budget_type ?? ''} onChange={(event) => filters.set('budget_type', event.target.value || null)} aria-label="نوع بودجه">
                        <option value="">هر نوع بودجه</option>
                        {options('budgetType').map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </select>
                    <div className="form-check form-switch mb-0">
                        <input
                            className="form-check-input"
                            type="checkbox"
                            id="beginner-only"
                            checked={Boolean(filters.filters.beginner)}
                            onChange={(event) => filters.set('beginner', event.target.checked ? '1' : null)}
                        />
                        <label className="form-check-label small" htmlFor="beginner-only">
                            فقط مناسب تازه‌کار
                        </label>
                    </div>
                    <Tabs
                        className="ms-auto"
                        items={[
                            { key: 'newest', label: 'جدیدترین' },
                            { key: 'budget', label: 'بیشترین بودجه' },
                        ]}
                        active={filters.filters.sort ?? 'newest'}
                        onChange={(sort) => filters.set('sort', sort)}
                    />
                </div>
            </div>

            {projects.data.length === 0 ? (
                <EmptyState
                    title="پروژه‌ای با این فیلترها نیست"
                    description="فیلترها را کمتر کن یا چند ساعت دیگر سر بزن؛ پروژه‌های جدید هر روز منتشر می‌شوند."
                    action={
                        (filters.filters.search || filters.filters.category || filters.filters.budget_type || filters.filters.beginner) && (
                            <button type="button" className="btn btn-soft btn-sm" onClick={() => filters.apply({ search: null, category: null, budget_type: null, beginner: null, sort: 'newest' })}>
                                حذف فیلترها
                            </button>
                        )
                    }
                />
            ) : (
                <div className="d-grid gap-3">
                    {projects.data.map((project, index) => {
                        const allowed = canBid(project);

                        return (
                            <article key={project.id} className={`jl-card jl-project-card jl-rise jl-rise-${Math.min(index + 1, 8)}`}>
                                <div className="jl-card-body">
                                    <div className="d-flex flex-wrap align-items-start justify-content-between gap-3">
                                        <div className="jl-row-main">
                                            <div className="d-flex flex-wrap align-items-center gap-2 mb-1">
                                                <h2 className="h6 fw-bold mb-0">{project.title}</h2>
                                                {project.is_beginner_friendly && (
                                                    <Badge tone="success" icon="bi-emoji-smile">
                                                        مناسب تازه‌کار
                                                    </Badge>
                                                )}
                                            </div>
                                            <div className="small text-muted mb-2">
                                                {project.category?.name} · منتشرشده {formatRelative(project.published_at ?? project.created_at)} · {formatNumber(project.proposals_count ?? 0)} پیشنهاد
                                            </div>
                                            <p className="text-muted-2 mb-2 jl-clamp-3">{project.description}</p>
                                            <div className="d-flex flex-wrap gap-1">
                                                {(project.skills ?? []).map((skill) => (
                                                    <span key={skill.id} className="jl-chip">
                                                        {skill.name}
                                                    </span>
                                                ))}
                                            </div>
                                        </div>
                                        <div className="jl-project-side">
                                            <div className="jl-money">{budgetText(project)}</div>
                                            {project.deadline && <div className="small text-muted">مهلت: {formatDate(project.deadline)}</div>}
                                            <Person user={project.employer} size="sm" />
                                            {project.has_proposed ? (
                                                <Badge tone="info" icon="bi-check2">
                                                    پیشنهاد داده‌ای
                                                </Badge>
                                            ) : allowed ? (
                                                <button type="button" className="btn btn-primary btn-sm" onClick={() => proposer.show({ project })}>
                                                    <i className="bi bi-send" /> ارسال پیشنهاد
                                                </button>
                                            ) : (
                                                <Link href={routes.fields} className="btn btn-soft btn-sm" title="برای پیشنهاد دادن، حوزه‌ی این پروژه باید فعال باشد">
                                                    <i className="bi bi-lock" /> فعال‌سازی حوزه
                                                </Link>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            </article>
                        );
                    })}
                </div>
            )}

            {projects.meta?.last_page > 1 && (
                <div className="jl-card mt-3">
                    <Pagination meta={projects.meta} />
                </div>
            )}

            <ProposalForm modal={proposer} storeUrl={routes.propose} />
        </>
    );
}
