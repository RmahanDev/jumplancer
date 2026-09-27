import { label, tone } from '../../lib/labels';

/** Soft badge with a colored dot. */
export function Badge({ tone: badgeTone = 'secondary', dot = true, icon = null, children, className = '' }) {
    return (
        <span className={`jl-badge is-${badgeTone} ${dot && !icon ? '' : 'no-dot'} ${className}`}>
            {icon && <i className={`bi ${icon}`} />}
            {children}
        </span>
    );
}

/** A server enum value shown with its Persian label and tone, e.g. <StatusBadge group="projectStatus" value="open" />. */
export function StatusBadge({ group, value, className = '' }) {
    return (
        <Badge tone={tone(group, value)} className={className}>
            {label(group, value)}
        </Badge>
    );
}
