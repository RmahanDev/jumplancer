// Persian labels and badge tones for every enum value the server sends (App\Enums\*).
// The server sends raw values; presentation lives here, in one place.

const withTones = (entries) => Object.fromEntries(entries.map(([value, label, tone = 'secondary']) => [value, { label, tone }]));

export const ENUMS = {
    role: withTones([
        ['super_admin', 'مدیر کل', 'accent'],
        ['admin', 'ادمین', 'primary'],
        ['support', 'پشتیبان', 'dark'],
        ['mentor', 'منتور', 'info'],
        ['freelancer', 'فریلنسر', 'success'],
        ['employer', 'کارفرما', 'warning'],
    ]),
    userStatus: withTones([
        ['active', 'فعال', 'success'],
        ['suspended', 'معلق', 'warning'],
        ['banned', 'مسدود', 'danger'],
    ]),
    projectStatus: withTones([
        ['draft', 'پیش‌نویس', 'secondary'],
        ['pending_review', 'در انتظار بررسی', 'warning'],
        ['open', 'باز برای پیشنهاد', 'success'],
        ['in_progress', 'در حال انجام', 'info'],
        ['completed', 'تکمیل‌شده', 'primary'],
        ['cancelled', 'لغوشده', 'danger'],
    ]),
    proposalStatus: withTones([
        ['draft', 'پیش‌نویس', 'secondary'],
        ['pending', 'در انتظار پاسخ', 'warning'],
        ['shortlisted', 'در فهرست کوتاه', 'info'],
        ['accepted', 'پذیرفته شد', 'success'],
        ['rejected', 'رد شد', 'danger'],
        ['withdrawn', 'پس گرفته شد', 'secondary'],
    ]),
    contractStatus: withTones([
        ['active', 'فعال', 'success'],
        ['completed', 'تکمیل‌شده', 'primary'],
        ['cancelled', 'لغوشده', 'secondary'],
        ['disputed', 'در اختلاف', 'danger'],
    ]),
    milestoneStatus: withTones([
        ['pending', 'منتظر تأمین', 'secondary'],
        ['funded', 'در امانت', 'info'],
        ['submitted', 'تحویل داده شد', 'warning'],
        ['approved', 'تأیید شد', 'primary'],
        ['released', 'پرداخت شد', 'success'],
        ['refunded', 'بازگشت وجه', 'danger'],
    ]),
    disputeStatus: withTones([
        ['open', 'باز', 'danger'],
        ['under_review', 'در حال بررسی', 'warning'],
        ['resolved', 'حل شد', 'success'],
        ['rejected', 'رد شد', 'secondary'],
    ]),
    ticketStatus: withTones([
        ['open', 'در صف', 'warning'],
        ['assigned', 'واگذار شد', 'info'],
        ['in_progress', 'در حال پیگیری', 'primary'],
        ['closed', 'بسته', 'secondary'],
    ]),
    ticketType: withTones([
        ['technical', 'فنی', 'info'],
        ['motivational', 'انگیزشی', 'accent'],
    ]),
    ticketChannel: withTones([
        ['ticket', 'تیکت متنی'],
        ['phone', 'تماس تلفنی'],
    ]),
    programStatus: withTones([
        ['active', 'فعال', 'success'],
        ['paused', 'متوقف', 'warning'],
        ['completed', 'به پایان رسید', 'primary'],
        ['cancelled', 'لغو شد', 'secondary'],
    ]),
    sessionStatus: withTones([
        ['scheduled', 'برنامه‌ریزی‌شده', 'info'],
        ['done', 'برگزار شد', 'success'],
        ['missed', 'غیبت', 'warning'],
        ['cancelled', 'لغو شد', 'secondary'],
    ]),
    sessionType: withTones([
        ['technical', 'فنی', 'info'],
        ['motivational', 'انگیزشی', 'accent'],
        ['review', 'بازبینی کار', 'primary'],
    ]),
    transactionType: withTones([
        ['deposit', 'شارژ کیف پول', 'success'],
        ['escrow_hold', 'بلوکه در امانت', 'info'],
        ['escrow_release', 'دریافت از امانت', 'success'],
        ['payout', 'تسویه به حساب', 'primary'],
        ['refund', 'بازگشت وجه', 'warning'],
        ['fee', 'کارمزد پلتفرم', 'secondary'],
        ['plan_purchase', 'خرید پلن', 'accent'],
        ['mentor_payout', 'پرداخت منتور', 'primary'],
        ['exam_fee', 'هزینه‌ی آزمون', 'secondary'],
        ['mentorship_fee', 'هزینه‌ی منتورینگ', 'secondary'],
    ]),
    transactionStatus: withTones([
        ['pending', 'در انتظار', 'warning'],
        ['succeeded', 'موفق', 'success'],
        ['failed', 'ناموفق', 'danger'],
        ['cancelled', 'لغوشده', 'secondary'],
    ]),
    withdrawalStatus: withTones([
        ['pending', 'در انتظار پرداخت', 'warning'],
        ['paid', 'پرداخت شد', 'success'],
        ['rejected', 'رد شد', 'danger'],
        ['cancelled', 'لغو شد', 'secondary'],
    ]),
    disputeOutcome: withTones([
        ['continue', 'ادامه‌ی قرارداد', 'info'],
        ['refund_employer', 'بازگشت پول به کارفرما', 'warning'],
        ['pay_freelancer', 'پرداخت به فریلنسر', 'success'],
    ]),
    budgetType: withTones([
        ['fixed', 'مبلغ ثابت'],
        ['hourly', 'ساعتی'],
    ]),
    level: withTones([
        ['beginner', 'تازه‌کار', 'success'],
        ['junior', 'جونیور', 'info'],
        ['intermediate', 'میانی', 'primary'],
        ['senior', 'ارشد', 'accent'],
    ]),
    availability: withTones([
        ['available', 'آماده‌ی کار', 'success'],
        ['busy', 'مشغول', 'warning'],
        ['unavailable', 'فعلاً در دسترس نیست', 'secondary'],
    ]),
    companySize: withTones([
        ['solo', 'تک‌نفره'],
        ['2-10', '۲ تا ۱۰ نفر'],
        ['11-50', '۱۱ تا ۵۰ نفر'],
        ['51-200', '۵۱ تا ۲۰۰ نفر'],
        ['200+', 'بیش از ۲۰۰ نفر'],
    ]),
    mentoringStyle: withTones([
        ['technical', 'فنی'],
        ['motivational', 'انگیزشی'],
        ['both', 'فنی و انگیزشی'],
    ]),
    contentType: withTones([
        ['article', 'مقاله', 'primary'],
        ['video', 'ویدیو', 'danger'],
        ['checklist', 'چک‌لیست', 'success'],
        ['roadmap', 'نقشه‌ی راه', 'accent'],
    ]),
    audience: withTones([
        ['freelancer', 'فریلنسرها'],
        ['employer', 'کارفرماها'],
        ['all', 'همه'],
    ]),
    purpose: withTones([
        ['technical', 'فنی', 'info'],
        ['motivational', 'انگیزشی', 'accent'],
    ]),
    moderationStatus: withTones([
        ['pending_review', 'در انتظار بررسی', 'warning'],
        ['approved', 'تأیید شد', 'success'],
        ['rejected', 'رد شد', 'danger'],
    ]),
    fieldStatus: withTones([
        ['pending_exam', 'در انتظار آزمون', 'warning'],
        ['active', 'فعال', 'success'],
        ['rejected', 'رد شد', 'danger'],
    ]),
    attemptStatus: withTones([
        ['in_progress', 'در حال آزمون', 'info'],
        ['passed', 'قبول', 'success'],
        ['failed', 'مردود', 'danger'],
        ['voided', 'باطل شد', 'secondary'],
    ]),
    violationType: withTones([
        ['phone', 'شماره تلفن', 'danger'],
        ['email', 'ایمیل', 'danger'],
        ['link', 'لینک', 'warning'],
        ['social_id', 'آیدی شبکه‌ی اجتماعی', 'warning'],
        ['other', 'سایر', 'secondary'],
    ]),
    violationSource: withTones([
        ['auto_filter', 'فیلتر خودکار'],
        ['staff', 'کارشناس'],
        ['user_report', 'گزارش کاربر'],
    ]),
    violationAction: withTones([
        ['message_blocked', 'پیام مسدود شد', 'warning'],
        ['warning', 'هشدار', 'info'],
        ['suspended', 'حساب معلق شد', 'danger'],
    ]),
    postingType: withTones([
        ['free_first', 'پروژه‌ی اول (رایگان)', 'success'],
        ['free_second', 'پروژه‌ی دوم (رایگان)', 'success'],
        ['subscription', 'از سهمیه‌ی اشتراک', 'accent'],
    ]),
    subscriptionStatus: withTones([
        ['active', 'فعال', 'success'],
        ['expired', 'منقضی', 'secondary'],
        ['cancelled', 'لغوشده', 'secondary'],
    ]),
    logLevel: withTones([
        ['emergency', 'EMERGENCY', 'danger'],
        ['alert', 'ALERT', 'danger'],
        ['critical', 'CRITICAL', 'danger'],
        ['error', 'ERROR', 'danger'],
        ['warning', 'WARNING', 'warning'],
        ['notice', 'NOTICE', 'info'],
        ['info', 'INFO', 'info'],
        ['debug', 'DEBUG', 'secondary'],
    ]),
};

/** The Persian label of an enum value, or the raw value when unknown. */
export function label(group, value) {
    return ENUMS[group]?.[value]?.label ?? value ?? '—';
}

export function tone(group, value) {
    return ENUMS[group]?.[value]?.tone ?? 'secondary';
}

/** [{ value, label }] for selects, in the order the server sent the values. */
export function options(group, values = Object.keys(ENUMS[group] ?? {})) {
    return values.map((value) => ({ value, label: label(group, value) }));
}

export const PANELS = {
    super: { label: 'مدیر کل (برنامه‌نویس)', short: 'مدیر کل', icon: 'bi-cpu' },
    admin: { label: 'پنل ادمین', short: 'ادمین', icon: 'bi-speedometer2' },
    mentor: { label: 'پنل منتور', short: 'منتور', icon: 'bi-mortarboard' },
    freelancer: { label: 'پنل فریلنسر', short: 'فریلنسر', icon: 'bi-laptop' },
    employer: { label: 'پنل کارفرما', short: 'کارفرما', icon: 'bi-briefcase' },
};

export const PERMISSIONS = {
    'users.manage': { label: 'مدیریت کاربران', description: 'ساخت، ویرایش، تعلیق و حذف فریلنسرها، کارفرماها و منتورها', icon: 'bi-people' },
    'admins.manage': { label: 'مدیریت مدیران', description: 'تعریف ادمین جدید و تعیین دسترسی‌ها (فقط در حد دسترسی خودش)', icon: 'bi-person-badge' },
    'projects.manage': { label: 'مدیریت پروژه‌ها', description: 'بررسی و انتشار، ویرایش و حذف پروژه‌ها', icon: 'bi-kanban' },
    'contracts.manage': { label: 'قراردادها و اختلاف‌ها', description: 'مشاهده‌ی قراردادها و رسیدگی به اختلاف‌ها', icon: 'bi-file-earmark-text' },
    'finance.view': { label: 'مشاهده‌ی مالی', description: 'دفتر تراکنش‌ها، امانت‌ها و درآمد پلتفرم', icon: 'bi-cash-stack' },
    'catalog.manage': { label: 'دسته‌ها و پلن‌ها', description: 'دسته‌بندی‌ها، مهارت‌ها، بازه‌ی قیمت و پلن‌های کارفرما', icon: 'bi-diagram-3' },
    'moderation.manage': { label: 'نظارت و بازبینی', description: 'تخلفات تبادل اطلاعات تماس، فایل‌های نمونه‌کار و آزمون حوزه‌ها', icon: 'bi-shield-exclamation' },
    'mentoring.manage': { label: 'منتورینگ', description: 'تیکت‌ها و واگذاری آن‌ها به منتورها', icon: 'bi-life-preserver' },
    'content.manage': { label: 'محتوای آموزشی', description: 'مقاله، ویدیو، چک‌لیست و نقشه‌ی راه', icon: 'bi-journal-richtext' },
    'settings.manage': { label: 'تنظیمات پلتفرم', description: 'قوانین کسب‌وکار مثل پروژه‌ی رایگان و هزینه‌ی آزمون', icon: 'bi-sliders' },
    'exams.manage': { label: 'آزمون‌ساز', description: 'طراحی آزمون مهارت‌ها: سوال‌ها، گزینه‌ها، زمان و نمره‌ی قبولی', icon: 'bi-ui-checks-grid' },
    'withdrawals.manage': { label: 'بازگشت وجه به کارت', description: 'بررسی درخواست‌ها، پرداخت با شماره‌ی پیگیری و رسید، یا رد درخواست', icon: 'bi-bank' },
};

export const SETTINGS = {
    employer_free_projects: { label: 'تعداد پروژه‌ی رایگان هر کارفرما', unit: 'پروژه', icon: 'bi-gift', help: 'پروژه‌ی اول همیشه رایگان است؛ دومی فقط در مهلت پایین.' },
    second_free_project_window_days: { label: 'مهلت پروژه‌ی رایگان دوم', unit: 'روز', icon: 'bi-calendar-range', help: 'چند روز بعد از پروژه‌ی اول، پروژه‌ی دوم هم رایگان است.' },
    beginner_free_mentorships: { label: 'منتورینگ رایگان فریلنسر تازه‌کار', unit: 'بار', icon: 'bi-mortarboard', help: 'تعداد برنامه‌ی منتورینگ رایگان برای فریلنسرهای سطح تازه‌کار.' },
    exam_required_from_level: { label: 'آزمون ورودی لازم از سطح', choices: 'level', icon: 'bi-clipboard-check', help: 'ثبت حوزه‌ی کاری در این سطح یا بالاتر، قبولی در آزمون می‌خواهد.' },
    primary_field_exam_fee: { label: 'هزینه‌ی آزمون حوزه‌ی اول', unit: 'تومان', icon: 'bi-cash', help: 'آزمون اولین حوزه‌ی هر فریلنسر؛ صفر یعنی رایگان.' },
    extra_field_exam_fee: { label: 'هزینه‌ی آزمون حوزه‌های بعدی', unit: 'تومان', icon: 'bi-cash-stack', help: 'هزینه‌ی آزمون هر حوزه‌ی اضافه؛ خالی یعنی هنوز تعیین نشده و به فریلنسر «به‌زودی» نمایش داده می‌شود.' },
    contact_violation_action: {
        label: 'واکنش به اشتراک اطلاعات تماس در چت',
        icon: 'bi-shield-exclamation',
        help: 'وقتی شماره، ایمیل یا لینک در چت پروژه فرستاده شود.',
        choiceLabels: { suspend: 'تعلیق فوری حساب', warning: 'فقط هشدار', block_message: 'فقط مسدود کردن پیام' },
    },
    platform_fee_percent: {
        label: 'کارمزد پلتفرم از فریلنسر',
        unit: 'درصد',
        icon: 'bi-percent',
        help: 'از هر مبلغی که به فریلنسر پرداخت می‌شود کم می‌شود. روی قراردادهای تازه اعمال می‌شود.',
    },
    mentorship_fee_percent: {
        label: 'کارمزد اضافه‌ی منتورینگ',
        unit: 'درصد',
        icon: 'bi-mortarboard',
        help: 'وقتی فریلنسر در پیشنهادش منتور خواسته، این درصد به کارمزد اضافه می‌شود (به‌جز منتورینگ‌های رایگان تازه‌کارها).',
    },
    mentor_share_percent: {
        label: 'سهم منتور از هر پرداخت',
        unit: 'درصد',
        icon: 'bi-person-check',
        help: 'این درصد از مبلغ هر مرحله به کیف پول منتور قرارداد واریز می‌شود و باقی کارمزد سهم سایت است.',
    },
    hire_deposit_percent: {
        label: 'امانت حسن انجام کار هنگام استخدام',
        unit: 'درصد',
        icon: 'bi-safe',
        help: 'کارفرما هنگام استخدام این درصد از مبلغ پیشنهاد فریلنسر را در کیف پولش امانت می‌گذارد؛ تا نظر کارشناس برداشتنی نیست.',
    },
    withdrawal_min_amount: { label: 'حداقل مبلغ برداشت از کیف پول', unit: 'تومان', icon: 'bi-bank', help: 'کمترین مبلغی که کاربر می‌تواند برای واریز به کارت بانکی‌اش درخواست بدهد.' },
};
