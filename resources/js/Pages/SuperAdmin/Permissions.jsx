import { Link, router } from '@inertiajs/react';
import { useMemo, useRef, useState } from 'react';
import PageHeader from '../../Components/Panel/PageHeader';
import { Person } from '../../Components/UI/Avatar';
import { Badge } from '../../Components/UI/Badge';
import EmptyState from '../../Components/UI/EmptyState';
import SearchInput from '../../Components/UI/SearchInput';
import { formatNumber } from '../../lib/format';
import { PERMISSIONS } from '../../lib/labels';
import { fillRoute, normalizeForSearch } from '../../lib/text';

/**
 * Every staff account × every permission. A click saves that cell right away.
 */
export default function Permissions({ staff, permissions, routes }) {
    const [pending, setPending] = useState({});
    const [search, setSearch] = useState('');
    const versions = useRef({});

    const rows = useMemo(() => {
        const query = normalizeForSearch(search);

        return query ? staff.filter((member) => normalizeForSearch(`${member.name} ${member.username ?? ''} ${member.email}`).includes(query)) : staff;
    }, [staff, search]);

    const permissionsOf = (member) => pending[member.id] ?? member.permissions;

    const toggle = (member, permission) => {
        if (!member.can_manage || member.is_super_admin) {
            return;
        }

        const current = permissionsOf(member);
        const next = current.includes(permission) ? current.filter((item) => item !== permission) : [...current, permission];
        const version = (versions.current[member.id] ?? 0) + 1;
        versions.current[member.id] = version;

        setPending((state) => ({ ...state, [member.id]: next }));

        router.put(
            fillRoute(routes.update, member.id),
            { permissions: next },
            {
                preserveScroll: true,
                preserveState: true,
                onFinish: () => {
                    if (versions.current[member.id] === version) {
                        setPending(({ [member.id]: _done, ...rest }) => rest);
                    }
                },
            },
        );
    };

    const lockReason = (member) => {
        if (member.is_super_admin) {
            return 'مدیر کل به همه‌چیز دسترسی دارد';
        }

        return member.locked_reason ?? null;
    };

    return (
        <>
            <PageHeader
                title="نقش‌ها و دسترسی‌ها"
                description="هر خانه یک دسترسی است؛ با کلیک همان لحظه ذخیره می‌شود. مدیر کل همیشه دسترسی کامل دارد."
                actions={
                    <Link href={routes.admins} className="btn btn-primary">
                        <i className="bi bi-person-plus" /> تعریف ادمین جدید
                    </Link>
                }
            />

            <section className="jl-card jl-table-card jl-rise jl-rise-2">
                <div className="jl-toolbar">
                    <SearchInput value={search} onChange={setSearch} placeholder="جستجوی مدیر..." className="jl-toolbar-search" />
                    <span className="text-muted small ms-auto">{formatNumber(rows.length)} حساب مدیریتی</span>
                </div>

                {rows.length === 0 ? (
                    <EmptyState compact title="مدیری با این مشخصات پیدا نشد" />
                ) : (
                    <div className="jl-table-wrap">
                        <table className="table jl-table jl-matrix align-middle">
                            <thead>
                                <tr>
                                    <th scope="col" className="jl-matrix-name">
                                        مدیر
                                    </th>
                                    {permissions.map((permission) => (
                                        <th scope="col" key={permission} className="text-center" title={PERMISSIONS[permission]?.description}>
                                            <span className="d-inline-flex flex-column align-items-center gap-1">
                                                <i className={`bi ${PERMISSIONS[permission]?.icon ?? 'bi-key'} fs-6`} />
                                                <span className="jl-matrix-label">{PERMISSIONS[permission]?.label ?? permission}</span>
                                            </span>
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {rows.map((member) => {
                                    const granted = permissionsOf(member);
                                    const locked = lockReason(member);
                                    const saving = Boolean(pending[member.id]);

                                    return (
                                        <tr key={member.id} className={saving ? 'is-saving' : ''}>
                                            <th scope="row" className="jl-matrix-name fw-normal">
                                                <div className="d-flex align-items-center gap-2">
                                                    <Person user={member} />
                                                    {member.is_super_admin && <Badge tone="accent">مدیر کل</Badge>}
                                                    {member.is_root && <Badge tone="secondary" icon="bi-lock">اصلی</Badge>}
                                                    {member.is_you && <Badge tone="info">خودت</Badge>}
                                                    {saving && <span className="spinner-border spinner-border-sm text-primary" aria-label="در حال ذخیره" />}
                                                </div>
                                            </th>
                                            {permissions.map((permission) => {
                                                const checked = member.is_super_admin || granted.includes(permission);
                                                const disabled = Boolean(locked) || !member.can_manage;

                                                return (
                                                    <td key={permission} className="text-center">
                                                        <label className="jl-matrix-cell" data-tip={disabled ? (locked ?? undefined) : undefined}>
                                                            <input
                                                                type="checkbox"
                                                                className="form-check-input"
                                                                checked={checked}
                                                                disabled={disabled}
                                                                onChange={() => toggle(member, permission)}
                                                                aria-label={`${PERMISSIONS[permission]?.label ?? permission} برای ${member.name}`}
                                                            />
                                                        </label>
                                                    </td>
                                                );
                                            })}
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                )}
            </section>

            <section className="jl-card mt-4 jl-rise jl-rise-3">
                <header className="jl-card-header">
                    <div>
                        <h2>هر دسترسی چه کاری را باز می‌کند؟</h2>
                        <p>ادمین‌ها فقط دسترسی‌هایی را می‌توانند به دیگران بدهند که خودشان دارند.</p>
                    </div>
                </header>
                <div className="jl-card-body">
                    <div className="row g-3">
                        {permissions.map((permission) => (
                            <div className="col-md-6 col-xl-4" key={permission}>
                                <div className="d-flex gap-2">
                                    <i className={`bi ${PERMISSIONS[permission]?.icon ?? 'bi-key'} text-primary-emphasis-soft fs-5`} />
                                    <div>
                                        <div className="fw-semibold small">
                                            {PERMISSIONS[permission]?.label ?? permission} <code className="small">{permission}</code>
                                        </div>
                                        <div className="text-muted small">{PERMISSIONS[permission]?.description}</div>
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            </section>
        </>
    );
}
