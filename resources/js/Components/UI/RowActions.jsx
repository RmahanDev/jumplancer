import Dropdown from './Dropdown';

/** The "⋮" menu at the end of a table row. `children` receives `close`. */
export default function RowActions({ children, label = 'عملیات' }) {
    return (
        <Dropdown
            width={210}
            trigger={
                <button type="button" className="jl-icon-btn" aria-label={label} aria-haspopup="menu">
                    <i className="bi bi-three-dots-vertical" />
                </button>
            }
        >
            {children}
        </Dropdown>
    );
}
