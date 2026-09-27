import { initials } from '../../lib/format';

// Calm, readable avatar colors (white initials clear 4.5:1 on each).
const COLORS = ['#006b82', '#0f6fbf', '#6d4bc2', '#b0417a', '#b85c00', '#2f7d4f', '#0e7490', '#475569'];

function colorFor(name) {
    let hash = 0;

    for (const char of String(name ?? '')) {
        hash = (hash * 31 + char.codePointAt(0)) >>> 0;
    }

    return COLORS[hash % COLORS.length];
}

export default function Avatar({ user, size = null, className = '' }) {
    const name = user?.name ?? '';

    return (
        <span className={`jl-avatar ${size ? `is-${size}` : ''} ${className}`} style={{ background: colorFor(name) }} aria-hidden="true">
            {user?.avatar ? <img src={user.avatar} alt="" /> : initials(name)}
        </span>
    );
}

/** Avatar + name + a muted second line (username, role, company ...). */
export function Person({ user, meta = null, size = null }) {
    if (!user) {
        return <span className="text-muted">—</span>;
    }

    return (
        <span className="jl-person">
            <Avatar user={user} size={size} />
            <span className="min-w-0 d-flex flex-column">
                <span className="jl-person-name">{user.name}</span>
                {(meta ?? user.username) && <span className="jl-person-meta">{meta ?? <span className="ltr">@{user.username}</span>}</span>}
            </span>
        </span>
    );
}
