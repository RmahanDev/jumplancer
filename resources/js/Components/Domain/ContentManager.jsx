import ModalForm from '../Form/ModalForm';
import RadioCards from '../Form/RadioCards';
import Select from '../Form/Select';
import Switch from '../Form/Switch';
import TextInput from '../Form/TextInput';
import Textarea from '../Form/Textarea';
import { Person } from '../UI/Avatar';
import { Badge, StatusBadge } from '../UI/Badge';
import { confirm } from '../UI/ConfirmDialog';
import { DropdownDivider, DropdownItem } from '../UI/Dropdown';
import EmptyState from '../UI/EmptyState';
import RowActions from '../UI/RowActions';
import { destroy, put } from '../../lib/actions';
import { formatRelative } from '../../lib/format';
import { label, options } from '../../lib/labels';
import { fillRoute } from '../../lib/text';

export const CONTENT_ICONS = {
    article: 'bi-file-earmark-text',
    video: 'bi-play-btn',
    checklist: 'bi-list-check',
    roadmap: 'bi-signpost-2',
};

function payload(content, overrides = {}) {
    return {
        title: content.title,
        content_type: content.content_type,
        audience: content.audience,
        purpose: content.purpose,
        category_id: content.category_id,
        body: content.body,
        media_url: content.media_url,
        is_published: content.is_published,
        ...overrides,
    };
}

/** Add / edit learning content (article, video, checklist, roadmap) in a modal. */
export function ContentForm({ modal, categories, options: choices, routes }) {
    const content = modal.record;
    const editing = Boolean(content);

    const categoryOptions = categories.flatMap((parent) => [
        { value: parent.id, label: `همه‌ی ${parent.name}`, group: parent.name },
        ...(parent.children ?? []).map((child) => ({ value: child.id, label: child.name, group: parent.name })),
    ]);

    return (
        <ModalForm
            open={modal.open}
            onClose={modal.close}
            title={editing ? 'ویرایش محتوا' : 'محتوای آموزشی جدید'}
            subtitle="محتوای منتشرشده در پنل فریلنسرها و کارفرماها نمایش داده می‌شود."
            icon="bi-journal-richtext"
            size="lg"
            method={editing ? 'put' : 'post'}
            url={editing ? fillRoute(routes.update, content.id) : routes.store}
            initial={{
                title: content?.title ?? '',
                content_type: content?.content_type ?? 'article',
                audience: content?.audience ?? 'freelancer',
                purpose: content?.purpose ?? 'technical',
                category_id: content?.category_id ?? '',
                body: content?.body ?? '',
                media_url: content?.media_url ?? '',
                is_published: content?.is_published ?? false,
            }}
            transform={(data) => ({ ...data, category_id: data.category_id || null, media_url: data.media_url || null, body: data.body || null })}
            submitLabel={editing ? 'ذخیره' : 'افزودن'}
        >
            {(form) => (
                <div className="row g-3">
                    <TextInput form={form} name="title" label="عنوان" required className="col-12" maxLength={200} placeholder="مثلاً نقشه‌ی راه یادگیری Laravel برای تازه‌کارها" />
                    <RadioCards
                        form={form}
                        name="content_type"
                        label="نوع محتوا"
                        className="col-12"
                        columns={4}
                        options={choices.types.map((type) => ({ value: type, label: label('contentType', type), icon: CONTENT_ICONS[type] }))}
                    />
                    <Select form={form} name="audience" label="مخاطب" required allowEmpty={false} className="col-md-4" options={options('audience', choices.audiences)} />
                    <Select form={form} name="purpose" label="هدف" required allowEmpty={false} className="col-md-4" options={options('purpose', choices.purposes)} />
                    <Select form={form} name="category_id" label="دسته (اختیاری)" className="col-md-4" placeholder="عمومی" options={categoryOptions} />
                    {form.data.content_type === 'video' && (
                        <TextInput form={form} name="media_url" label="آدرس ویدیو" ltr className="col-12" icon="bi-link-45deg" placeholder="https://www.aparat.com/v/..." />
                    )}
                    <Textarea
                        form={form}
                        name="body"
                        label={form.data.content_type === 'checklist' ? 'موارد چک‌لیست (هر مورد در یک خط)' : 'متن'}
                        required={form.data.content_type !== 'video' || !form.data.media_url}
                        rows={8}
                        maxLength={20000}
                        className="col-12"
                    />
                    {form.data.content_type !== 'video' && (
                        <TextInput form={form} name="media_url" label="لینک تکمیلی (اختیاری)" ltr className="col-12" icon="bi-link-45deg" placeholder="https://" />
                    )}
                    <Switch form={form} name="is_published" className="col-12" label="منتشر شود" description="خاموش بماند تا به‌صورت پیش‌نویس ذخیره شود." />
                </div>
            )}
        </ModalForm>
    );
}

/** Grid of content cards with edit / publish / delete. */
export function ContentGrid({ contents, routes, onEdit, showAuthor = false, emptyAction = null }) {
    const togglePublish = (content) => put(fillRoute(routes.update, content.id), payload(content, { is_published: !content.is_published }));

    const remove = async (content) => {
        const ok = await confirm({
            title: 'حذف محتوا؟',
            message: `«${content.title}» برای همیشه حذف می‌شود.`,
            confirmLabel: 'حذف',
        });

        if (ok) {
            destroy(fillRoute(routes.destroy, content.id));
        }
    };

    if (!contents.length) {
        return <EmptyState title="هنوز محتوایی نیست" description="یک مقاله، ویدیو، چک‌لیست یا نقشه‌ی راه بنویس تا تازه‌کارها سریع‌تر راه بیفتند." action={emptyAction} />;
    }

    return (
        <div className="row g-3">
            {contents.map((content, index) => (
                <div className="col-md-6 col-xl-4" key={content.id}>
                    <article className={`jl-card jl-card-hover jl-content-card h-100 jl-rise jl-rise-${Math.min(index + 1, 8)}`} onClick={(event) => !event.target.closest('button, a, .jl-dropdown') && onEdit(content)}>
                        <div className="jl-card-body d-flex flex-column h-100">
                            <div className="d-flex align-items-start gap-2 mb-2">
                                <span className={`jl-content-icon is-${content.content_type}`}>
                                    <i className={`bi ${CONTENT_ICONS[content.content_type] ?? 'bi-journal'}`} />
                                </span>
                                <div className="min-w-0 flex-grow-1">
                                    <h3 className="h6 fw-bold mb-1 jl-clamp-2">{content.title}</h3>
                                    <div className="d-flex flex-wrap gap-1">
                                        <StatusBadge group="contentType" value={content.content_type} />
                                        <StatusBadge group="purpose" value={content.purpose} />
                                        {content.is_published ? <Badge tone="success">منتشرشده</Badge> : <Badge tone="secondary">پیش‌نویس</Badge>}
                                    </div>
                                </div>
                                <RowActions>
                                    {(close) => (
                                        <>
                                            <DropdownItem icon="bi-pencil" onClick={() => { close(); onEdit(content); }}>
                                                ویرایش
                                            </DropdownItem>
                                            <DropdownItem icon={content.is_published ? 'bi-eye-slash' : 'bi-send'} onClick={() => { close(); togglePublish(content); }}>
                                                {content.is_published ? 'برگرداندن به پیش‌نویس' : 'انتشار'}
                                            </DropdownItem>
                                            <DropdownDivider />
                                            <DropdownItem icon="bi-trash3" danger onClick={() => { close(); remove(content); }}>
                                                حذف
                                            </DropdownItem>
                                        </>
                                    )}
                                </RowActions>
                            </div>
                            {content.body && <p className="small text-muted-2 jl-clamp-3 mb-2">{content.body}</p>}
                            <div className="mt-auto d-flex align-items-center justify-content-between gap-2 small text-muted">
                                <span>
                                    {label('audience', content.audience)}
                                    {content.category && ` · ${content.category.name}`}
                                </span>
                                <span>{formatRelative(content.published_at ?? content.created_at)}</span>
                            </div>
                            {showAuthor && content.author && (
                                <div className="border-top pt-2 mt-2">
                                    <Person user={content.author} size="sm" />
                                </div>
                            )}
                        </div>
                    </article>
                </div>
            ))}
        </div>
    );
}
