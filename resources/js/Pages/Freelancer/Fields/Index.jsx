import { Link } from '@inertiajs/react';
import { useState } from 'react';
import ModalForm from '../../../Components/Form/ModalForm';
import RadioCards from '../../../Components/Form/RadioCards';
import Select from '../../../Components/Form/Select';
import PageHeader from '../../../Components/Panel/PageHeader';
import { Badge, StatusBadge } from '../../../Components/UI/Badge';
import { confirm } from '../../../Components/UI/ConfirmDialog';
import EmptyState from '../../../Components/UI/EmptyState';
import Modal, { ModalBody, ModalFooter } from '../../../Components/UI/Modal';
import { useModal } from '../../../hooks/useModal';
import { destroy, post } from '../../../lib/actions';
import { formatDate, formatMoney, formatNumber } from '../../../lib/format';
import { label } from '../../../lib/labels';
import { fillRoute } from '../../../lib/text';

const LEVEL_TEXT = {
    beginner: 'تازه شروع کرده‌ام و پروژه‌ی واقعی کمی دارم',
    junior: 'چند پروژه‌ی کوچک انجام داده‌ام',
    intermediate: 'مستقل پروژه‌ی متوسط تحویل می‌دهم',
    senior: 'پروژه‌ی بزرگ و تیمی را هدایت می‌کنم',
};

function levelIndex(levels, level) {
    return levels.indexOf(level);
}

function FieldForm({ modal, categories, taken, levels, rules, isFirst, routes }) {
    return (
        <ModalForm
            open={modal.open}
            onClose={modal.close}
            title="ثبت حوزه‌ی کاری"
            subtitle="فقط برای پروژه‌های حوزه‌های فعال می‌توانی پیشنهاد بفرستی."
            icon="bi-bullseye"
            size="lg"
            method="post"
            url={routes.store}
            initial={{ category_id: '', claimed_level: 'beginner' }}
            submitLabel="ثبت حوزه"
        >
            {(form) => {
                const examByLevel = levelIndex(levels, form.data.claimed_level) >= levelIndex(levels, rules.exam_from_level);
                const examRequired = !isFirst || examByLevel;

                return (
                    <div className="d-grid gap-3">
                        <Select
                            form={form}
                            name="category_id"
                            label="حوزه"
                            required
                            options={categories.map((category) => ({ value: category.id, label: category.name, disabled: taken.includes(category.id) }))}
                        />
                        <RadioCards
                            form={form}
                            name="claimed_level"
                            label="سطح تو در این حوزه"
                            columns={2}
                            options={levels.map((level) => ({ value: level, label: label('level', level), description: LEVEL_TEXT[level] }))}
                        />
                        <div className={`alert ${examRequired ? 'alert-warning' : 'alert-success'} d-flex gap-2 mb-0`}>
                            <i className={`bi ${examRequired ? 'bi-clipboard-check' : 'bi-lightning-charge'} mt-1`} />
                            <div className="small">
                                {examRequired ? (
                                    <>
                                        <strong>آزمون ورودی لازم است.</strong>{' '}
                                        {isFirst
                                            ? `سطح «${label('level', rules.exam_from_level)}» و بالاتر آزمون دارد؛ آزمون حوزه‌ی اول رایگان است.`
                                            : `برای حوزه‌های بعدی آزمون لازم است؛ هزینه: ${rules.extra_field_fee === null || rules.extra_field_fee === undefined ? 'به‌زودی اعلام می‌شود' : formatMoney(rules.extra_field_fee)}.`}{' '}
                                        بعد از قبولی، حوزه فعال می‌شود.
                                    </>
                                ) : (
                                    <>
                                        <strong>بدون آزمون فعال می‌شود.</strong> می‌توانی همین حالا برای پروژه‌هایش پیشنهاد بفرستی.
                                    </>
                                )}
                            </div>
                        </div>
                    </div>
                );
            }}
        </ModalForm>
    );
}

function feeText(fee) {
    if (fee === null || fee === undefined) {
        return 'هزینه به‌زودی اعلام می‌شود';
    }

    return Number(fee) === 0 ? 'رایگان' : formatMoney(fee);
}

/** Rules of the exam page, shown before the attempt starts (and the fee is paid). */
function ExamIntro({ modal, routes }) {
    const exam = modal.record;
    const [busy, setBusy] = useState(false);

    const start = () => {
        setBusy(true);
        post(fillRoute(routes.start, exam.id), {}, { onFinish: () => setBusy(false) });
    };

    return (
        <Modal
            open={modal.open}
            onClose={modal.close}
            title={exam ? `آزمون «${exam.title}»` : ''}
            subtitle={exam?.skill ? `با قبولی، مهارت ${exam.skill.name} در پروفایلت تأییدشده نشان داده می‌شود.` : null}
            icon="bi-ui-checks"
            busy={busy}
            footer={
                <ModalFooter>
                    <button type="button" className="btn btn-ghost" onClick={modal.close} disabled={busy}>
                        بعداً
                    </button>
                    <button type="button" className="btn btn-primary" onClick={start} disabled={busy}>
                        {busy ? <span className="spinner-border spinner-border-sm" /> : <i className="bi bi-play-fill" />}
                        {exam && Number(exam.fee) > 0 ? `پرداخت ${formatMoney(exam.fee)} و شروع` : 'شروع آزمون'}
                    </button>
                </ModalFooter>
            }
        >
            {exam && (
                <ModalBody>
                    {exam.description && <p className="text-muted-2 small">{exam.description}</p>}
                    <div className="jl-exam-meta mb-3 fs-6">
                        <span>
                            <i className="bi bi-list-ol" /> {formatNumber(exam.questions_count)} سؤال
                        </span>
                        <span>
                            <i className="bi bi-stopwatch" /> {formatNumber(exam.time_limit_minutes)} دقیقه
                        </span>
                        <span>
                            <i className="bi bi-bullseye" /> قبولی با {formatNumber(exam.pass_score)} از {formatNumber(exam.total_score)}
                        </span>
                        <span>
                            <i className="bi bi-wallet2" /> {feeText(exam.fee)}
                        </span>
                    </div>
                    <div className="jl-callout is-warning">
                        <strong className="d-block mb-1">
                            <i className="bi bi-shield-exclamation" /> قوانین صفحه‌ی آزمون
                        </strong>
                        <ul className="mb-0 ps-3">
                            <li>زمان از همین لحظه شروع می‌شود و با تمام شدنش، جواب‌های ثبت‌شده تصحیح می‌شوند.</li>
                            <li>رفتن به تب یا برنامه‌ی دیگر یا کوچک کردن صفحه (روی کامپیوتر یا گوشی) بار اول فقط هشدار دارد؛ بار دوم آزمون باطل می‌شود{Number(exam.fee) > 0 ? ' و هزینه‌ی پرداختی برنمی‌گردد' : ''}.</li>
                            <li>کلیک راست، انتخاب متن و ابزار توسعه‌دهنده (Inspect) در صفحه‌ی آزمون بسته است.</li>
                            <li>اگر قبول نشدی، بلافاصله می‌توانی دوباره شرکت کنی{Number(exam.fee) > 0 ? ' (با پرداخت دوباره‌ی هزینه)' : ''}.</li>
                        </ul>
                    </div>
                </ModalBody>
            )}
        </Modal>
    );
}

function ExamItem({ exam, onStart, routes }) {
    const unavailable = exam.fee === null || exam.fee === undefined;
    const failed = exam.last_attempt && ['failed', 'voided'].includes(exam.last_attempt.status);

    return (
        <li className={`jl-exam-item ${exam.passed ? 'is-passed' : ''}`}>
            <div className="min-w-0 flex-grow-1">
                <div className="fw-semibold">
                    {exam.title}
                    {exam.skill && (
                        <Badge tone="secondary" className="ms-2">
                            <span className="ltr d-inline-block">{exam.skill.name}</span>
                        </Badge>
                    )}
                </div>
                <div className="jl-exam-meta mt-1">
                    <span>{formatNumber(exam.questions_count)} سؤال</span>
                    <span>{formatNumber(exam.time_limit_minutes)} دقیقه</span>
                    <span>
                        قبولی {formatNumber(exam.pass_score)} از {formatNumber(exam.total_score)}
                    </span>
                    <span>{feeText(exam.fee)}</span>
                    {failed && (
                        <Link href={fillRoute(routes.attempt, exam.last_attempt.id)} className="text-danger">
                            {exam.last_attempt.status === 'voided' ? 'آخرین تلاش باطل شد' : `آخرین نمره: ${formatNumber(exam.last_attempt.score ?? 0)}`}
                        </Link>
                    )}
                </div>
            </div>
            {exam.passed ? (
                <Badge tone="success" icon="bi-patch-check-fill">
                    قبول شدی
                </Badge>
            ) : exam.running_attempt_id ? (
                <Link href={fillRoute(routes.attempt, exam.running_attempt_id)} className="btn btn-accent btn-sm">
                    <i className="bi bi-play-fill" /> ادامه‌ی آزمون
                </Link>
            ) : (
                <button type="button" className="btn btn-primary btn-sm" onClick={() => onStart(exam)} disabled={unavailable}>
                    <i className={`bi ${failed ? 'bi-arrow-repeat' : 'bi-play-fill'}`} /> {failed ? 'شرکت دوباره' : 'شروع آزمون'}
                </button>
            )}
        </li>
    );
}

export default function Index({ fields, exams = [], skills = [], categories, levels, rules, routes }) {
    const creator = useModal();
    const intro = useModal();
    const taken = fields.map((field) => field.category_id);

    const remove = async (field) => {
        const ok = await confirm({
            title: `حذف حوزه‌ی «${field.category?.name}»؟`,
            message: 'دیگر نمی‌توانی برای پروژه‌های این حوزه پیشنهاد بفرستی. برای برگشت باید دوباره ثبتش کنی.',
            confirmLabel: 'حذف حوزه',
        });

        if (ok) {
            destroy(fillRoute(routes.destroy, field.id));
        }
    };

    return (
        <>
            <PageHeader
                title="حوزه‌ها و مهارت‌ها"
                description="حوزه‌ی کاری‌ات را ثبت کن؛ آزمون‌های مهارت همان حوزه همین‌جا ظاهر می‌شوند و با قبولی، مهارت در پروفایلت تأییدشده نشان داده می‌شود."
                primary={{ label: 'حوزه‌ی جدید', icon: 'bi-plus-lg', onClick: () => creator.show(null), disabled: taken.length >= categories.length }}
            />

            {fields.length === 0 ? (
                <EmptyState
                    icon="bi-bullseye"
                    title="هنوز حوزه‌ای ثبت نکرده‌ای"
                    description="اولین حوزه، حوزه‌ی اصلی توست و اگر سطحت پایین‌تر از حد آزمون باشد بلافاصله فعال می‌شود."
                    action={
                        <button type="button" className="btn btn-primary btn-sm" onClick={() => creator.show(null)}>
                            <i className="bi bi-plus-lg" /> ثبت حوزه‌ی اصلی
                        </button>
                    }
                />
            ) : (
                <>
                <section className="jl-card mb-3 jl-rise">
                    <div className="jl-card-body d-flex flex-wrap align-items-center gap-2">
                        <strong className="me-2">
                            <i className="bi bi-patch-check text-success" /> مهارت‌های تأییدشده‌ی من
                        </strong>
                        {skills.length > 0 ? (
                            skills.map((skill) => (
                                <Badge key={skill.id} tone="success" icon="bi-patch-check-fill">
                                    <span className="ltr d-inline-block">{skill.name}</span>
                                </Badge>
                            ))
                        ) : (
                            <span className="small text-muted">هنوز مهارتی تأیید نشده؛ در یکی از آزمون‌های زیر شرکت کن.</span>
                        )}
                    </div>
                </section>
                <div className="row g-3">
                    {fields.map((field, index) => {
                        const fieldExams = exams.filter((exam) => exam.field_id === field.category_id);

                        return (
                        <div className="col-lg-6" key={field.id}>
                            <article className={`jl-card h-100 jl-field-card is-${field.status} jl-rise jl-rise-${Math.min(index + 1, 8)}`}>
                                <div className="jl-card-body d-flex flex-column gap-2 h-100">
                                    <div className="d-flex align-items-start justify-content-between gap-2">
                                        <div>
                                            <h2 className="h6 fw-bold mb-1">{field.category?.name}</h2>
                                            <div className="d-flex flex-wrap gap-1">
                                                {field.is_primary && (
                                                    <Badge tone="accent" icon="bi-star-fill">
                                                        حوزه‌ی اصلی
                                                    </Badge>
                                                )}
                                                <StatusBadge group="level" value={field.claimed_level} />
                                            </div>
                                        </div>
                                        <StatusBadge group="fieldStatus" value={field.status} />
                                    </div>
                                    <div className="small text-muted">
                                        {field.status === 'active' && (field.verified_at ? `فعال از ${formatDate(field.verified_at)}` : 'فعال')}
                                        {field.status === 'pending_exam' && 'با قبولی در یکی از آزمون‌های مهارت این حوزه فعال می‌شود.'}
                                        {field.status === 'rejected' && 'در آزمون قبول نشدی؛ همین حالا می‌توانی دوباره شرکت کنی.'}
                                    </div>
                                    <div className="mt-2">
                                        <div className="small fw-bold mb-2">
                                            <i className="bi bi-ui-checks" /> آزمون‌های مهارت این حوزه
                                        </div>
                                        {fieldExams.length > 0 ? (
                                            <ul className="jl-exam-list">
                                                {fieldExams.map((exam) => (
                                                    <ExamItem key={exam.id} exam={exam} onStart={(record) => intro.show(record)} routes={routes} />
                                                ))}
                                            </ul>
                                        ) : (
                                            <p className="small text-muted mb-0">برای این حوزه هنوز آزمونی طراحی نشده است.</p>
                                        )}
                                    </div>
                                    {!field.is_primary && (
                                        <div className="mt-auto pt-2">
                                            <button type="button" className="btn btn-ghost btn-sm text-danger" onClick={() => remove(field)}>
                                                <i className="bi bi-trash3" /> حذف حوزه
                                            </button>
                                        </div>
                                    )}
                                </div>
                            </article>
                        </div>
                        );
                    })}
                </div>
                </>
            )}

            <ExamIntro modal={intro} routes={routes} />

            <FieldForm modal={creator} categories={categories} taken={taken} levels={levels} rules={rules} isFirst={fields.length === 0} routes={routes} />
        </>
    );
}
