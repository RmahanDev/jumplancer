import EmptyState from './EmptyState';

/**
 * Data table that turns into stacked cards on phones.
 *
 * columns: [{ key, label, render?(row), sortable?, className?, primary? (card title), mobile? (false hides it on cards) }]
 */
export default function DataTable({
    columns,
    rows,
    rowKey = 'id',
    sort = null,
    onSort = null,
    actions = null,
    empty = null,
    busy = false,
    rowClassName = null,
    onRowClick = null,
    caption = null,
}) {
    const cell = (column, row) => (column.render ? column.render(row) : (row[column.key] ?? '—'));
    const primary = columns.find((column) => column.primary) ?? columns[0];
    const others = columns.filter((column) => column !== primary && column.mobile !== false);

    if (!rows?.length) {
        return empty ?? <EmptyState />;
    }

    return (
        <div style={{ opacity: busy ? 0.6 : 1, transition: 'opacity .2s ease' }} aria-busy={busy}>
            <div className="jl-table-wrap has-cards">
                <table className="table table-hover jl-table align-middle">
                    {caption && <caption className="visually-hidden">{caption}</caption>}
                    <thead>
                        <tr>
                            {columns.map((column) => (
                                <th key={column.key} scope="col" className={column.headerClassName ?? column.className}>
                                    {column.sortable && onSort ? (
                                        <button type="button" className={`jl-sort ${sort?.column === column.key ? 'is-active' : ''}`} onClick={() => onSort(column.key)}>
                                            {column.label}
                                            <i className={`bi ${sort?.column === column.key ? (sort.direction === 'asc' ? 'bi-sort-up' : 'bi-sort-down') : 'bi-arrow-down-up'}`} />
                                        </button>
                                    ) : (
                                        column.label
                                    )}
                                </th>
                            ))}
                            {actions && (
                                <th scope="col" className="jl-row-actions">
                                    <span className="visually-hidden">عملیات</span>
                                </th>
                            )}
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((row, index) => (
                            <tr
                                key={row[rowKey] ?? index}
                                className={`${rowClassName?.(row) ?? ''} ${onRowClick ? 'cursor-pointer' : ''}`}
                                onClick={onRowClick ? (event) => !event.target.closest('button, a, input, .jl-dropdown') && onRowClick(row) : undefined}
                                style={{ animationDelay: `${Math.min(index, 10) * 25}ms` }}
                            >
                                {columns.map((column) => (
                                    <td key={column.key} className={column.className}>
                                        {cell(column, row)}
                                    </td>
                                ))}
                                {actions && <td className="jl-row-actions">{actions(row)}</td>}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            <div className="jl-cards">
                {rows.map((row, index) => (
                    <div
                        key={row[rowKey] ?? index}
                        className={`jl-card-row ${rowClassName?.(row) ?? ''}`}
                        onClick={onRowClick ? (event) => !event.target.closest('button, a, input, .jl-dropdown') && onRowClick(row) : undefined}
                    >
                        <div className="d-flex align-items-start justify-content-between gap-2">
                            <div className="min-w-0 flex-grow-1">{cell(primary, row)}</div>
                            {actions && <div className="flex-shrink-0">{actions(row)}</div>}
                        </div>
                        {others.length > 0 && (
                            <dl>
                                {others.map((column) => (
                                    <div key={column.key} style={{ display: 'contents' }}>
                                        <dt>{column.label}</dt>
                                        <dd>{cell(column, row)}</dd>
                                    </div>
                                ))}
                            </dl>
                        )}
                    </div>
                ))}
            </div>
        </div>
    );
}
