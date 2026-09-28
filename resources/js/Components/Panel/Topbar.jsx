import { Link, router } from '@inertiajs/react';
import { label, PANELS } from '../../lib/labels';
import ThemeToggle from '../ThemeToggle';
import Avatar from '../UI/Avatar';
import Dropdown, { DropdownDivider, DropdownItem } from '../UI/Dropdown';
import Kbd from '../UI/Kbd';

/**
 * Sticky bar above the content: menu buttons, where you are, search (Ctrl+K), panel switcher,
 * theme, shortcuts help and the account menu.
 */
export default function Topbar({ panel, user, onToggleSidebar, onOpenMobile, onOpenPalette, onOpenHelp }) {
    const current = panel?.current ? PANELS[panel.current] : null;
    const activeItem = panel?.navigation?.find((item) => item.active);
    const otherPanels = (panel?.available ?? []).filter((item) => item.key !== panel.current);

    return (
        <header className="jl-topbar">
            <button type="button" className="jl-icon-btn d-lg-none" onClick={onOpenMobile} aria-label="باز کردن منو">
                <i className="bi bi-list" />
            </button>
            <button
                type="button"
                className="jl-icon-btn d-none d-lg-inline-grid"
                onClick={onToggleSidebar}
                aria-label="جمع و باز کردن منوی کناری"
                data-tip="منوی کناری — کلید B"
            >
                <i className="bi bi-layout-sidebar-inset-reverse" />
            </button>

            <div className="jl-topbar-title d-none d-md-block">
                {current && <span className="text-muted fw-normal">{current.short}</span>}
                {activeItem && (
                    <>
                        <i className="bi bi-chevron-left mx-2 small text-muted" />
                        {activeItem.label}
                    </>
                )}
            </div>

            <button type="button" className="jl-search-trigger ms-md-3" onClick={onOpenPalette} aria-label="جستجو و دستورها (Ctrl+K)">
                <i className="bi bi-search" />
                <span>جستجو و دستورها…</span>
                <Kbd combo="mod+k" />
            </button>

            <div className="flex-grow-1" />

            {otherPanels.length > 0 && current && (
                <Dropdown
                    width={250}
                    trigger={
                        <button type="button" className="btn btn-soft btn-sm d-none d-sm-inline-flex" aria-haspopup="menu">
                            <i className={`bi ${current.icon}`} />
                            {current.short}
                            <i className="bi bi-chevron-down small" />
                        </button>
                    }
                >
                    {(close) => (
                        <>
                            <h6 className="dropdown-header">رفتن به پنل دیگر</h6>
                            {otherPanels.map((item) => (
                                <DropdownItem key={item.key} as={Link} href={item.href} icon={PANELS[item.key]?.icon} onClick={close}>
                                    {PANELS[item.key]?.label ?? item.key}
                                </DropdownItem>
                            ))}
                        </>
                    )}
                </Dropdown>
            )}

            <ThemeToggle />

            <button type="button" className="jl-icon-btn is-bordered d-none d-sm-inline-grid" onClick={onOpenHelp} aria-label="میان‌برهای صفحه‌کلید" data-tip="میان‌برها — کلید ?">
                <i className="bi bi-keyboard" />
            </button>

            {user && (
                <Dropdown
                    width={260}
                    trigger={
                        <button type="button" className="btn btn-link p-0 border-0 d-flex align-items-center gap-2 text-decoration-none" aria-haspopup="menu" aria-label="منوی حساب">
                            <Avatar user={user} />
                        </button>
                    }
                >
                    {(close) => (
                        <>
                            <div className="px-3 py-2">
                                <div className="fw-bold text-truncate">{user.name}</div>
                                <div className="small text-muted text-truncate">
                                    {user.username ? <span className="ltr">@{user.username}</span> : user.email}
                                    {' · '}
                                    {user.roles.map((role) => label('role', role)).join('، ')}
                                </div>
                            </div>
                            <DropdownDivider />
                            <DropdownItem as={Link} href={panel.profileUrl} icon="bi-person-gear" onClick={close}>
                                حساب کاربری و امنیت
                            </DropdownItem>
                            {otherPanels.map((item) => (
                                <DropdownItem key={item.key} as={Link} href={item.href} icon={PANELS[item.key]?.icon} onClick={close} className="dropdown-item d-sm-none">
                                    {PANELS[item.key]?.label ?? item.key}
                                </DropdownItem>
                            ))}
                            <DropdownItem
                                icon="bi-keyboard"
                                onClick={() => {
                                    close();
                                    onOpenHelp();
                                }}
                            >
                                میان‌برهای صفحه‌کلید
                            </DropdownItem>
                            <DropdownDivider />
                            <DropdownItem
                                icon="bi-box-arrow-left"
                                danger
                                onClick={() => {
                                    close();
                                    router.post(panel.logoutUrl);
                                }}
                            >
                                خروج از حساب
                            </DropdownItem>
                        </>
                    )}
                </Dropdown>
            )}
        </header>
    );
}
