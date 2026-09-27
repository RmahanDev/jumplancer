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
