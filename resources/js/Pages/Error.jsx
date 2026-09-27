import { Head, Link, usePage } from '@inertiajs/react';
import { toPersianDigits } from '../lib/format';

const MESSAGES = {
    403: { title: 'به این بخش دسترسی نداری', text: 'اگر فکر می‌کنی باید این صفحه را ببینی، از مدیر کل بخواه دسترسی لازم را به حسابت بدهد.', icon: 'bi-shield-lock' },
    404: { title: 'این صفحه پیدا نشد', text: 'ممکن است آدرس اشتباه باشد یا این مورد حذف شده باشد.', icon: 'bi-signpost-split' },
    500: { title: 'مشکلی در سرور پیش آمد', text: 'خطا ثبت شد و بررسی می‌شود. چند لحظه‌ی دیگر دوباره امتحان کن.', icon: 'bi-bug' },
    503: { title: 'در حال به‌روزرسانی هستیم', text: 'پلتفرم برای مدت کوتاهی در دسترس نیست. کمی بعد برگرد.', icon: 'bi-tools' },
};

export default function Error({ status }) {
    const { auth } = usePage().props;
    const message = MESSAGES[status] ?? MESSAGES[500];

    return (
        <>
            <Head title={message.title} />
            <div className="jl-card jl-rise mx-auto my-4 text-center" style={{ maxWidth: 560 }}>
                <div className="jl-card-body p-4 p-md-5">
                    <span className="jl-stat-icon mx-auto mb-3" style={{ width: 64, height: 64, fontSize: '1.8rem' }}>
                        <i className={`bi ${message.icon}`} />
                    </span>
                    <div className="jl-hero-number text-accent mb-2">{toPersianDigits(status)}</div>
                    <h1 className="h4 fw-bold">{message.title}</h1>
                    <p className="text-muted-2 mb-4">{message.text}</p>
                    <div className="d-flex flex-wrap justify-content-center gap-2">
                        <button type="button" className="btn btn-ghost" onClick={() => window.history.back()}>
                            <i className="bi bi-arrow-right" /> برگشت
                        </button>
                        {auth?.user ? (
                            <Link href="/dashboard" className="btn btn-primary">
                                <i className="bi bi-grid-1x2" /> رفتن به داشبورد
                            </Link>
                        ) : (
                            <a href="/login" className="btn btn-primary">
                                <i className="bi bi-box-arrow-in-left" /> ورود به حساب
                            </a>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}
