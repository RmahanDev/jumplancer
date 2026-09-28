import { useMemo } from 'react';
import ChipPicker from '../../../Components/Form/ChipPicker';
import FileInput from '../../../Components/Form/FileInput';
import Field from '../../../Components/Form/Field';
import ModalForm from '../../../Components/Form/ModalForm';
import NumberInput from '../../../Components/Form/NumberInput';
import Select from '../../../Components/Form/Select';
import Switch from '../../../Components/Form/Switch';
import TextInput from '../../../Components/Form/TextInput';
import Textarea from '../../../Components/Form/Textarea';
import PageHeader from '../../../Components/Panel/PageHeader';
import { Badge, StatusBadge } from '../../../Components/UI/Badge';
import { confirm } from '../../../Components/UI/ConfirmDialog';
import { DropdownDivider, DropdownItem } from '../../../Components/UI/Dropdown';
import EmptyState from '../../../Components/UI/EmptyState';
import RowActions from '../../../Components/UI/RowActions';
import { useModal } from '../../../hooks/useModal';
import { destroy } from '../../../lib/actions';
import { formatNumber } from '../../../lib/format';
import { categoryOptions } from '../../../lib/project';
import { fillRoute } from '../../../lib/text';

function ItemForm({ modal, categories, maxFiles, routes }) {
    const item = modal.record;
    const editing = Boolean(item);

    const skillsByCategory = useMemo(
        () => Object.fromEntries(categories.flatMap((parent) => (parent.children ?? []).map((child) => [child.id, child.skills ?? []]))),
        [categories],
    );

    return (
        <ModalForm
            open={modal.open}
            onClose={modal.close}
            title={editing ? 'ویرایش نمونه‌کار' : 'نمونه‌کار جدید'}
            subtitle="یک مطالعه‌ی موردی ساختاریافته: مسئله، نقش تو و نتیجه. فایل‌ها بعد از بررسی به کارفرماها نمایش داده می‌شوند."
            icon="bi-images"
            size="lg"
            method={editing ? 'put' : 'post'}
            forceFormData
            url={editing ? fillRoute(routes.update, item.id) : routes.store}
            initial={{
                title: item?.title ?? '',
                category_id: item?.category_id ?? '',
                role: item?.role ?? '',
                description: item?.description ?? '',
                outcome: item?.outcome ?? '',
                duration_days: item?.duration_days ?? '',
                is_visible: item?.is_visible ?? true,
                skills: (item?.skills ?? []).map((skill) => skill.id),
                files: [],
                remove_media: [],
            }}
            transform={(data) => ({ ...data, duration_days: data.duration_days === '' ? null : data.duration_days, category_id: data.category_id || null })}
        >
            {(form) => {
                const kept = (item?.media ?? []).filter((media) => !form.data.remove_media.includes(media.id));
                const skills = skillsByCategory[Number(form.data.category_id)] ?? [];

                return (
                    <div className="row g-3">
                        <TextInput form={form} name="title" label="عنوان" required className="col-md-7" maxLength={200} placeholder="مثلاً فروشگاه اینترنتی لوازم خانگی با ووکامرس" />
                        <Select
                            name="category_id"
                            label="دسته"
                            className="col-md-5"
                            options={categoryOptions(categories)}
                            value={form.data.category_id}
                            error={form.errors.category_id}
                            onChange={(value) => form.setData({ ...form.data, category_id: value, skills: [] })}
                        />
                        <TextInput form={form} name="role" label="نقش تو" className="col-md-7" maxLength={150} placeholder="مثلاً توسعه‌دهنده‌ی بک‌اند" />
                        <NumberInput form={form} name="duration_days" label="مدت انجام" unit="روز" className="col-md-5" />
                        <Textarea form={form} name="description" label="شرح پروژه و مسئله" required rows={4} maxLength={5000} className="col-12" />
                        <Textarea form={form} name="outcome" label="نتیجه" rows={2} maxLength={2000} className="col-12" placeholder="مثلاً سرعت بارگذاری صفحه‌ها ۴۰٪ بهتر شد." />
                        <ChipPicker
                            form={form}
                            name="skills"
                            label="مهارت‌های به‌کاررفته"
                            className="col-12"
                            max={10}
                            options={skills.map((skill) => ({ value: skill.id, label: skill.name }))}
                            emptyText="اول دسته را انتخاب کن تا مهارت‌هایش نمایش داده شود."
                        />

                        {editing && (item.media ?? []).length > 0 && (
                            <div className="col-12">
                                <Field label="فایل‌های فعلی" hint="با علامت حذف، فایل بعد از ذخیره پاک می‌شود.">
                                    <ul className="jl-file-list">
                                        {item.media.map((media) => {
                                            const removing = form.data.remove_media.includes(media.id);

                                            return (
                                                <li key={media.id} className={removing ? 'is-removing' : ''}>
                                                    <i className={`bi ${media.file_type === 'pdf' ? 'bi-file-earmark-pdf' : 'bi-file-earmark-image'}`} />
                                                    <span className="flex-grow-1 text-truncate ltr text-start">{media.original_filename}</span>
                                                    <StatusBadge group="moderationStatus" value={media.status} />
                                                    <button
                                                        type="button"
                                                        className="jl-icon-btn"
                                                        onClick={() =>
                                                            form.setData(
                                                                'remove_media',
                                                                removing ? form.data.remove_media.filter((id) => id !== media.id) : [...form.data.remove_media, media.id],
                                                            )
                                                        }
                                                        aria-label={removing ? 'برگرداندن' : 'حذف'}
                                                    >
                                                        <i className={`bi ${removing ? 'bi-arrow-counterclockwise' : 'bi-x-lg'}`} />
                                                    </button>
                                                </li>
                                            );
                                        })}
                                    </ul>
                                </Field>
                            </div>
                        )}

                        <FileInput form={form} name="files" label="افزودن فایل" accept="image/jpeg,image/png,image/webp,application/pdf" max={Math.max(0, maxFiles - kept.length)} className="col-12" />
                        <Switch form={form} name="is_visible" className="col-12" label="نمایش در پروفایل" description="خاموش باشد فقط خودت می‌بینی." />
                    </div>
                );
            }}
        </ModalForm>
    );
}

export default function Index({ items, categories, maxFiles, routes }) {
    const editor = useModal();

    const remove = async (item) => {
        const ok = await confirm({ title: 'حذف نمونه‌کار؟', message: `«${item.title}» و فایل‌هایش برای همیشه حذف می‌شوند.`, confirmLabel: 'حذف' });

        if (ok) {
            destroy(fillRoute(routes.destroy, item.id));
        }
    };

    return (
        <>
            <PageHeader
                title="نمونه‌کارها"
                description="نمونه‌کار ساختاریافته اعتماد کارفرما را جلب می‌کند؛ لینک و اطلاعات تماس در فایل‌ها مجاز نیست."
                primary={{ label: 'نمونه‌کار جدید', icon: 'bi-plus-lg', onClick: () => editor.show(null) }}
            />

            {items.length === 0 ? (
                <EmptyState
                    title="هنوز نمونه‌کاری نداری"
                    description="حتی یک پروژه‌ی تمرینی یا کلاسی خوب، شانس پذیرفته شدن پیشنهادت را بیشتر می‌کند."
                    action={
                        <button type="button" className="btn btn-primary btn-sm" onClick={() => editor.show(null)}>
                            <i className="bi bi-plus-lg" /> اولین نمونه‌کار
                        </button>
                    }
                />
            ) : (
                <div className="row g-3">
                    {items.map((item, index) => {
                        const pending = (item.media ?? []).filter((media) => media.status === 'pending_review').length;
                        const cover = (item.media ?? []).find((media) => media.file_type === 'image');

                        return (
                            <div className="col-md-6 col-xl-4" key={item.id}>
                                <article className={`jl-card h-100 jl-media-card jl-rise jl-rise-${Math.min(index + 1, 8)} ${item.is_visible ? '' : 'is-muted'}`}>
                                    <div className="jl-media-thumb">{cover ? <img src={cover.url} alt="" loading="lazy" /> : <i className="bi bi-kanban" />}</div>
                                    <div className="jl-card-body d-flex flex-column gap-2">
                                        <div className="d-flex align-items-start justify-content-between gap-2">
                                            <div className="min-w-0">
                                                <h2 className="h6 fw-bold mb-1 jl-clamp-2">{item.title}</h2>
                                                <div className="small text-muted">
                                                    {item.category?.name ?? 'بدون دسته'}
                                                    {item.role && ` · ${item.role}`}
                                                    {item.duration_days && ` · ${formatNumber(item.duration_days)} روز`}
                                                </div>
                                            </div>
                                            <RowActions>
                                                {(close) => (
                                                    <>
                                                        <DropdownItem
                                                            icon="bi-pencil"
                                                            onClick={() => {
                                                                close();
                                                                editor.show(item);
                                                            }}
                                                        >
                                                            ویرایش
                                                        </DropdownItem>
                                                        <DropdownDivider />
                                                        <DropdownItem
                                                            icon="bi-trash3"
                                                            danger
                                                            onClick={() => {
                                                                close();
                                                                remove(item);
                                                            }}
                                                        >
                                                            حذف
                                                        </DropdownItem>
                                                    </>
                                                )}
                                            </RowActions>
                                        </div>
                                        <p className="small text-muted-2 jl-clamp-3 mb-0">{item.description}</p>
                                        {item.outcome && (
                                            <div className="small">
                                                <i className="bi bi-trophy text-accent" /> {item.outcome}
                                            </div>
                                        )}
                                        <div className="d-flex flex-wrap gap-1">
                                            {(item.skills ?? []).map((skill) => (
                                                <span key={skill.id} className="jl-chip">
                                                    {skill.name}
                                                </span>
                                            ))}
                                        </div>
                                        <div className="mt-auto d-flex flex-wrap gap-1">
                                            <Badge tone="secondary" icon="bi-paperclip">
                                                {formatNumber(item.media?.length ?? 0)} فایل
                                            </Badge>
                                            {pending > 0 && <Badge tone="warning">{formatNumber(pending)} فایل در انتظار بررسی</Badge>}
                                            {!item.is_visible && <Badge tone="secondary">پنهان</Badge>}
                                        </div>
                                    </div>
                                </article>
                            </div>
                        );
                    })}
                </div>
            )}

            <ItemForm modal={editor} categories={categories} maxFiles={maxFiles} routes={routes} />
        </>
    );
}
