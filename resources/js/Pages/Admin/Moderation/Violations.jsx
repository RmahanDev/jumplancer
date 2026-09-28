import PageHeader from '../../../Components/Panel/PageHeader';
import TableCard from '../../../Components/Panel/TableCard';
import { Person } from '../../../Components/UI/Avatar';
import { StatusBadge } from '../../../Components/UI/Badge';
import { confirm } from '../../../Components/UI/ConfirmDialog';
import DataTable from '../../../Components/UI/DataTable';
import EmptyState from '../../../Components/UI/EmptyState';
import Tabs from '../../../Components/UI/Tabs';
import UserName from '../../../Components/UI/UserName';
import { useFilters } from '../../../hooks/useFilters';
import { put } from '../../../lib/actions';
import { formatDateTime, formatRelative } from '../../../lib/format';
import { fillRoute } from '../../../lib/text';

export default function Violations({ violations, filters: initialFilters, counts, routes }) {
    const filters = useFilters(initialFilters);

    const review = async (violation, lift) => {
        if (lift) {
            const ok = await confirm({
                title: `رفع تعلیق ${violation.user?.name}؟`,
                message: 'تخلف بررسی‌شده علامت می‌خورد و کاربر دوباره می‌تواند وارد پنل شود.',
                confirmLabel: 'رفع تعلیق',
                tone: 'primary',
                icon: 'bi-person-check',
            });

            if (!ok) {
                return;
            }
        }

        put(fillRoute(routes.update, violation.id), { lift_suspension: lift });
    };

    const columns = [
        {
            key: 'user',
            label: 'کاربر',
            primary: true,
            render: (violation) => (
                <div className="d-flex align-items-center gap-2">
                    <Person user={violation.user} size="sm" />
                    {violation.user?.status !== 'active' && <StatusBadge group="userStatus" value={violation.user?.status} />}
                </div>
            ),
        },
        {
            key: 'type',
            label: 'تخلف',
            render: (violation) => (
                <div className="d-flex flex-column gap-1 align-items-start">
                    <StatusBadge group="violationType" value={violation.violation_type} />
                    <code className="small text-truncate d-inline-block" style={{ maxWidth: 220 }} title={violation.detected_content}>
                        {violation.detected_content}
                    </code>
                </div>
            ),
        },
        { key: 'source', label: 'تشخیص', render: (violation) => <StatusBadge group="violationSource" value={violation.detected_by} /> },
        { key: 'action', label: 'واکنش خودکار', render: (violation) => <StatusBadge group="violationAction" value={violation.action_taken} /> },
        { key: 'created_at', label: 'زمان', className: 'text-nowrap', render: (violation) => <span title={formatDateTime(violation.created_at)}>{formatRelative(violation.created_at)}</span> },
    ];

    return (
        <>
            <PageHeader title="تخلفات تماس" description="شماره، ایمیل و لینکی که در چت‌ها و پیشنهادها فرستاده شده. واکنش خودکار از «تنظیمات پلتفرم» تعیین می‌شود." />

            <TableCard
                meta={violations.meta}
                toolbar={
                    <Tabs
                        items={[
                            { key: 'unreviewed', label: 'بررسی‌نشده', icon: 'bi-hourglass-split', count: counts.unreviewed },
                            { key: 'reviewed', label: 'بررسی‌شده', icon: 'bi-check2-circle', count: counts.reviewed },
                            { key: 'all', label: 'همه', count: counts.unreviewed + counts.reviewed },
                        ]}
                        active={filters.filters.status}
                        onChange={(status) => filters.set('status', status)}
                        className="w-100"
                    />
                }
            >
                <DataTable
                    columns={columns}
                    rows={violations.data}
                    rowClassName={(violation) => (violation.reviewed_at ? '' : 'is-highlight')}
                    empty={<EmptyState icon="bi-shield-check" title="تخلفی در این فهرست نیست" description="فیلتر خودکار چت‌ها، شماره و ایمیل و لینک را قبل از ارسال می‌گیرد." />}
                    actions={(violation) =>
                        violation.reviewed_at ? (
                            <span className="small text-muted text-nowrap d-inline-flex align-items-center gap-1">
                                <i className="bi bi-check2" /> {violation.reviewer ? <UserName user={violation.reviewer} /> : 'بررسی‌شده'}
                            </span>
                        ) : (
                            <div className="d-flex gap-1 justify-content-end">
                                <button type="button" className="btn btn-soft btn-sm" onClick={() => review(violation, false)}>
                                    بررسی شد
                                </button>
                                {violation.user?.status === 'suspended' && (
                                    <button type="button" className="btn btn-ghost btn-sm" onClick={() => review(violation, true)}>
                                        رفع تعلیق
                                    </button>
                                )}
                            </div>
                        )
                    }
                />
            </TableCard>
        </>
    );
}
