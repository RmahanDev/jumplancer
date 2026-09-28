import { router } from '@inertiajs/react';
import CheckboxCards from '../../../Components/Form/CheckboxCards';
import ModalForm from '../../../Components/Form/ModalForm';
import RadioCards from '../../../Components/Form/RadioCards';
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
import { formatDate, formatNumber, formatRelative } from '../../../lib/format';
import { label, options } from '../../../lib/labels';
import { fillRoute } from '../../../lib/text';
import { destroy } from '../../../lib/actions';

const ROLE_OPTIONS = {
    freelancer: { icon: 'bi-laptop', description: 'پروژه پیدا می‌کند، پیشنهاد می‌دهد و نمونه‌کار می‌سازد.' },
    employer: { icon: 'bi-briefcase', description: 'پروژه ثبت می‌کند و فریلنسر استخدام می‌کند.' },
    mentor: { icon: 'bi-mortarboard', description: 'تیکت‌ها را جواب می‌دهد و تازه‌کارها را همراهی می‌کند. (تأییدشده توسط پلتفرم)' },
};

function MemberForm({ modal, roles, routes }) {
    const member = modal.record;
    const editing = Boolean(member);

    return (
        <ModalForm
            open={modal.open}
            onClose={modal.close}
            title={editing ? `ویرایش ${member.name}` : 'کاربر جدید'}
            subtitle={editing ? 'نقش‌ها و اطلاعات حساب را به‌روز کن.' : 'حساب با ایمیل تأییدشده ساخته می‌شود و کاربر می‌تواند فوراً وارد شود.'}
            icon={editing ? 'bi-person-gear' : 'bi-person-plus'}
            size="lg"
            method={editing ? 'put' : 'post'}
            url={editing ? fillRoute(routes.update, member.id) : routes.store}
            initial={{
                name: member?.name ?? '',
                username: member?.username ?? '',
                email: member?.email ?? '',
                phone: member?.phone ?? '',
                password: '',
                roles: member?.roles ?? ['freelancer'],
            }}
            submitLabel={editing ? 'ذخیره‌ی تغییرات' : 'ساخت حساب'}
        >
            {(form) => (
                <div className="row g-3">
                    <TextInput form={form} name="name" label="نام و نام خانوادگی" required className="col-md-6" icon="bi-person" autoComplete="off" />
                    <TextInput form={form} name="username" label="نام کاربری" required ltr className="col-md-6" icon="bi-at" hint="حروف کوچک انگلیسی، عدد، نقطه یا خط تیره" autoComplete="off" />
                    <TextInput form={form} name="email" label="ایمیل" type="email" required ltr className="col-md-6" icon="bi-envelope" autoComplete="off" />
                    <TextInput form={form} name="phone" label="موبایل" type="tel" ltr className="col-md-6" icon="bi-phone" placeholder="09xxxxxxxxx" inputMode="numeric" />
                    <TextInput
                        form={form}
                        name="password"
                        label="رمز عبور"
                        type="password"
                        required={!editing}
                        ltr
                        className="col-12"
                        icon="bi-key"
                        autoComplete="new-password"
                        hint={editing ? 'خالی بگذار تا رمز فعلی تغییر نکند. حداقل ۸ کاراکتر با حرف و عدد.' : 'حداقل ۸ کاراکتر، شامل حرف و عدد.'}
                    />
                    <CheckboxCards
                        form={form}
                        name="roles"
                        label="نقش‌ها"
                        className="col-12"
                        options={roles.map((role) => ({ value: role, label: label('role', role), ...ROLE_OPTIONS[role] }))}
                    />
                </div>
            )}
        </ModalForm>
    );
}

function StatusForm({ modal, routes }) {
    const member = modal.record;
    const suspended = member?.status && member.status !== 'active';

    return (
        <ModalForm
            open={modal.open}
            onClose={modal.close}
            title={suspended ? `فعال‌سازی دوباره‌ی ${member?.name}` : `تعلیق ${member?.name ?? ''}`}
            subtitle={suspended ? `دلیل فعلی: ${member?.suspension_reason ?? '—'}` : 'کاربر در درخواست بعدی از حساب خارج می‌شود و تا فعال‌سازی نمی‌تواند وارد شود.'}
            icon={suspended ? 'bi-person-check' : 'bi-person-slash'}
            tone={suspended ? 'success' : 'warning'}
            method="put"
            url={member ? fillRoute(routes.status, member.id) : ''}
            initial={{ status: suspended ? 'active' : 'suspended', reason: '' }}
            submitLabel="ثبت وضعیت"
            submitTone={suspended ? 'primary' : 'warning'}
        >
            {(form) => (
                <div className="d-grid gap-3">
                    <RadioCards
                        form={form}
                        name="status"
                        label="وضعیت جدید"
                        columns={3}
                        options={[
                            { value: 'active', label: 'فعال', icon: 'bi-check-circle', description: 'دسترسی کامل' },
                            { value: 'suspended', label: 'معلق', icon: 'bi-pause-circle', description: 'موقت، تا بررسی' },
                            { value: 'banned', label: 'مسدود', icon: 'bi-slash-circle', description: 'دائمی' },
                        ]}
                    />
                    {form.data.status !== 'active' && (
                        <Textarea form={form} name="reason" label="دلیل (به کاربر نمایش داده می‌شود)" required rows={3} maxLength={255} placeholder="مثلاً: اشتراک شماره تماس در چت پروژه" />
                    )}
                </div>
            )}
        </ModalForm>
    );
}

export default function Index({ users, filters: initialFilters, counts, roles, canImpersonate, routes }) {
    const filters = useFilters(initialFilters);
    const editor = useModal();
    const statusEditor = useModal();

    const sort = (column) =>
        filters.apply({
            ...filters.filters,
            sort: column,
            direction: filters.filters.sort === column && filters.filters.direction === 'desc' ? 'asc' : 'desc',
        });

    const remove = async (member) => {
        const ok = await confirm({
            title: `حذف حساب ${member.name}؟`,
            message: 'حساب غیرفعال و از فهرست‌ها حذف می‌شود؛ پروژه‌ها، قراردادها و تراکنش‌های گذشته‌اش برای سوابق باقی می‌مانند.',
            confirmLabel: 'حذف حساب',
            requireText: member.username ?? undefined,
        });

        if (ok) {
            destroy(fillRoute(routes.destroy, member.id));
        }
    };

    const impersonate = async (member) => {
        const ok = await confirm({
            title: `ورود به‌جای ${member.name}؟`,
            message: 'پنل را دقیقاً همان‌طور که این کاربر می‌بیند خواهی دید. با دکمه‌ی بالای صفحه به حساب خودت برمی‌گردی.',
            confirmLabel: 'ورود',
            tone: 'primary',
            icon: 'bi-incognito',
        });

        if (ok) {
            router.post(fillRoute(routes.impersonate, member.id));
        }
    };

    const columns = [
        {
            key: 'name',
            label: 'کاربر',
            primary: true,
            sortable: true,
            render: (member) => <Person user={member} />,
        },
        {
            key: 'roles',
            label: 'نقش',
            render: (member) => (
                <div className="d-flex flex-wrap gap-1">
                    {(member.roles ?? []).map((role) => (
                        <StatusBadge key={role} group="role" value={role} />
                    ))}
                </div>
            ),
        },
        {
            key: 'details',
            label: 'جزئیات',
            render: (member) => (
                <div className="d-flex flex-wrap gap-1">
                    {member.freelancer_level && <StatusBadge group="level" value={member.freelancer_level} />}
                    {member.company_name && <span className="jl-chip">{member.company_name}</span>}
                    {member.mentor_verified && (
                        <Badge tone="info" icon="bi-patch-check">
                            منتور تأییدشده
                        </Badge>
                    )}
                    {!member.freelancer_level && !member.company_name && !member.mentor_verified && <span className="text-muted">—</span>}
                </div>
            ),
        },
        {
            key: 'status',
            label: 'وضعیت',
            render: (member) => (
                <span title={member.suspension_reason ?? undefined}>
                    <StatusBadge group="userStatus" value={member.status} />
                </span>
            ),
        },
        { key: 'created_at', label: 'عضویت', sortable: true, render: (member) => <span title={formatDate(member.created_at)}>{formatRelative(member.created_at)}</span> },
        {
            key: 'last_login_at',
            label: 'آخرین ورود',
            sortable: true,
            render: (member) => (member.last_login_at ? formatRelative(member.last_login_at) : <span className="text-muted">هرگز</span>),
        },
    ];

    const roleTabs = [{ key: '', label: 'همه', count: counts.all }, ...roles.map((role) => ({ key: role, label: label('role', role) }))];

    return (
        <>
            <PageHeader
                title="کاربران"
                description={`فریلنسرها، کارفرماها و منتورها — ${formatNumber(counts.all)} حساب${counts.suspended ? ` (${formatNumber(counts.suspended)} معلق یا مسدود)` : ''}`}
                primary={{ label: 'کاربر جدید', icon: 'bi-person-plus', onClick: () => editor.show(null) }}
            />

            <TableCard
                meta={users.meta}
                toolbar={
                    <>
                        <SearchInput value={filters.filters.search} onChange={filters.search} placeholder="نام، نام کاربری، ایمیل یا موبایل..." className="jl-toolbar-search" />
                        <Tabs items={roleTabs} active={filters.filters.role ?? ''} onChange={(role) => filters.set('role', role || null)} />
                        <select className="form-select" value={filters.filters.status ?? ''} onChange={(event) => filters.set('status', event.target.value || null)} aria-label="وضعیت">
                            <option value="">همه‌ی وضعیت‌ها</option>
                            {options('userStatus').map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                        {(filters.filters.search || filters.filters.role || filters.filters.status) && (
                            <button type="button" className="btn btn-link btn-sm text-decoration-none" onClick={() => filters.apply({ ...filters.filters, search: null, role: null, status: null })}>
                                <i className="bi bi-x-circle" /> حذف فیلترها
                            </button>
                        )}
                    </>
                }
            >
                <DataTable
                    columns={columns}
                    rows={users.data}
                    sort={{ column: filters.filters.sort, direction: filters.filters.direction }}
                    onSort={sort}
                    onRowClick={(member) => editor.show(member)}
                    rowClassName={(member) => (member.status !== 'active' ? 'is-muted' : '')}
                    empty={
                        <EmptyState
                            title="کاربری پیدا نشد"
                            description="فیلترها را تغییر بده یا یک حساب جدید بساز."
                            action={
                                <button type="button" className="btn btn-primary btn-sm" onClick={() => editor.show(null)}>
                                    <i className="bi bi-person-plus" /> کاربر جدید
                                </button>
                            }
                        />
                    }
                    actions={(member) => (
                        <RowActions>
                            {(close) => (
                                <>
                                    <DropdownItem
                                        icon="bi-pencil"
                                        onClick={() => {
                                            close();
                                            editor.show(member);
                                        }}
                                    >
                                        ویرایش
                                    </DropdownItem>
                                    <DropdownItem
                                        icon={member.status === 'active' ? 'bi-person-slash' : 'bi-person-check'}
                                        onClick={() => {
                                            close();
                                            statusEditor.show(member);
                                        }}
                                    >
                                        {member.status === 'active' ? 'تعلیق یا مسدود کردن' : 'فعال‌سازی دوباره'}
                                    </DropdownItem>
                                    {canImpersonate && (
                                        <DropdownItem
                                            icon="bi-incognito"
                                            onClick={() => {
                                                close();
                                                impersonate(member);
                                            }}
                                        >
                                            ورود به‌جای کاربر
                                        </DropdownItem>
                                    )}
                                    <DropdownDivider />
                                    <DropdownItem
                                        icon="bi-trash3"
                                        danger
                                        onClick={() => {
                                            close();
                                            remove(member);
                                        }}
                                    >
                                        حذف حساب
                                    </DropdownItem>
                                </>
                            )}
                        </RowActions>
                    )}
                />
            </TableCard>

            <MemberForm modal={editor} roles={roles} routes={routes} />
            <StatusForm modal={statusEditor} routes={routes} />
        </>
    );
}
