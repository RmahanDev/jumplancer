/** Search box with a clear button. */
export default function SearchInput({ value, onChange, placeholder = 'جستجو...', className = '' }) {
    return (
        <div className={`jl-input ${value ? 'has-action' : ''} ${className}`}>
            <i className="bi bi-search jl-input-icon" />
            <input
                type="search"
                className="form-control"
                value={value ?? ''}
                onChange={(event) => onChange(event.target.value)}
                onKeyDown={(event) => {
                    if (event.key === 'Escape' && value) {
                        event.stopPropagation();
                        onChange('');
                    }
                }}
                placeholder={placeholder}
                aria-label={placeholder}
            />
            {value && (
                <button type="button" className="jl-input-action" onClick={() => onChange('')} aria-label="پاک کردن جستجو">
                    <i className="bi bi-x-lg" />
                </button>
            )}
        </div>
    );
}
