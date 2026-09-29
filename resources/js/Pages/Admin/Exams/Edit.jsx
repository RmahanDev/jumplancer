import { Link, useForm } from '@inertiajs/react';
import { useMemo, useRef, useState } from 'react';
import NumberInput from '../../../Components/Form/NumberInput';
import Select from '../../../Components/Form/Select';
import Switch from '../../../Components/Form/Switch';
import TextInput from '../../../Components/Form/TextInput';
import Textarea from '../../../Components/Form/Textarea';
import PageHeader from '../../../Components/Panel/PageHeader';
import { confirm } from '../../../Components/UI/ConfirmDialog';
import { toast } from '../../../Components/UI/Toaster';
import { formatNumber } from '../../../lib/format';

const STEPS = [
    { key: 'skill', label: 'مهارت', icon: 'bi-bullseye' },
    { key: 'settings', label: 'تنظیمات', icon: 'bi-sliders' },
    { key: 'questions', label: 'سؤال‌ها', icon: 'bi-ui-checks' },
];

// Which step shows the field of a validation error.
const FIELD_STEP = {
    title: 0,
    description: 0,
    category_id: 0,
    skill_id: 0,
    time_limit_minutes: 1,
    total_score: 1,
    pass_score: 1,
    is_active: 1,
    questions: 2,
};

const LETTERS = ['الف', 'ب', 'ج', 'د', 'ه', 'و', 'ز', 'ح'];

let uid = 0;
const key = () => `k${++uid}`;

const blankOption = () => ({ key: key(), body: '' });
const blankQuestion = () => ({ key: key(), body: '', hint: '', options: [blankOption(), blankOption(), blankOption(), blankOption()], correct: null });

function initialData(exam) {
    return {
        title: exam?.title ?? '',
        description: exam?.description ?? '',
        category_id: exam?.category_id ?? '',
        skill_id: exam?.skill_id ?? '',
        time_limit_minutes: exam?.time_limit_minutes ?? 20,
        total_score: exam?.total_score ?? 100,
        pass_score: exam?.pass_score ?? 60,
        is_active: exam?.is_active ?? true,
        questions: exam?.questions?.length
            ? exam.questions.map((question) => ({
                  key: key(),
                  body: question.body,
                  hint: question.hint ?? '',
                  options: question.options.map((option) => ({ key: key(), body: option.body })),
                  correct: question.correct === false ? null : question.correct,
              }))
            : [blankQuestion()],
    };
}

/** Top-level field of the category (skills sit in sub-categories). */
function fieldOf(categories, categoryId) {
    return categories.find((category) => category.id === Number(categoryId) || category.children?.some((child) => child.id === Number(categoryId)));
}

function QuestionCard({ question, index, count, errors, limits, onChange, onRemove, onMove, onDuplicate }) {
    const prefix = `questions.${index}`;
    const error = (name) => errors[`${prefix}.${name}`];
    const optionsError = errors[`${prefix}.options`];
    const [hintOpen, setHintOpen] = useState(Boolean(question.hint));

    const setOption = (position, body) => onChange({ ...question, options: question.options.map((option, i) => (i === position ? { ...option, body } : option)) });

    const removeOption = (position) => {
        const correct = question.correct === position ? null : question.correct !== null && question.correct > position ? question.correct - 1 : question.correct;
        onChange({ ...question, options: question.options.filter((_, i) => i !== position), correct });
    };

    const invalid = Object.keys(errors).some((name) => name === prefix || name.startsWith(`${prefix}.`));

    return (
        <article className={`jl-card jl-question ${invalid ? 'is-invalid' : ''}`} id={`question-${index}`}>
            <header className="jl-question-head">
                <span className="jl-question-number">{formatNumber(index + 1)}</span>
                <strong className="flex-grow-1">سؤال {formatNumber(index + 1)}</strong>
                <div className="d-flex gap-1">
                    <button type="button" className="jl-icon-btn" onClick={() => onMove(-1)} disabled={index === 0} aria-label="انتقال به بالا" data-tip="بالاتر">
                        <i className="bi bi-arrow-up" />
                    </button>
                    <button type="button" className="jl-icon-btn" onClick={() => onMove(1)} disabled={index === count - 1} aria-label="انتقال به پایین" data-tip="پایین‌تر">
                        <i className="bi bi-arrow-down" />
                    </button>
                    <button type="button" className="jl-icon-btn" onClick={onDuplicate} aria-label="تکثیر سؤال" data-tip="تکثیر">
                        <i className="bi bi-copy" />
                    </button>
                    <button type="button" className="jl-icon-btn text-danger" onClick={onRemove} disabled={count === 1} aria-label={`حذف سؤال ${index + 1}`} data-tip="حذف">
                        <i className="bi bi-trash3" />
                    </button>
                </div>
            </header>

            <div className="jl-card-body d-grid gap-3">
                <Textarea label="متن سؤال" required rows={2} maxLength={2000} value={question.body} onChange={(body) => onChange({ ...question, body })} error={error('body')} placeholder="سؤال را بنویس..." />

                {hintOpen ? (
                    <Textarea
                        label="راهنمایی (اختیاری)"
                        rows={2}
                        maxLength={1000}
                        value={question.hint}
                        onChange={(hint) => onChange({ ...question, hint })}
                        error={error('hint')}
                        hint="زیر سؤال به فریلنسر نشان داده می‌شود؛ جواب را لو نده."
                    />
                ) : (
                    <div>
                        <button type="button" className="btn btn-ghost btn-sm" onClick={() => setHintOpen(true)}>
                            <i className="bi bi-lightbulb" /> افزودن راهنمایی
                        </button>
                    </div>
                )}

                <fieldset>
                    <legend className="form-label jl-required d-flex align-items-center justify-content-between gap-2 w-100">
                        <span>گزینه‌ها</span>
                        <span className="small text-muted fw-normal">
                            {formatNumber(question.options.length)} از حداکثر {formatNumber(limits.maxOptions)} · گزینه‌ی درست را علامت بزن
                        </span>
                    </legend>
                    <div className="d-grid gap-2">
                        {question.options.map((option, position) => {
                            const optionError = error(`options.${position}.body`);
                            const isCorrect = question.correct === position;

                            return (
                                <div key={option.key}>
                                    <div className={`jl-option ${isCorrect ? 'is-correct' : ''}`}>
                                        <label className="jl-option-mark" data-tip={isCorrect ? 'گزینه‌ی درست' : 'انتخاب به‌عنوان گزینه‌ی درست'}>
                                            <input
                                                type="radio"
                                                className="form-check-input"
                                                name={`correct-${question.key}`}
                                                checked={isCorrect}
                                                onChange={() => onChange({ ...question, correct: position })}
                                                aria-label={`گزینه‌ی ${LETTERS[position]} درست است`}
                                            />
                                            <span>{LETTERS[position]}</span>
                                        </label>
                                        <input
                                            type="text"
                                            className={`form-control ${optionError ? 'is-invalid' : ''}`}
                                            value={option.body}
                                            maxLength={500}
                                            onChange={(event) => setOption(position, event.target.value)}
                                            placeholder={`گزینه‌ی ${LETTERS[position]}`}
                                            aria-label={`متن گزینه‌ی ${LETTERS[position]}`}
                                            aria-invalid={Boolean(optionError)}
                                        />
                                        <button
                                            type="button"
                                            className="jl-icon-btn"
                                            onClick={() => removeOption(position)}
                                            disabled={question.options.length <= limits.minOptions}
                                            aria-label={`حذف گزینه‌ی ${LETTERS[position]}`}
                                            data-tip="حذف گزینه"
                                        >
                                            <i className="bi bi-x-lg" />
                                        </button>
                                    </div>
                                    {optionError && <div className="invalid-feedback d-block">{optionError}</div>}
                                </div>
                            );
                        })}
                    </div>
                    {question.options.length < limits.maxOptions && (
                        <button type="button" className="btn btn-soft btn-sm mt-2" onClick={() => onChange({ ...question, options: [...question.options, blankOption()] })}>
                            <i className="bi bi-plus-lg" /> گزینه‌ی جدید
                        </button>
                    )}
                    {(error('correct') || optionsError) && <div className="invalid-feedback d-block">{error('correct') ?? optionsError}</div>}
                </fieldset>
            </div>
        </article>
    );
}

export default function Edit({ exam, categories, skills, limits, routes }) {
    const form = useForm(initialData(exam));
    const [step, setStep] = useState(0);
    const bottom = useRef(null);
    const { data } = form;

    const field = fieldOf(categories, data.category_id);
    const skillOptions = useMemo(() => {
        if (!field) {
            return [];
        }

        const groups = [field, ...(field.children ?? [])];

        return skills
            .filter((skill) => groups.some((category) => category.id === skill.category_id))
            .map((skill) => ({ value: skill.id, label: skill.name, group: groups.find((category) => category.id === skill.category_id)?.name }));
    }, [field, skills]);

    const questionCount = data.questions.length;
    const perQuestion = questionCount > 0 && Number(data.total_score) > 0 ? Number(data.total_score) / questionCount : 0;
    const passCount = perQuestion > 0 ? Math.ceil(Number(data.pass_score) / perQuestion) : 0;

    const set = (name, value) => {
        form.setData(name, value);
        form.clearErrors(name);
    };

    const setQuestions = (questions) => form.setData('questions', questions);

    const updateQuestion = (index, question) => {
        setQuestions(data.questions.map((current, i) => (i === index ? question : current)));

        const stale = Object.keys(form.errors).filter((name) => name.startsWith(`questions.${index}.`) || name === 'questions');

        if (stale.length > 0) {
            form.clearErrors(...stale);
        }
    };

    const addQuestion = () => {
        setQuestions([...data.questions, blankQuestion()]);
        window.setTimeout(() => bottom.current?.scrollIntoView({ behavior: 'smooth', block: 'end' }), 30);
    };

    const removeQuestion = async (index) => {
        const question = data.questions[index];

        if (question.body.trim() !== '' || question.options.some((option) => option.body.trim() !== '')) {
            const ok = await confirm({ title: `حذف سؤال ${formatNumber(index + 1)}؟`, message: 'متن سؤال و گزینه‌هایش پاک می‌شود.', confirmLabel: 'حذف سؤال' });

            if (!ok) {
                return;
            }
        }

        form.clearErrors();
        setQuestions(data.questions.filter((_, i) => i !== index));
    };

    const moveQuestion = (index, direction) => {
        const next = [...data.questions];
        const target = index + direction;
        [next[index], next[target]] = [next[target], next[index]];
        form.clearErrors();
        setQuestions(next);
    };

    const duplicateQuestion = (index) => {
        const source = data.questions[index];
        const copy = { ...source, key: key(), options: source.options.map((option) => ({ ...option, key: key() })) };
        setQuestions([...data.questions.slice(0, index + 1), copy, ...data.questions.slice(index + 1)]);
    };

    // A quick check before leaving a step, so obvious gaps are caught before the server round trip.
    const stepErrors = (index) => {
        const errors = {};

        if (index === 0) {
            if (data.title.trim().length < 3) errors.title = 'عنوان آزمون را بنویس (حداقل ۳ حرف).';
            if (!data.category_id) errors.category_id = 'حوزه‌ی آزمون را انتخاب کن.';
            if (!data.skill_id) errors.skill_id = 'مهارتی را که این آزمون تأییدش می‌کند انتخاب کن.';
        }

        if (index === 1) {
            const total = Number(data.total_score);
            const pass = Number(data.pass_score);

            if (!Number(data.time_limit_minutes)) errors.time_limit_minutes = 'زمان آزمون را بنویس.';
            else if (Number(data.time_limit_minutes) > 300) errors.time_limit_minutes = 'زمان آزمون حداکثر ۳۰۰ دقیقه است.';
            if (!total) errors.total_score = 'جمع نمرات را بنویس.';
            else if (total > 1000) errors.total_score = 'جمع نمرات حداکثر ۱۰۰۰ است.';
            if (!pass) errors.pass_score = 'حداقل نمره‌ی قبولی را بنویس.';
            else if (total && pass >= total) errors.pass_score = 'حداقل نمره‌ی قبولی باید از جمع نمرات کمتر باشد.';
        }

        return errors;
    };

    const goTo = (target) => {
        for (let index = 0; index < target; index++) {
            const errors = stepErrors(index);

            if (Object.keys(errors).length > 0) {
                form.setError(errors);
                setStep(index);

                return;
            }
        }

        setStep(target);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    const submit = (event) => {
        event.preventDefault();

        if (form.processing) {
            return;
        }

        const local = { ...stepErrors(0), ...stepErrors(1) };

        if (Object.keys(local).length > 0) {
            goTo(2);

            return;
        }

        form.transform((values) => ({
            ...values,
            category_id: Number(values.category_id),
            skill_id: Number(values.skill_id),
            time_limit_minutes: Number(values.time_limit_minutes),
            total_score: Number(values.total_score),
            pass_score: Number(values.pass_score),
            questions: values.questions.map((question) => ({
                body: question.body,
                hint: question.hint.trim() === '' ? null : question.hint,
                options: question.options.map((option) => ({ body: option.body })),
                correct: question.correct,
            })),
        }));

        form[exam ? 'put' : 'post'](routes.save, {
            onError: (errors) => {
                const names = Object.keys(errors);
                const general = names.find((name) => !(name.split('.')[0] in FIELD_STEP));

                if (general) {
                    toast.error(errors[general]);
                }

                const target = Math.min(...names.map((name) => FIELD_STEP[name.split('.')[0]] ?? 2));
                setStep(target);

                const question = names.map((name) => name.match(/^questions\.(\d+)/)?.[1]).find((match) => match !== undefined);

                window.setTimeout(() => {
                    const node = target === 2 && question !== undefined ? document.getElementById(`question-${question}`) : document.querySelector('.jl-exam-builder .is-invalid');
                    node?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }, 60);
            },
        });
    };

    const stepHasError = (index) => Object.keys(form.errors).some((name) => (FIELD_STEP[name.split('.')[0]] ?? 2) === index);

    return (
        <form onSubmit={submit} className="jl-exam-builder" noValidate>
            <PageHeader
                title={exam ? `ویرایش آزمون «${exam.title}»` : 'افزودن آزمون'}
                description="مهارت را انتخاب کن، تنظیمات نمره و زمان را بگذار و هر تعداد سؤال چهارگزینه‌ای (۲ تا ۸ گزینه) که می‌خواهی بنویس."
                actions={
                    <Link href={routes.index} className="btn btn-ghost">
                        <i className="bi bi-arrow-right" /> بازگشت به آزمون‌ها
                    </Link>
                }
            />

            <div className="jl-card mb-3 jl-rise">
                <div className="jl-card-body">
                    <ol className="jl-steps jl-steps-nav">
                        {STEPS.map((item, index) => (
                            <li key={item.key} className={`${index < step ? 'is-done' : index === step ? 'is-current' : ''} ${stepHasError(index) ? 'has-error' : ''}`}>
                                <button type="button" onClick={() => (index <= step ? setStep(index) : goTo(index))} aria-current={index === step ? 'step' : undefined}>
                                    <span className="jl-steps-dot">{stepHasError(index) ? <i className="bi bi-exclamation" /> : index < step ? <i className="bi bi-check" /> : formatNumber(index + 1)}</span>
                                    {item.label}
                                </button>
                            </li>
                        ))}
                    </ol>
                </div>
            </div>

            {step === 0 && (
                <section className="jl-card jl-rise">
                    <div className="jl-card-header">
                        <h2 className="d-flex align-items-center gap-2">
                            <i className="bi bi-bullseye" /> حوزه و مهارت
                        </h2>
                    </div>
                    <div className="jl-card-body row g-3">
                        <Select
                            className="col-md-6"
                            label="حوزه (دسته‌بندی)"
                            required
                            value={field?.id ?? ''}
                            error={form.errors.category_id}
                            onChange={(value) => {
                                form.setData((current) => ({ ...current, category_id: value, skill_id: '' }));
                                form.clearErrors('category_id', 'skill_id');
                            }}
                            options={categories.map((category) => ({ value: category.id, label: category.name }))}
                        />
                        <Select
                            className="col-md-6"
                            label="مهارت"
                            required
                            value={data.skill_id}
                            error={form.errors.skill_id}
                            onChange={(value) => set('skill_id', value)}
                            options={skillOptions}
                            placeholder={field ? 'انتخاب مهارت' : 'اول حوزه را انتخاب کن'}
                            disabled={!field}
                            hint={field && skillOptions.length === 0 ? 'این حوزه هنوز مهارتی ندارد؛ از «دسته‌ها و مهارت‌ها» اضافه کن.' : 'قبولی در آزمون، همین مهارت را در پروفایل فریلنسر تأییدشده نشان می‌دهد.'}
                        />
                        <TextInput className="col-12" label="عنوان آزمون" required value={data.title} error={form.errors.title} onChange={(value) => set('title', value)} maxLength={200} placeholder="مثلاً آزمون مقدماتی لاراول" />
                        <Textarea className="col-12" label="توضیح برای فریلنسر" rows={3} maxLength={2000} value={data.description} error={form.errors.description} onChange={(value) => set('description', value)} hint="چه چیزهایی سنجیده می‌شود و برای آماده شدن چه بخواند." />
                    </div>
                </section>
            )}

            {step === 1 && (
                <section className="jl-card jl-rise">
                    <div className="jl-card-header">
                        <h2 className="d-flex align-items-center gap-2">
                            <i className="bi bi-sliders" /> تنظیمات آزمون
                        </h2>
                    </div>
                    <div className="jl-card-body row g-3">
                        <NumberInput className="col-md-4" label="زمان آزمون" unit="دقیقه" required value={data.time_limit_minutes} error={form.errors.time_limit_minutes} onChange={(value) => set('time_limit_minutes', value)} hint="بین ۱ تا ۳۰۰ دقیقه" />
                        <NumberInput
                            className="col-md-4"
                            label="جمع نمرات"
                            unit="نمره"
                            required
                            value={data.total_score}
                            error={form.errors.total_score}
                            onChange={(value) => {
                                set('total_score', value);
                                form.clearErrors('pass_score');
                            }}
                            hint="بین سؤال‌ها مساوی تقسیم می‌شود."
                        />
                        <NumberInput className="col-md-4" label="حداقل نمره‌ی قبولی" unit="نمره" required value={data.pass_score} error={form.errors.pass_score} onChange={(value) => set('pass_score', value)} hint="باید از جمع نمرات کمتر باشد." />
                        <div className="col-12">
                            <div className="jl-callout">
                                <i className="bi bi-calculator" />
                                <div className="small">
                                    {perQuestion > 0 ? (
                                        <>
                                            با {formatNumber(questionCount)} سؤال، هر سؤال {formatNumber(Math.round(perQuestion * 100) / 100)} نمره دارد و برای قبولی دست‌کم {formatNumber(Math.min(passCount, questionCount))} جواب درست لازم است.
                                        </>
                                    ) : (
                                        'جمع نمرات را بنویس تا نمره‌ی هر سؤال را ببینی.'
                                    )}
                                </div>
                            </div>
                        </div>
                        <Switch className="col-12" label="آزمون فعال باشد" description="آزمون غیرفعال به فریلنسرها نشان داده نمی‌شود." value={data.is_active} onChange={(value) => set('is_active', value)} />
                    </div>
                </section>
            )}

            {step === 2 && (
                <div className="d-grid gap-3">
                    {form.errors.questions && <div className="alert alert-danger mb-0">{form.errors.questions}</div>}
                    {data.questions.map((question, index) => (
                        <QuestionCard
                            key={question.key}
                            question={question}
                            index={index}
                            count={questionCount}
                            errors={form.errors}
                            limits={limits}
                            onChange={(next) => updateQuestion(index, next)}
                            onRemove={() => removeQuestion(index)}
                            onMove={(direction) => moveQuestion(index, direction)}
                            onDuplicate={() => duplicateQuestion(index)}
                        />
                    ))}
                    <button type="button" className="jl-add-question" onClick={addQuestion} ref={bottom}>
                        <i className="bi bi-plus-lg" />
                        سؤال جدید
                    </button>
                </div>
            )}

            <div className="jl-builder-bar">
                <div className="small text-muted d-none d-sm-block">
                    {formatNumber(questionCount)} سؤال · {formatNumber(data.time_limit_minutes || 0)} دقیقه · قبولی {formatNumber(data.pass_score || 0)} از {formatNumber(data.total_score || 0)}
                </div>
                <div className="d-flex gap-2 ms-auto">
                    {step > 0 && (
                        <button type="button" className="btn btn-ghost" onClick={() => setStep(step - 1)}>
                            <i className="bi bi-arrow-right" /> مرحله‌ی قبل
                        </button>
                    )}
                    {step < STEPS.length - 1 ? (
                        <button type="button" className="btn btn-primary" onClick={() => goTo(step + 1)}>
                            مرحله‌ی بعد <i className="bi bi-arrow-left" />
                        </button>
                    ) : (
                        <button type="submit" className="btn btn-primary" disabled={form.processing}>
                            {form.processing ? <span className="spinner-border spinner-border-sm" /> : <i className="bi bi-check2" />}
                            {exam ? 'ذخیره‌ی تغییرات' : 'ثبت آزمون'}
                        </button>
                    )}
                </div>
            </div>
        </form>
    );
}
