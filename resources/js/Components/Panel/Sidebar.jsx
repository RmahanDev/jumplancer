import { Link } from '@inertiajs/react';
import { Fragment } from 'react';
import { formatNumber } from '../../lib/format';
import { PANELS } from '../../lib/labels';
import Logo from '../Logo';
import Avatar from '../UI/Avatar';
import { RoleTag } from '../UI/UserName';

/**
 * Navigation of the current dashboard (items come from App\Support\PanelNavigation).
 * Collapsed on desktop it shows icons with tooltips; on phones it is a drawer.
 */
export default function Sidebar({ panel, user, collapsed = false }) {
    const items = panel?.navigation ?? [];
    const home = panel?.available?.find((item) => item.key === panel.current)?.href ?? '/dashboard';
    let lastGroup = null;

    return (
        <aside className="jl-sidebar" aria-label="منوی پنل">
            <Link href={home} className="jl-sidebar-brand" aria-label="داشبورد">
                <Logo size={36} subtitle={PANELS[panel?.current]?.label} />
            </Link>

            <nav className="jl-sidebar-nav">
                {items.map((item) => {
                    const header = item.group && item.group !== lastGroup ? item.group : null;
                    lastGroup = item.group;

                    return (
                        <Fragment key={item.key}>
                            {header && <div className="jl-nav-group">{header}</div>}
                            <Link
                                href={item.href}
                                className={`jl-nav-link ${item.active ? 'active' : ''}`}
                                aria-current={item.active ? 'page' : undefined}
                                data-tip={collapsed ? item.label : undefined}
                                data-tip-pos={collapsed ? 'start' : undefined}
                                prefetch
                            >
                                <i className={`bi ${item.icon}`} />
                                <span className="jl-nav-label">{item.label}</span>
                                {item.badge ? (
                                    <span className="jl-nav-badge" aria-label={`${formatNumber(item.badge)} مورد در انتظار`}>
                                        {formatNumber(item.badge)}
                                    </span>
                                ) : null}
                            </Link>
                        </Fragment>
                    );
                })}
            </nav>

            {user && (
                <div className="jl-sidebar-footer">
                    <Link
                        href={panel.profileUrl}
                        className="jl-nav-link mb-0"
                        data-tip={collapsed ? 'حساب کاربری' : undefined}
                        data-tip-pos={collapsed ? 'start' : undefined}
                    >
                        <Avatar user={user} size="sm" />
                        <span className="jl-sidebar-footer-text min-w-0 d-flex flex-column lh-sm">
                            <span className="fw-semibold text-truncate">{user.name}</span>
                            <span className="d-flex flex-wrap gap-1 mt-1">
                                {user.roles.slice(0, 2).map((role) => (
                                    <RoleTag key={role} role={role} />
                                ))}
                            </span>
                        </span>
                    </Link>
                </div>
            )}
        </aside>
    );
}
