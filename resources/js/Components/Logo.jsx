/** Brand mark (orange, #f7941d) with the wordmark; mirrors resources/views/components/logo.blade.php. */
export default function Logo({ size = 40, wordmark = true, subtitle = null }) {
    return (
        <span className="d-inline-flex align-items-center gap-2 jl-sidebar-brand">
            <svg
                className="jl-logo-mark"
                width={size}
                height={size}
                viewBox="0 0 40 40"
                aria-hidden="true"
            >
                <rect
                    width="40"
                    height="40"
                    rx="11"
                    fill="#f7941d"
                />

                <path
                    d="M24.5 11.5v12a6.5 6.5 0 0 1-13 0"
                    fill="none"
                    stroke="#fff"
                    strokeWidth="3.4"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                />

                <path
                    d="M19.8 15.4 24.5 10.7l4.7 4.7"
                    fill="none"
                    stroke="#fff"
                    strokeWidth="3.4"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                />
            </svg>

            {wordmark && (
                <span className="d-flex flex-column jl-sidebar-brand-text">
                    <span className="jl-wordmark">
                        جامپ{'‌'}
                        <b>لنسر</b>
                    </span>

                    {subtitle && (
                        <span className="jl-sidebar-panel-label">
                            {subtitle}
                        </span>
                    )}
                </span>
            )}
        </span>
    );
}