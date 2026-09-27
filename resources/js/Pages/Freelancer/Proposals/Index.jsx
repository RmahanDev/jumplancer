import { Link } from '@inertiajs/react';
import ProposalForm from '../../../Components/Domain/ProposalForm';
import PageHeader from '../../../Components/Panel/PageHeader';
import TableCard from '../../../Components/Panel/TableCard';
import { StatusBadge } from '../../../Components/UI/Badge';
import { confirm } from '../../../Components/UI/ConfirmDialog';
import DataTable from '../../../Components/UI/DataTable';
import { DropdownItem } from '../../../Components/UI/Dropdown';
import EmptyState from '../../../Components/UI/EmptyState';
import RowActions from '../../../Components/UI/RowActions';
import Tabs from '../../../Components/UI/Tabs';
import { useFilters } from '../../../hooks/useFilters';
import { useModal } from '../../../hooks/useModal';
import { destroy } from '../../../lib/actions';
import { formatMoney, formatNumber, formatRelative } from '../../../lib/format';
import { label } from '../../../lib/labels';
import { fillRoute } from '../../../lib/text';

const TAB_STATUSES = ['pending', 'shortlisted', 'accepted', 'rejected', 'withdrawn'];

export default function Index({ proposals, filters: initialFilters, statusCounts, routes }) {
    const filters = useFilters(initialFilters);
    const editor = useModal();
    const total = Object.values(statusCounts).reduce((sum, count) => sum + Number(count), 0);

    const withdraw = async (proposal) => {
        const ok = await confirm({
            title: 'پس گرفتن پیشنهاد؟',
            message: `پیشنهادت برای «${proposal.project?.title}» پس گرفته می‌شود و دیگر برای کارفرما قابل انتخاب نیست.`,
            confirmLabel: 'پس بگیر',
        });

        if (ok) {
            destroy(fillRoute(routes.destroy, proposal.id));
        }
    };

    const columns = [
        {
            key: 'project',
            label: 'پروژه',
            primary: true,
            render: (proposal) => (
                <div className="min-w-0">
                    <div className="fw-semibold text-truncate" style={{ maxWidth: 340 }}>
                        {proposal.project?.title}
                    </div>
                    <div className="small text-muted">
                        {proposal.project?.employer?.name} · {formatRelative(proposal.created_at)}
                    </div>
                </div>
            ),
        },
        { key: 'price', label: 'قیمت', render: (proposal) => <span className="jl-money small">{formatMoney(proposal.proposed_price)}</span> },
        { key: 'days', label: 'تحویل', render: (proposal) => `${formatNumber(proposal.delivery_days)} روز` },
        { key: 'status', label: 'وضعیت', render: (proposal) => <StatusBadge group="proposalStatus" value={proposal.status} /> },
        {
            key: 'mentor',
            label: 'بازخورد منتور',
            render: (proposal) =>
                proposal.mentor_feedback ? (
                    <span className="small d-inline-block text-truncate" style={{ maxWidth: 240 }} title={proposal.mentor_feedback}>
                        <i className="bi bi-chat-square-quote text-accent" /> {proposal.mentor_feedback}
                    </span>
                ) : (
                    <span className="small text-muted">—</span>
                ),
        },
    ];

    return (
        <>
            <PageHeader
                title="پیشنهادهای من"
                description="تا وقتی کارفرما تصمیم نگرفته، پیشنهاد در انتظار را می‌توانی ویرایش یا پس بگیری."
                actions={
                    <Link href={routes.projects} className="btn btn-primary">
                        <i className="bi bi-search" /> پروژه‌های باز
                    </Link>
                }
            />

            <TableCard
                meta={proposals.meta}
                toolbar={
                    <Tabs
                        items={[{ key: '', label: 'همه', count: total }, ...TAB_STATUSES.map((status) => ({ key: status, label: label('proposalStatus', status), count: statusCounts[status] ?? 0 }))]}
                        active={filters.filters.status ?? ''}
                        onChange={(status) => filters.set('status', status || null)}
                    />
                }
            >
                <DataTable
                    columns={columns}
                    rows={proposals.data}
                    onRowClick={(proposal) => proposal.status === 'pending' && editor.show({ proposal })}
                    empty={
                        <EmptyState
                            icon="bi-send"
                            title="پیشنهادی پیدا نشد"
                            description="از صفحه‌ی پروژه‌ها برای اولین پروژه‌ات پیشنهاد بفرست."
                            action={
                                <Link href={routes.projects} className="btn btn-primary btn-sm">
                                    پیدا کردن پروژه
                                </Link>
                            }
                        />
                    }
                    actions={(proposal) => {
                        if (proposal.status === 'accepted') {
                            return (
                                <Link href={routes.contracts} className="btn btn-soft btn-sm">
                                    <i className="bi bi-file-earmark-check" /> قرارداد
                                </Link>
                            );
                        }

                        if (!['pending', 'shortlisted'].includes(proposal.status)) {
                            return null;
                        }

                        return (
                            <RowActions>
                                {(close) => (
                                    <>
                                        {proposal.status === 'pending' && (
                                            <DropdownItem
                                                icon="bi-pencil"
                                                onClick={() => {
                                                    close();
                                                    editor.show({ proposal });
                                                }}
                                            >
                                                ویرایش پیشنهاد
                                            </DropdownItem>
                                        )}
                                        <DropdownItem
                                            icon="bi-arrow-counterclockwise"
                                            danger
                                            onClick={() => {
                                                close();
                                                withdraw(proposal);
                                            }}
                                        >
                                            پس گرفتن
                                        </DropdownItem>
                                    </>
                                )}
                            </RowActions>
                        );
                    }}
                />
            </TableCard>

            <ProposalForm modal={editor} updateUrl={routes.update} />
        </>
    );
}
