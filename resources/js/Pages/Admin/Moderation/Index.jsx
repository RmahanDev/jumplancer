import ModalForm from '../../../Components/Form/ModalForm';
import Textarea from '../../../Components/Form/Textarea';
import PageHeader from '../../../Components/Panel/PageHeader';
import Pagination from '../../../Components/Panel/Pagination';
import TableCard from '../../../Components/Panel/TableCard';
import { Person } from '../../../Components/UI/Avatar';
import { Badge, StatusBadge } from '../../../Components/UI/Badge';
import { confirm } from '../../../Components/UI/ConfirmDialog';
import DataTable from '../../../Components/UI/DataTable';
import EmptyState from '../../../Components/UI/EmptyState';
import Tabs from '../../../Components/UI/Tabs';
import { useFilters } from '../../../hooks/useFilters';
import { useModal } from '../../../hooks/useModal';
import { put } from '../../../lib/actions';
import { formatDateTime, formatRelative } from '../../../lib/format';
import { fillRoute } from '../../../lib/text';

function RejectMediaForm({ modal, routes }) {
    const media = modal.record;

    return (
        <ModalForm
            open={modal.open}
            onClose={modal.close}
            title="رد فایل نمونه‌کار"
            subtitle={media?.original_filename}
            icon="bi-file-earmark-x"
            tone="danger"
            method="put"
            url={media ? fillRoute(routes.media, media.id) : ''}
            initial={{ status: 'rejected', rejection_reason: '' }}
            submitLabel="رد فایل"
            submitTone="danger"
        >
            {(form) => (
                <Textarea
                    form={form}
                    name="rejection_reason"
                    label="دلیل رد (به فریلنسر نمایش داده می‌شود)"
                    required
                    rows={3}
                    maxLength={255}
                    placeholder="مثلاً: در تصویر شماره‌ی تماس دیده می‌شود؛ لطفاً آن را بپوشانید و دوباره آپلود کنید."
                />
            )}
        </ModalForm>
    );
}

function MediaGrid({ media, routes, onReject }) {
    if (!media.data.length) {
        return <EmptyState icon="bi-images" title="فایلی برای بررسی نیست" description="فایل‌های جدید نمونه‌کار قبل از نمایش به کارفرماها اینجا بررسی می‌شوند." />;
    }

    return (
        <>
            <div className="row g-3">
                {media.data.map((file, index) => (
                    <div className="col-sm-6 col-lg-4 col-xxl-3" key={file.id}>
                        <article className={`jl-card h-100 jl-media-card jl-rise jl-rise-${Math.min(index + 1, 8)}`}>
                            <a href={file.url} target="_blank" rel="noreferrer" className="jl-media-thumb" aria-label={`نمایش ${file.original_filename}`}>
                                {file.file_type === 'image' ? <img src={file.url} alt="" loading="lazy" /> : <i className="bi bi-file-earmark-pdf" />}
                            </a>
                            <div className="jl-card-body d-flex flex-column gap-2">
                                <div className="d-flex align-items-start justify-content-between gap-2">
                                    <div className="min-w-0">
                                        <div className="fw-semibold small text-truncate ltr text-start" title={file.original_filename}>
                                            {file.original_filename}
                                        </div>
                                        <div className="small text-muted text-truncate">{file.item?.title}</div>
                                    </div>
                                    <StatusBadge group="moderationStatus" value={file.status} />
                                </div>
                                {file.item?.freelancer && <Person user={file.item.freelancer} size="sm" />}
                                {file.rejection_reason && <div className="small text-danger">{file.rejection_reason}</div>}
                                {file.status === 'pending_review' ? (
                                    <div className="d-flex gap-2 mt-auto">
                                        <button type="button" className="btn btn-primary btn-sm flex-grow-1" onClick={() => put(fillRoute(routes.media, file.id), { status: 'approved' })}>
                                            <i className="bi bi-check2" /> تأیید
                                        </button>
                                        <button type="button" className="btn btn-soft-danger btn-sm flex-grow-1" onClick={() => onReject(file)}>
                                            <i className="bi bi-x-lg" /> رد
                                        </button>
                                    </div>
                                ) : (
                                    <div className="small text-muted mt-auto">بررسی‌شده {formatRelative(file.reviewed_at)}</div>
                                )}
                            </div>
                        </article>
                    </div>
                ))}
            </div>
            {media.meta?.last_page > 1 && (
                <div className="jl-card mt-3">
                    <Pagination meta={media.meta} />
                </div>
            )}
        </>
    );
}

export default function Index({ tab, media, violations, fields, counts, routes }) {
    const filters = useFilters({ tab });
    const rejecter = useModal();
    const current = filters.filters.tab ?? 'media';

    const reviewViolation = async (violation, lift) => {
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

        put(fillRoute(routes.violation, violation.id), { lift_suspension: lift });
    };

    const decideField = async (field, status) => {
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

        put(fillRoute(routes.field, field.id), { status });
    };

    const violationColumns = [
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
        { key: 'created_at', label: 'زمان', render: (violation) => <span title={formatDateTime(violation.created_at)}>{formatRelative(violation.created_at)}</span> },
    ];

    const fieldColumns = [
        { key: 'freelancer', label: 'فریلنسر', primary: true, render: (field) => <Person user={field.freelancer} size="sm" /> },
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
            <PageHeader title="تخلفات و بازبینی" description="فایل‌های نمونه‌کار، اشتراک اطلاعات تماس در چت‌ها و نتیجه‌ی آزمون حوزه‌های کاری." />

            <Tabs
                className="mb-3"
                items={[
                    { key: 'media', label: 'فایل‌های نمونه‌کار', icon: 'bi-images', count: counts.media },
                    { key: 'violations', label: 'تخلفات تماس', icon: 'bi-shield-exclamation', count: counts.violations },
                    { key: 'fields', label: 'آزمون حوزه‌ها', icon: 'bi-bullseye', count: counts.fields },
                ]}
                active={current}
                onChange={(next) => filters.apply({ tab: next })}
            />

            {current === 'media' && media && <MediaGrid media={media} routes={routes} onReject={(file) => rejecter.show(file)} />}

            {current === 'violations' && violations && (
                <TableCard meta={violations.meta}>
                    <DataTable
                        columns={violationColumns}
                        rows={violations.data}
                        rowClassName={(violation) => (violation.reviewed_at ? '' : 'is-highlight')}
                        empty={<EmptyState icon="bi-shield-check" title="تخلفی ثبت نشده" description="فیلتر خودکار چت‌ها، شماره و ایمیل و لینک را قبل از ارسال می‌گیرد." />}
                        actions={(violation) =>
                            violation.reviewed_at ? (
                                <span className="small text-muted text-nowrap">
                                    <i className="bi bi-check2" /> {violation.reviewer?.name ?? 'بررسی‌شده'}
                                </span>
                            ) : (
                                <div className="d-flex gap-1 justify-content-end flex-wrap">
                                    <button type="button" className="btn btn-soft btn-sm" onClick={() => reviewViolation(violation, false)}>
                                        بررسی شد
                                    </button>
                                    {violation.user?.status === 'suspended' && (
                                        <button type="button" className="btn btn-ghost btn-sm" onClick={() => reviewViolation(violation, true)}>
                                            رفع تعلیق
                                        </button>
                                    )}
                                </div>
                            )
                        }
                    />
                </TableCard>
            )}

            {current === 'fields' && fields && (
                <TableCard meta={fields.meta}>
                    <DataTable
                        columns={fieldColumns}
                        rows={fields.data}
                        rowClassName={(field) => (field.status === 'pending_exam' ? 'is-highlight' : '')}
                        empty={<EmptyState icon="bi-bullseye" title="حوزه‌ای منتظر آزمون نیست" />}
                        actions={(field) =>
                            field.status === 'pending_exam' ? (
                                <div className="d-flex gap-1 justify-content-end flex-wrap">
                                    <button type="button" className="btn btn-primary btn-sm" onClick={() => decideField(field, 'active')}>
                                        <i className="bi bi-patch-check" /> قبول شد
                                    </button>
                                    <button type="button" className="btn btn-soft-danger btn-sm" onClick={() => decideField(field, 'rejected')}>
                                        رد
                                    </button>
                                </div>
                            ) : (
                                <span className="small text-muted">{field.verified_at ? `تأیید ${formatRelative(field.verified_at)}` : '—'}</span>
                            )
                        }
                    />
                </TableCard>
            )}

            <RejectMediaForm modal={rejecter} routes={routes} />
        </>
    );
}
