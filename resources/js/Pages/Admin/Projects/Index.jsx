import JalaliDateInput from '../../../Components/Form/JalaliDateInput';
import ModalForm from '../../../Components/Form/ModalForm';
import NumberInput from '../../../Components/Form/NumberInput';
import RadioCards from '../../../Components/Form/RadioCards';
import Select from '../../../Components/Form/Select';
import Switch from '../../../Components/Form/Switch';
import TextInput from '../../../Components/Form/TextInput';
import ProposalList from '../../../Components/Domain/ProposalList';
import Textarea from '../../../Components/Form/Textarea';
import PageHeader from '../../../Components/Panel/PageHeader';
import TableCard from '../../../Components/Panel/TableCard';
import { Person } from '../../../Components/UI/Avatar';
import UserName from '../../../Components/UI/UserName';
import { Badge, StatusBadge } from '../../../Components/UI/Badge';
import { confirm } from '../../../Components/UI/ConfirmDialog';
import DataTable from '../../../Components/UI/DataTable';
import { DropdownDivider, DropdownItem } from '../../../Components/UI/Dropdown';
import EmptyState from '../../../Components/UI/EmptyState';
import Modal, { ModalBody } from '../../../Components/UI/Modal';
import RowActions from '../../../Components/UI/RowActions';
import SearchInput from '../../../Components/UI/SearchInput';
import Tabs from '../../../Components/UI/Tabs';
import { useFilters } from '../../../hooks/useFilters';
import { useModal } from '../../../hooks/useModal';
import { destroy, put } from '../../../lib/actions';
import { formatDate, formatNumber, formatRelative } from '../../../lib/format';
import { budgetShort, budgetText, categoryOptions } from '../../../lib/project';
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
                                <Person user={project.employer} role="employer" />
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

function HiredFreelancer({ project }) {
    if (!project.freelancer) {
        return null;
    }

    return (
        <div className="jl-callout is-success d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div className="d-flex align-items-center gap-2 min-w-0">
                <i className="bi bi-person-check fs-5" />
                <span className="small text-muted">فریلنسر استخدام‌شده:</span>
                <Person user={project.freelancer} role="freelancer" size="sm" />
            </div>
            {project.contract && <StatusBadge group="contractStatus" value={project.contract.status} />}
        </div>
    );
}

function ProposalsSection({ project }) {
    const proposals = project?.proposals ?? [];
    const withMentor = proposals.filter((proposal) => proposal.mentorship_requested).length;

    return (
        <section>
            <h3 className="jl-section-title">
                <i className="bi bi-inboxes text-primary" /> پیشنهادهای فریلنسرها
                <span className="text-muted small fw-normal">
                    ({formatNumber(proposals.length)} پیشنهاد{withMentor > 0 ? ` · ${formatNumber(withMentor)} با درخواست منتور` : ''})
                </span>
            </h3>
            <ProposalList proposals={proposals} />
        </section>
    );
}

function ProposalsModal({ modal }) {
    const project = modal.record;

    return (
        <Modal open={modal.open} onClose={modal.close} title="پیشنهادهای پروژه" subtitle={project?.title} icon="bi-inboxes" size="lg">
            <ModalBody>
                {project && (
                    <div className="d-grid gap-3">
                        <HiredFreelancer project={project} />
                        <ProposalsSection project={project} />
                    </div>
                )}
            </ModalBody>
        </Modal>
    );
}

function EditForm({ modal, routes, categories, statuses, budgetTypes }) {
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
            }}
            transform={(data) => ({
                ...data,
                budget_min: data.budget_min === '' ? null : Number(data.budget_min),
                budget_max: data.budget_max === '' ? null : Number(data.budget_max),
                deadline: data.deadline || null,
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
                    <Switch form={form} name="is_beginner_friendly" className="col-md-6 align-self-end" label="مناسب تازه‌کارها" description="در فهرست پروژه‌های فریلنسرهای تازه‌کار بالاتر نمایش داده می‌شود." />
                    {project && (
                        <div className="col-12 d-grid gap-3 pt-2 border-top">
                            <HiredFreelancer project={project} />
                            <ProposalsSection project={project} />
                        </div>
                    )}
                </div>
            )}
        </ModalForm>
    );
}

export default function Index({ projects, filters: initialFilters, statusCounts, categories, options: choices, routes }) {
    const filters = useFilters(initialFilters);
    const reviewer = useModal();
    const editor = useModal();
    const proposalsViewer = useModal();

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
                <div className="min-w-0" style={{ maxWidth: 260 }}>
                    <div className="fw-semibold text-truncate">{project.title}</div>
                    <div className="small text-muted d-flex flex-wrap align-items-center gap-1 min-w-0">
                        <UserName user={project.employer} role="employer" /> <span>·</span> <span className="text-truncate">{project.category?.name}</span> <span>·</span>
                        <span className="text-nowrap" title={formatDate(project.created_at)}>
                            {formatRelative(project.status === 'pending_review' ? (project.submitted_at ?? project.created_at) : project.created_at)}
                        </span>
                    </div>
                </div>
            ),
        },
        {
            key: 'budget',
            label: 'بودجه',
            className: 'text-nowrap',
            render: (project) => (
                <span className="small num" title={budgetText(project)}>
                    {budgetShort(project)}
                </span>
            ),
        },
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
        {
            key: 'proposals_count',
            label: 'پیشنهاد',
            render: (project) => {
                const count = project.proposals?.length ?? project.proposals_count ?? 0;
                const withMentor = (project.proposals ?? []).filter((proposal) => proposal.mentorship_requested).length;

                return count === 0 ? (
                    <span className="text-muted num">۰</span>
                ) : (
                    <button
                        type="button"
                        className="btn btn-soft btn-sm num text-nowrap"
                        onClick={(event) => {
                            event.stopPropagation();
                            proposalsViewer.show(project);
                        }}
                        title={withMentor > 0 ? `${formatNumber(withMentor)} پیشنهاد با درخواست منتور` : undefined}
                    >
                        {formatNumber(count)} پیشنهاد
                        {withMentor > 0 && <i className="bi bi-mortarboard text-info" />}
                    </button>
                );
            },
        },
        {
            key: 'freelancer',
            label: 'فریلنسر',
            render: (project) =>
                project.freelancer ? (
                    <Person user={project.freelancer} role="freelancer" size="sm" />
                ) : (
                    <span className="small text-muted text-nowrap" title="هنوز کسی استخدام نشده">
                        —
                    </span>
                ),
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
                                            icon="bi-inboxes"
                                            onClick={() => {
                                                close();
                                                proposalsViewer.show(project);
                                            }}
                                        >
                                            پیشنهادها
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
            <EditForm modal={editor} routes={routes} categories={categories} statuses={choices.statuses} budgetTypes={choices.budgetTypes} />
            <ProposalsModal modal={proposalsViewer} />
        </>
    );
}
