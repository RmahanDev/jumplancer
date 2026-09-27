import JalaliDateInput from '../../../Components/Form/JalaliDateInput';
import ModalForm from '../../../Components/Form/ModalForm';
import NumberInput from '../../../Components/Form/NumberInput';
import RadioCards from '../../../Components/Form/RadioCards';
import Select from '../../../Components/Form/Select';
import Switch from '../../../Components/Form/Switch';
import TextInput from '../../../Components/Form/TextInput';
import Textarea from '../../../Components/Form/Textarea';
import PageHeader from '../../../Components/Panel/PageHeader';
import TableCard from '../../../Components/Panel/TableCard';
import { Person } from '../../../Components/UI/Avatar';
import { Badge, StatusBadge } from '../../../Components/UI/Badge';
import { confirm } from '../../../Components/UI/ConfirmDialog';
import DataTable from '../../../Components/UI/DataTable';
import { DropdownDivider, DropdownItem } from '../../../Components/UI/Dropdown';
import EmptyState from '../../../Components/UI/EmptyState';
import RowActions from '../../../Components/UI/RowActions';
import SearchInput from '../../../Components/UI/SearchInput';
import Tabs from '../../../Components/UI/Tabs';
import { useFilters } from '../../../hooks/useFilters';
import { useModal } from '../../../hooks/useModal';
import { destroy, put } from '../../../lib/actions';
import { formatDate, formatRelative } from '../../../lib/format';
import { budgetText, categoryOptions } from '../../../lib/project';
import { label, options } from '../../../lib/labels';
import { fillRoute } from '../../../lib/text';

function ReviewForm({ modal, routes }) {
    const project = modal.record;

    return (
        <ModalForm
            open={modal.open}
            onClose={modal.close}
            title="بررسی پروژه"
            subtitle={project?.title}
            icon="bi-clipboard-check"
            size="lg"
            method="put"
            url={project ? fillRoute(routes.review, project.id) : ''}
            initial={{ decision: 'approve', review_note: '' }}
            submitLabel="ثبت نتیجه‌ی بررسی"
        >
            {(form) =>
                project && (
                    <div className="d-grid gap-3">
                        <dl className="jl-details">
                            <dt>کارفرما</dt>
                            <dd>
                                <Person user={project.employer} />
                            </dd>
                            <dt>دسته</dt>
                            <dd>{project.category?.name ?? '—'}</dd>
                            <dt>بودجه</dt>
                            <dd className="num">{budgetText(project)}</dd>
                            <dt>مهلت</dt>
                            <dd>{project.deadline ? formatDate(project.deadline) : 'بدون مهلت'}</dd>
                            <dt>نوع ثبت</dt>
                            <dd>
                                <StatusBadge group="postingType" value={project.posting_type} />
                            </dd>
                        </dl>
                        <div className="jl-text-block">{project.description}</div>
                        <RadioCards
                            form={form}
                            name="decision"
                            label="نتیجه"
                            columns={2}
                            options={[
                                { value: 'approve', label: 'تأیید و انتشار', icon: 'bi-check2-circle', description: 'پروژه همین حالا برای فریلنسرها باز می‌شود.' },
                                { value: 'return', label: 'برگشت برای اصلاح', icon: 'bi-arrow-return-right', description: 'با یادداشت به کارفرما برمی‌گردد.' },
                            ]}
                        />
                        {form.data.decision === 'return' && (
                            <Textarea form={form} name="review_note" label="یادداشت برای کارفرما" required rows={3} maxLength={500} placeholder="مثلاً: لطفاً شرح کار و خروجی نهایی را دقیق‌تر بنویسید." />
                        )}
                    </div>
                )
            }
        </ModalForm>
    );
}

function EditForm({ modal, routes, categories, mentors, statuses, budgetTypes }) {
    const project = modal.record;

    return (
        <ModalForm
            open={modal.open}
            onClose={modal.close}
            title="ویرایش پروژه"
            subtitle={project?.title}
            icon="bi-pencil-square"
            size="lg"
            method="put"
            url={project ? fillRoute(routes.update, project.id) : ''}
            initial={{
                title: project?.title ?? '',
                description: project?.description ?? '',
                category_id: project?.category_id ?? '',
                status: project?.status ?? 'open',
                budget_type: project?.budget_type ?? 'fixed',
                budget_min: project?.budget_min ?? '',
                budget_max: project?.budget_max ?? '',
                is_beginner_friendly: project?.is_beginner_friendly ?? true,
                deadline: project?.deadline ?? '',
                mentor_id: project?.mentor_id ?? '',
            }}
            transform={(data) => ({
                ...data,
                budget_min: data.budget_min === '' ? null : Number(data.budget_min),
                budget_max: data.budget_max === '' ? null : Number(data.budget_max),
                deadline: data.deadline || null,
                mentor_id: data.mentor_id || null,
            })}
        >
            {(form) => (
                <div className="row g-3">
                    <TextInput form={form} name="title" label="عنوان" required className="col-12" maxLength={200} />
                    <Textarea form={form} name="description" label="شرح پروژه" required rows={5} className="col-12" maxLength={10000} />
                    <Select form={form} name="category_id" label="دسته" required className="col-md-6" options={categoryOptions(categories, { subcategoriesOnly: true })} />
                    <Select form={form} name="status" label="وضعیت" required className="col-md-6" allowEmpty={false} options={options('projectStatus', statuses)} />
                    <Select form={form} name="budget_type" label="نوع بودجه" required className="col-md-4" allowEmpty={false} options={options('budgetType', budgetTypes)} />
                    <NumberInput form={form} name="budget_min" label="حداقل بودجه" money className="col-md-4" />
                    <NumberInput form={form} name="budget_max" label="حداکثر بودجه" money className="col-md-4" />
                    <JalaliDateInput form={form} name="deadline" label="مهلت انجام" className="col-md-6" />
                    <Select
                        form={form}
                        name="mentor_id"
                        label="منتور همراه"
                        className="col-md-6"
                        placeholder="بدون منتور"
                        options={mentors.map((mentor) => ({ value: mentor.id, label: mentor.name }))}
                    />
                    <Switch form={form} name="is_beginner_friendly" className="col-12" label="مناسب تازه‌کارها" description="در فهرست پروژه‌های فریلنسرهای تازه‌کار بالاتر نمایش داده می‌شود." />
                </div>
            )}
        </ModalForm>
    );
}

export default function Index({ projects, filters: initialFilters, statusCounts, categories, mentors, options: choices, routes }) {
    const filters = useFilters(initialFilters);
    const reviewer = useModal();
    const editor = useModal();

    const total = Object.values(statusCounts).reduce((sum, count) => sum + Number(count), 0);
    const tabs = [
        { key: '', label: 'همه', count: total },
        ...['pending_review', 'open', 'in_progress', 'completed', 'draft', 'cancelled'].map((status) => ({ key: status, label: label('projectStatus', status), count: statusCounts[status] ?? 0 })),
    ];

    const approve = (project) => put(fillRoute(routes.review, project.id), { decision: 'approve' });

    const remove = async (project) => {
        const ok = await confirm({
            title: 'حذف پروژه؟',
            message: `«${project.title}» از بازارگاه حذف می‌شود. قراردادها و تراکنش‌های مرتبط برای سوابق می‌مانند.`,
            confirmLabel: 'حذف پروژه',
        });

        if (ok) {
            destroy(fillRoute(routes.destroy, project.id));
        }
    };

    const open = (project) => (project.status === 'pending_review' ? reviewer.show(project) : editor.show(project));

    const columns = [
        {
            key: 'title',
            label: 'پروژه',
            primary: true,
            render: (project) => (
                <div className="min-w-0">
                    <div className="fw-semibold text-truncate" style={{ maxWidth: 360 }}>
                        {project.title}
                    </div>
                    <div className="small text-muted text-truncate">
                        {project.employer?.name} · {project.category?.name}
                    </div>
                </div>
            ),
        },
        { key: 'budget', label: 'بودجه', render: (project) => <span className="small num">{budgetText(project)}</span> },
        {
            key: 'status',
            label: 'وضعیت',
            render: (project) => (
                <div className="d-flex flex-wrap gap-1">
                    <StatusBadge group="projectStatus" value={project.status} />
                    {project.is_beginner_friendly && (
                        <Badge tone="success" icon="bi-emoji-smile">
                            تازه‌کار
                        </Badge>
                    )}
                </div>
            ),
        },
        { key: 'proposals_count', label: 'پیشنهاد', className: 'num', render: (project) => project.proposals_count ?? 0 },
        { key: 'mentor', label: 'منتور', render: (project) => (project.mentor ? <Person user={project.mentor} size="sm" /> : <span className="text-muted">—</span>) },
        {
            key: 'created_at',
            label: 'ثبت',
            render: (project) => <span title={formatDate(project.created_at)}>{formatRelative(project.status === 'pending_review' ? (project.submitted_at ?? project.created_at) : project.created_at)}</span>,
        },
    ];

    return (
        <>
            <PageHeader title="پروژه‌ها" description="پروژه‌های منتظر بررسی همیشه اول فهرست‌اند. روی هر ردیف کلیک کن تا بررسی یا ویرایشش کنی." />

            <TableCard
                meta={projects.meta}
                toolbar={
                    <>
                        <SearchInput value={filters.filters.search} onChange={filters.search} placeholder="جستجوی عنوان پروژه..." className="jl-toolbar-search" />
                        <select
                            className="form-select"
                            value={filters.filters.category ?? ''}
                            onChange={(event) => filters.set('category', event.target.value || null)}
                            aria-label="دسته"
                        >
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
                        <Tabs items={tabs} active={filters.filters.status ?? ''} onChange={(status) => filters.set('status', status || null)} className="w-100" />
                    </>
                }
            >
                <DataTable
                    columns={columns}
                    rows={projects.data}
                    onRowClick={open}
                    rowClassName={(project) => (project.status === 'pending_review' ? 'is-highlight' : '')}
                    empty={<EmptyState title="پروژه‌ای با این فیلترها نیست" description="فیلتر وضعیت یا دسته را تغییر بده." />}
                    actions={(project) => (
                        <div className="d-flex align-items-center gap-1 justify-content-end">
                            {project.status === 'pending_review' && (
                                <button type="button" className="btn btn-soft btn-sm d-none d-md-inline-flex" onClick={() => reviewer.show(project)}>
                                    بررسی
                                </button>
                            )}
                            <RowActions>
                                {(close) => (
                                    <>
                                        {project.status === 'pending_review' && (
                                            <>
                                                <DropdownItem
                                                    icon="bi-check2-circle"
                                                    onClick={() => {
                                                        close();
                                                        approve(project);
                                                    }}
                                                >
                                                    تأیید سریع و انتشار
                                                </DropdownItem>
                                                <DropdownItem
                                                    icon="bi-clipboard-check"
                                                    onClick={() => {
                                                        close();
                                                        reviewer.show(project);
                                                    }}
                                                >
                                                    بررسی با یادداشت
                                                </DropdownItem>
                                                <DropdownDivider />
                                            </>
                                        )}
                                        <DropdownItem
                                            icon="bi-pencil"
                                            onClick={() => {
                                                close();
                                                editor.show(project);
                                            }}
                                        >
                                            ویرایش
                                        </DropdownItem>
                                        <DropdownItem
                                            icon="bi-trash3"
                                            danger
                                            onClick={() => {
                                                close();
                                                remove(project);
                                            }}
                                        >
                                            حذف
                                        </DropdownItem>
                                    </>
                                )}
                            </RowActions>
                        </div>
                    )}
                />
            </TableCard>

            <ReviewForm modal={reviewer} routes={routes} />
            <EditForm modal={editor} routes={routes} categories={categories} mentors={mentors} statuses={choices.statuses} budgetTypes={choices.budgetTypes} />
        </>
    );
}
