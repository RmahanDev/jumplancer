import { useForm } from '@inertiajs/react';
import { useEffect } from 'react';
import Modal, { ModalBody, ModalFooter } from '../UI/Modal';

/**
 * Every "add / edit" form in the dashboards lives in a modal.
 *
 * <ModalForm open url method="post" initial={{ title: '' }} title="..." onClose>
 *     {(form) => <TextInput form={form} name="title" label="عنوان" />}
 * </ModalForm>
 *
 * The submit button (or Enter in a single-line field) submits, field errors come back from Laravel validation, and errors
 * about the operation itself (keys that are not fields) are shown above the fields.
 * `submitLabel` / `submitIcon` may be functions of the form, for a label that follows a switch.
 */
export default function ModalForm({
    open,
    onClose,
    title,
    subtitle = null,
    icon = 'bi-pencil-square',
    tone = 'primary',
    size = null,
    method = 'post',
    url,
    initial = {},
    transform = null,
    submitLabel = 'ذخیره',
    submitIcon = 'bi-check2',
    submitTone = 'primary',
    onSuccess = null,
    forceFormData = false,
    children,
    extraActions = null,
}) {
    const form = useForm(initial);

    // Load the record being edited each time the modal opens.
    useEffect(() => {
        if (open) {
            form.setData(initial);
            form.clearErrors();
        }
    }, [open]);

    const submit = (event) => {
        event?.preventDefault();

        if (form.processing) {
            return;
        }

        const spoofed = forceFormData && method !== 'post';

        form.transform((data) => {
            const payload = transform ? transform(data) : data;

            return spoofed ? { ...payload, _method: method } : payload;
        });

        const verb = spoofed ? 'post' : method;

        form[verb](url, {
            preserveScroll: true,
            forceFormData,
            onSuccess: (page) => {
                onSuccess?.(page);
                onClose();
            },
            onError: (errors) => {
                const general = Object.keys(errors).filter((key) => !(key.split('.')[0] in initial));

                if (general.length === 0) {
                    window.setTimeout(() => document.querySelector('.jl-modal .is-invalid')?.focus(), 30);
                }
            },
        });
    };

    const generalErrors = Object.entries(form.errors).filter(([key]) => !(key.split('.')[0] in initial));

    return (
        <Modal
            open={open}
            onClose={onClose}
            title={title}
            subtitle={subtitle}
            icon={icon}
            tone={tone}
            size={size}
            busy={form.processing}
            footer={null}
        >
            <form onSubmit={submit} noValidate style={{ display: 'contents' }}>
                <ModalBody>
                    {generalErrors.length > 0 && (
                        <div className="alert alert-danger d-flex gap-2 align-items-start py-2" role="alert">
                            <i className="bi bi-exclamation-octagon mt-1" />
                            <div>
                                {generalErrors.map(([key, message]) => (
                                    <div key={key}>{message}</div>
                                ))}
                            </div>
                        </div>
                    )}
                    {children(form)}
                </ModalBody>
                <ModalFooter>
                    {extraActions}
                    <button type="button" className="btn btn-ghost" onClick={onClose} disabled={form.processing}>
                        انصراف
                    </button>
                    <button type="submit" className={`btn btn-${submitTone}`} disabled={form.processing}>
                        {form.processing ? <span className="spinner-border spinner-border-sm" aria-hidden="true" /> : <i className={`bi ${typeof submitIcon === 'function' ? submitIcon(form) : submitIcon}`} />}
                        {typeof submitLabel === 'function' ? submitLabel(form) : submitLabel}
                    </button>
                </ModalFooter>
            </form>
        </Modal>
    );
}
