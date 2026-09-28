import { ContentForm, ContentGrid } from '../../../Components/Domain/ContentManager';
import PageHeader from '../../../Components/Panel/PageHeader';
import Pagination from '../../../Components/Panel/Pagination';
import SearchInput from '../../../Components/UI/SearchInput';
import Tabs from '../../../Components/UI/Tabs';
import { useFilters } from '../../../hooks/useFilters';
import { useModal } from '../../../hooks/useModal';
import { options } from '../../../lib/labels';

export default function Index({ contents, filters: initialFilters, categories, options: choices, routes }) {
    const filters = useFilters(initialFilters);
    const editor = useModal();

    return (
        <>
            <PageHeader
                title="محتوای آموزشی"
                description="همه‌ی مقاله‌ها، ویدیوها، چک‌لیست‌ها و نقشه‌های راه، از هر نویسنده‌ای."
                primary={{ label: 'محتوای جدید', icon: 'bi-plus-lg', onClick: () => editor.show(null) }}
            />

            <div className="jl-card mb-3 jl-rise">
                <div className="jl-toolbar border-0">
                    <SearchInput value={filters.filters.search} onChange={filters.search} placeholder="جستجوی عنوان..." className="jl-toolbar-search" />
                    <select className="form-select" value={filters.filters.type ?? ''} onChange={(event) => filters.set('type', event.target.value || null)} aria-label="نوع محتوا">
                        <option value="">همه‌ی انواع</option>
                        {options('contentType', choices.types).map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </select>
                    <Tabs
                        items={[
                            { key: '', label: 'همه' },
                            { key: '1', label: 'منتشرشده' },
                            { key: '0', label: 'پیش‌نویس' },
                        ]}
                        active={filters.filters.published ?? ''}
                        onChange={(value) => filters.set('published', value === '' ? null : value)}
                    />
                </div>
            </div>

            <ContentGrid
                contents={contents.data}
                routes={routes}
                onEdit={(content) => editor.show(content)}
                showAuthor
                emptyAction={
                    <button type="button" className="btn btn-primary btn-sm" onClick={() => editor.show(null)}>
                        <i className="bi bi-plus-lg" /> محتوای جدید
                    </button>
                }
            />

            {contents.meta?.total > 0 && (
                <div className="jl-card mt-3">
                    <Pagination meta={contents.meta} />
                </div>
            )}

            <ContentForm modal={editor} categories={categories} options={choices} routes={routes} />
        </>
    );
}
