import { label, tone } from '../../lib/labels';

/** The role of a person in a small box: کارفرما / فریلنسر / ادمین / منتور / پشتیبان. */
export function RoleTag({ role, className = '' }) {
    if (!role) {
        return null;
    }

    return <span className={`jl-role is-${tone('role', role)} ${className}`}>{label('role', role)}</span>;
}

/**
 * A person's name followed by their role box, e.g. «امیرعباس محمودی [کارفرما]».
 * `role` pins the role that matters in this place (the employer of a project, say); otherwise every role the user holds is shown.
 */
export default function UserName({ user, role = null, className = '', nameClassName = '' }) {
    if (!user) {
        return <span className="text-muted">—</span>;
    }

    const roles = role ? [role] : (user.roles ?? []).slice(0, 2);

    return (
        <span className={`jl-user-name ${className}`}>
            <span className={`jl-user-name-text ${nameClassName}`}>{user.name}</span>
            {roles.map((item) => (
                <RoleTag key={item} role={item} />
            ))}
        </span>
    );
}
