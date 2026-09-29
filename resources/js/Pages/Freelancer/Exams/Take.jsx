import { Head, router } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import ConfirmHost, { confirm } from '../../../Components/UI/ConfirmDialog';
import Modal, { ModalBody } from '../../../Components/UI/Modal';
import Toaster, { toast } from '../../../Components/UI/Toaster';
import { formatMoney, formatNumber, toPersianDigits } from '../../../lib/format';
import { sendJson } from '../../../lib/http';

const LETTERS = ['الف', 'ب', 'ج', 'د', 'ه', 'و', 'ز', 'ح'];

// hidden + blur usually fire together for one "left the page": count them once.
const VIOLATION_DEBOUNCE_MS = 2000;
const DEVTOOLS_GAP_PX = 160;

function clock(seconds) {
    const safe = Math.max(0, seconds);
    const minutes = Math.floor(safe / 60);
    const rest = safe % 60;

    return toPersianDigits(`${String(minutes).padStart(2, '0')}:${String(rest).padStart(2, '0')}`);
}

/** Keys that open developer tools or the page source, save or print the page. */
function isBlockedKey(event) {
    const key = event.key?.toLowerCase();
    const mod = event.ctrlKey || event.metaKey;

    return (
        event.key === 'F12' ||
        (mod && event.shiftKey && ['i', 'j', 'c', 'k'].includes(key)) ||
        (event.metaKey && event.altKey && ['i', 'j', 'c', 'u'].includes(key)) ||
        (mod && ['u', 's', 'p'].includes(key))
    );
}

/**
 * The exam page: no panel chrome, one question at a time, a server-synced timer and autosaved
 * answers. Leaving the page (other tab or app, minimizing, developer tools) is reported: the first
 * time shows a warning, the second voids the attempt on the server.
 */
export default function Take({ exam, attempt, routes }) {
    const questions = exam.questions;
    const [answers, setAnswers] = useState(() => ({ ...attempt.answers }));
    const [current, setCurrent] = useState(() => Math.max(0, questions.findIndex((question) => !attempt.answers?.[question.id])));
    const [warnings, setWarnings] = useState(attempt.warnings);
    const [warningOpen, setWarningOpen] = useState(false);
    const [saving, setSaving] = useState('idle');
    const [submitting, setSubmitting] = useState(false);

    const offset = useMemo(() => Date.parse(attempt.now) - Date.now(), [attempt.now]);
    const deadline = useMemo(() => Date.parse(attempt.expires_at), [attempt.expires_at]);
    const remainingNow = () => Math.round((deadline - (Date.now() + offset)) / 1000);
    const [remaining, setRemaining] = useState(remainingNow);

    const finished = useRef(false);
    const lastViolation = useRef(0);
    const saveTimer = useRef(null);
    const pending = useRef({});
    const answersRef = useRef(answers);
    answersRef.current = answers;

    const answeredCount = questions.filter((question) => answers[question.id]).length;
    const question = questions[current];

    // Autosave ------------------------------------------------------------------------------------------------------------------------
    const flush = useCallback(async () => {
        window.clearTimeout(saveTimer.current);
        const batch = pending.current;
        pending.current = {};

        if (Object.keys(batch).length === 0) {
            return;
        }

        setSaving('saving');

        try {
            const response = await sendJson('put', routes.answers, { answers: batch });

            if (response.status === 409) {
                finished.current = true;
                router.visit(response.data?.redirect ?? routes.show);

                return;
            }

            setSaving(response.ok ? 'saved' : 'error');

            if (!response.ok) {
                pending.current = { ...batch, ...pending.current };
            }
        } catch {
            pending.current = { ...batch, ...pending.current };
            setSaving('error');
        }
    }, [routes]);

    const choose = (questionId, optionId) => {
        setAnswers((previous) => ({ ...previous, [questionId]: optionId }));
        pending.current[questionId] = optionId;
        window.clearTimeout(saveTimer.current);
        saveTimer.current = window.setTimeout(flush, 600);
    };

    // Submit ----------------------------------------------------------------------------------------------------------------------------
    const submit = useCallback(
        (auto = false) => {
            if (finished.current) {
                return;
            }

            finished.current = true;
            window.clearTimeout(saveTimer.current);
            setSubmitting(true);

            if (auto) {
                toast.info('وقت آزمون تمام شد؛ جواب‌هایت ثبت و تصحیح می‌شود.');
            }

            router.post(
                routes.submit,
                { answers: answersRef.current },
                {
                    onError: () => {
                        finished.current = false;
                        setSubmitting(false);
                    },
                    onNetworkError: () => {
                        finished.current = false;
                        setSubmitting(false);
                    },
                },
            );
        },
        [routes],
    );

    const confirmSubmit = async () => {
        const missing = questions.length - answeredCount;
        const ok = await confirm({
            title: 'پایان آزمون و تصحیح؟',
            message: missing > 0 ? `${formatNumber(missing)} سؤال بی‌جواب مانده است. بعد از ثبت، دیگر نمی‌توانی جواب‌ها را عوض کنی.` : 'بعد از ثبت، دیگر نمی‌توانی جواب‌ها را عوض کنی.',
            confirmLabel: 'ثبت و پایان آزمون',
            tone: 'primary',
        });

        if (ok) {
            submit(false);
        }
    };

    // Timer -----------------------------------------------------------------------------------------------------------------------------
    useEffect(() => {
        const timer = window.setInterval(() => {
            const left = remainingNow();
            setRemaining(left);

            if (left <= 0) {
                window.clearInterval(timer);
                submit(true);
            }
        }, 1000);

        return () => window.clearInterval(timer);
    }, [submit]);

    // Leaving the page ------------------------------------------------------------------------------------------------------------------
    const report = useCallback(
        async (reason) => {
            const now = Date.now();

            if (finished.current || now - lastViolation.current < VIOLATION_DEBOUNCE_MS) {
                return;
            }

            lastViolation.current = now;

            try {
                const response = await sendJson('post', routes.violation, { reason }, { keepalive: true });

                if (!response.ok || !response.data) {
                    return;
                }

                setWarnings(response.data.warnings);

                if (response.data.voided) {
                    finished.current = true;
                    router.visit(response.data.redirect ?? routes.show);
                } else {
                    setWarningOpen(true);
                }
            } catch {
                // Offline: the next report (or the submit) still reaches the server.
            }
        },
        [routes],
    );

    useEffect(() => {
        const onVisibility = () => document.visibilityState === 'hidden' && report('hidden');
        const onBlur = () => report('blur');
        const block = (event) => event.preventDefault();
        const onKey = (event) => {
            if (isBlockedKey(event)) {
                event.preventDefault();
                event.stopPropagation();
            }
        };
        const onBeforeUnload = (event) => {
            if (!finished.current) {
                event.preventDefault();
                event.returnValue = '';
            }
        };

        document.addEventListener('visibilitychange', onVisibility);
        window.addEventListener('blur', onBlur);
        document.addEventListener('contextmenu', block);
        document.addEventListener('copy', block);
        document.addEventListener('cut', block);
        document.addEventListener('dragstart', block);
        document.addEventListener('selectstart', block);
        window.addEventListener('keydown', onKey, true);
        window.addEventListener('beforeunload', onBeforeUnload);

        // Docked developer tools shrink the page inside the window (desktop only).
        const coarse = window.matchMedia?.('(pointer: coarse)').matches;
        const gap = () => Math.max(window.outerWidth - window.innerWidth, window.outerHeight - window.innerHeight);
        const baseline = gap();
        let devtoolsOpen = false;
        const devtools = coarse
            ? null
            : window.setInterval(() => {
                  const open = gap() - baseline > DEVTOOLS_GAP_PX;

                  if (open && !devtoolsOpen) {
                      report('devtools');
                  }

                  devtoolsOpen = open;
              }, 1000);

        return () => {
            document.removeEventListener('visibilitychange', onVisibility);
            window.removeEventListener('blur', onBlur);
            document.removeEventListener('contextmenu', block);
            document.removeEventListener('copy', block);
            document.removeEventListener('cut', block);
            document.removeEventListener('dragstart', block);
            document.removeEventListener('selectstart', block);
            window.removeEventListener('keydown', onKey, true);
            window.removeEventListener('beforeunload', onBeforeUnload);
            window.clearInterval(devtools);
        };
    }, [report]);

    const low = remaining <= 60;

    return (
        <div className="jl-exam" onContextMenu={(event) => event.preventDefault()}>
            <Head title={`آزمون ${exam.title}`} />

            <header className="jl-exam-top">
                <div className="min-w-0 flex-grow-1">
                    <div className="fw-bold text-truncate">{exam.title}</div>
                    <div className="small text-muted">
                        {formatNumber(answeredCount)} از {formatNumber(questions.length)} جواب داده شده
                        {saving === 'saving' && ' · در حال ذخیره…'}
                        {saving === 'saved' && ' · ذخیره شد'}
                        {saving === 'error' && <span className="text-danger"> · ذخیره نشد، دوباره تلاش می‌شود</span>}
                    </div>
                </div>
                {warnings > 0 && (
                    <span className="jl-badge is-danger no-dot d-none d-sm-inline-flex" data-tip="یک بار دیگر صفحه را ترک کنی، آزمون باطل می‌شود.">
                        <i className="bi bi-exclamation-triangle-fill" /> {formatNumber(warnings)} هشدار
                    </span>
                )}
                <span className={`jl-exam-timer ${low ? 'is-low' : ''}`} role="timer" aria-label="زمان باقی‌مانده">
                    <i className="bi bi-stopwatch" />
                    {clock(remaining)}
                </span>
                <button type="button" className="btn btn-primary btn-sm" onClick={confirmSubmit} disabled={submitting}>
                    {submitting ? <span className="spinner-border spinner-border-sm" /> : <i className="bi bi-check2-all" />}
                    <span className="d-none d-sm-inline">پایان آزمون</span>
                </button>
            </header>

            <main className="jl-exam-main">
                <nav className="jl-exam-dots mb-3" aria-label="سؤال‌ها">
                    {questions.map((item, index) => (
                        <button
                            key={item.id}
                            type="button"
                            className={`${answers[item.id] ? 'is-answered' : ''} ${index === current ? 'is-current' : ''}`}
                            onClick={() => setCurrent(index)}
                            aria-label={`سؤال ${index + 1}${answers[item.id] ? ' (جواب داده شده)' : ''}`}
                            aria-current={index === current ? 'step' : undefined}
                        >
                            {formatNumber(index + 1)}
                        </button>
                    ))}
                </nav>

                {question && (
                    <section className="jl-card" key={question.id}>
                        <div className="jl-card-body pt-3 d-grid gap-3">
                            <div className="small text-muted">
                                سؤال {formatNumber(current + 1)} از {formatNumber(questions.length)}
                            </div>
                            <div className="jl-exam-question">{question.body}</div>
                            {question.hint && (
                                <div className="jl-exam-hint">
                                    <i className="bi bi-lightbulb" />
                                    <span>{question.hint}</span>
                                </div>
                            )}
                            <div className="d-grid gap-2" role="radiogroup" aria-label="گزینه‌ها">
                                {question.options.map((option, position) => {
                                    const selected = Number(answers[question.id]) === option.id;

                                    return (
                                        <button
                                            key={option.id}
                                            type="button"
                                            role="radio"
                                            aria-checked={selected}
                                            className={`jl-exam-choice ${selected ? 'is-selected' : ''}`}
                                            onClick={() => choose(question.id, option.id)}
                                            disabled={submitting}
                                        >
                                            <span className="jl-exam-letter">{LETTERS[position]}</span>
                                            <span>{option.body}</span>
                                        </button>
                                    );
                                })}
                            </div>
                        </div>
                    </section>
                )}
            </main>

            <footer className="jl-exam-nav">
                <div>
                    <button type="button" className="btn btn-ghost" onClick={() => setCurrent(current - 1)} disabled={current === 0}>
                        <i className="bi bi-arrow-right" /> قبلی
                    </button>
                    <div className="flex-grow-1 text-center small text-muted d-none d-sm-block">
                        قبولی با {formatNumber(exam.pass_score)} از {formatNumber(exam.total_score)}
                    </div>
                    {current < questions.length - 1 ? (
                        <button type="button" className="btn btn-soft ms-auto" onClick={() => setCurrent(current + 1)}>
                            بعدی <i className="bi bi-arrow-left" />
                        </button>
                    ) : (
                        <button type="button" className="btn btn-primary ms-auto" onClick={confirmSubmit} disabled={submitting}>
                            <i className="bi bi-check2-all" /> ثبت و پایان
                        </button>
                    )}
                </div>
            </footer>

            <Modal open={warningOpen} onClose={() => setWarningOpen(false)} title="هشدار: صفحه‌ی آزمون را ترک کردی" icon="bi-exclamation-octagon" tone="danger" closeOnBackdrop={false}>
                <ModalBody>
                    <p className="mb-2">رفتن به تب یا برنامه‌ی دیگر، کوچک کردن صفحه یا باز کردن ابزار توسعه‌دهنده در آزمون مجاز نیست.</p>
                    <p className="mb-3 fw-semibold text-danger">
                        این هشدار {formatNumber(warnings)} از {formatNumber(attempt.max_warnings - 1)} بود؛ یک بار دیگر تکرار شود، آزمون باطل می‌شود
                        {attempt.fee_amount > 0 ? ` و ${formatMoney(attempt.fee_amount)} هزینه‌ی آزمون برنمی‌گردد` : ''}.
                    </p>
                    <button type="button" className="btn btn-primary w-100" onClick={() => setWarningOpen(false)}>
                        متوجه شدم، ادامه می‌دهم
                    </button>
                </ModalBody>
            </Modal>

            <Toaster />
            <ConfirmHost />
        </div>
    );
}
