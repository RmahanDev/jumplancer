import { Link } from '@inertiajs/react';
import { useEffect, useMemo } from 'react';
import PostingBanner from '../../../Components/Domain/PostingBanner';
import ChipPicker from '../../../Components/Form/ChipPicker';
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
import { destroy, post } from '../../../lib/actions';
import { formatCompact, formatNumber, formatRelative } from '../../../lib/format';
import { label } from '../../../lib/labels';
import { budgetText, categoryOptions } from '../../../lib/project';
import { fillRoute } from '../../../lib/text';

const TAB_STATUSES = ['draft', 'pending_review', 'open', 'in_progress', 'completed', 'cancelled'];

function tomorrow() {
    const date = new Date();
    date.setDate(date.getDate() + 1);

    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

/** The active budget range for a sub-category and budget type (the sub-category's own range wins). */
function rangeFor(categories, categoryId, budgetType) {
    for (const parent of categories) {
        const child = (parent.children ?? []).find((item) => item.id === Number(categoryId));

        if (child) {
            const own = (child.budget_ranges ?? []).find((range) => range.budget_type === budgetType && range.is_active);
            const inherited = (parent.budget_ranges ?? []).find((range) => range.budget_type === budgetType && range.is_active);

            return own ?? inherited ?? null;
        }
    }

    return null;
}

function ProjectForm({ modal, categories, budgetTypes, canPublish, routes }) {
    const project = modal.record;
    const editing = Boolean(project);
    // A draft returned by the reviewer was already paid for, so it can always be sent again.
    const allowSubmit = canPublish || Boolean(project?.submitted_at);
    const skillsByCategory = useMemo(
        () => Object.fromEntries(categories.flatMap((parent) => (parent.children ?? []).map((child) => [child.id, child.skills ?? []]))),
        [categories],
    );

    return (
        <ModalForm
            open={modal.open}
            onClose={modal.close}
            title={editing ? 'ویرایش پروژه' : 'ثبت پروژه‌ی جدید'}
            subtitle={editing ? project.title : 'هر چه دقیق‌تر بنویسی، پیشنهادهای بهتری می‌گیری. پروژه بعد از بررسی ادمین منتشر می‌شود.'}
            icon="bi-kanban"
            size="lg"
            method={editing ? 'put' : 'post'}
            url={editing ? fillRoute(routes.update, project.id) : routes.store}
            initial={{
                title: project?.title ?? '',
                description: project?.description ?? '',
                category_id: project?.category_id ?? '',
                budget_type: project?.budget_type ?? 'fixed',
                budget_min: project?.budget_min ?? '',
                budget_max: project?.budget_max ?? '',
                deadline: project?.deadline ?? '',
                is_beginner_friendly: project?.is_beginner_friendly ?? true,
                skills: (project?.skills ?? []).map((skill) => skill.id),
                submit: (!editing || project?.status === 'draft') && allowSubmit,
            }}
            transform={(data) => ({
                ...data,
                budget_min: data.budget_min === '' ? null : Number(data.budget_min),
                budget_max: data.budget_max === '' ? null : Number(data.budget_max),
                deadline: data.deadline || null,
            })}
            submitLabel={(form) => (form.data.submit ? 'ذخیره و ارسال برای بررسی' : editing ? 'ذخیره‌ی تغییرات' : 'ذخیره‌ی پیش‌نویس')}
            submitIcon={(form) => (form.data.submit ? 'bi-send' : 'bi-check2')}
        >
            {(form) => {
                const range = rangeFor(categories, form.data.category_id, form.data.budget_type);
                const skills = skillsByCategory[Number(form.data.category_id)] ?? [];

                return (
                    <div className="row g-3">
                        <TextInput form={form} name="title" label="عنوان پروژه" required className="col-12" maxLength={200} placeholder="مثلاً طراحی سایت شرکتی با وردپرس" />
                        <Textarea
                            form={form}
                            name="description"
                            label="شرح کار"
                            required
                            rows={6}
                            minLength={30}
                            maxLength={10000}
                            className="col-12"
                            placeholder="چه چیزی لازم داری، خروجی نهایی چیست، چه امکاناتی باید داشته باشد و چه چیزهایی آماده است."
                        />
                        <Select
                            name="category_id"
                            label="دسته"
                            required
                            className="col-md-6"
                            options={categoryOptions(categories, { subcategoriesOnly: true })}
                            value={form.data.category_id}
                            error={form.errors.category_id}
                            onChange={(value) => form.setData({ ...form.data, category_id: value, skills: [] })}
                        />
                        <RadioCards
                            form={form}
                            name="budget_type"
                            label="نوع بودجه"
                            className="col-md-6"
                            columns={2}
                            options={budgetTypes.map((type) => ({ value: type, label: label('budgetType', type), icon: type === 'hourly' ? 'bi-clock' : 'bi-cash' }))}
                        />
                        <NumberInput form={form} name="budget_min" label={form.data.budget_type === 'hourly' ? 'حداقل نرخ ساعتی' : 'حداقل بودجه'} money required className="col-md-6" />
                        <NumberInput form={form} name="budget_max" label={form.data.budget_type === 'hourly' ? 'حداکثر نرخ ساعتی' : 'حداکثر بودجه'} money className="col-md-6" />
                        {range && (
                            <div className="col-12">
                                <div className="small text-muted">
                                    <i className="bi bi-info-circle" /> بودجه‌ی مجاز این دسته: از {formatCompact(range.min_amount)} {range.max_amount ? `تا ${formatCompact(range.max_amount)}` : 'به بالا'} تومان
                                </div>
                            </div>
                        )}
                        <JalaliDateInput form={form} name="deadline" label="مهلت تحویل" min={tomorrow()} className="col-md-6" />
                        <div className="col-md-6 d-flex align-items-end">
                            <Switch form={form} name="is_beginner_friendly" className="w-100" label="مناسب تازه‌کارها" description="فریلنسرهای تازه‌کار این پروژه را بالاتر می‌بینند." />
                        </div>
                        <ChipPicker
                            form={form}
                            name="skills"
                            label="مهارت‌های لازم"
                            className="col-12"
                            max={10}
                            options={skills.map((skill) => ({ value: skill.id, label: skill.name }))}
                            emptyText="اول دسته را انتخاب کن تا مهارت‌هایش نمایش داده شود."
                        />
                        {(!editing || project.status === 'draft') && (
                            <Switch
                                form={form}
                                name="submit"
                                className="col-12"
                                disabled={!allowSubmit}
                                label="بعد از ذخیره برای بررسی و انتشار ارسال شود"
                                description={allowSubmit ? 'خاموش باشد، پروژه به‌صورت پیش‌نویس می‌ماند.' : 'پروژه‌ی رایگان یا سهمیه‌ی پلن نداری؛ فعلاً پیش‌نویس ذخیره می‌شود.'}
                            />
                        )}
                    </div>
                );
            }}
        </ModalForm>
    );
}

/** Shown instead of the project form when there is no free posting and no plan quota left. */
function NoSubscription({ modal, plansUrl, onDraft }) {
    return (
        <Modal open={modal.open} onClose={modal.close} title="اشتراک فعالی نداری" subtitle="برای ثبت پروژه‌ی جدید باید اشتراک بخری." icon="bi-gem" tone="warning">
            <ModalBody>
                <div className="jl-callout is-warning mb-3">
                    <i className="bi bi-info-circle" /> دو پروژه‌ی رایگانت را استفاده کرده‌ای و اشتراک فعالی (یا سهمیه‌ی باقی‌مانده‌ای) نداری. با خرید اشتراک، پروژه‌ی بعدی بلافاصله برای بررسی و انتشار ارسال می‌شود.
                </div>
                <div className="d-grid gap-2">
                    <Link href={plansUrl} className="btn btn-primary">
                        <i className="bi bi-gem" /> خرید اشتراک
                    </Link>
                    <button type="button" className="btn btn-ghost" onClick={onDraft}>
                        <i className="bi bi-file-earmark" /> فعلاً به‌صورت پیش‌نویس نگه دارم
                    </button>
                </div>
            </ModalBody>
        </Modal>
    );
}

export default function Index({ projects, filters: initialFilters, statusCounts, categories, budgetTypes, posting, routes }) {
    const filters = useFilters(initialFilters);
    const editor = useModal();
    const noPlan = useModal();
    const canPublish = posting.next !== null;
    const create = () => (canPublish ? editor.show(null) : noPlan.show(null));
    const total = Object.values(statusCounts).reduce((sum, count) => sum + Number(count), 0);

    // The dashboard's "ثبت پروژه" button links here with ?create=1.
    useEffect(() => {
        const params = new URLSearchParams(window.location.search);

        if (params.get('create') === '1') {
            create();
            params.delete('create');
            const query = params.toString();
            window.history.replaceState(window.history.state, '', `${window.location.pathname}${query ? `?${query}` : ''}`);
        }
    }, []);

    const publish = async (project) => {
        if (!canPublish && !project.submitted_at) {
            noPlan.show(project);

            return;
        }

        const ok = await confirm({
            title: 'ارسال برای بررسی؟',
            message:
                posting.next === 'subscription'
                    ? 'یک پروژه از سهمیه‌ی پلنت کم می‌شود. بعد از تأیید ادمین، پروژه منتشر می‌شود.'
                    : 'از پروژه‌های رایگانت استفاده می‌شود. بعد از تأیید ادمین، پروژه منتشر می‌شود.',
            confirmLabel: 'ارسال',
            tone: 'primary',
            icon: 'bi-send',
        });

        if (ok) {
            post(fillRoute(routes.publish, project.id));
        }
    };

    const remove = async (project) => {
        const live = project.status === 'open';
        const ok = await confirm({
            title: live ? 'لغو پروژه؟' : 'حذف پروژه؟',
            message: live ? 'پروژه دیگر پیشنهاد جدید نمی‌گیرد و از فهرست پروژه‌های باز برداشته می‌شود.' : `«${project.title}» حذف می‌شود.`,
            confirmLabel: live ? 'لغو پروژه' : 'حذف',
        });

        if (ok) {
            destroy(fillRoute(routes.destroy, project.id));
        }
    };

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
                    <div className="small text-muted">
                        {project.category?.name} · {formatRelative(project.created_at)}
                    </div>
                    {project.review_note && project.status === 'draft' && (
                        <div className="small text-warning-emphasis mt-1">
                            <i className="bi bi-arrow-return-right" /> یادداشت بررسی: {project.review_note}
                        </div>
                    )}
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
                    {project.freelancer && (
                        <Badge tone="success" icon="bi-person-check">
                            فریلنسر: {project.freelancer.name}
                        </Badge>
                    )}
                </div>
            ),
        },
        {
            key: 'proposals_count',
            label: 'پیشنهاد',
            render: (project) =>
                project.proposals_count ? (
                    <Link href={`${routes.proposals}?project=${project.id}`} className="fw-semibold">
                        {formatNumber(project.proposals_count)}
                    </Link>
                ) : (
                    <span className="text-muted">۰</span>
                ),
        },
    ];

    return (
        <>
            <PageHeader
                title="پروژه‌های من"
                description="پیش‌نویس بساز، برای بررسی بفرست و پیشنهادهای رسیده را مقایسه کن."
                primary={{ label: 'پروژه‌ی جدید', icon: 'bi-plus-lg', onClick: create }}
            />

            <PostingBanner next={posting.next} plansUrl={routes.plans} secondFreeUntil={posting.second_free_until} subscription={posting.subscription} />

            <TableCard
                meta={projects.meta}
                toolbar={
                    <>
                        <SearchInput value={filters.filters.search} onChange={filters.search} placeholder="جستجوی عنوان..." className="jl-toolbar-search" />
                        <Tabs
                            items={[{ key: '', label: 'همه', count: total }, ...TAB_STATUSES.map((status) => ({ key: status, label: label('projectStatus', status), count: statusCounts[status] ?? 0 }))]}
                            active={filters.filters.status ?? ''}
                            onChange={(status) => filters.set('status', status || null)}
                        />
                    </>
                }
            >
                <DataTable
                    columns={columns}
                    rows={projects.data}
                    onRowClick={(project) => ['draft', 'open'].includes(project.status) && editor.show(project)}
                    empty={
                        <EmptyState
                            title="پروژه‌ای پیدا نشد"
                            description="اولین پروژه‌ات رایگان است؛ همین حالا ثبتش کن."
                            action={
                                <button type="button" className="btn btn-primary btn-sm" onClick={create}>
                                    <i className="bi bi-plus-lg" /> ثبت پروژه
                                </button>
                            }
                        />
                    }
                    actions={(project) => (
                        <div className="d-flex align-items-center gap-1 justify-content-end">
                            {project.status === 'draft' && (
                                <button type="button" className="btn btn-soft btn-sm d-none d-md-inline-flex" onClick={() => publish(project)} disabled={!canPublish && !project.submitted_at}>
                                    <i className="bi bi-send" /> ارسال
                                </button>
                            )}
                            <RowActions>
                                {(close) => (
                                    <>
                                        {['draft', 'open'].includes(project.status) && (
                                            <DropdownItem
                                                icon="bi-pencil"
                                                onClick={() => {
                                                    close();
                                                    editor.show(project);
                                                }}
                                            >
                                                ویرایش
                                            </DropdownItem>
                                        )}
                                        {project.status === 'draft' && (
                                            <DropdownItem
                                                icon="bi-send"
                                                disabled={!canPublish && !project.submitted_at}
                                                onClick={() => {
                                                    close();
                                                    publish(project);
                                                }}
                                            >
                                                ارسال برای بررسی
                                            </DropdownItem>
                                        )}
                                        {project.proposals_count > 0 && (
                                            <DropdownItem as={Link} icon="bi-inboxes" href={`${routes.proposals}?project=${project.id}`} onClick={close}>
                                                دیدن پیشنهادها
                                            </DropdownItem>
                                        )}
                                        {['draft', 'pending_review', 'open'].includes(project.status) && (
                                            <>
                                                <DropdownDivider />
                                                <DropdownItem
                                                    icon={project.status === 'open' ? 'bi-x-circle' : 'bi-trash3'}
                                                    danger
                                                    onClick={() => {
                                                        close();
                                                        remove(project);
                                                    }}
                                                >
                                                    {project.status === 'open' ? 'لغو پروژه' : 'حذف'}
                                                </DropdownItem>
                                            </>
                                        )}
                                    </>
                                )}
                            </RowActions>
                        </div>
                    )}
                />
            </TableCard>

            <ProjectForm modal={editor} categories={categories} budgetTypes={budgetTypes} canPublish={canPublish} routes={routes} />
            <NoSubscription
                modal={noPlan}
                plansUrl={routes.plans}
                onDraft={() => {
                    const draft = noPlan.record;
                    noPlan.close();

                    if (!draft) {
                        window.setTimeout(() => editor.show(null), 160);
                    }
                }}
            />
        </>
    );
}
