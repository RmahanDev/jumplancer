import { router } from '@inertiajs/react';
import PageHeader from '../../../Components/Panel/PageHeader';
import TableCard from '../../../Components/Panel/TableCard';
import { Badge } from '../../../Components/UI/Badge';
import { confirm } from '../../../Components/UI/ConfirmDialog';
import DataTable from '../../../Components/UI/DataTable';
import { DropdownDivider, DropdownItem } from '../../../Components/UI/Dropdown';
import EmptyState from '../../../Components/UI/EmptyState';
import RowActions from '../../../Components/UI/RowActions';
import SearchInput from '../../../Components/UI/SearchInput';
import Tabs from '../../../Components/UI/Tabs';
import UserName from '../../../Components/UI/UserName';
import { useFilters } from '../../../hooks/useFilters';
import { destroy, put } from '../../../lib/actions';
import { formatNumber, formatRelative } from '../../../lib/format';
import { fillRoute } from '../../../lib/text';

export default function Index({ exams, filters: initialFilters, categories, routes }) {
    const filters = useFilters(initialFilters);
    const create = () => router.visit(routes.create);
    const edit = (exam) => router.visit(fillRoute(routes.edit, exam.id));

    const toggle = (exam) => put(fillRoute(routes.status, exam.id), { is_active: !exam.is_active });

    const remove = async (exam) => {
        const ok = await confirm({
            title: `حذف آزمون «${exam.title}»؟`,
            message: exam.attempts_count > 0 ? 'فریلنسرها در این آزمون شرکت کرده‌اند؛ حذف نمی‌شود. به‌جایش غیرفعالش کن.' : 'هنوز کسی در این آزمون شرکت نکرده و با همه‌ی سؤال‌هایش حذف می‌شود.',
            confirmLabel: 'حذف آزمون',
        });

        if (ok) {
            destroy(fillRoute(routes.destroy, exam.id));
        }
    };

    const columns = [
        {
            key: 'title',
            label: 'آزمون',
            primary: true,
            render: (exam) => (
                <div className="min-w-0">
                    <button type="button" className="btn btn-link p-0 fw-semibold text-start text-body text-decoration-none" onClick={() => edit(exam)}>
                        {exam.title}
                    </button>
                    <div className="small text-muted">
                        {exam.category?.name} · <span className="ltr d-inline-block">{exam.skill?.name}</span>
                    </div>
                </div>
            ),
        },
        { key: 'questions', label: 'سؤال', render: (exam) => formatNumber(exam.questions_count ?? 0) },
        { key: 'time', label: 'زمان', render: (exam) => `${formatNumber(exam.time_limit_minutes)} دقیقه` },
        {
            key: 'score',
            label: 'قبولی / کل نمره',
            render: (exam) => (
                <span className="num">
                    {formatNumber(exam.pass_score)} / {formatNumber(exam.total_score)}
                </span>
            ),
        },
        {
            key: 'attempts',
            label: 'شرکت‌کننده',
            render: (exam) =>
                exam.attempts_count > 0 ? (
                    <span>
                        {formatNumber(exam.attempts_count)} <span className="small text-muted">({formatNumber(exam.passed_count ?? 0)} قبول)</span>
                    </span>
                ) : (
                    <span className="text-muted">—</span>
                ),
        },
        {
            key: 'status',
            label: 'وضعیت',
            render: (exam) => (
                <div className="d-flex flex-wrap gap-1">
                    {exam.is_active ? <Badge tone="success">فعال</Badge> : <Badge tone="secondary">غیرفعال</Badge>}
                    {exam.in_progress_count > 0 && (
                        <Badge tone="warning" icon="bi-hourglass-split">
                            {formatNumber(exam.in_progress_count)} در حال آزمون
                        </Badge>
                    )}
                </div>
            ),
        },
        {
            key: 'author',
            label: 'آخرین تغییر',
            mobile: false,
            render: (exam) => (
                <div className="small">
                    {exam.author ? <UserName user={exam.author} /> : <span className="text-muted">—</span>}
                    <div className="text-muted">{formatRelative(exam.updated_at)}</div>
                </div>
            ),
        },
    ];

    return (
        <>
            <PageHeader
                title="آزمون‌ساز"
                description="هر آزمون به یک مهارت وصل است؛ فریلنسری که قبول شود، آن مهارت را با نشان «تأییدشده» در پروفایلش نشان می‌دهد."
                primary={{ label: 'افزودن آزمون', icon: 'bi-plus-lg', onClick: create }}
            />

            <TableCard
                meta={exams.meta}
                toolbar={
                    <>
                        <SearchInput value={filters.filters.search} onChange={filters.search} placeholder="جستجوی عنوان آزمون..." className="jl-toolbar-search" />
                        <select className="form-select" value={filters.filters.category ?? ''} onChange={(event) => filters.set('category', event.target.value || null)} aria-label="حوزه">
                            <option value="">همه‌ی حوزه‌ها</option>
                            {categories.map((category) => (
                                <option key={category.id} value={category.id}>
                                    {category.name}
                                </option>
                            ))}
                        </select>
                        <Tabs
                            items={[
                                { key: '', label: 'همه' },
                                { key: 'active', label: 'فعال' },
                                { key: 'inactive', label: 'غیرفعال' },
                            ]}
                            active={filters.filters.status ?? ''}
                            onChange={(status) => filters.set('status', status || null)}
                        />
                    </>
                }
            >
                <DataTable
                    columns={columns}
                    rows={exams.data}
                    rowClassName={(exam) => (exam.is_active ? '' : 'is-muted')}
                    empty={
                        <EmptyState
                            title="آزمونی پیدا نشد"
                            description="با «افزودن آزمون» مهارت را انتخاب کن، تنظیمات را بگذار و سؤال‌ها را بنویس."
                            action={
                                <button type="button" className="btn btn-primary btn-sm" onClick={create}>
                                    <i className="bi bi-plus-lg" /> افزودن آزمون
                                </button>
                            }
                        />
                    }
                    actions={(exam) => (
                        <RowActions>
                            {(close) => (
                                <>
                                    <DropdownItem
                                        icon="bi-pencil"
                                        onClick={() => {
                                            close();
                                            edit(exam);
                                        }}
                                    >
                                        ویرایش سؤال‌ها و تنظیمات
                                    </DropdownItem>
                                    <DropdownItem
                                        icon={exam.is_active ? 'bi-eye-slash' : 'bi-eye'}
                                        onClick={() => {
                                            close();
                                            toggle(exam);
                                        }}
                                    >
                                        {exam.is_active ? 'غیرفعال کن' : 'فعال کن'}
                                    </DropdownItem>
                                    <DropdownDivider />
                                    <DropdownItem
                                        icon="bi-trash3"
                                        danger
                                        onClick={() => {
                                            close();
                                            remove(exam);
                                        }}
                                    >
                                        حذف آزمون
                                    </DropdownItem>
                                </>
                            )}
                        </RowActions>
                    )}
                />
            </TableCard>
        </>
    );
}
