import { useEffect, useSyncExternalStore } from 'react';

// A tiny toast store: toast.success('...') from anywhere, <Toaster /> renders them once in the layout.
let toasts = [];
let nextId = 1;
const subscribers = new Set();

function emit() {
    subscribers.forEach((subscriber) => subscriber());
}

function dismiss(id) {
    toasts = toasts.map((item) => (item.id === id ? { ...item, leaving: true } : item));
    emit();
    window.setTimeout(() => {
        toasts = toasts.filter((item) => item.id !== id);
        emit();
    }, 200);
}

function push(type, message, { duration = 4500 } = {}) {
    if (!message) {
        return null;
    }

    const id = nextId++;
    toasts = [...toasts.slice(-3), { id, type, message, duration, leaving: false }];
    emit();

    return id;
}

export const toast = {
    success: (message, options) => push('success', message, options),
    error: (message, options) => push('danger', message, { duration: 6500, ...options }),
    warning: (message, options) => push('warning', message, options),
    info: (message, options) => push('info', message, options),
    show: (type, message, options) => push(type === 'error' ? 'danger' : type, message, options),
    dismiss,
};

const ICONS = {
    success: 'bi-check-circle-fill',
    danger: 'bi-x-octagon-fill',
    warning: 'bi-exclamation-triangle-fill',
    info: 'bi-info-circle-fill',
};

function ToastItem({ item }) {
    useEffect(() => {
        const timer = window.setTimeout(() => dismiss(item.id), item.duration);

        return () => window.clearTimeout(timer);
    }, [item.id, item.duration]);

    return (
        <div className={`jl-toast is-${item.type} ${item.leaving ? 'is-leaving' : ''}`} role={item.type === 'danger' ? 'alert' : 'status'}>
            <i className={`bi ${ICONS[item.type] ?? ICONS.info}`} />
            <div className="jl-toast-body">{item.message}</div>
            <button type="button" className="jl-toast-close" onClick={() => dismiss(item.id)} aria-label="بستن">
                <i className="bi bi-x-lg" />
            </button>
            <span className="jl-toast-progress" style={{ animationDuration: `${item.duration}ms` }} />
        </div>
    );
}

export default function Toaster() {
    const items = useSyncExternalStore(
        (subscriber) => {
            subscribers.add(subscriber);

            return () => subscribers.delete(subscriber);
        },
        () => toasts,
        () => toasts,
    );

    return (
        <div className="jl-toaster" aria-live="polite">
            {items.map((item) => (
                <ToastItem key={item.id} item={item} />
            ))}
        </div>
    );
}
