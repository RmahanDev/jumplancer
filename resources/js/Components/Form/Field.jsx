/** First validation error of a field, including nested ones ("permissions.2" belongs to "permissions"). */
export function fieldError(errors, name) {
    if (!errors || !name) {
        return null;
    }

    if (errors[name]) {
        return errors[name];
    }

    const nested = Object.keys(errors).find((key) => key.startsWith(`${name}.`));

    return nested ? errors[nested] : null;
}

/** Reads "freelancer.headline" from { freelancer: { headline } } (useForm's setData writes such paths too). */
function readPath(data, name) {
    if (!name) {
        return undefined;
    }

    if (!name.includes('.')) {
        return data?.[name];
    }

    return name.split('.').reduce((current, key) => (current === null || current === undefined ? undefined : current[key]), data);
}

/**
 * value / onChange / error for an input bound to an Inertia useForm() object.
 * Names may be nested ("employer.company_name"). Editing a field clears its error so the
 * message never lingers.
 */
export function useBinding({ form, name, value, onChange, error }) {
    if (!form) {
        return { value, onChange, error };
    }

    return {
        value: readPath(form.data, name),
        error: error ?? fieldError(form.errors, name),
        onChange: (next) => {
            form.setData(name, next);

            const stale = Object.keys(form.errors ?? {}).filter((key) => key === name || key.startsWith(`${name}.`));

            if (stale.length > 0) {
                form.clearErrors(...stale);
            }
        },
    };
}

/** Label + control + error/hint. */
export default function Field({ label, htmlFor, error = null, hint = null, required = false, className = '', children, extra = null }) {
    return (
        <div className={className}>
            {(label || extra) && (
                <div className="d-flex align-items-center justify-content-between gap-2">
                    {label && (
                        <label htmlFor={htmlFor} className={`form-label ${required ? 'jl-required' : ''}`}>
                            {label}
                        </label>
                    )}
                    {extra}
                </div>
            )}
            {children}
            {error ? <div className="invalid-feedback d-block">{error}</div> : hint ? <div className="form-text">{hint}</div> : null}
        </div>
    );
}
