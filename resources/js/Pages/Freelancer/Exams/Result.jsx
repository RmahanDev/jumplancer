import { Link } from '@inertiajs/react';
import { useState } from 'react';
import PageHeader from '../../../Components/Panel/PageHeader';
import { Badge, StatusBadge } from '../../../Components/UI/Badge';
import Meter from '../../../Components/UI/Meter';
import { post } from '../../../lib/actions';
import { formatDateTime, formatMoney, formatNumber } from '../../../lib/format';

const VOID_TEXT = {
    hidden: 'صفحه‌ی آزمون دو بار ترک شد (تب یا برنامه‌ی دیگر یا کوچک کردن صفحه).',
    blur: 'صفحه‌ی آزمون دو بار از حالت فعال خارج شد.',
    devtools: 'ابزار توسعه‌دهنده در حین آزمون باز شد.',
};

export default function Result({ exam, attempt, retake, routes }) {
    const [busy, setBusy] = useState(false);
    const passed = attempt.status === 'passed';
    const voided = attempt.status === 'voided';
    const retakeFee = retake?.fee;

    const again = () => {
        setBusy(true);
        post(routes.retake, {}, { onFinish: () => setBusy(false) });
    };

    return (
        <>
            <PageHeader
                title={`نتیجه‌ی آزمون «${exam.title}»`}
                description={`${exam.category ?? ''}${exam.skill ? ` · مهارت ${exam.skill}` : ''}`}
                actions={
                    <Link href={routes.fields} className="btn btn-ghost">
                        <i className="bi bi-arrow-right" /> حوزه‌ها و مهارت‌ها
                    </Link>
                }
            />

            <section className="jl-card jl-exam-result jl-rise">
                <div className="jl-card-body d-grid gap-3 py-4">
                    <div>
                        <i className={`bi ${passed ? 'bi-patch-check-fill text-success' : voided ? 'bi-slash-circle text-secondary' : 'bi-emoji-neutral text-danger'} display-5`} />
                    </div>
                    <div>
                        <StatusBadge group="attemptStatus" value={attempt.status} />
                    </div>

                    {voided ? (
                        <>
                            <h2 className="h5 fw-bold mb-0">آزمون باطل شد</h2>
                            <p className="text-muted-2 mb-0">
                                {VOID_TEXT[attempt.void_reason] ?? VOID_TEXT.hidden}
                                {attempt.fee_amount > 0 && ` هزینه‌ی ${formatMoney(attempt.fee_amount)} این آزمون برگشت داده نمی‌شود.`}
                            </p>
                        </>
                    ) : (
                        <>
                            <div className={`jl-exam-score ${passed ? 'text-success' : 'text-danger'}`}>
                                {formatNumber(attempt.score ?? 0)}
                                <span className="fs-5 text-muted fw-semibold"> از {formatNumber(exam.total_score)}</span>
                            </div>
                            <Meter value={attempt.score ?? 0} max={exam.total_score} tone={passed ? 'primary' : 'accent'} label={`نمره‌ی قبولی: ${formatNumber(exam.pass_score)}`} />
                            <p className="text-muted-2 mb-0">
                                {formatNumber(attempt.correct_count ?? 0)} جواب درست از {formatNumber(exam.questions_count)} سؤال.{' '}
                                {passed ? (
                                    <>
                                        مهارت <strong className="ltr d-inline-block">{exam.skill}</strong> حالا با نشان تأییدشده در پروفایلت دیده می‌شود.
                                    </>
                                ) : (
                                    `برای قبولی دست‌کم ${formatNumber(exam.pass_score)} نمره لازم بود.`
                                )}
                            </p>
                        </>
                    )}

                    {passed && (
                        <div>
                            <Badge tone="success" icon="bi-patch-check-fill">
                                <span className="ltr d-inline-block">{exam.skill}</span> · تأییدشده
                            </Badge>
                        </div>
                    )}

                    {retake && (
                        <div className="d-grid gap-2 justify-content-center">
                            <button type="button" className="btn btn-primary" onClick={again} disabled={busy || retakeFee === null || retakeFee === undefined}>
                                {busy ? <span className="spinner-border spinner-border-sm" /> : <i className="bi bi-arrow-repeat" />}
                                {retakeFee === null || retakeFee === undefined ? 'شرکت دوباره (هزینه به‌زودی اعلام می‌شود)' : Number(retakeFee) > 0 ? `شرکت دوباره با پرداخت ${formatMoney(retakeFee)}` : 'شرکت دوباره (رایگان)'}
                            </button>
                            <span className="small text-muted">می‌توانی همین حالا دوباره شرکت کنی؛ قوانین صفحه‌ی آزمون همان است.</span>
                        </div>
                    )}

                    <div className="small text-muted">
                        شروع {formatDateTime(attempt.started_at)}
                        {attempt.finished_at && ` · پایان ${formatDateTime(attempt.finished_at)}`}
                        {attempt.warnings > 0 && ` · ${formatNumber(attempt.warnings)} بار ترک صفحه`}
                    </div>
                </div>
            </section>
        </>
    );
}
