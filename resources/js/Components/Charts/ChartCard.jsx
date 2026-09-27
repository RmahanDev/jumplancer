import { useState } from 'react';

/**
 * Card around a chart with its title and a table-view twin (the accessible equivalent of the chart).
 * table: { head: ['تاریخ', 'مقدار'], rows: [['...', '...'], ...] }
 */
export default function ChartCard({ title, subtitle = null, table = null, actions = null, children, className = '' }) {
    const [asTable, setAsTable] = useState(false);

    return (
        <section className={`jl-card h-100 ${className}`}>
            <header className="jl-card-header">
                <div className="min-w-0">
                    <h2>{title}</h2>
                    {subtitle && <p>{subtitle}</p>}
                </div>
                <div className="d-flex align-items-center gap-1">
                    {actions}
                    {table && (
                        <button
                            type="button"
                            className="jl-icon-btn"
                            onClick={() => setAsTable((value) => !value)}
                            aria-pressed={asTable}
                            data-tip={asTable ? 'نمایش نمودار' : 'نمایش جدول'}
                        >
                            <i className={`bi ${asTable ? 'bi-bar-chart-line' : 'bi-table'}`} />
                        </button>
                    )}
                </div>
            </header>
            <div className="jl-card-body">
                {asTable && table ? (
                    <div className="jl-chart-table">
                        <table className="table table-sm mb-0">
                            <thead>
                                <tr>
                                    {table.head.map((cell) => (
                                        <th key={cell}>{cell}</th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {table.rows.map((row, index) => (
                                    <tr key={index}>
                                        {row.map((cell, cellIndex) => (
                                            <td key={cellIndex} className="num">
                                                {cell}
                                            </td>
                                        ))}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                ) : (
                    children
                )}
            </div>
        </section>
    );
}
