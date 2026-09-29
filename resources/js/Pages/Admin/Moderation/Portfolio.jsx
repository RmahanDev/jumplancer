import ModalForm from '../../../Components/Form/ModalForm';
import Textarea from '../../../Components/Form/Textarea';
import PageHeader from '../../../Components/Panel/PageHeader';
import Pagination from '../../../Components/Panel/Pagination';
import { Person } from '../../../Components/UI/Avatar';
import { StatusBadge } from '../../../Components/UI/Badge';
import { confirm } from '../../../Components/UI/ConfirmDialog';
import EmptyState from '../../../Components/UI/EmptyState';
import Tabs from '../../../Components/UI/Tabs';
import UserName from '../../../Components/UI/UserName';
import { useFilters } from '../../../hooks/useFilters';
import { useModal } from '../../../hooks/useModal';
import { put } from '../../../lib/actions';
import { formatRelative } from '../../../lib/format';
import { fillRoute } from '../../../lib/text';

function RejectMediaForm({ modal, routes }) {
    const media = modal.record;

    return (
        <ModalForm
            open={modal.open}
            onClose={modal.close}
            title={media?.status === 'approved' ? 'تجدیدنظر: رد فایل تأییدشده' : 'رد فایل نمونه‌کار'}
            subtitle={media?.original_filename}
            icon="bi-file-earmark-x"
            tone="danger"
            method="put"
            url={media ? fillRoute(routes.update, media.id) : ''}
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

export default function Portfolio({ media, filters: initialFilters, counts, routes }) {
    const filters = useFilters(initialFilters);
    const rejecter = useModal();
    const total = Object.values(counts).reduce((sum, count) => sum + Number(count), 0);

    // A human mistake can be corrected: a rejected file can still be approved later.
    const reapprove = async (file) => {
        const ok = await confirm({
            title: 'تأیید دوباره‌ی فایل؟',
            message: `«${file.original_filename}» قبلاً رد شده بود. با تأیید، برای کارفرماها نمایش داده می‌شود.`,
            confirmLabel: 'تأیید فایل',
            tone: 'primary',
            icon: 'bi-check2-circle',
        });

        if (ok) {
            put(fillRoute(routes.update, file.id), { status: 'approved' });
        }
    };

    return (
        <>
            <PageHeader
                title="فایل‌های نمونه‌کار"
                description="هر فایل نمونه‌کار قبل از نمایش به کارفرماها اینجا بررسی می‌شود؛ اطلاعات تماس داخل تصویر یا PDF مجاز نیست. اگر اشتباهی رخ داد، فایل تأییدشده را می‌شود بعداً رد کرد و فایل ردشده را تأیید."
            />

            <Tabs
                className="mb-3"
                items={[
                    { key: 'pending_review', label: 'منتظر بررسی', icon: 'bi-hourglass-split', count: counts.pending_review ?? 0 },
                    { key: 'approved', label: 'تأییدشده', icon: 'bi-check2-circle', count: counts.approved ?? 0 },
                    { key: 'rejected', label: 'ردشده', icon: 'bi-x-circle', count: counts.rejected ?? 0 },
                    { key: 'all', label: 'همه', count: total },
                ]}
                active={filters.filters.status}
                onChange={(status) => filters.set('status', status)}
            />

            {media.data.length === 0 ? (
                <EmptyState icon="bi-images" title="فایلی در این فهرست نیست" description="فایل‌های جدید نمونه‌کار قبل از نمایش به کارفرماها اینجا بررسی می‌شوند." />
            ) : (
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
                                    {file.item?.freelancer && <Person user={file.item.freelancer} role="freelancer" size="sm" />}
                                    {file.rejection_reason && <div className="small text-danger">{file.rejection_reason}</div>}
                                    {file.status === 'pending_review' ? (
                                        <div className="d-flex gap-2 mt-auto">
                                            <button type="button" className="btn btn-primary btn-sm flex-grow-1" onClick={() => put(fillRoute(routes.update, file.id), { status: 'approved' })}>
                                                <i className="bi bi-check2" /> تأیید
                                            </button>
                                            <button type="button" className="btn btn-soft-danger btn-sm flex-grow-1" onClick={() => rejecter.show(file)}>
                                                <i className="bi bi-x-lg" /> رد
                                            </button>
                                        </div>
                                    ) : (
                                        <div className="mt-auto d-grid gap-2">
                                            <div className="small text-muted">
                                                بررسی‌شده {formatRelative(file.reviewed_at)}
                                                {file.reviewer && (
                                                    <>
                                                        {' '}
                                                        توسط <UserName user={file.reviewer} />
                                                    </>
                                                )}
                                            </div>
                                            {file.status === 'approved' ? (
                                                <button type="button" className="btn btn-soft-danger btn-sm" onClick={() => rejecter.show(file)}>
                                                    <i className="bi bi-arrow-counterclockwise" /> تجدیدنظر: رد فایل
                                                </button>
                                            ) : (
                                                <button type="button" className="btn btn-soft btn-sm" onClick={() => reapprove(file)}>
                                                    <i className="bi bi-arrow-counterclockwise" /> تجدیدنظر: تأیید فایل
                                                </button>
                                            )}
                                        </div>
                                    )}
                                </div>
                            </article>
                        </div>
                    ))}
                </div>
            )}

            {media.meta?.last_page > 1 && (
                <div className="jl-card mt-3">
                    <Pagination meta={media.meta} />
                </div>
            )}

            <RejectMediaForm modal={rejecter} routes={routes} />
        </>
    );
}
