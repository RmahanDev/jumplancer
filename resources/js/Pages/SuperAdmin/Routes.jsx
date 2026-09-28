import { useMemo, useState } from 'react';
import PageHeader from '../../Components/Panel/PageHeader';
import TableCard from '../../Components/Panel/TableCard';
import DataTable from '../../Components/UI/DataTable';
import EmptyState from '../../Components/UI/EmptyState';
import SearchInput from '../../Components/UI/SearchInput';
import Tabs from '../../Components/UI/Tabs';
import { formatNumber } from '../../lib/format';
import { normalizeForSearch } from '../../lib/text';

const METHOD_TONES = { GET: 'is-get', POST: 'is-post', PUT: 'is-put', PATCH: 'is-put', DELETE: 'is-delete' };

const AREAS = [
    { key: 'all', label: 'همه' },
    { key: 'panel/super', label: 'مدیر کل' },
    { key: 'panel/admin', label: 'ادمین' },
    { key: 'panel/mentor', label: 'منتور' },
    { key: 'panel/freelancer', label: 'فریلنسر' },
    { key: 'panel/employer', label: 'کارفرما' },
];

export default function Routes({ routes }) {
    const [search, setSearch] = useState('');
    const [area, setArea] = useState('all');
    const [showVendor, setShowVendor] = useState(false);

    const rows = useMemo(() => {
        const query = normalizeForSearch(search);

        return routes
            .filter((route) => showVendor || !route.vendor)
            .filter((route) => area === 'all' || route.uri.startsWith(`/${area}`))
            .filter((route) => !query || normalizeForSearch(`${route.uri} ${route.name ?? ''} ${route.action} ${route.methods.join(' ')}`).includes(query))
            .map((route, index) => ({ ...route, key: `${route.methods.join('|')} ${route.uri} ${index}` }));
    }, [routes, search, area, showVendor]);

    const columns = [
        {
            key: 'uri',
            label: 'آدرس',
            primary: true,
            render: (route) => (
                <div className="d-flex align-items-center gap-2 ltr text-start">
                    {route.methods.map((method) => (
                        <span key={method} className={`jl-method ${METHOD_TONES[method] ?? ''}`}>
                            {method}
                        </span>
                    ))}
                    <code className="text-body">{route.uri}</code>
                </div>
            ),
        },
        { key: 'name', label: 'نام', render: (route) => (route.name ? <code className="small">{route.name}</code> : <span className="text-muted">—</span>) },
        { key: 'action', label: 'کنترلر', render: (route) => <span className="small ltr d-inline-block text-start">{route.action}</span> },
        {
            key: 'middleware',
            label: 'میان‌افزار',
            render: (route) => (
                <div className="d-flex flex-wrap gap-1 ltr justify-content-end">
                    {route.middleware.map((middleware) => (
                        <span key={middleware} className="jl-chip">
                            {middleware}
                        </span>
                    ))}
                </div>
            ),
        },
    ];

    return (
        <>
            <PageHeader title="مسیرها (Routes)" description="همان خروجی php artisan route:list، با جستجو و فیلتر." />

            <TableCard
                toolbar={
                    <>
                        <SearchInput value={search} onChange={setSearch} placeholder="آدرس، نام یا کنترلر..." className="jl-toolbar-search" />
                        <Tabs items={AREAS} active={area} onChange={setArea} />
                        <div className="form-check form-switch ms-auto mb-0">
                            <input className="form-check-input" type="checkbox" id="vendor-routes" checked={showVendor} onChange={(event) => setShowVendor(event.target.checked)} />
                            <label className="form-check-label small" htmlFor="vendor-routes">
                                مسیرهای پکیج‌ها
                            </label>
                        </div>
                        <span className="text-muted small">{formatNumber(rows.length)} مسیر</span>
                    </>
                }
            >
                <DataTable columns={columns} rows={rows} rowKey="key" empty={<EmptyState compact title="مسیری پیدا نشد" />} />
            </TableCard>
        </>
    );
}
