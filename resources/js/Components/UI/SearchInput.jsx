import { useRef } from 'react';
import { useHotkeys } from '../../hooks/useHotkeys';

/** Search box; "/" focuses it from anywhere on the page. */
export default function SearchInput({ value, onChange, placeholder = 'جستجو...', className = '', shortcut = true }) {
    const inputRef = useRef(null);

    useHotkeys('/', () => inputRef.current?.focus(), { enabled: shortcut });

    return (
        <div className={`jl-input ${value ? 'has-action' : ''} ${className}`}>
            <i className="bi bi-search jl-input-icon" />
            <input
                ref={inputRef}
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
            {value ? (
                <button type="button" className="jl-input-action" onClick={() => onChange('')} aria-label="پاک کردن جستجو">
                    <i className="bi bi-x-lg" />
                </button>
            ) : (
                shortcut && (
                    <span className="jl-input-unit d-none d-md-inline">
                        <kbd>/</kbd>
                    </span>
                )
            )}
        </div>
    );
}
