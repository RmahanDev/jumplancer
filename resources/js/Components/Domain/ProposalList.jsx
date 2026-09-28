import { useState } from 'react';
import { Person } from '../UI/Avatar';
import { Badge, StatusBadge } from '../UI/Badge';
import EmptyState from '../UI/EmptyState';
import { formatMoney, formatNumber, formatRelative } from '../../lib/format';

function ProposalItem({ proposal }) {
    const [expanded, setExpanded] = useState(false);
    const long = (proposal.cover_letter ?? '').length > 220;

    return (
        <li className={`jl-proposal-item ${proposal.status === 'accepted' ? 'is-accepted' : ''}`}>
            <div className="d-flex flex-wrap align-items-start justify-content-between gap-2">
                <Person user={proposal.freelancer} role="freelancer" size="sm" meta={`ارسال ${formatRelative(proposal.created_at)}`} />
                <div className="d-flex flex-wrap gap-1">
                    <StatusBadge group="proposalStatus" value={proposal.status} />
                    {proposal.mentorship_requested ? (
                        <Badge tone="info" icon="bi-mortarboard">
                            درخواست منتور دارد
                        </Badge>
                    ) : (
                        <Badge tone="secondary" icon="bi-person">
                            بدون منتور
                        </Badge>
                    )}
                </div>
            </div>

            <dl className="jl-proposal-facts">
                <div>
                    <dt>قیمت پیشنهادی</dt>
                    <dd className="num">{formatMoney(proposal.proposed_price)}</dd>
                </div>
                <div>
                    <dt>زمان انجام</dt>
                    <dd className="num">{formatNumber(proposal.delivery_days)} روز</dd>
                </div>
            </dl>

            <p className={`jl-proposal-letter mb-0 ${expanded ? '' : 'jl-clamp-3'}`}>{proposal.cover_letter}</p>
            {long && (
                <button type="button" className="btn btn-link btn-sm p-0 mt-1" onClick={() => setExpanded((value) => !value)}>
                    {expanded ? 'نمایش کمتر' : 'نمایش کامل متن'}
                </button>
            )}
        </li>
    );
}

/** Freelancers' proposals on one project: cover letter, price, delivery time and whether they asked for a mentor. */
export default function ProposalList({ proposals = [], emptyText = 'هنوز پیشنهادی برای این پروژه نرسیده.' }) {
    if (proposals.length === 0) {
        return <EmptyState compact icon="bi-inbox" title={emptyText} />;
    }

    return (
        <ul className="jl-proposal-list">
            {proposals.map((proposal) => (
                <ProposalItem key={proposal.id} proposal={proposal} />
            ))}
        </ul>
    );
}
