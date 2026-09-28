import { router } from '@inertiajs/react';
import { useState } from 'react';
import PageHeader from '../../Components/Panel/PageHeader';
import TableCard from '../../Components/Panel/TableCard';
import { StatusBadge } from '../../Components/UI/Badge';
import { confirm } from '../../Components/UI/ConfirmDialog';
import EmptyState from '../../Components/UI/EmptyState';
import SearchInput from '../../Components/UI/SearchInput';
import { useFilters } from '../../hooks/useFilters';
import { formatBytes, formatNumber } from '../../lib/format';
import { options } from '../../lib/labels';
import { destroy } from '../../lib/actions';

export default function Logs({ entries, filters: initialFilters, file, routes }) {
    const filters = useFilters(initialFilters);
    const [open, setOpen] = useState(() => new Set());

    const toggle = (id) =>
        setOpen((current) => {
            const next = new Set(current);
            next.has(id) ? next.delete(id) : next.add(id);

            return next;
        });

    const clear = async () => {
        const ok = await confirm({
            title: 'پاک کردن فایل لاگ؟',
            message: `همه‌ی محتوای ${file.path} پاک می‌شود و قابل برگشت نیست.`,
            confirmLabel: 'پاک کن',
        });

        if (ok) {
            destroy(routes.clear);
        }
    };

    return (
        <>
            <PageHeader
                title="لاگ‌های برنامه"
                description={
                    <>
                        آخرین رویدادهای <code className="ltr">{file.path}</code> ({formatBytes(file.size)}) — جدیدترین بالا.
                    </>
                }
                actions={
                    <>
                        <button type="button" className="btn btn-ghost" onClick={() => router.reload({ preserveScroll: true })}>
                            <i className="bi bi-arrow-clockwise" /> تازه‌سازی
                        </button>
                        <button type="button" className="btn btn-soft-danger" onClick={clear} disabled={!file.size}>
                            <i className="bi bi-trash3" /> پاک کردن فایل
                        </button>
                    </>
                }
            />

            <TableCard
                toolbar={
                    <>
                        <SearchInput value={filters.filters.search} onChange={filters.search} placeholder="جستجو در پیام و جزئیات..." className="jl-toolbar-search" />
                        <select className="form-select" value={filters.filters.level ?? ''} onChange={(event) => filters.set('level', event.target.value || null)} aria-label="سطح لاگ">
                            <option value="">همه‌ی سطح‌ها</option>
                            {options('logLevel').map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                        {filters.active && (
                            <button type="button" className="btn btn-link btn-sm text-decoration-none" onClick={filters.reset}>
                                حذف فیلترها
                            </button>
                        )}
                        <span className="text-muted small ms-auto">{formatNumber(entries.length)} رویداد</span>
                    </>
                }
            >
                {entries.length === 0 ? (
                    <EmptyState title={filters.active ? 'رویدادی با این فیلتر نیست' : 'فایل لاگ خالی است'} description="وقتی خطا یا پیامی ثبت شود اینجا نمایش داده می‌شود." />
                ) : (
                    <ul className="jl-log-list">
                        {entries.map((entry) => (
                            <li key={entry.id} className={`is-${entry.level}`}>
                                <button type="button" className="jl-log-head" onClick={() => entry.context && toggle(entry.id)} aria-expanded={open.has(entry.id)} disabled={!entry.context}>
                                    <StatusBadge group="logLevel" value={entry.level} />
                                    <span className="jl-log-message ltr">{entry.message}</span>
                                    <span className="jl-log-date ltr">{entry.date}</span>
                                    {entry.context && <i className={`bi ${open.has(entry.id) ? 'bi-chevron-up' : 'bi-chevron-down'} text-muted`} />}
                                </button>
                                {open.has(entry.id) && <pre className="jl-code mt-2 mb-0">{entry.context}</pre>}
                            </li>
                        ))}
                    </ul>
                )}
            </TableCard>
        </>
    );
}
