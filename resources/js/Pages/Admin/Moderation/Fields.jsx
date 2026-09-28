import PageHeader from '../../../Components/Panel/PageHeader';
import TableCard from '../../../Components/Panel/TableCard';
import { Person } from '../../../Components/UI/Avatar';
import { Badge, StatusBadge } from '../../../Components/UI/Badge';
import { confirm } from '../../../Components/UI/ConfirmDialog';
import DataTable from '../../../Components/UI/DataTable';
import EmptyState from '../../../Components/UI/EmptyState';
import Tabs from '../../../Components/UI/Tabs';
import { useFilters } from '../../../hooks/useFilters';
import { put } from '../../../lib/actions';
import { formatRelative } from '../../../lib/format';
import { fillRoute } from '../../../lib/text';

export default function Fields({ fields, filters: initialFilters, counts, routes }) {
    const filters = useFilters(initialFilters);
    const total = Object.values(counts).reduce((sum, count) => sum + Number(count), 0);

    const decide = async (field, status) => {
        if (status === 'rejected') {
            const ok = await confirm({
                title: 'رد حوزه‌ی کاری؟',
                message: `${field.freelancer?.name} در آزمون «${field.category?.name}» قبول نشده و نمی‌تواند برای پروژه‌های این حوزه پیشنهاد بدهد.`,
                confirmLabel: 'رد',
            });

            if (!ok) {
                return;
            }
        }

        put(fillRoute(routes.update, field.id), { status });
    };

    const columns = [
        { key: 'freelancer', label: 'فریلنسر', primary: true, render: (field) => <Person user={field.freelancer} role="freelancer" size="sm" /> },
        {
            key: 'category',
            label: 'حوزه',
            render: (field) => (
                <div className="d-flex flex-wrap gap-1 align-items-center">
                    <span className="fw-semibold">{field.category?.name}</span>
                    {field.is_primary && <Badge tone="accent">اصلی</Badge>}
                </div>
            ),
        },
        { key: 'level', label: 'سطح ادعایی', render: (field) => <StatusBadge group="level" value={field.claimed_level} /> },
        {
            key: 'exam',
            label: 'آزمون',
            render: (field) =>
                field.exam_required ? (
                    <span className="small">{field.exam_fee_required ? 'لازم، با هزینه' : 'لازم، رایگان'}</span>
                ) : (
                    <span className="small text-muted">لازم نیست</span>
                ),
        },
        { key: 'status', label: 'وضعیت', render: (field) => <StatusBadge group="fieldStatus" value={field.status} /> },
    ];

    return (
        <>
            <PageHeader title="آزمون و حوزه‌های کاری" description="نتیجه‌ی آزمون حوزه‌هایی که فریلنسرها ثبت کرده‌اند. تا قبولی، فریلنسر نمی‌تواند برای پروژه‌های آن حوزه پیشنهاد بدهد." />

            <TableCard
                meta={fields.meta}
                toolbar={
                    <Tabs
                        items={[
                            { key: 'pending_exam', label: 'منتظر نتیجه‌ی آزمون', icon: 'bi-hourglass-split', count: counts.pending_exam ?? 0 },
                            { key: 'active', label: 'فعال', icon: 'bi-patch-check', count: counts.active ?? 0 },
                            { key: 'rejected', label: 'ردشده', icon: 'bi-x-circle', count: counts.rejected ?? 0 },
                            { key: 'all', label: 'همه', count: total },
                        ]}
                        active={filters.filters.status}
                        onChange={(status) => filters.set('status', status)}
                        className="w-100"
                    />
                }
            >
                <DataTable
                    columns={columns}
                    rows={fields.data}
                    rowClassName={(field) => (field.status === 'pending_exam' ? 'is-highlight' : '')}
                    empty={<EmptyState icon="bi-bullseye" title="حوزه‌ای در این فهرست نیست" />}
                    actions={(field) =>
                        field.status === 'pending_exam' ? (
                            <div className="d-flex gap-1 justify-content-end">
                                <button type="button" className="btn btn-primary btn-sm" onClick={() => decide(field, 'active')}>
                                    <i className="bi bi-patch-check" /> قبول شد
                                </button>
                                <button type="button" className="btn btn-soft-danger btn-sm" onClick={() => decide(field, 'rejected')}>
                                    رد
                                </button>
                            </div>
                        ) : (
                            <span className="small text-muted">{field.verified_at ? `تأیید ${formatRelative(field.verified_at)}` : '—'}</span>
                        )
                    }
                />
            </TableCard>
        </>
    );
}
