import { Link, router } from '@inertiajs/react';
import PageHeader from '../../Components/Panel/PageHeader';
import { Badge, StatusBadge } from '../../Components/UI/Badge';
import { Person } from '../../Components/UI/Avatar';
import { confirm } from '../../Components/UI/ConfirmDialog';
import StatCard from '../../Components/UI/StatCard';
import { formatNumber, formatRelative } from '../../lib/format';
import { fillRoute } from '../../lib/text';
import { destroy } from '../../lib/actions';

const HEALTH = {
    database: { label: 'اتصال پایگاه‌داده', hint: 'زمان پاسخ یک کوئری ساده' },
    cache: { label: 'کش', hint: 'نوشتن و خواندن یک کلید آزمایشی' },
    storage: { label: 'دسترسی نوشتن در storage', hint: 'پوشه‌های storage/app و storage/logs' },
    storage_link: { label: 'لینک فایل‌های عمومی', hint: 'اگر خطاست: php artisan storage:link' },
    failed_jobs: { label: 'جاب‌های ناموفق', hint: 'صف باید خالی از خطا باشد' },
    debug_mode: { label: 'حالت دیباگ در production', hint: 'APP_DEBUG در محیط اصلی باید خاموش باشد' },
    root_admin: { label: 'حساب مدیر کل اصلی', hint: 'از SUPER_ADMIN_* در فایل ‎.env' },
    log_size: { label: 'حجم فایل لاگ', hint: 'کمتر از ۲۰ مگابایت' },
};

const ENVIRONMENT = [
    ['app_env', 'محیط اجرا'],
    ['app_debug', 'حالت دیباگ'],
    ['app_url', 'آدرس برنامه'],
    ['php', 'نسخه‌ی PHP'],
    ['laravel', 'نسخه‌ی Laravel'],
    ['inertia', 'Inertia (سرور)'],
    ['permission', 'spatie/laravel-permission'],
    ['database', 'پایگاه‌داده'],
    ['cache', 'درایور کش'],
    ['queue', 'درایور صف'],
    ['session', 'درایور نشست'],
    ['mail', 'درایور ایمیل'],
    ['timezone', 'منطقه‌ی زمانی'],
    ['locale', 'زبان'],
    ['payments_sandbox', 'درگاه پرداخت آزمایشی'],
];

const TABLES = [
    ['users', 'کاربران', 'bi-people'],
    ['projects', 'پروژه‌ها', 'bi-kanban'],
    ['proposals', 'پیشنهادها', 'bi-send'],
    ['contracts', 'قراردادها', 'bi-file-earmark-check'],
    ['transactions', 'تراکنش‌ها', 'bi-cash-stack'],
    ['tickets', 'تیکت‌ها', 'bi-life-preserver'],
];

function envValue(value) {
    if (typeof value === 'boolean') {
        return value ? <Badge tone="warning">روشن</Badge> : <Badge tone="success">خاموش</Badge>;
    }

    return <span className="ltr d-inline-block">{value ?? '—'}</span>;
}

export default function Dashboard({ environment, health, tables, staff, recentLogins, routes }) {
    const failing = health.filter((check) => !check.ok).length;

    const clearCache = async () => {
        const ok = await confirm({
            title: 'پاک کردن همه‌ی کش‌ها؟',
            message: 'معادل php artisan optimize:clear است: کش کانفیگ، مسیرها، ویوها و رویدادها پاک می‌شود. درخواست بعدی کمی کندتر خواهد بود.',
            confirmLabel: 'پاک کن',
            tone: 'primary',
            icon: 'bi-lightning-charge',
        });

        if (ok) {
            destroy(routes.clearCache);
        }
    };

    const impersonate = async (user) => {
        const ok = await confirm({
            title: `ورود به‌جای ${user.name}؟`,
            message: 'پنل را دقیقاً همان‌طور که این کاربر می‌بیند خواهی دید. هر وقت خواستی با دکمه‌ی بالای صفحه به حساب خودت برمی‌گردی.',
            confirmLabel: 'ورود',
            tone: 'primary',
            icon: 'bi-incognito',
        });

        if (ok) {
            router.post(fillRoute(routes.impersonate, user.id));
        }
    };

    return (
        <>
            <PageHeader
                title="وضعیت سیستم"
                description="نمای برنامه‌نویس: پیکربندی اجرا، سلامت سرویس‌ها و حجم داده‌ها."
                actions={
                    <>
                        <Link href={routes.logs} className="btn btn-ghost">
                            <i className="bi bi-journal-code" /> لاگ‌ها
                        </Link>
                        <button type="button" className="btn btn-soft" onClick={clearCache}>
                            <i className="bi bi-lightning-charge" /> پاک کردن کش‌ها
                        </button>
                    </>
                }
            />

            <div className={`alert ${failing ? 'alert-warning' : 'alert-success'} d-flex align-items-center gap-2 jl-rise`} role="status">
                <i className={`bi ${failing ? 'bi-exclamation-triangle' : 'bi-check2-circle'} fs-5`} />
                {failing ? `${formatNumber(failing)} بررسی نیاز به توجه دارد.` : 'همه‌ی بررسی‌های سلامت موفق بودند.'}
            </div>

            <div className="row g-3 mb-4">
                {TABLES.map(([key, label, icon], index) => (
                    <div className="col-6 col-md-4 col-xl-2" key={key}>
                        <StatCard label={label} value={tables[key]} icon={icon} index={index} tone={index % 2 ? 'accent' : 'primary'} />
                    </div>
                ))}
            </div>

            <div className="row g-4">
                <div className="col-xl-7">
                    <section className="jl-card h-100 jl-rise jl-rise-2">
                        <header className="jl-card-header">
                            <div>
                                <h2>سلامت سرویس‌ها</h2>
                                <p>هر بار که این صفحه باز می‌شود دوباره بررسی می‌شود.</p>
                            </div>
                            <button type="button" className="jl-icon-btn" onClick={() => router.reload()} data-tip="بررسی دوباره" aria-label="بررسی دوباره">
                                <i className="bi bi-arrow-clockwise" />
                            </button>
                        </header>
                        <div className="jl-card-body">
                            <div className="row g-2">
                                {health.map((check) => (
                                    <div className="col-md-6" key={check.key}>
                                        <div className={`jl-health ${check.ok ? 'is-ok' : 'is-fail'}`}>
                                            <i className={`bi ${check.ok ? 'bi-check-circle-fill' : 'bi-x-octagon-fill'}`} />
                                            <div className="min-w-0 flex-grow-1">
                                                <div className="fw-semibold small">{HEALTH[check.key]?.label ?? check.key}</div>
                                                <div className="text-muted small text-truncate">{HEALTH[check.key]?.hint}</div>
                                            </div>
                                            {check.detail && <span className="jl-chip ltr">{check.detail}</span>}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </section>
                </div>

                <div className="col-xl-5">
                    <section className="jl-card h-100 jl-rise jl-rise-3">
                        <header className="jl-card-header">
                            <div>
                                <h2>پیکربندی اجرا</h2>
                                <p>
                                    {formatNumber(staff.super_admins)} مدیر کل · {formatNumber(staff.admins)} ادمین
                                </p>
                            </div>
                        </header>
                        <div className="jl-card-body">
                            <dl className="jl-details">
                                {ENVIRONMENT.map(([key, label]) => (
                                    <div key={key} style={{ display: 'contents' }}>
                                        <dt>{label}</dt>
                                        <dd>{envValue(environment[key])}</dd>
                                    </div>
                                ))}
                            </dl>
                        </div>
                    </section>
                </div>

                <div className="col-12">
                    <section className="jl-card jl-rise jl-rise-4">
                        <header className="jl-card-header">
                            <div>
                                <h2>آخرین ورودها</h2>
                                <p>برای بررسی مشکل یک کاربر می‌توانی پنل را با حساب او ببینی.</p>
                            </div>
                        </header>
                        <div className="jl-card-body pt-0">
                            {recentLogins.length === 0 ? (
                                <p className="text-muted mb-0">هنوز کسی وارد نشده است.</p>
                            ) : (
                                <ul className="jl-list">
                                    {recentLogins.map((user) => (
                                        <li key={user.id}>
                                            <div className="flex-grow-1 min-w-0">
                                                <Person user={user} />
                                            </div>
                                            <div className="d-none d-md-flex gap-1 flex-wrap">
                                                {(user.roles ?? []).map((role) => (
                                                    <StatusBadge key={role} group="role" value={role} />
                                                ))}
                                            </div>
                                            <span className="text-muted small text-nowrap">{formatRelative(user.last_login_at)}</span>
                                            {!(user.roles ?? []).includes('super_admin') && (
                                                <button type="button" className="btn btn-ghost btn-sm" onClick={() => impersonate(user)} data-tip="دیدن پنل با حساب این کاربر">
                                                    <i className="bi bi-incognito" />
                                                    <span className="d-none d-lg-inline">ورود به‌جای او</span>
                                                </button>
                                            )}
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                    </section>
                </div>
            </div>
        </>
    );
}
