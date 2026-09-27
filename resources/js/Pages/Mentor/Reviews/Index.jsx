import ModalForm from '../../../Components/Form/ModalForm';
import Textarea from '../../../Components/Form/Textarea';
import PageHeader from '../../../Components/Panel/PageHeader';
import Pagination from '../../../Components/Panel/Pagination';
import { Person } from '../../../Components/UI/Avatar';
import { StatusBadge } from '../../../Components/UI/Badge';
import EmptyState from '../../../Components/UI/EmptyState';
import Meter from '../../../Components/UI/Meter';
import Tabs from '../../../Components/UI/Tabs';
import { useFilters } from '../../../hooks/useFilters';
import { useModal } from '../../../hooks/useModal';
import { formatMoney, formatNumber, formatRelative } from '../../../lib/format';
import { label } from '../../../lib/labels';
import { budgetText } from '../../../lib/project';
import { fillRoute } from '../../../lib/text';

const TIPS = ['آیا نیاز کارفرما را با کلمات خودش تکرار کرده؟', 'قیمت و زمان تحویل با بودجه و پیچیدگی کار می‌خواند؟', 'نمونه‌کار یا تجربه‌ی مرتبطی آورده؟', 'لحن محترمانه و بدون وعده‌ی غیرواقعی است؟'];

function FeedbackForm({ modal, routes }) {
    const proposal = modal.record;

    return (
        <ModalForm
            open={modal.open}
            onClose={modal.close}
            title="بازخورد منتور"
            subtitle={proposal ? `پیشنهاد ${proposal.freelancer?.name} برای «${proposal.project?.title}»` : null}
            icon="bi-chat-square-quote"
            size="lg"
            method="put"
            url={proposal ? fillRoute(routes.update, proposal.id) : ''}
            initial={{ mentor_feedback: proposal?.mentor_feedback ?? '' }}
            submitLabel="ارسال بازخورد"
            submitIcon="bi-send"
        >
            {(form) =>
                proposal && (
                    <div className="d-grid gap-3">
                        <div className="jl-text-block">{proposal.cover_letter}</div>
                        <div className="small text-muted">
                            قیمت پیشنهادی {formatMoney(proposal.proposed_price)} · تحویل در {formatNumber(proposal.delivery_days)} روز · بودجه‌ی کارفرما {budgetText(proposal.project)}
                        </div>
                        <details className="small">
                            <summary className="text-primary-emphasis-soft">چک‌لیست یک پیشنهاد خوب</summary>
                            <ul className="mt-2 mb-0">
                                {TIPS.map((tip) => (
                                    <li key={tip}>{tip}</li>
                                ))}
                            </ul>
                        </details>
                        <Textarea
                            form={form}
                            name="mentor_feedback"
                            label="بازخورد تو"
                            required
                            rows={6}
                            minLength={10}
                            maxLength={2000}
                            placeholder="نقاط قوت را بگو و یکی دو پیشنهاد مشخص برای بهتر شدن بده."
                        />
                    </div>
                )
            }
        </ModalForm>
    );
}

export default function Index({ tab, proposals, counts, routes }) {
    const filters = useFilters({ tab });
    const reviewer = useModal();
    const current = filters.filters.tab ?? 'pending';

    return (
        <>
            <PageHeader title="بازبینی پیشنهادها" description="پیشنهادهای منتی‌ها و پروژه‌هایی که ناظرشان هستی؛ قبل از تصمیم کارفرما بازخورد بده." />

            <Tabs
                className="mb-3"
                items={[
                    { key: 'pending', label: 'منتظر بازخورد', icon: 'bi-hourglass-split', count: counts.pending },
                    { key: 'reviewed', label: 'بازبینی‌شده', icon: 'bi-check2-circle', count: counts.reviewed },
                ]}
                active={current}
                onChange={(next) => filters.apply({ tab: next })}
            />

            {proposals.data.length === 0 ? (
                <EmptyState icon="bi-chat-square-quote" title={current === 'pending' ? 'پیشنهادی منتظر بازخورد نیست' : 'هنوز بازخوردی نداده‌ای'} />
            ) : (
                <div className="row g-3">
                    {proposals.data.map((proposal, index) => (
                        <div className="col-xl-6" key={proposal.id}>
                            <article className={`jl-card h-100 jl-rise jl-rise-${Math.min(index + 1, 8)}`}>
                                <div className="jl-card-body d-flex flex-column h-100 gap-3">
                                    <div className="d-flex align-items-start justify-content-between gap-2">
                                        <Person user={proposal.freelancer} meta={proposal.freelancer?.level ? label('level', proposal.freelancer.level) : null} />
                                        <StatusBadge group="proposalStatus" value={proposal.status} />
                                    </div>
                                    {proposal.freelancer?.readiness_score !== null && proposal.freelancer?.readiness_score !== undefined && (
                                        <div>
                                            <div className="d-flex justify-content-between small mb-1">
                                                <span className="text-muted">آمادگی فریلنسر</span>
                                                <strong>{formatNumber(proposal.freelancer.readiness_score)}٪</strong>
                                            </div>
                                            <Meter value={proposal.freelancer.readiness_score} tone="accent" />
                                        </div>
                                    )}
                                    <div>
                                        <div className="fw-semibold">{proposal.project?.title}</div>
                                        <div className="small text-muted">
                                            {proposal.project?.employer?.name} · بودجه {budgetText(proposal.project)} · {formatRelative(proposal.created_at)}
                                        </div>
                                    </div>
                                    <p className="small text-muted-2 jl-clamp-3 mb-0">{proposal.cover_letter}</p>
                                    <div className="d-flex flex-wrap gap-2 small">
                                        <span className="jl-chip">
                                            <i className="bi bi-cash" /> {formatMoney(proposal.proposed_price)}
                                        </span>
                                        <span className="jl-chip">
                                            <i className="bi bi-clock" /> {formatNumber(proposal.delivery_days)} روز
                                        </span>
                                    </div>
                                    {proposal.mentor_feedback && (
                                        <div className="jl-feedback">
                                            <i className="bi bi-quote" />
                                            <div>
                                                <div className="small">{proposal.mentor_feedback}</div>
                                                {proposal.mentor_reviewer && <div className="small text-muted mt-1">— {proposal.mentor_reviewer.name}</div>}
                                            </div>
                                        </div>
                                    )}
                                    <div className="mt-auto">
                                        <button type="button" className={`btn btn-sm ${proposal.mentor_feedback ? 'btn-soft' : 'btn-primary'}`} onClick={() => reviewer.show(proposal)}>
                                            <i className="bi bi-chat-square-quote" /> {proposal.mentor_feedback ? 'ویرایش بازخورد' : 'نوشتن بازخورد'}
                                        </button>
                                    </div>
                                </div>
                            </article>
                        </div>
                    ))}
                </div>
            )}

            {proposals.meta?.last_page > 1 && (
                <div className="jl-card mt-3">
                    <Pagination meta={proposals.meta} />
                </div>
            )}

            <FeedbackForm modal={reviewer} routes={routes} />
        </>
    );
}
