import { useMemo, useState } from 'react';
import CheckboxCards from '../../../Components/Form/CheckboxCards';
import ModalForm from '../../../Components/Form/ModalForm';
import RadioCards from '../../../Components/Form/RadioCards';
import TextInput from '../../../Components/Form/TextInput';
import PageHeader from '../../../Components/Panel/PageHeader';
import Avatar from '../../../Components/UI/Avatar';
import { Badge, StatusBadge } from '../../../Components/UI/Badge';
import { RoleTag } from '../../../Components/UI/UserName';
import { confirm } from '../../../Components/UI/ConfirmDialog';
import EmptyState from '../../../Components/UI/EmptyState';
import SearchInput from '../../../Components/UI/SearchInput';
import { useModal } from '../../../hooks/useModal';
import { formatNumber, formatRelative } from '../../../lib/format';
import { PERMISSIONS } from '../../../lib/labels';
import { fillRoute, normalizeForSearch } from '../../../lib/text';
import { destroy } from '../../../lib/actions';

function StaffForm({ modal, permissions, grantable, supportDefaults, canCreateSuperAdmin, routes }) {
    const staff = modal.record;
    const editing = Boolean(staff);

    const permissionOptions = permissions.map((permission) => ({
        value: permission,
        label: PERMISSIONS[permission]?.label ?? permission,
        description: PERMISSIONS[permission]?.description,
        icon: PERMISSIONS[permission]?.icon,
        disabled: !grantable.includes(permission),
        disabledReason: 'خودت این دسترسی را نداری، پس نمی‌توانی آن را به کسی بدهی.',
    }));

    return (
        <ModalForm
            open={modal.open}
            onClose={modal.close}
            title={editing ? `ویرایش ${staff.name}` : 'تعریف ادمین یا پشتیبان جدید'}
            subtitle="هر ادمین یا پشتیبان فقط بخش‌هایی را می‌بیند که به آن‌ها دسترسی دارد."
            icon={editing ? 'bi-person-gear' : 'bi-person-badge'}
            size="lg"
            method={editing ? 'put' : 'post'}
            url={editing ? fillRoute(routes.update, staff.id) : routes.store}
            initial={{
                name: staff?.name ?? '',
                username: staff?.username ?? '',
                email: staff?.email ?? '',
                phone: staff?.phone ?? '',
                password: '',
                role: staff?.role ?? 'admin',
                status: staff?.status ?? 'active',
                permissions: staff?.is_super_admin ? [] : (staff?.permissions ?? []),
            }}
            submitLabel={editing ? 'ذخیره‌ی تغییرات' : 'تعریف حساب'}
        >
            {(form) => (
                <div className="row g-3">
                    <TextInput form={form} name="name" label="نام و نام خانوادگی" required className="col-md-6" icon="bi-person" autoComplete="off" />
                    <TextInput form={form} name="username" label="نام کاربری" required ltr className="col-md-6" icon="bi-at" autoComplete="off" />
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
                        hint={editing ? 'خالی بگذار تا رمز فعلی تغییر نکند.' : 'حداقل ۸ کاراکتر، شامل حرف و عدد. رمز را جداگانه به او بده.'}
                    />

                    <RadioCards
                        label="نقش"
                        className="col-12"
                        columns={canCreateSuperAdmin ? 3 : 2}
                        value={form.data.role}
                        error={form.errors.role}
                        onChange={(role) => {
                            form.setData((data) => ({
                                ...data,
                                role,
                                // A new support agent starts with the usual support access; admins pick theirs.
                                permissions: !editing && role === 'support' ? supportDefaults.filter((permission) => grantable.includes(permission)) : data.permissions,
                            }));
                        }}
                        options={[
                            { value: 'admin', label: 'ادمین', icon: 'bi-person-badge', description: 'مدیریت بخش‌هایی که دسترسی‌اش را دارد' },
                            { value: 'support', label: 'پشتیبان', icon: 'bi-headset', description: 'پاسخ به کاربران، تیکت‌ها و درخواست‌های بازگشت وجه' },
                            ...(canCreateSuperAdmin
                                ? [{ value: 'super_admin', label: 'مدیر کل', icon: 'bi-stars', description: 'دسترسی کامل به همه‌ی بخش‌ها و ابزار برنامه‌نویس' }]
                                : []),
                        ]}
                    />

                    {editing && (
                        <RadioCards
                            form={form}
                            name="status"
                            label="وضعیت حساب"
                            className="col-12"
                            columns={2}
                            options={[
                                { value: 'active', label: 'فعال', icon: 'bi-check-circle', description: 'می‌تواند وارد پنل شود' },
                                { value: 'suspended', label: 'معلق', icon: 'bi-pause-circle', description: 'تا فعال‌سازی دوباره نمی‌تواند وارد شود' },
                            ]}
                        />
                    )}

                    {form.data.role === 'super_admin' ? (
                        <div className="col-12">
                            <div className="alert alert-warning d-flex gap-2 mb-0">
                                <i className="bi bi-shield-exclamation mt-1" />
                                <div>
                                    <strong>دسترسی کامل.</strong> مدیر کل همه‌ی دسترسی‌ها را به‌طور خودکار دارد؛ این نقش را فقط به برنامه‌نویس‌ها و افراد کاملاً مورد اعتماد بده.
                                </div>
                            </div>
                        </div>
                    ) : (
                        <CheckboxCards
                            form={form}
                            name="permissions"
                            label="دسترسی‌ها"
                            hint="«مدیریت مدیران» یعنی این ادمین هم می‌تواند ادمین جدید تعریف کند (فقط با دسترسی‌هایی که خودش دارد)."
                            className="col-12"
                            options={permissionOptions}
                        />
                    )}
                </div>
            )}
        </ModalForm>
    );
}

export default function Index({ staff, permissions, grantable, supportDefaults = [], canCreateSuperAdmin, routes }) {
    const editor = useModal();
    const [search, setSearch] = useState('');

    const visible = useMemo(() => {
        const query = normalizeForSearch(search);

        return query ? staff.filter((member) => normalizeForSearch(`${member.name} ${member.username ?? ''} ${member.email}`).includes(query)) : staff;
    }, [staff, search]);

    const remove = async (member) => {
        const ok = await confirm({
            title: `حذف ${member.name} از مدیران؟`,
            message: 'حساب او حذف می‌شود و دیگر نمی‌تواند وارد پنل شود.',
            confirmLabel: 'حذف مدیر',
            requireText: member.username ?? undefined,
        });

        if (ok) {
            destroy(fillRoute(routes.destroy, member.id));
        }
    };

    return (
        <>
            <PageHeader
                title="مدیران، پشتیبان‌ها و دسترسی‌ها"
                description={`${formatNumber(staff.length)} حساب مدیریتی. ساختن ادمین یا پشتیبان جدید خودش یک دسترسی جداست («مدیریت مدیران»).`}
                primary={{ label: 'حساب جدید', icon: 'bi-person-plus', onClick: () => editor.show(null) }}
            />

            <div className="d-flex align-items-center gap-2 mb-3 jl-rise">
                <SearchInput value={search} onChange={setSearch} placeholder="جستجوی مدیر..." className="flex-grow-1" />
            </div>

            {visible.length === 0 ? (
                <EmptyState title="مدیری پیدا نشد" />
            ) : (
                <div className="row g-3">
                    {visible.map((member, index) => (
                        <div className="col-md-6 col-xxl-4" key={member.id}>
                            <article className={`jl-card h-100 jl-staff-card jl-rise jl-rise-${Math.min(index + 1, 8)} ${member.status !== 'active' ? 'is-muted' : ''}`}>
                                <div className="jl-card-body">
                                    <div className="d-flex align-items-start gap-3">
                                        <Avatar user={member} size="lg" />
                                        <div className="min-w-0 flex-grow-1">
                                            <div className="d-flex flex-wrap align-items-center gap-1">
                                                <h2 className="h6 fw-bold mb-0 text-truncate">{member.name}</h2>
                                                {member.is_you && <Badge tone="info">خودت</Badge>}
                                            </div>
                                            <div className="small text-muted text-truncate">
                                                <span className="ltr">@{member.username}</span> · {member.email}
                                            </div>
                                            <div className="d-flex flex-wrap gap-1 mt-2">
                                                <RoleTag role={member.role} />
                                                {member.is_root && <Badge tone="secondary" icon="bi-lock">حساب اصلی</Badge>}
                                                <StatusBadge group="userStatus" value={member.status} />
                                            </div>
                                        </div>
                                    </div>

                                    <div className="mt-3">
                                        {member.is_super_admin ? (
                                            <p className="small text-muted mb-0">
                                                <i className="bi bi-infinity" /> دسترسی کامل به همه‌ی بخش‌ها و ابزار برنامه‌نویس
                                            </p>
                                        ) : member.permissions.length === 0 ? (
                                            <p className="small text-muted mb-0">هنوز دسترسی‌ای ندارد.</p>
                                        ) : (
                                            <div className="d-flex flex-wrap gap-1">
                                                {member.permissions.map((permission) => (
                                                    <span key={permission} className="jl-chip" title={PERMISSIONS[permission]?.description}>
                                                        <i className={`bi ${PERMISSIONS[permission]?.icon ?? 'bi-key'}`} />
                                                        {PERMISSIONS[permission]?.label ?? permission}
                                                    </span>
                                                ))}
                                            </div>
                                        )}
                                    </div>
                                </div>
                                <footer className="jl-staff-card-footer">
                                    <span className="small text-muted">{member.last_login_at ? `آخرین ورود ${formatRelative(member.last_login_at)}` : 'هنوز وارد نشده'}</span>
                                    {member.can_manage ? (
                                        <div className="d-flex gap-1">
                                            <button type="button" className="btn btn-soft btn-sm" onClick={() => editor.show(member)}>
                                                <i className="bi bi-pencil" /> ویرایش
                                            </button>
                                            <button type="button" className="jl-icon-btn text-danger" onClick={() => remove(member)} aria-label={`حذف ${member.name}`} data-tip="حذف">
                                                <i className="bi bi-trash3" />
                                            </button>
                                        </div>
                                    ) : (
                                        <span className="small text-muted d-inline-flex align-items-center gap-1" title={member.locked_reason ?? undefined}>
                                            <i className="bi bi-lock" /> {member.is_you ? 'حساب خودت' : 'قفل'}
                                        </span>
                                    )}
                                </footer>
                            </article>
                        </div>
                    ))}
                </div>
            )}

            <StaffForm modal={editor} permissions={permissions} grantable={grantable} supportDefaults={supportDefaults} canCreateSuperAdmin={canCreateSuperAdmin} routes={routes} />
        </>
    );
}
