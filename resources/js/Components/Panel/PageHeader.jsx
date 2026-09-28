import { Head } from '@inertiajs/react';
import { usePageCommands } from '../../lib/commands';

/**
 * Page title (document title + h1), a short description and the page actions.
 * `primary` is the main action of the page ({ label, icon, onClick }); it is also listed in the panel search.
 */
export default function PageHeader({ title, description = null, actions = null, primary = null, children = null }) {
    const primaryEnabled = Boolean(primary) && !primary.disabled;

    usePageCommands(
        primaryEnabled
            ? [
                  {
                      id: 'page:primary',
                      label: primary.label,
                      icon: primary.icon ?? 'bi-plus-lg',
                      keywords: ['جدید', 'افزودن', 'new', 'add'],
                      run: primary.onClick,
                  },
              ]
            : [],
    );

    return (
        <>
            <Head title={title} />
            <header className="jl-page-header jl-rise">
                <div className="min-w-0">
                    <h1>{title}</h1>
                    {description && <p>{description}</p>}
                    {children}
                </div>
                {(actions || primary) && (
                    <div className="jl-page-actions">
                        {actions}
                        {primary && (
                            <button type="button" className="btn btn-primary" onClick={primary.onClick} disabled={primary.disabled}>
                                <i className={`bi ${primary.icon ?? 'bi-plus-lg'}`} />
                                {primary.label}
                            </button>
                        )}
                    </div>
                )}
            </header>
        </>
    );
}
