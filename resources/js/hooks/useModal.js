import { useState } from 'react';

/**
 * Open/close state of a modal plus the record it shows. The record is kept while the modal
 * animates out, so its content does not blink away.
 *
 * const edit = useModal(); edit.show(user); <ModalForm open={edit.open} onClose={edit.close} ... />
 */
export function useModal() {
    const [state, setState] = useState({ open: false, record: null });

    return {
        open: state.open,
        record: state.record,
        show: (record = null) => setState({ open: true, record }),
        close: () => setState((current) => ({ ...current, open: false })),
    };
}
