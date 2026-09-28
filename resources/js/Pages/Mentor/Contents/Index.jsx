import { ContentForm, ContentGrid } from '../../../Components/Domain/ContentManager';
import PageHeader from '../../../Components/Panel/PageHeader';
import Pagination from '../../../Components/Panel/Pagination';
import { useModal } from '../../../hooks/useModal';

export default function Index({ contents, categories, options: choices, routes }) {
    const editor = useModal();

    return (
        <>
            <PageHeader
                title="محتوای آموزشی من"
                description="تجربه‌ات را به مقاله، ویدیو، چک‌لیست یا نقشه‌ی راه تبدیل کن تا تازه‌کارهای بیشتری به آن برسند."
                primary={{ label: 'محتوای جدید', icon: 'bi-plus-lg', onClick: () => editor.show(null) }}
            />

            <ContentGrid
                contents={contents.data}
                routes={routes}
                onEdit={(content) => editor.show(content)}
                emptyAction={
                    <button type="button" className="btn btn-primary btn-sm" onClick={() => editor.show(null)}>
                        <i className="bi bi-plus-lg" /> اولین محتوا
                    </button>
                }
            />

            {contents.meta?.last_page > 1 && (
                <div className="jl-card mt-3">
                    <Pagination meta={contents.meta} />
                </div>
            )}

            <ContentForm modal={editor} categories={categories} options={choices} routes={routes} />
        </>
    );
}
