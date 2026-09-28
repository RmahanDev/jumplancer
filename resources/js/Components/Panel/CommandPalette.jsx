import { useEffect, useMemo, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { useEscape } from '../../hooks/useEscape';
import { normalizeForSearch } from '../../lib/text';

/**
 * Search opened from the topbar button: jump to any page of the panel, run the page's actions,
 * switch panel or theme. commands: [{ id, label, group, icon, keywords?, hint?, run }]
 */
export default function CommandPalette({ open, onClose, commands }) {
    const [query, setQuery] = useState('');
    const [active, setActive] = useState(0);
    const inputRef = useRef(null);
    const listRef = useRef(null);

    useEffect(() => {
        if (!open) {
            return undefined;
        }

        setQuery('');
        setActive(0);
        const timer = window.setTimeout(() => inputRef.current?.focus(), 10);

        return () => {
            window.clearTimeout(timer);
        };
    }, [open]);

    const results = useMemo(() => {
        const tokens = normalizeForSearch(query).split(/\s+/).filter(Boolean);

        if (tokens.length === 0) {
            return commands;
        }

        return commands.filter((command) => {
            const haystack = normalizeForSearch(`${command.label} ${command.group ?? ''} ${(command.keywords ?? []).join(' ')}`);

            return tokens.every((token) => haystack.includes(token));
        });
    }, [commands, query]);

    useEffect(() => {
        setActive(0);
    }, [query]);

    useEffect(() => {
        listRef.current?.querySelector('.is-active')?.scrollIntoView({ block: 'nearest' });
    }, [active]);

    useEscape(onClose, open);

    if (!open) {
        return null;
    }

    const run = (command) => {
        onClose();
        window.setTimeout(() => command.run(), 0);
    };

    const onKeyDown = (event) => {
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            setActive((index) => (results.length ? (index + 1) % results.length : 0));
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            setActive((index) => (results.length ? (index - 1 + results.length) % results.length : 0));
        } else if (event.key === 'Home') {
            event.preventDefault();
            setActive(0);
        } else if (event.key === 'End') {
            event.preventDefault();
            setActive(Math.max(0, results.length - 1));
        } else if (event.key === 'Enter') {
            event.preventDefault();

            if (results[active]) {
                run(results[active]);
            }
        }
    };

    let lastGroup = null;

    return createPortal(
        <>
            <div className="jl-palette-backdrop" onMouseDown={onClose} />
            <div className="jl-palette" role="dialog" aria-modal="true" aria-label="جستجو و دستورها">
                <div className="jl-palette-input">
                    <i className="bi bi-search" />
                    <input
                        ref={inputRef}
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                        onKeyDown={onKeyDown}
                        placeholder="کجا بروم یا چه کار کنم؟ مثلاً «کاربران» یا «تم تیره»"
                        aria-label="جستجوی صفحه یا دستور"
                        aria-controls="jl-palette-list"
                        aria-activedescendant={results[active] ? `jl-cmd-${results[active].id}` : undefined}
                        autoComplete="off"
                        spellCheck={false}
                    />
                    <button type="button" className="jl-icon-btn" onClick={onClose} aria-label="بستن جستجو">
                        <i className="bi bi-x-lg" />
                    </button>
                </div>
                <div className="jl-palette-list" id="jl-palette-list" role="listbox" ref={listRef}>
                    {results.length === 0 && (
                        <div className="text-center text-muted py-4 small">
                            <i className="bi bi-emoji-neutral d-block fs-3 mb-1" />
                            چیزی با «{query}» پیدا نشد.
                        </div>
                    )}
                    {results.map((command, index) => {
                        const header = command.group !== lastGroup ? command.group : null;
                        lastGroup = command.group;

                        return (
                            <div key={command.id}>
                                {header && <div className="jl-palette-group">{header}</div>}
                                <button
                                    type="button"
                                    id={`jl-cmd-${command.id}`}
                                    role="option"
                                    aria-selected={index === active}
                                    className={`jl-palette-item ${index === active ? 'is-active' : ''}`}
                                    onMouseMove={() => index !== active && setActive(index)}
                                    onClick={() => run(command)}
                                >
                                    <i className={`bi ${command.icon ?? 'bi-arrow-left-short'}`} />
                                    <span className="jl-palette-label">
                                        {command.label}
                                        {command.hint && <small className="d-block text-muted">{command.hint}</small>}
                                    </span>
                                </button>
                            </div>
                        );
                    })}
                </div>
            </div>
        </>,
        document.body,
    );
}
