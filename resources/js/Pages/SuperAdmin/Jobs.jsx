import PageHeader from '../../Components/Panel/PageHeader';
import TableCard from '../../Components/Panel/TableCard';
import { confirm } from '../../Components/UI/ConfirmDialog';
import DataTable from '../../Components/UI/DataTable';
import EmptyState from '../../Components/UI/EmptyState';
import { formatDateTime, formatRelative } from '../../lib/format';
import { fillRoute } from '../../lib/text';
import { destroy, post } from '../../lib/actions';

export default function Jobs({ jobs, routes }) {
    const retry = (job) => post(fillRoute(routes.retry, job.uuid));

    const forget = async (job) => {
        const ok = await confirm({
            title: 'حذف جاب ناموفق؟',
            message: 'این جاب از فهرست ناموفق‌ها حذف می‌شود و دیگر قابل تلاش دوباره نیست.',
            confirmLabel: 'حذف',
        });

        if (ok) {
            destroy(fillRoute(routes.forget, job.uuid));
        }
    };

    const columns = [
        {
            key: 'job',
            label: 'جاب',
            primary: true,
            render: (job) => (
                <div className="min-w-0">
                    <div className="fw-semibold ltr text-start">{job.job ?? 'نامشخص'}</div>
                    <div className="small text-muted ltr text-start text-truncate" style={{ maxWidth: 520 }} title={job.exception}>
                        {job.exception}
                    </div>
                </div>
            ),
        },
        {
            key: 'queue',
            label: 'صف',
            render: (job) => (
                <span className="jl-chip ltr">
                    {job.connection}/{job.queue}
                </span>
            ),
        },
        { key: 'failed_at', label: 'زمان خطا', render: (job) => <span title={formatDateTime(job.failed_at)}>{formatRelative(job.failed_at)}</span> },
    ];

    return (
        <>
            <PageHeader title="صف و جاب‌های ناموفق" description="به‌جای اجرای queue:retry و queue:forget روی سرور، از همین‌جا مدیریت کن." />

            <TableCard meta={jobs}>
                <DataTable
                    columns={columns}
                    rows={jobs.data}
                    rowKey="uuid"
                    empty={<EmptyState title="هیچ جاب ناموفقی نیست" description="صف سالم است. اگر جابی خطا بدهد اینجا نمایش داده می‌شود." />}
                    actions={(job) => (
                        <div className="d-flex gap-1 justify-content-end">
                            <button type="button" className="btn btn-soft btn-sm" onClick={() => retry(job)}>
                                <i className="bi bi-arrow-repeat" /> تلاش دوباره
                            </button>
                            <button type="button" className="jl-icon-btn text-danger" onClick={() => forget(job)} aria-label="حذف" data-tip="حذف">
                                <i className="bi bi-trash3" />
                            </button>
                        </div>
                    )}
                />
            </TableCard>
        </>
    );
}
