import { Link, router } from '@inertiajs/react';
import { ContractProgress, DepositNote, MilestoneList, OpenDisputeNote } from '../../../Components/Domain/Contract';
import DisputeForm from '../../../Components/Domain/DisputeForm';
import JalaliDateInput from '../../../Components/Form/JalaliDateInput';
import ModalForm from '../../../Components/Form/ModalForm';
import NumberInput from '../../../Components/Form/NumberInput';
import StarRating from '../../../Components/Form/StarRating';
import TextInput from '../../../Components/Form/TextInput';
import Textarea from '../../../Components/Form/Textarea';
import PageHeader from '../../../Components/Panel/PageHeader';
import Pagination from '../../../Components/Panel/Pagination';
import { Person } from '../../../Components/UI/Avatar';
import { Badge, StatusBadge } from '../../../Components/UI/Badge';
import { confirm } from '../../../Components/UI/ConfirmDialog';
import { DropdownDivider, DropdownItem } from '../../../Components/UI/Dropdown';
import EmptyState from '../../../Components/UI/EmptyState';
import RowActions from '../../../Components/UI/RowActions';
import Tabs from '../../../Components/UI/Tabs';
import { useFilters } from '../../../hooks/useFilters';
import { useModal } from '../../../hooks/useModal';
import { put } from '../../../lib/actions';
import { formatDate, formatMoney, formatNumber } from '../../../lib/format';
import { options } from '../../../lib/labels';
import { fillRoute } from '../../../lib/text';

function today() {
    const date = new Date();

    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

function remainingToPlan(contract) {
    const planned = (contract.milestones ?? []).filter((milestone) => milestone.status !== 'refunded').reduce((sum, milestone) => sum + Number(milestone.amount), 0);

    return Math.max(0, Number(contract.amount) - planned);
}

function MilestoneForm({ modal, routes }) {
    const contract = modal.record;
    const remaining = contract ? remainingToPlan(contract) : 0;

    return (
        <ModalForm
            open={modal.open}
            onClose={modal.close}
            title="مرحله‌ی جدید"
            subtitle={contract ? `${contract.project?.title} — ${formatMoney(remaining)} برای برنامه‌ریزی مانده` : null}
            icon="bi-signpost-2"
            size="lg"
            method="post"
            url={contract ? fillRoute(routes.milestoneStore, contract.id) : ''}
            initial={{ title: '', description: '', amount: remaining || '', due_date: '' }}
            transform={(data) => ({ ...data, amount: Number(data.amount || 0), due_date: data.due_date || null, description: data.description || null })}
            submitLabel="افزودن مرحله"
        >
            {(form) => (
                <div className="row g-3">
                    <TextInput form={form} name="title" label="عنوان مرحله" required className="col-12" maxLength={200} placeholder="مثلاً طراحی صفحه‌ی اصلی" />
                    <Textarea form={form} name="description" label="خروجی این مرحله" rows={3} maxLength={2000} className="col-12" placeholder="دقیقاً چه چیزی باید تحویل داده شود؟" />
                    <NumberInput form={form} name="amount" label="مبلغ" money required className="col-md-6" hint={`حداکثر ${formatMoney(remaining)}`} />
                    <JalaliDateInput form={form} name="due_date" label="سررسید" min={today()} className="col-md-6" />
                </div>
            )}
        </ModalForm>
    );
}

function ReviewForm({ modal, routes }) {
    const contract = modal.record;

    return (
        <ModalForm
            open={modal.open}
            onClose={modal.close}
            title={contract ? `نظر درباره‌ی ${contract.freelancer?.name}` : 'ثبت نظر'}
            subtitle="نظر تو به تازه‌کارها کمک می‌کند بهتر شوند و به کارفرماهای بعدی کمک می‌کند انتخاب کنند."
            icon="bi-star"
            method="post"
            url={contract ? fillRoute(routes.review, contract.id) : ''}
            initial={{ rating: 5, comment: '' }}
            submitLabel="ثبت نظر"
        >
            {(form) => (
                <div className="d-grid gap-3">
                    <StarRating form={form} name="rating" label="امتیاز" />
                    <Textarea form={form} name="comment" label="توضیح (اختیاری)" rows={4} maxLength={2000} placeholder="کیفیت کار، ارتباط و تحویل به‌موقع چطور بود؟" />
                </div>
            )}
        </ModalForm>
    );
}

export default function Index({ contracts, filters: initialFilters, balance, routes }) {
    const filters = useFilters(initialFilters);
    const planner = useModal();
    const reviewer = useModal();
    const disputer = useModal();

    const fund = async (milestone, contract) => {
        const fromDeposit = Math.min(Number(contract.deposit_balance ?? 0), Number(milestone.amount));
        const short = Number(milestone.amount) - fromDeposit - Number(balance);

        if (short > 0) {
            const ok = await confirm({
                title: 'موجودی کافی نیست',
                message: `برای تأمین این مرحله ${formatMoney(short)} دیگر لازم داری. اول کیف پولت را شارژ کن.`,
                confirmLabel: 'رفتن به کیف پول',
                tone: 'primary',
                icon: 'bi-wallet2',
            });

            if (ok) {
                router.visit(routes.wallet);
            }

            return;
        }

        const ok = await confirm({
            title: `تأمین «${milestone.title}»؟`,
            message:
                fromDeposit > 0
                    ? `${formatMoney(fromDeposit)} از امانت حسن انجام کار${fromDeposit < Number(milestone.amount) ? ` و ${formatMoney(Number(milestone.amount) - fromDeposit)} از کیف پولت` : ''} برای این مرحله در امانت می‌ماند تا کار تحویل شود.`
                    : `${formatMoney(milestone.amount)} از کیف پولت برداشته و تا تحویل کار در امانت نگه داشته می‌شود.`,
            confirmLabel: 'تأمین و امانت',
            tone: 'primary',
            icon: 'bi-safe2',
        });

        if (ok) {
            put(fillRoute(routes.milestoneUpdate, milestone.id), { action: 'fund' });
        }
    };

    const release = async (milestone) => {
        const ok = await confirm({
            title: `تأیید و پرداخت «${milestone.title}»؟`,
            message: `کار این مرحله را بررسی کرده‌ای؟ ${formatMoney(milestone.amount)} از امانت به فریلنسر پرداخت می‌شود و قابل برگشت نیست.`,
            confirmLabel: 'تأیید و پرداخت',
            tone: 'primary',
            icon: 'bi-send-check',
        });

        if (ok) {
            put(fillRoute(routes.milestoneUpdate, milestone.id), { action: 'release' });
        }
    };

    const close = async (contract, status) => {
        const completing = status === 'completed';
        const held = Number(contract.deposit_balance ?? 0) + Number(contract.progress?.funded ?? 0);

        // Money in escrow: only an expert can cancel, so the dispute form opens instead.
        if (!completing && held > 0) {
            disputer.show({ contract, cancelling: true });

            return;
        }

        const ok = await confirm({
            title: completing ? 'پایان قرارداد؟' : 'لغو قرارداد؟',
            message: completing
                ? `قرارداد تکمیل‌شده ثبت می‌شود و می‌توانی برای فریلنسر نظر بگذاری.${Number(contract.deposit_balance) > 0 ? ` ${formatMoney(contract.deposit_balance)} امانت مصرف‌نشده به کیف پولت برمی‌گردد.` : ''}`
                : 'قرارداد و پروژه لغو می‌شوند.',
            confirmLabel: completing ? 'پایان قرارداد' : 'لغو قرارداد',
            tone: completing ? 'primary' : 'danger',
        });

        if (ok) {
            put(fillRoute(routes.update, contract.id), { status });
        }
    };

    return (
        <>
            <PageHeader
                title="قراردادها"
                description="مرحله‌ها را تعریف کن، مبلغ هر مرحله را در امانت بگذار و بعد از تحویل، پرداخت را آزاد کن."
                actions={
                    <Link href={routes.wallet} className="btn btn-ghost">
                        <i className="bi bi-wallet2" /> موجودی: {formatMoney(balance)}
                    </Link>
                }
            />

            <Tabs
                className="mb-3"
                items={[{ key: '', label: 'همه' }, ...options('contractStatus').map((option) => ({ key: option.value, label: option.label }))]}
                active={filters.filters.status ?? ''}
                onChange={(status) => filters.set('status', status || null)}
            />

            {contracts.data.length === 0 ? (
                <EmptyState title="قراردادی نداری" description="از صفحه‌ی پیشنهادها یکی را استخدام کن تا قرارداد ساخته شود." />
            ) : (
                <div className="d-grid gap-3">
                    {contracts.data.map((contract, index) => {
                        const active = contract.status === 'active';
                        const remaining = remainingToPlan(contract);

                        return (
                            <article key={contract.id} className={`jl-card jl-rise jl-rise-${Math.min(index + 1, 8)}`}>
                                <header className="jl-card-header">
                                    <div className="min-w-0">
                                        <h2 className="text-truncate">{contract.project?.title}</h2>
                                        <p>
                                            قرارداد #{formatNumber(contract.id)} · {formatMoney(contract.amount)} · شروع {formatDate(contract.started_at ?? contract.created_at)}
                                        </p>
                                    </div>
                                    <div className="d-flex align-items-center gap-1">
                                        <StatusBadge group="contractStatus" value={contract.status} />
                                        {active && (
                                            <RowActions>
                                                {(closeMenu) => (
                                                    <>
                                                        <DropdownItem
                                                            icon="bi-plus-lg"
                                                            disabled={remaining <= 0}
                                                            onClick={() => {
                                                                closeMenu();
                                                                planner.show(contract);
                                                            }}
                                                        >
                                                            مرحله‌ی جدید
                                                        </DropdownItem>
                                                        <DropdownItem
                                                            icon="bi-flag"
                                                            onClick={() => {
                                                                closeMenu();
                                                                close(contract, 'completed');
                                                            }}
                                                        >
                                                            پایان قرارداد
                                                        </DropdownItem>
                                                        <DropdownItem
                                                            icon="bi-shield-exclamation"
                                                            onClick={() => {
                                                                closeMenu();
                                                                disputer.show({ contract });
                                                            }}
                                                        >
                                                            درخواست بررسی کارشناس
                                                        </DropdownItem>
                                                        <DropdownDivider />
                                                        <DropdownItem
                                                            icon="bi-x-circle"
                                                            danger
                                                            onClick={() => {
                                                                closeMenu();
                                                                close(contract, 'cancelled');
                                                            }}
                                                        >
                                                            لغو قرارداد
                                                        </DropdownItem>
                                                    </>
                                                )}
                                            </RowActions>
                                        )}
                                    </div>
                                </header>
                                <div className="jl-card-body">
                                    <div className="row g-3 mb-3">
                                        <div className="col-md-6">
                                            <div className="small text-muted mb-1">فریلنسر</div>
                                            <Person user={contract.freelancer} role="freelancer" />
                                        </div>
                                        <div className="col-md-6">
                                            <div className="small text-muted mb-1">پرداخت‌شده</div>
                                            <ContractProgress contract={contract} />
                                        </div>
                                    </div>

                                    <div className="d-grid gap-2 mb-3">
                                        <OpenDisputeNote dispute={contract.open_dispute} />
                                        <DepositNote contract={contract} viewer="employer" />
                                    </div>

                                    <MilestoneList
                                        milestones={contract.milestones}
                                        actions={(milestone) =>
                                            !active ? null : milestone.status === 'pending' ? (
                                                <button type="button" className="btn btn-accent btn-sm" onClick={() => fund(milestone, contract)}>
                                                    <i className="bi bi-safe2" /> تأمین
                                                </button>
                                            ) : milestone.status === 'submitted' ? (
                                                <button type="button" className="btn btn-primary btn-sm" onClick={() => release(milestone)}>
                                                    <i className="bi bi-send-check" /> تأیید و پرداخت
                                                </button>
                                            ) : null
                                        }
                                    />

                                    <div className="d-flex flex-wrap gap-2 mt-3">
                                        {active && remaining > 0 && (
                                            <button type="button" className="btn btn-soft btn-sm" onClick={() => planner.show(contract)}>
                                                <i className="bi bi-plus-lg" /> مرحله‌ی جدید ({formatMoney(remaining)} مانده)
                                            </button>
                                        )}
                                        {contract.status === 'completed' && !contract.reviewed && (
                                            <button type="button" className="btn btn-accent btn-sm" onClick={() => reviewer.show(contract)}>
                                                <i className="bi bi-star" /> ثبت نظر برای فریلنسر
                                            </button>
                                        )}
                                        {contract.status === 'completed' && contract.reviewed && (
                                            <Badge tone="success" icon="bi-star-fill">
                                                نظرت ثبت شده
                                            </Badge>
                                        )}
                                    </div>
                                </div>
                            </article>
                        );
                    })}
                </div>
            )}

            {contracts.meta?.last_page > 1 && (
                <div className="jl-card mt-3">
                    <Pagination meta={contracts.meta} />
                </div>
            )}

            <MilestoneForm modal={planner} routes={routes} />
            <ReviewForm modal={reviewer} routes={routes} />
            <DisputeForm modal={disputer} url={routes.dispute} viewer="employer" />
        </>
    );
}
