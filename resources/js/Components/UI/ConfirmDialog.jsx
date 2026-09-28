import { useState, useSyncExternalStore } from 'react';
import Modal, { ModalBody, ModalFooter } from './Modal';

// const ok = await confirm({ title, message, confirmLabel, tone: 'danger', requireText })
let request = null;
let nextId = 1;
const subscribers = new Set();

function emit() {
    subscribers.forEach((subscriber) => subscriber());
}

export function confirm(options) {
    return new Promise((resolve) => {
        request = { ...options, id: nextId++, resolve };
        emit();
    });
}

function settle(answer) {
    if (!request || request.closing) {
        return;
    }

    request.resolve(answer);
    request = { ...request, closing: true };
    emit();
    window.setTimeout(() => {
        request = null;
        emit();
    }, 160);
}

function ConfirmModal({ current }) {
    const [typed, setTyped] = useState('');
    const open = !current.closing;
    const tone = current.tone ?? 'danger';
    const blocked = Boolean(current.requireText) && typed.trim() !== current.requireText;

    return (
        <Modal
            open={open}
            onClose={() => settle(false)}
            title={current.title ?? 'مطمئنی؟'}
            icon={current.icon ?? (tone === 'danger' ? 'bi-exclamation-triangle' : 'bi-question-circle')}
            tone={tone === 'danger' ? 'danger' : tone}
            size="sm"
            footer={
                <ModalFooter hint={false}>
                    <button type="button" className="btn btn-ghost" onClick={() => settle(false)}>
                        انصراف
                    </button>
                    <button
                        type="button"
                        className={`btn btn-${tone === 'danger' ? 'danger' : 'primary'}`}
                        onClick={() => settle(true)}
                        disabled={blocked}
                        data-autofocus={current.requireText ? undefined : true}
                    >
                        {current.confirmLabel ?? 'تأیید'}
                        <kbd>Enter</kbd>
                    </button>
                </ModalFooter>
            }
        >
            <ModalBody>
                <p className="mb-0 text-muted-2">{current.message}</p>
                {current.requireText && (
                    <div className="mt-3">
                        <label className="form-label small" htmlFor={`confirm-${current.id}`}>
                            برای تأیید، <code>{current.requireText}</code> را بنویس:
                        </label>
                        <input
                            id={`confirm-${current.id}`}
                            className="form-control"
                            dir="ltr"
                            value={typed}
                            onChange={(event) => setTyped(event.target.value)}
                            onKeyDown={(event) => {
                                if (event.key === 'Enter' && !blocked) {
                                    event.preventDefault();
                                    settle(true);
                                }
                            }}
                            autoComplete="off"
                            data-autofocus
                        />
                    </div>
                )}
            </ModalBody>
        </Modal>
    );
}

/** Mounted once in the layout; shows the dialog requested through confirm(). */
export default function ConfirmHost() {
    const current = useSyncExternalStore(
        (subscriber) => {
            subscribers.add(subscriber);

            return () => subscribers.delete(subscriber);
        },
        () => request,
        () => request,
    );

    return current ? <ConfirmModal key={current.id} current={current} /> : null;
}
