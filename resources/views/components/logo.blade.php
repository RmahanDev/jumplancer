@props(['size' => 40, 'wordmark' => true, 'subtitle' => null])

<span {{ $attributes->merge(['class' => 'd-inline-flex align-items-center gap-2']) }}>
    <svg class="jl-logo-mark" width="{{ $size }}" height="{{ $size }}" viewBox="0 0 40 40" aria-hidden="true">
        <rect width="40" height="40" rx="11" fill="#f7941d" />
        <path d="M24.5 11.5v12a6.5 6.5 0 0 1-13 0" fill="none" stroke="#fff" stroke-width="3.4" stroke-linecap="round" stroke-linejoin="round" />
        <path d="M19.8 15.4 24.5 10.7l4.7 4.7" fill="none" stroke="#fff" stroke-width="3.4" stroke-linecap="round" stroke-linejoin="round" />
    </svg>
    @if ($wordmark)
        <span class="d-flex flex-column">
            <span class="jl-wordmark">جامپ&zwnj;<b>لنسر</b></span>
            @if ($subtitle)
                <span class="jl-sidebar-panel-label">{{ $subtitle }}</span>
            @endif
        </span>
    @endif
</span>
