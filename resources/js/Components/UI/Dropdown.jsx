import { useEffect, useLayoutEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';

const MENU_GAP = 6;

function isRtl() {
    return document.documentElement.dir === 'rtl';
}

/**
 * Click-to-open menu (no Bootstrap JS). `children` receives `close` so items can close it.
 * <Dropdown trigger={<button className="jl-icon-btn">...</button>}>{(close) => ...}</Dropdown>
 *
 * The menu is rendered in a portal with fixed positioning, so it is never clipped by a
 * scrolling table, and it opens upwards when there is no room below.
 */
export default function Dropdown({ trigger, children, align = 'end', up = false, className = '', menuClassName = '', width = null }) {
    const [open, setOpen] = useState(false);
    const [position, setPosition] = useState(null);
    const rootRef = useRef(null);
    const menuRef = useRef(null);

    const place = () => {
        const anchor = rootRef.current?.getBoundingClientRect();

        if (!anchor) {
            return;
        }

        const menuHeight = menuRef.current?.offsetHeight ?? 240;
        const spaceBelow = window.innerHeight - anchor.bottom;
        const openUp = up || (spaceBelow < menuHeight + MENU_GAP * 2 && anchor.top > spaceBelow);
        const toLeftEdge = (align === 'end') === isRtl();

        setPosition({
            position: 'fixed',
            top: openUp ? 'auto' : anchor.bottom + MENU_GAP,
            bottom: openUp ? window.innerHeight - anchor.top + MENU_GAP : 'auto',
            left: toLeftEdge ? Math.max(8, anchor.left) : 'auto',
            right: toLeftEdge ? 'auto' : Math.max(8, window.innerWidth - anchor.right),
            minWidth: width ?? undefined,
        });
    };

    useLayoutEffect(() => {
        if (open) {
            place();
        } else {
            setPosition(null);
        }
    }, [open]);

    useEffect(() => {
        if (!open) {
            return undefined;
        }

        // Measure again once the menu has its real height.
        const frame = requestAnimationFrame(place);

        const onPointer = (event) => {
            if (!rootRef.current?.contains(event.target) && !menuRef.current?.contains(event.target)) {
                setOpen(false);
            }
        };
        const onKey = (event) => {
            if (event.key === 'Escape') {
                event.stopPropagation();
                event.preventDefault();
                setOpen(false);
                rootRef.current?.querySelector('button, a')?.focus();
            }

            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                const items = [...(menuRef.current?.querySelectorAll('.dropdown-item:not(.disabled)') ?? [])];

                if (items.length) {
                    event.preventDefault();
                    const index = items.indexOf(document.activeElement);
                    const next = event.key === 'ArrowDown' ? (index + 1) % items.length : (index - 1 + items.length) % items.length;
                    items[next].focus();
                }
            }
        };
        const onViewportChange = () => setOpen(false);

        document.addEventListener('mousedown', onPointer);
        document.addEventListener('keydown', onKey, true);
        window.addEventListener('resize', onViewportChange);
        window.addEventListener('scroll', onViewportChange, true);

        return () => {
            cancelAnimationFrame(frame);
            document.removeEventListener('mousedown', onPointer);
            document.removeEventListener('keydown', onKey, true);
            window.removeEventListener('resize', onViewportChange);
            window.removeEventListener('scroll', onViewportChange, true);
        };
    }, [open]);

    const close = () => setOpen(false);

    return (
        <div className={`jl-dropdown ${className}`} ref={rootRef}>
            <span
                onClick={() => setOpen((value) => !value)}
                onKeyDown={(event) => {
                    if (event.key === 'ArrowDown' && !open) {
                        event.preventDefault();
                        setOpen(true);
                    }
                }}
            >
                {trigger}
            </span>
            {open &&
                createPortal(
                    <div
                        ref={menuRef}
                        className={`dropdown-menu show jl-dropdown-menu ${menuClassName}`}
                        style={position ?? { position: 'fixed', visibility: 'hidden' }}
                        role="menu"
                    >
                        {typeof children === 'function' ? children(close) : children}
                    </div>,
                    document.body,
                )}
        </div>
    );
}

export function DropdownItem({ icon, children, onClick, danger = false, disabled = false, as: Component = 'button', ...props }) {
    return (
        <Component
            type={Component === 'button' ? 'button' : undefined}
            className={`dropdown-item ${danger ? 'text-danger' : ''} ${disabled ? 'disabled' : ''}`}
            onClick={onClick}
            disabled={Component === 'button' ? disabled : undefined}
            role="menuitem"
            {...props}
        >
            {icon && <i className={`bi ${icon}`} />}
            <span className="flex-grow-1">{children}</span>
        </Component>
    );
}

export function DropdownDivider() {
    return <hr className="dropdown-divider" />;
}
