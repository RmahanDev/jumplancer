import { Link } from '@inertiajs/react';
import { formatNumber, formatRelative } from '../../lib/format';

/**
 * Which posting the employer's next project uses (v4 rules): 1st free, 2nd free inside the window,
 * then the quota of a plan; without any, publishing needs a plan (drafts are always allowed).
 */
export default function PostingBanner({ next, plansUrl, secondFreeUntil = null, subscription = null }) {
    if (next === 'free_first') {
        return (
            <div className="alert alert-success d-flex align-items-center gap-2 jl-rise" role="status">
                <i className="bi bi-gift fs-5" />
                <div>
                    <strong>اولین پروژه‌ات رایگان است.</strong> بعد از انتشار آن، پروژه‌ی دوم هم تا مدتی رایگان می‌ماند.
                </div>
            </div>
        );
    }

    if (next === 'free_second') {
        return (
            <div className="alert alert-success d-flex align-items-center gap-2 jl-rise" role="status">
                <i className="bi bi-gift fs-5" />
                <div>
                    <strong>پروژه‌ی دومت هم رایگان است</strong>
                    {secondFreeUntil ? ` — تا ${formatRelative(secondFreeUntil)} فرصت داری.` : '.'}
                </div>
            </div>
        );
    }

    if (next === 'subscription') {
        const plan = subscription?.plan;
        const left = plan?.project_quota ? plan.project_quota - subscription.projects_used : null;

        return (
            <div className="alert alert-info d-flex align-items-center gap-2 jl-rise" role="status">
                <i className="bi bi-gem fs-5" />
                <div>
                    پروژه‌ی بعدی از سهمیه‌ی پلن <strong>{plan?.name}</strong> ثبت می‌شود
                    {left !== null ? ` — ${formatNumber(left)} پروژه باقی مانده.` : ' (نامحدود).'}
                </div>
            </div>
        );
    }

    return (
        <div className="alert alert-warning d-flex flex-wrap align-items-center gap-2 jl-rise" role="status">
            <i className="bi bi-exclamation-circle fs-5" />
            <div className="flex-grow-1">پروژه‌های رایگانت تمام شده؛ برای انتشار پروژه‌ی جدید یک پلن بخر. ذخیره‌ی پیش‌نویس همیشه آزاد است.</div>
            <Link href={plansUrl} className="btn btn-accent btn-sm">
                <i className="bi bi-gem" /> دیدن پلن‌ها
            </Link>
        </div>
    );
}
