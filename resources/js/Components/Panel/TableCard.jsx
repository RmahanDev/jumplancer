import Pagination from './Pagination';

/**
 * Card around a data table: a toolbar row (search, filters, tabs), the table and the pagination footer.
 */
export default function TableCard({ toolbar = null, meta = null, only = null, children, className = '' }) {
    return (
        <section className={`jl-card jl-table-card jl-rise jl-rise-2 ${className}`}>
            {toolbar && <div className="jl-toolbar">{toolbar}</div>}
            {children}
            <Pagination meta={meta} only={only} />
        </section>
    );
}
