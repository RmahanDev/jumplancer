import { useEffect, useId, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { useEscape } from '../../hooks/useEscape';

let openModals = 0;

/**
 * Controlled Bootstrap modal rendered by React (no Bootstrap JS), with enter/leave animation,
 * Esc or the close button to close, focus on the first field and body scroll lock.
 */
export default function Modal({
    open,
    onClose,
    title,
    subtitle = null,
    icon = null,
    tone = 'primary',
    size = null,
    children,
    footer = null,
    closeOnBackdrop = true,
    busy = false,
}) {
    const [mounted, setMounted] = useState(open);
    const [leaving, setLeaving] = useState(false);
    const dialogRef = useRef(null);
    const titleId = useId();

    useEffect(() => {
        if (open) {
            setMounted(true);
            setLeaving(false);

            return undefined;
        }

        if (!mounted) {
            return undefined;
        }

        setLeaving(true);
        const timer = window.setTimeout(() => {
            setMounted(false);
            setLeaving(false);
        }, 150);

        return () => window.clearTimeout(timer);
    }, [open]);

    useEffect(() => {
        if (!mounted) {
            return undefined;
        }

        openModals += 1;
        document.body.classList.add('overflow-hidden');
        const previous = document.activeElement;

        const focusTimer = window.setTimeout(() => {
            const target = dialogRef.current?.querySelector('[data-autofocus], input:not([type=hidden]):not([disabled]), textarea, select, .btn-primary');
            target?.focus({ preventScroll: true });
        }, 60);

        return () => {
            window.clearTimeout(focusTimer);
            openModals -= 1;

            if (openModals === 0) {
                document.body.classList.remove('overflow-hidden');
            }

            previous?.focus?.({ preventScroll: true });
        };
    }, [mounted]);

    useEscape(() => !busy && onClose(), open);

    if (!mounted) {
        return null;
    }

    const sizeClass = size ? `modal-${size}` : '';

    return createPortal(
        <>
            <div className={`jl-modal-backdrop ${leaving ? 'is-leaving' : ''}`} />
            <div
                className={`modal jl-modal ${leaving ? 'is-leaving' : ''}`}
                role="dialog"
                aria-modal="true"
                aria-labelledby={titleId}
                onMouseDown={(event) => {
                    if (closeOnBackdrop && !busy && event.target === event.currentTarget) {
                        onClose();
                    }
                }}
            >
                <div className={`modal-dialog modal-dialog-centered modal-dialog-scrollable ${sizeClass}`} ref={dialogRef}>
                    <div className="modal-content">
                        <div className="modal-header">
                            {icon && (
                                <span className={`jl-modal-icon ${tone !== 'primary' ? `is-${tone}` : ''}`}>
                                    <i className={`bi ${icon}`} />
                                </span>
                            )}
                            <div className="flex-grow-1 min-w-0">
                                <h2 className="modal-title" id={titleId}>
                                    {title}
                                </h2>
                                {subtitle && <p className="jl-modal-subtitle">{subtitle}</p>}
                            </div>
                            <button type="button" className="btn-close m-0" onClick={onClose} disabled={busy} aria-label="بستن" />
                        </div>
                        {children}
                        {footer}
                    </div>
                </div>
            </div>
        </>,
        document.body,
    );
}

/** Standard modal body wrapper. */
export function ModalBody({ children, className = '' }) {
    return <div className={`modal-body ${className}`}>{children}</div>;
}

/** Footer with the actions on the end side. */
export function ModalFooter({ children }) {
    return (
        <div className="modal-footer">
            <div className="d-flex gap-2">{children}</div>
        </div>
    );
}
