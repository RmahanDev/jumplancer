import { router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import CommandPalette from '../Components/Panel/CommandPalette';
import Sidebar from '../Components/Panel/Sidebar';
import Topbar from '../Components/Panel/Topbar';
import ConfirmHost from '../Components/UI/ConfirmDialog';
import Toaster from '../Components/UI/Toaster';
import { useEscape } from '../hooks/useEscape';
import { getPageCommands } from '../lib/commands';
import { PANELS } from '../lib/labels';
import { setThemePreference } from '../lib/theme';

const COLLAPSE_KEY = 'jl-sidebar-collapsed';

function readCollapsed() {
    try {
        return localStorage.getItem(COLLAPSE_KEY) === '1';
    } catch {
        return false;
    }
}

function isDesktop() {
    return window.matchMedia?.('(min-width: 992px)').matches ?? true;
}

/**
 * Shell of every dashboard page (the default Inertia layout, kept across visits):
 * sidebar, topbar, panel search, toasts and confirm dialogs.
 */
export default function PanelLayout({ children }) {
    const { auth, panel, impersonating } = usePage().props;
    const user = auth?.user ?? null;
    const [collapsed, setCollapsed] = useState(readCollapsed);
    const [mobileOpen, setMobileOpen] = useState(false);
    const [paletteOpen, setPaletteOpen] = useState(false);
    const [paletteCommands, setPaletteCommands] = useState([]);

    useEffect(() => router.on('navigate', () => setMobileOpen(false)), []);

    useEffect(() => {
        try {
            localStorage.setItem(COLLAPSE_KEY, collapsed ? '1' : '0');
        } catch {
            // Private mode: the choice lasts for this page only.
        }
    }, [collapsed]);

    const toggleSidebar = () => (isDesktop() ? setCollapsed((value) => !value) : setMobileOpen((value) => !value));

    const baseCommands = useMemo(() => {
        if (!panel) {
            return [];
        }

        const navigation = (panel.navigation ?? []).map((item) => ({
            id: `nav:${item.key}`,
            group: 'رفتن به',
            label: item.label,
            icon: item.icon,
            keywords: [item.group ?? '', PANELS[panel.current]?.short ?? ''],
            run: () => router.visit(item.href),
        }));

        const panels = (panel.available ?? [])
            .filter((item) => item.key !== panel.current)
            .map((item) => ({
                id: `panel:${item.key}`,
                group: 'پنل‌ها',
                label: PANELS[item.key]?.label ?? item.key,
                icon: PANELS[item.key]?.icon,
                keywords: ['پنل', 'panel', 'switch'],
                run: () => router.visit(item.href),
            }));

        return [
            ...navigation,
            ...panels,
            { id: 'account:profile', group: 'حساب', label: 'حساب کاربری و امنیت', icon: 'bi-person-gear', keywords: ['پروفایل', 'رمز', 'profile', 'password'], run: () => router.visit(panel.profileUrl) },
            { id: 'account:logout', group: 'حساب', label: 'خروج از حساب', icon: 'bi-box-arrow-left', keywords: ['logout', 'خروج'], run: () => router.post(panel.logoutUrl) },
            { id: 'theme:light', group: 'ظاهر', label: 'تم روشن', icon: 'bi-sun', keywords: ['light', 'روشن', 'تم'], run: () => setThemePreference('light') },
            { id: 'theme:dark', group: 'ظاهر', label: 'تم تیره', icon: 'bi-moon-stars', keywords: ['dark', 'تیره', 'تم', 'شب'], run: () => setThemePreference('dark') },
            { id: 'theme:auto', group: 'ظاهر', label: 'تم هماهنگ با سیستم', icon: 'bi-circle-half', keywords: ['auto', 'system', 'سیستم', 'تم'], run: () => setThemePreference('auto') },
            { id: 'ui:sidebar', group: 'ظاهر', label: 'جمع و باز کردن منوی کناری', icon: 'bi-layout-sidebar-inset-reverse', keywords: ['sidebar', 'منو'], run: toggleSidebar },
        ];
    }, [panel]);

    const openPalette = () => {
        const pageCommands = getPageCommands().map((command) => ({ ...command, group: 'این صفحه', id: `page:${command.id}` }));
        setPaletteCommands([...pageCommands, ...baseCommands]);
        setPaletteOpen(true);
    };

    useEscape(() => setMobileOpen(false), mobileOpen);

    // Pages outside a panel (e.g. an error page for a guest) get the bare frame.
    if (!panel || !user) {
        return (
            <div className="jl-app">
                <main className="jl-content">{children}</main>
                <Toaster />
                <ConfirmHost />
            </div>
        );
    }

    return (
        <div className={`jl-app ${collapsed ? 'is-collapsed' : ''} ${mobileOpen ? 'is-mobile-open' : ''}`}>
            <a href="#main" className="visually-hidden-focusable jl-skip-link">
                رفتن به محتوای اصلی
            </a>

            <Sidebar panel={panel} user={user} collapsed={collapsed} />
            {mobileOpen && <div className="jl-sidebar-backdrop d-lg-none" onClick={() => setMobileOpen(false)} />}

            <div className="jl-main">
                {impersonating && (
                    <div className="jl-impersonation" role="status">
                        <i className="bi bi-incognito" />
                        <span>در حال مشاهده‌ی پنل با حساب «{impersonating.name}» هستی.</span>
                        <button type="button" className="btn btn-sm btn-accent" onClick={() => router.delete(impersonating.leaveUrl)}>
                            بازگشت به حساب خودم
                        </button>
                    </div>
                )}

                <Topbar
                    panel={panel}
                    user={user}
                    onToggleSidebar={toggleSidebar}
                    onOpenMobile={() => setMobileOpen(true)}
                    onOpenPalette={openPalette}
                />

                <main id="main" className="jl-content" tabIndex={-1}>
                    {children}
                </main>

                <footer className="jl-footer d-flex flex-wrap justify-content-between gap-2">
                    <span>جامپ‌لنسر — شروع کار آزاد، قدم‌به‌قدم</span>
                </footer>
            </div>

            <CommandPalette open={paletteOpen} onClose={() => setPaletteOpen(false)} commands={paletteCommands} />
            <Toaster />
            <ConfirmHost />
        </div>
    );
}
