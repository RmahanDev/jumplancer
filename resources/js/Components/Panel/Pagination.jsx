import { Link } from '@inertiajs/react';
import { formatNumber, toPersianDigits } from '../../lib/format';

/**
 * Footer of a paginated table: "۱ تا ۱۵ از ۴۲" and the page links of a Laravel paginator
 * (the `meta` of a resource collection). The first and last links are previous / next.
 */
export default function Pagination({ meta, only = null }) {
    if (!meta || !meta.total) {
        return null;
    }

    const links = meta.links ?? [];

    return (
        <div className="jl-table-footer">
            <span>
                نمایش {formatNumber(meta.from)} تا {formatNumber(meta.to)} از {formatNumber(meta.total)} مورد
            </span>
            {meta.last_page > 1 && (
                <nav className="jl-pagination" aria-label="صفحه‌بندی">
                    {links.map((link, index) => {
                        const isPrevious = index === 0;
                        const isNext = index === links.length - 1;
                        const content = isPrevious ? (
                            <i className="bi bi-chevron-right" aria-label="صفحه‌ی قبل" />
                        ) : isNext ? (
                            <i className="bi bi-chevron-left" aria-label="صفحه‌ی بعد" />
                        ) : (
                            toPersianDigits(link.label)
                        );

                        if (!link.url || link.active) {
                            return (
                                <span
                                    key={`${index}-${link.label}`}
                                    className={link.active ? 'is-active' : 'is-disabled'}
                                    aria-current={link.active ? 'page' : undefined}
                                >
                                    {content}
                                </span>
                            );
                        }

                        return (
                            <Link key={`${index}-${link.label}`} href={link.url} preserveScroll preserveState only={only ?? undefined} prefetch>
                                {content}
                            </Link>
                        );
                    })}
                </nav>
            )}
        </div>
    );
}
