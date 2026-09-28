import { formatDate, formatMoney } from '../../lib/format';
import { StatusBadge } from '../UI/Badge';
import Meter from '../UI/Meter';

/** Milestones of a contract as a timeline; `actions(milestone)` adds buttons per step. */
export function MilestoneList({ milestones, actions = null }) {
    if (!milestones?.length) {
        return <p className="text-muted small mb-0">هنوز مرحله‌ای تعریف نشده است.</p>;
    }

    return (
        <ol className="jl-timeline">
            {milestones.map((milestone) => (
                <li key={milestone.id} className={`is-${milestone.status}`}>
                    <span className="jl-timeline-dot" />
                    <div className="d-flex flex-wrap align-items-start justify-content-between gap-2">
                        <div className="jl-row-main">
                            <div className="fw-semibold">{milestone.title}</div>
                            <div className="small text-muted">
                                {formatMoney(milestone.amount)}
                                {milestone.due_date && ` · سررسید ${formatDate(milestone.due_date)}`}
                            </div>
                            {milestone.description && <div className="small text-muted-2 mt-1">{milestone.description}</div>}
                        </div>
                        <div className="d-flex align-items-center gap-2">
                            <StatusBadge group="milestoneStatus" value={milestone.status} />
                            {actions?.(milestone)}
                        </div>
                    </div>
                </li>
            ))}
        </ol>
    );
}

/** Paid-out share of a contract as a meter with the amounts. */
export function ContractProgress({ contract }) {
    const progress = contract.progress ?? { planned: 0, funded: 0, released: 0 };

    return (
        <div style={{ minWidth: 140 }}>
            <Meter value={progress.released} max={contract.amount} label="پرداخت‌شده از مبلغ قرارداد" />
            <div className="small text-muted mt-1">
                {formatMoney(progress.released, { unit: false })} از {formatMoney(contract.amount)}
            </div>
        </div>
    );
}

/** The good-faith deposit of a contract: how much was held when hiring and how much is still unspent. */
export function DepositNote({ contract, viewer = 'employer' }) {
    if (!contract.deposit_amount) {
        return null;
    }

    const left = Number(contract.deposit_balance ?? 0);

    return (
        <div className="jl-callout is-warning d-flex flex-wrap align-items-center gap-2 small">
            <i className="bi bi-shield-lock" />
            <span>
                امانت حسن انجام کار: <strong>{formatMoney(contract.deposit_amount)}</strong>
                {left > 0 ? ` — ${formatMoney(left)} هنوز خرج مرحله‌ها نشده` : ' — کامل خرج مرحله‌ها شده'}
            </span>
            <span className="text-muted">
                {viewer === 'employer'
                    ? 'مرحله‌ها اول از این مبلغ تأمین می‌شوند؛ مانده‌ی مصرف‌نشده بعد از پایان قرارداد به کیف پولت برمی‌گردد.'
                    : 'این مبلغ از طرف کارفرما در امانت است؛ اگر بدون دلیل پرداخت نکند، کارشناس درباره‌اش تصمیم می‌گیرد.'}
            </span>
        </div>
    );
}

/** Banner shown while an expert is looking at a dispute on the contract. */
export function OpenDisputeNote({ dispute }) {
    if (!dispute) {
        return null;
    }

    return (
        <div className="jl-callout is-danger d-flex gap-2 small">
            <i className="bi bi-shield-exclamation mt-1" />
            <div>
                <strong>{dispute.raised_by_viewer ? 'درخواست بررسی کارشناس را ثبت کرده‌ای.' : 'طرف مقابل درخواست بررسی کارشناس داده است.'}</strong> پول امانت این قرارداد تا تصمیم
                کارشناس جابه‌جا نمی‌شود.
                <div className="text-muted mt-1">«{dispute.reason}»</div>
            </div>
        </div>
    );
}
