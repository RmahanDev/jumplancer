import ModalForm from '../../Components/Form/ModalForm';
import NumberInput from '../../Components/Form/NumberInput';
import Select from '../../Components/Form/Select';
import Switch from '../../Components/Form/Switch';
import TextInput from '../../Components/Form/TextInput';
import Textarea from '../../Components/Form/Textarea';
import PasswordStrength from '../../Components/PasswordStrength';
import PageHeader from '../../Components/Panel/PageHeader';
import Avatar from '../../Components/UI/Avatar';
import { Badge, StatusBadge } from '../../Components/UI/Badge';
import UserName from '../../Components/UI/UserName';
import Meter from '../../Components/UI/Meter';
import { useAuthUser } from '../../hooks/usePanel';
import { useModal } from '../../hooks/useModal';
import { formatDate, formatMoney, formatNumber, formatRelative } from '../../lib/format';
import { label, options } from '../../lib/labels';

const ROLE_SECTIONS = {
    freelancer: { title: 'پروفایل فریلنسری', icon: 'bi-laptop' },
    employer: { title: 'پروفایل کارفرمایی', icon: 'bi-briefcase' },
    mentor: { title: 'پروفایل منتوری', icon: 'bi-mortarboard' },
};

function accountFields(account) {
    return {
        name: account.name ?? '',
        username: account.username ?? '',
        email: account.email ?? '',
        phone: account.phone ?? '',
        bio: account.bio ?? '',
    };
}

function AccountForm({ modal, account, routes }) {
    return (
        <ModalForm open={modal.open} onClose={modal.close} title="اطلاعات حساب" icon="bi-person-gear" size="lg" method="put" url={routes.update} initial={accountFields(account)}>
            {(form) => (
                <div className="row g-3">
                    <TextInput form={form} name="name" label="نام و نام خانوادگی" required className="col-md-6" icon="bi-person" />
                    <TextInput form={form} name="username" label="نام کاربری" required ltr className="col-md-6" icon="bi-at" />
                    <TextInput form={form} name="email" label="ایمیل" type="email" required ltr className="col-md-6" icon="bi-envelope" />
                    <TextInput form={form} name="phone" label="موبایل" type="tel" ltr className="col-md-6" icon="bi-phone" placeholder="09xxxxxxxxx" inputMode="numeric" />
                    <Textarea form={form} name="bio" label="درباره‌ی من" rows={4} maxLength={1000} className="col-12" placeholder="چند جمله درباره‌ی خودت و کاری که دوست داری انجام بدهی." />
                </div>
            )}
        </ModalForm>
    );
}

function RoleForm({ modal, account, profiles, choices, routes }) {
    const role = modal.record;
    const profile = role ? profiles[role] : null;

    const initial = { ...accountFields(account) };

    if (role === 'freelancer') {
        initial.freelancer = { headline: profile?.headline ?? '', hourly_rate: profile?.hourly_rate ?? '', availability: profile?.availability ?? 'available' };
    } else if (role === 'employer') {
        initial.employer = {
            company_name: profile?.company_name ?? '',
            company_size: profile?.company_size ?? '',
            industry: profile?.industry ?? '',
            website: profile?.website ?? '',
            open_to_beginners: profile?.open_to_beginners ?? true,
        };
    } else if (role === 'mentor') {
        initial.mentor = {
            expertise_summary: profile?.expertise_summary ?? '',
            years_experience: profile?.years_experience ?? 0,
            mentoring_style: profile?.mentoring_style ?? 'both',
            max_mentees: profile?.max_mentees ?? 5,
        };
    }

    const clean = (data) => {
        const next = { ...data };

        if (next.freelancer) {
            next.freelancer = { ...next.freelancer, hourly_rate: next.freelancer.hourly_rate === '' ? null : Number(next.freelancer.hourly_rate) };
        }

        if (next.employer) {
            next.employer = { ...next.employer, company_size: next.employer.company_size || null, website: next.employer.website || null };
        }

        if (next.mentor) {
            next.mentor = { ...next.mentor, years_experience: Number(next.mentor.years_experience || 0), max_mentees: Number(next.mentor.max_mentees || 1) };
        }

        return next;
    };

    return (
        <ModalForm
            open={modal.open}
            onClose={modal.close}
            title={ROLE_SECTIONS[role]?.title ?? 'پروفایل'}
            icon={ROLE_SECTIONS[role]?.icon}
            size="lg"
            method="put"
            url={routes.update}
            initial={initial}
            transform={clean}
        >
            {(form) => (
                <div className="row g-3">
                    {role === 'freelancer' && (
                        <>
                            <TextInput form={form} name="freelancer.headline" label="عنوان حرفه‌ای" className="col-12" maxLength={150} placeholder="مثلاً توسعه‌دهنده‌ی لاراول و وردپرس" />
                            <NumberInput form={form} name="freelancer.hourly_rate" label="نرخ ساعتی" money className="col-md-6" />
                            <Select form={form} name="freelancer.availability" label="آمادگی کار" required allowEmpty={false} className="col-md-6" options={options('availability', choices.availability)} />
                        </>
                    )}
                    {role === 'employer' && (
                        <>
                            <TextInput form={form} name="employer.company_name" label="نام شرکت یا برند" className="col-md-6" />
                            <Select form={form} name="employer.company_size" label="اندازه‌ی تیم" className="col-md-6" options={options('companySize', choices.companySize)} />
                            <TextInput form={form} name="employer.industry" label="حوزه‌ی فعالیت" className="col-md-6" placeholder="مثلاً فروشگاه اینترنتی" />
                            <TextInput form={form} name="employer.website" label="وب‌سایت" ltr className="col-md-6" placeholder="https://" />
                            <Switch
                                form={form}
                                name="employer.open_to_beginners"
                                className="col-12"
                                label="با فریلنسرهای تازه‌کار کار می‌کنم"
                                description="پروژه‌هایت به تازه‌کارها پیشنهاد داده می‌شود و منتور همراهشان است."
                            />
                        </>
                    )}
                    {role === 'mentor' && (
                        <>
                            <Textarea form={form} name="mentor.expertise_summary" label="خلاصه‌ی تخصص" rows={4} maxLength={2000} className="col-12" />
                            <NumberInput form={form} name="mentor.years_experience" label="سابقه" unit="سال" className="col-md-4" />
                            <NumberInput form={form} name="mentor.max_mentees" label="حداکثر منتی هم‌زمان" unit="نفر" className="col-md-4" />
                            <Select form={form} name="mentor.mentoring_style" label="سبک منتورینگ" required allowEmpty={false} className="col-md-4" options={options('mentoringStyle', choices.mentoringStyle)} />
                        </>
                    )}
                </div>
            )}
        </ModalForm>
    );
}

function PasswordForm({ modal, routes }) {
    return (
        <ModalForm
            open={modal.open}
            onClose={modal.close}
            title="تغییر رمز عبور"
            subtitle="بعد از تغییر، در همین مرورگر وارد می‌مانی."
            icon="bi-shield-lock"
            method="put"
            url={routes.password}
            initial={{ current_password: '', password: '', password_confirmation: '' }}
            submitLabel="تغییر رمز"
        >
            {(form) => (
                <div className="d-grid gap-3">
                    <TextInput form={form} name="current_password" label="رمز فعلی" type="password" required ltr icon="bi-lock" autoComplete="current-password" />
                    <div>
                        <TextInput form={form} name="password" label="رمز جدید" type="password" required ltr icon="bi-key" autoComplete="new-password" />
                        <PasswordStrength password={form.data.password} />
                    </div>
                    <TextInput form={form} name="password_confirmation" label="تکرار رمز جدید" type="password" required ltr icon="bi-key-fill" autoComplete="new-password" />
                </div>
            )}
        </ModalForm>
    );
}

function Row({ label: title, children }) {
    return (
        <>
            <dt>{title}</dt>
            <dd>{children ?? <span className="text-muted">—</span>}</dd>
        </>
    );
}

export default function Profile({ account, profiles, options: choices, routes }) {
    const user = useAuthUser();
    const accountEditor = useModal();
    const roleEditor = useModal();
    const passwordEditor = useModal();

    return (
        <>
            <PageHeader
                title="حساب کاربری و امنیت"
                description="اطلاعات حساب، پروفایل نقش‌ها و رمز عبور."
                actions={
                    <button type="button" className="btn btn-soft" onClick={() => passwordEditor.show(null)}>
                        <i className="bi bi-shield-lock" /> تغییر رمز عبور
                    </button>
                }
            />

            <div className="row g-4">
                <div className="col-xl-5">
                    <section className="jl-card h-100 jl-rise">
                        <div className="jl-card-body">
                            <div className="d-flex align-items-center gap-3 mb-3">
                                <Avatar user={user} size="lg" />
                                <div className="min-w-0 flex-grow-1">
                                    <h2 className="h5 fw-bold mb-1">
                                        <UserName user={{ name: account.name, roles: user?.roles ?? [] }} />
                                    </h2>
                                </div>
                                <button type="button" className="btn btn-primary btn-sm" onClick={() => accountEditor.show(null)}>
                                    <i className="bi bi-pencil" /> ویرایش
                                </button>
                            </div>
                            <dl className="jl-details">
                                <Row label="نام کاربری">{account.username && <span className="ltr">@{account.username}</span>}</Row>
                                <Row label="ایمیل">{account.email && <span className="ltr">{account.email}</span>}</Row>
                                <Row label="موبایل">{account.phone && <span className="ltr">{account.phone}</span>}</Row>
                                <Row label="عضویت">{formatDate(account.created_at)}</Row>
                                <Row label="آخرین ورود">{account.last_login_at ? formatRelative(account.last_login_at) : null}</Row>
                            </dl>
                            {account.bio && <div className="jl-text-block mt-3">{account.bio}</div>}
                        </div>
                    </section>
                </div>

                <div className="col-xl-7 d-grid gap-4">
                    {Object.keys(profiles).length === 0 && (
                        <section className="jl-card jl-rise jl-rise-2">
                            <div className="jl-card-body text-muted">حساب‌های مدیریتی پروفایل نقش ندارند؛ همین اطلاعات حساب کافی است.</div>
                        </section>
                    )}

                    {profiles.freelancer && (
                        <section className="jl-card jl-rise jl-rise-2">
                            <header className="jl-card-header">
                                <div>
                                    <h2>
                                        <i className={`bi ${ROLE_SECTIONS.freelancer.icon} ms-1`} /> {ROLE_SECTIONS.freelancer.title}
                                    </h2>
                                    <p>{profiles.freelancer.headline ?? 'هنوز عنوان حرفه‌ای ننوشته‌ای.'}</p>
                                </div>
                                <button type="button" className="btn btn-soft btn-sm" onClick={() => roleEditor.show('freelancer')}>
                                    <i className="bi bi-pencil" /> ویرایش
                                </button>
                            </header>
                            <div className="jl-card-body">
                                <div className="mb-3">
                                    <div className="d-flex justify-content-between small mb-1">
                                        <span>آمادگی برای پروژه‌ی واقعی</span>
                                        <strong>{formatNumber(profiles.freelancer.readiness_score ?? 0)}٪</strong>
                                    </div>
                                    <Meter value={profiles.freelancer.readiness_score ?? 0} tone="accent" label="امتیاز آمادگی" />
                                </div>
                                <dl className="jl-details">
                                    <Row label="سطح">
                                        <StatusBadge group="level" value={profiles.freelancer.level} />
                                    </Row>
                                    <Row label="نرخ ساعتی">{profiles.freelancer.hourly_rate ? formatMoney(profiles.freelancer.hourly_rate) : null}</Row>
                                    <Row label="آمادگی کار">
                                        <StatusBadge group="availability" value={profiles.freelancer.availability} />
                                    </Row>
                                </dl>
                            </div>
                        </section>
                    )}

                    {profiles.employer && (
                        <section className="jl-card jl-rise jl-rise-3">
                            <header className="jl-card-header">
                                <div>
                                    <h2>
                                        <i className={`bi ${ROLE_SECTIONS.employer.icon} ms-1`} /> {ROLE_SECTIONS.employer.title}
                                    </h2>
                                    <p>{profiles.employer.company_name ?? 'نام شرکت ثبت نشده'}</p>
                                </div>
                                <button type="button" className="btn btn-soft btn-sm" onClick={() => roleEditor.show('employer')}>
                                    <i className="bi bi-pencil" /> ویرایش
                                </button>
                            </header>
                            <div className="jl-card-body">
                                <dl className="jl-details">
                                    <Row label="اندازه‌ی تیم">{profiles.employer.company_size ? label('companySize', profiles.employer.company_size) : null}</Row>
                                    <Row label="حوزه‌ی فعالیت">{profiles.employer.industry}</Row>
                                    <Row label="وب‌سایت">
                                        {profiles.employer.website && (
                                            <a href={profiles.employer.website} target="_blank" rel="noreferrer" className="ltr">
                                                {profiles.employer.website}
                                            </a>
                                        )}
                                    </Row>
                                    <Row label="کار با تازه‌کارها">{profiles.employer.open_to_beginners ? <Badge tone="success">بله</Badge> : <Badge tone="secondary">خیر</Badge>}</Row>
                                </dl>
                            </div>
                        </section>
                    )}

                    {profiles.mentor && (
                        <section className="jl-card jl-rise jl-rise-4">
                            <header className="jl-card-header">
                                <div>
                                    <h2>
                                        <i className={`bi ${ROLE_SECTIONS.mentor.icon} ms-1`} /> {ROLE_SECTIONS.mentor.title}
                                    </h2>
                                    <p>{profiles.mentor.is_verified ? 'منتور تأییدشده‌ی پلتفرم' : 'در انتظار تأیید پلتفرم'}</p>
                                </div>
                                <button type="button" className="btn btn-soft btn-sm" onClick={() => roleEditor.show('mentor')}>
                                    <i className="bi bi-pencil" /> ویرایش
                                </button>
                            </header>
                            <div className="jl-card-body">
                                <dl className="jl-details">
                                    <Row label="سابقه">{`${formatNumber(profiles.mentor.years_experience ?? 0)} سال`}</Row>
                                    <Row label="سبک">{label('mentoringStyle', profiles.mentor.mentoring_style)}</Row>
                                    <Row label="ظرفیت">{`${formatNumber(profiles.mentor.max_mentees)} منتی هم‌زمان`}</Row>
                                </dl>
                                {profiles.mentor.expertise_summary && <div className="jl-text-block mt-3">{profiles.mentor.expertise_summary}</div>}
                            </div>
                        </section>
                    )}
                </div>
            </div>

            <AccountForm modal={accountEditor} account={account} routes={routes} />
            <RoleForm modal={roleEditor} account={account} profiles={profiles} choices={choices} routes={routes} />
            <PasswordForm modal={passwordEditor} routes={routes} />
        </>
    );
}
